#!/usr/bin/env python3
"""
signer/pipeline.py - MiBoleta - Pipeline compartido de firma digital legal.

Extrae, del spike original (spike_sign.py), la lógica REUTILIZABLE del
pipeline:

    normalizar (Ghostscript -> PDF/A-2b)
        -> firmar (pyHanko, PAdES, incremental) con un certificado REAL (.pfx)
            -> [opcional] sellar con una TSA (RFC 3161)
                -> verificar la firma resultante

para que la puedan usar tanto la CLI de spike (spike_sign.py, uso manual /
debugging) como la API HTTP productiva (app.py, usada por el Job
`SignDocument` de Laravel).

Diseño deliberado: este módulo NO imprime nada por consola ni arma "reportes"
legibles para humanos (eso es responsabilidad de spike_sign.py). Cada función
levanta una excepción de la jerarquía `PipelineError` con un mensaje claro
ante cualquier fallo; el llamador decide cómo presentarlo (texto en la CLI,
JSON en la API).
"""
from __future__ import annotations

import glob
import hashlib
import logging
import math
import os
import secrets
import shutil
import subprocess
import traceback
from fractions import Fraction
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Optional

from signer_details import extract_signer_details

logger = logging.getLogger("signer.pipeline")
# fontTools avisa de tablas que no sabe recortar al incrustar la fuente: ruido inofensivo.
logging.getLogger("fontTools").setLevel(logging.ERROR)


# --------------------------------------------------------------------------
# Perfiles ICC candidatos para el OutputIntent de PDF/A. El paquete Debian
# "ghostscript" NO trae perfiles ICC propios; los provee "icc-profiles-free"
# (instalado en signer/Dockerfile), que deja sRGB.icc en
# /usr/share/color/icc/sRGB.icc. Se dejan alternativas por si el pipeline
# corre en otra imagen/host.
# --------------------------------------------------------------------------
ICC_CANDIDATES = [
    "/usr/share/color/icc/sRGB.icc",
    "/usr/share/color/icc/sRGB2014.icc",
    "/usr/share/color/icc/compatibleWithAdobeRGB1998.icc",
]
ICC_GLOB_PATTERNS = [
    "/usr/share/ghostscript/*/iccprofiles/srgb.icc",
    "/usr/share/color/icc/**/sRGB*.icc",
]

DEFAULT_FIELD_NAME = "MiBoletaFirma"
DEFAULT_MD_ALGORITHM = "sha256"
DEFAULT_REASON = "Firma digital de documento laboral - MiBoleta (DS-009-2011-TR)"
DEFAULT_LOCATION = "MiBoleta - plataforma"

# Certificados de CA (PEM/CRT) en los que la verificación confía ADEMÁS del
# almacén del sistema: las CA raíz autofirmadas entran como trust roots y las
# intermedias solo como material para construir la cadena. Así el campo
# "trusted" sale True para firmas de una CA acreditada por INDECOPI que no
# está en el almacén del sistema (p.ej. Llama.pe), sin perder las CA públicas
# que sí lo están (p.ej. la de la TSA). Ver signer/trust/README.md.
TRUST_DIR = Path(os.environ.get("SIGNER_TRUST_DIR", Path(__file__).parent / "trust"))

# Sello de firma visible en el PIE de la última página (ver build_stamp_lines
# y _build_stamp_style). Medidas en puntos PDF (1pt = 1/72").
STAMP_MARGIN_X = 36.0       # margen izquierdo/derecho respecto del borde de página
STAMP_BOTTOM = 18.0         # distancia desde el borde inferior
STAMP_HEIGHT = 58.0
STAMP_RESERVE = STAMP_BOTTOM + STAMP_HEIGHT + 6.0  # franja que se agrega al pie de la página
STAMP_PADDING = 5.0         # relleno interno entre el borde del sello y el texto
STAMP_FONT_MAX = 8.0
STAMP_FONT_MIN = 6.0
STAMP_FONT_STEP = 0.25
STAMP_SEPARATOR = " · "
# Nimbus Sans (fonts-urw-base35, la misma que usa Ghostscript): TrueType/OpenType
# con tildes/ñ y 1000 unidades por em. Se prefiere a DejaVu Sans porque pyHanko
# 0.35 escribe mal los anchos (/W) de fuentes con 2048 unidades por em (DejaVu,
# Liberation...) y el texto sale con las letras separadas.
STAMP_FONT_CANDIDATES = [
    "/usr/share/fonts/opentype/urw-base35/NimbusSans-Regular.otf",
    "/usr/share/fonts/urw-base35/NimbusSans-Regular.otf",
]
COUNTRY_NAMES = {"PE": "Perú"}


# --------------------------------------------------------------------------
# Excepciones: una jerarquía simple que distingue en qué ETAPA falló el
# pipeline, para que app.py pueda reportar un mensaje/código útil al
# Laravel/Job que lo invoque (y para que el Job, a su vez, decida si
# reintentar o no).
# --------------------------------------------------------------------------
class PipelineError(Exception):
    """Error base: cualquier fallo del pipeline normalizar->firmar->verificar."""

    stage = "pipeline"


class InputValidationError(PipelineError):
    stage = "input"


class NormalizationError(PipelineError):
    stage = "normalize"


class SignerLoadError(PipelineError):
    stage = "load_signer"


class TsaError(PipelineError):
    stage = "tsa"


class SigningError(PipelineError):
    stage = "sign"


class VerificationError(PipelineError):
    stage = "verify"


class ConformityError(PipelineError):
    """Falló el dibujo del nombre/fecha de conformidad del trabajador."""

    stage = "conformity"


class ExtractError(PipelineError):
    """No se pudo recuperar la revisión 0 de un PDF firmado."""

    stage = "extract"


# --------------------------------------------------------------------------
# Utilidades
# --------------------------------------------------------------------------
def escape_ps_string(value: str) -> str:
    """Escapa una cadena para usarla dentro de un literal PostScript ( ... )."""
    return value.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def find_icc_profile(explicit: Optional[str]) -> Optional[Path]:
    if explicit:
        p = Path(explicit)
        return p if p.is_file() else None
    for candidate in ICC_CANDIDATES:
        p = Path(candidate)
        if p.is_file():
            return p
    for pattern in ICC_GLOB_PATTERNS:
        matches = sorted(Path("/").glob(pattern.lstrip("/")))
        if matches:
            return matches[0]
    return None


def build_pdfa_def_ps(icc_path: Path, title: str) -> str:
    """
    Genera un PDFA_def.ps equivalente al que distribuye el propio proyecto
    Ghostscript (lib/PDFA_def.ps en el repo ghostpdl), con el ICCProfile y el
    Title sustituidos. Este archivo es el que le dice a Ghostscript qué
    OutputIntent /GTS_PDFA1 debe incrustar; sin él, `-dPDFA=2` reescribe el
    contenido pero NO agrega el OutputIntent, y el resultado no califica
    como PDF/A-2b conforme.
    """
    icc_literal = escape_ps_string(str(icc_path))
    title_literal = escape_ps_string(title)
    return f"""%!
% Generado por signer/pipeline.py - definicion de PDF/A-2b para Ghostscript.
[ /Title ({title_literal})
  /DOCINFO pdfmark

/ICCProfile ({icc_literal})
def

[/_objdef {{icc_PDFA}} /type /stream /OBJ pdfmark

[{{icc_PDFA}}
<<
  systemdict /ColorConversionStrategy known {{
    systemdict /ColorConversionStrategy get cvn dup /Gray eq {{
      pop /N 1 false
    }}{{
      dup /RGB eq {{
        pop /N 3 false
      }}{{
        /CMYK eq {{
          /N 4 false
        }}{{
          (\\tColorConversionStrategy no es un espacio de dispositivo, se usa ProcessColorModel.\\n)=
          true
        }} ifelse
      }} ifelse
    }} ifelse
  }} {{
    (\\tColorConversionStrategy no definido, se usa ProcessColorModel.\\n)=
    true
  }} ifelse

  {{
    currentpagedevice /ProcessColorModel get
    dup /DeviceGray eq {{
      pop /N 1
    }}{{
      dup /DeviceRGB eq {{
        pop /N 3
      }}{{
        dup /DeviceCMYK eq {{
          pop /N 4
        }} {{
          (\\tProcessColorModel no es un espacio de dispositivo valido.)=
          /ProcessColorModel cvx /rangecheck signalerror
        }} ifelse
      }} ifelse
    }} ifelse
  }} if

>> /PUT pdfmark
[
{{icc_PDFA}}
{{ICCProfile (r) file}} stopped
{{
  (\\n\\tNo se pudo abrir el ICCProfile indicado. Verifica --permit-file-read.\\n) print
  cleartomark
}}
{{
  /PUT pdfmark
  [/_objdef {{OutputIntent_PDFA}} /type /dict /OBJ pdfmark
  [{{OutputIntent_PDFA}} <<
    /Type /OutputIntent
    /S /GTS_PDFA1
    /DestOutputProfile {{icc_PDFA}}
    /OutputConditionIdentifier (sRGB)
  >> /PUT pdfmark
  [{{Catalog}} <</OutputIntents [ {{OutputIntent_PDFA}} ]>> /PUT pdfmark
}} ifelse
"""


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


# --------------------------------------------------------------------------
# Resultado de cada etapa (para que el llamador -CLI o API- arme su propio
# reporte/JSON sin tener que adivinar qué pasó).
# --------------------------------------------------------------------------
@dataclass
class NormalizeResult:
    icc_profile: Optional[str]
    conformant: bool
    stderr_tail: str = ""


@dataclass
class SignerHandle:
    """Envuelve el SimpleSigner de pyHanko + metadatos legibles del certificado."""

    signer: Any
    subject: str


@dataclass
class SignResult:
    tsa_applied: bool
    stamp_applied: bool = False
    conformity_applied: bool = False


@dataclass
class VerifyResult:
    intact: bool
    valid: bool
    trusted: bool
    covers_whole_file: bool
    signer_subject: str
    signing_time: Optional[str]
    tsa_applied: bool
    tsa_time: Optional[str]
    digest_algo: Optional[str]
    details: str = ""


# --------------------------------------------------------------------------
# Etapa 0: validar el PDF de entrada
# --------------------------------------------------------------------------
def check_input_pdf(input_path: Path) -> int:
    """Valida que el archivo exista y sea un PDF. Devuelve el tamaño en bytes.

    Lanza InputValidationError si no es un PDF legible.
    """
    if not input_path.is_file():
        raise InputValidationError(f"No existe el archivo de entrada: {input_path}")
    try:
        header = input_path.open("rb").read(5)
    except OSError as e:
        raise InputValidationError(f"No se pudo leer el archivo: {e}") from e
    if header != b"%PDF-":
        raise InputValidationError(
            f"El archivo no parece un PDF (cabecera leída: {header!r})."
        )
    return input_path.stat().st_size


# --------------------------------------------------------------------------
# Etapa 1: normalizar a PDF/A-2b con Ghostscript
# --------------------------------------------------------------------------
def normalize_to_pdfa(
    input_path: Path,
    output_path: Path,
    work_dir: Path,
    gs_bin: str = "gs",
    icc_override: Optional[str] = None,
) -> NormalizeResult:
    """Reescribe `input_path` como PDF/A-2b en `output_path` vía Ghostscript.

    Lanza NormalizationError si Ghostscript no está disponible, falla, o no
    produce un archivo de salida válido.
    """
    gs_path = shutil.which(gs_bin)
    if gs_path is None:
        raise NormalizationError(
            f"No se encontró el binario '{gs_bin}' en PATH. "
            "¿Corriste esto dentro del contenedor 'signer'?"
        )

    icc_path = find_icc_profile(icc_override)
    pdfa_def_path = work_dir / "PDFA_def.ps"

    base_cmd = [
        gs_path,
        "-dPDFA=2",
        "-dBATCH",
        "-dNOPAUSE",
        "-dNOOUTERSAVE",
        # NOTA: se usa "RGB" (no "UseDeviceIndependentColor"): en pruebas
        # empíricas, UseDeviceIndependentColor + pdfwrite + PDFA=2 emite
        # repetidamente "pdfwrite cannot guarantee creating a conformant
        # PDF/A-2 file with device-independent colour" (por imagen), y
        # aunque Ghostscript igual termina con código 0, es una señal de
        # degradación de conformidad que "RGB" evita de raíz.
        "-sColorConversionStrategy=RGB",
        "-sProcessColorModel=DeviceRGB",
        "-dPDFACompatibilityPolicy=1",
        "-sDEVICE=pdfwrite",
    ]

    if icc_path is None:
        cmd = base_cmd + [f"-sOutputFile={output_path}", str(input_path)]
    else:
        pdfa_def_path.write_text(
            build_pdfa_def_ps(icc_path, title=input_path.stem), encoding="ascii"
        )
        cmd = base_cmd + [
            f"--permit-file-read={icc_path}",
            f"-sOutputFile={output_path}",
            str(pdfa_def_path),
            str(input_path),
        ]

    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
    except subprocess.TimeoutExpired as e:
        raise NormalizationError(
            "Ghostscript no terminó dentro del tiempo esperado (120s)."
        ) from e
    except OSError as e:
        raise NormalizationError(f"No se pudo ejecutar gs: {e}") from e

    stderr_tail = "\n".join(proc.stderr.strip().splitlines()[-25:])

    if proc.returncode != 0:
        raise NormalizationError(
            f"gs devolvió código {proc.returncode}. "
            f"--- stderr (últimas líneas) ---\n{stderr_tail}"
        )

    if not output_path.is_file() or output_path.stat().st_size == 0:
        raise NormalizationError(
            "gs terminó con código 0 pero no generó un archivo de salida válido."
        )

    warning_markers = (
        "cannot be converted",
        "PDF/A structure not generated",
        "cannot guarantee creating a conformant PDF/A",
    )
    degraded = any(m in proc.stderr for m in warning_markers)

    return NormalizeResult(
        icc_profile=str(icc_path) if icc_path else None,
        conformant=(icc_path is not None) and not degraded,
        stderr_tail=stderr_tail,
    )


# --------------------------------------------------------------------------
# Etapa 2: cargar el firmante desde un certificado REAL (.pfx/.p12)
# --------------------------------------------------------------------------
def load_signer_from_pfx(pfx_path: Path, password: Optional[str]) -> SignerHandle:
    """Carga un SimpleSigner de pyHanko desde un .pfx/.p12 real.

    Lanza SignerLoadError si el archivo no existe, pyHanko no está
    disponible, o la password/archivo no son válidos.
    """
    try:
        from pyhanko.sign import signers
    except ImportError as e:
        raise SignerLoadError(f"No se pudo importar pyHanko: {e}") from e

    if not pfx_path.is_file():
        raise SignerLoadError(f"No existe el certificado: {pfx_path}")

    password_bytes = password.encode("utf-8") if password else None

    try:
        signer = signers.SimpleSigner.load_pkcs12(
            pfx_file=str(pfx_path), passphrase=password_bytes
        )
    except Exception as e:  # noqa: BLE001 - cualquier fallo de parseo del pfx
        raise SignerLoadError(
            f"No se pudo cargar el certificado '{pfx_path}': {type(e).__name__}: {e}"
        ) from e

    if signer is None:
        raise SignerLoadError(
            f"No se pudo cargar el certificado '{pfx_path}'. Verifica la "
            "contraseña y que el archivo sea un PKCS#12 válido."
        )

    subject = signer.signing_cert.subject.human_friendly
    return SignerHandle(signer=signer, subject=subject)


def generate_test_signer(work_dir: Path) -> SignerHandle:
    """Genera un certificado AUTOFIRMADO de PRUEBA (RSA 2048) al vuelo.

    Solo para uso del spike/CLI cuando no se pasa un .pfx real; NUNCA debe
    usarse desde la API productiva (app.py exige certificate_path real).
    """
    try:
        from cryptography import x509
        from cryptography.hazmat.primitives import hashes, serialization
        from cryptography.hazmat.primitives.asymmetric import rsa
        from cryptography.hazmat.primitives.serialization import pkcs12
        from cryptography.x509.oid import NameOID
        from pyhanko.sign import signers
    except ImportError as e:
        raise SignerLoadError(f"Falta una dependencia requerida: {e}") from e

    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    subject = issuer = x509.Name(
        [
            x509.NameAttribute(NameOID.COUNTRY_NAME, "PE"),
            x509.NameAttribute(
                NameOID.ORGANIZATION_NAME, "MiBoleta SPIKE - NO VALIDO PARA PRODUCCION"
            ),
            x509.NameAttribute(
                NameOID.COMMON_NAME, "MiBoleta Test Signer (autofirmado, solo pruebas)"
            ),
        ]
    )
    now = datetime.now(timezone.utc)
    cert = (
        x509.CertificateBuilder()
        .subject_name(subject)
        .issuer_name(issuer)
        .public_key(key.public_key())
        .serial_number(x509.random_serial_number())
        .not_valid_before(now - timedelta(minutes=5))
        .not_valid_after(now + timedelta(days=365))
        .add_extension(x509.BasicConstraints(ca=False, path_length=None), critical=True)
        .add_extension(
            x509.KeyUsage(
                digital_signature=True,
                content_commitment=True,
                key_encipherment=False,
                data_encipherment=False,
                key_agreement=False,
                key_cert_sign=False,
                crl_sign=False,
                encipher_only=False,
                decipher_only=False,
            ),
            critical=True,
        )
        .add_extension(
            x509.ExtendedKeyUsage([x509.ObjectIdentifier("1.3.6.1.5.5.7.3.36")]),
            critical=False,
        )
        .sign(key, hashes.SHA256())
    )

    password = secrets.token_urlsafe(24).encode("utf-8")
    p12_bytes = pkcs12.serialize_key_and_certificates(
        name=b"miboleta-spike-test",
        key=key,
        cert=cert,
        cas=None,
        encryption_algorithm=serialization.BestAvailableEncryption(password),
    )
    pfx_out = work_dir / "self-signed-test.pfx"
    pfx_out.write_bytes(p12_bytes)

    signer = signers.SimpleSigner.load_pkcs12(pfx_file=str(pfx_out), passphrase=password)
    if signer is None:
        raise SignerLoadError(
            f"El certificado autofirmado se generó pero pyHanko no pudo recargarlo desde {pfx_out}."
        )

    return SignerHandle(signer=signer, subject=cert.subject.rfc4514_string())


# --------------------------------------------------------------------------
# Sello visible del pie de página
# --------------------------------------------------------------------------
def find_stamp_font() -> Optional[Path]:
    """Ruta a una fuente con tildes/ñ y 1000 unidades por em (Nimbus Sans), o None.

    SIGNER_STAMP_FONT permite forzar otra fuente (debe tener 1000 unidades por em).
    """
    explicit = os.environ.get("SIGNER_STAMP_FONT")
    for candidate in ([explicit] if explicit else []) + STAMP_FONT_CANDIDATES:
        if candidate and Path(candidate).is_file():
            return Path(candidate)
    return None


def build_stamp_lines(details: dict) -> list[str]:
    """Arma las líneas del sello (dos campos por línea, separados por " · ").

    Los campos faltantes se omiten (nunca se imprime "None"); una línea sin
    ningún campo no se emite.
    """
    name = details.get("name")
    first = f"Firmado digitalmente por: {name}" if name else "Firmado digitalmente"
    country = details.get("country")
    place = " / ".join(
        v for v in (COUNTRY_NAMES.get(country, country) if country else None,
                    details.get("locality")) if v
    )
    rows = [
        [first, f"Cargo: {details['title']}" if details.get("title") else None],
        [
            f"Razón social: {details['organization']}" if details.get("organization") else None,
            f"RUC: {details['ruc']}" if details.get("ruc") else None,
        ],
        [
            f"País/Provincia: {place}" if place else None,
            f"Fecha: {details['signed_at_local']}" if details.get("signed_at_local") else None,
        ],
    ]
    return [STAMP_SEPARATOR.join(p for p in row if p) for row in rows if any(row)]


class _TextMeter:
    """Mide el ancho de un texto en puntos para una fuente (TTF o aproximado)."""

    def __init__(self, font_path: Optional[Path]):
        self._cmap = None
        self._hmtx = None
        self._upem = 1000
        if font_path is not None:
            try:
                from fontTools.ttLib import TTFont

                tt = TTFont(str(font_path), lazy=True)
                self._cmap = tt.getBestCmap()
                self._hmtx = tt["hmtx"]
                self._upem = tt["head"].unitsPerEm
            except Exception as e:  # noqa: BLE001
                logger.warning("No se pudo leer la fuente %s: %s", font_path, e)
                self._cmap = None

    def width(self, text: str, size: float) -> float:
        if self._cmap is None:
            return 0.6 * size * len(text)  # Courier: 600/1000 por carácter
        total = 0
        for ch in text:
            glyph = self._cmap.get(ord(ch), ".notdef")
            total += self._hmtx[glyph][0] if glyph in self._hmtx.metrics else self._upem // 2
        return total * size / self._upem

    def fit(self, text: str, size: float, max_width: float) -> str:
        """Trunca con "…" hasta que el texto quepa en `max_width`."""
        if self.width(text, size) <= max_width:
            return text
        while text and self.width(text + "…", size) > max_width:
            text = text[:-1]
        return text.rstrip() + "…"


def fit_stamp_lines(
    lines: list[str], max_width: float, meter: _TextMeter
) -> tuple[list[str], float]:
    """Devuelve (líneas, tamaño de fuente) que caben en `max_width`.

    Reduce la fuente desde STAMP_FONT_MAX hasta STAMP_FONT_MIN; si aun así
    alguna línea no entra, la trunca con "…". NUNCA desborda el ancho.
    """
    size = STAMP_FONT_MAX
    while size > STAMP_FONT_MIN and any(meter.width(l, size) > max_width for l in lines):
        size -= STAMP_FONT_STEP
    size = max(size, STAMP_FONT_MIN)
    return [meter.fit(l, size, max_width) for l in lines], size


def _inherited(page: Any, key: str) -> Any:
    """Valor de un atributo de página, heredado de /Parent si hace falta."""
    node = page
    while node is not None:
        if key in node:
            return node[key]
        node = node["/Parent"] if "/Parent" in node else None
    return None


def _page_box(page: Any) -> tuple[float, float, float, float]:
    """CropBox (o MediaBox, heredada de /Parent) de una página: (x0, y0, x1, y1)."""
    for key in ("/CropBox", "/MediaBox"):
        raw = _inherited(page, key)
        if raw is not None:
            x0, y0, x1, y1 = (float(v) for v in raw)
            return min(x0, x1), min(y0, y1), max(x0, x1), max(y0, y1)
    return 0.0, 0.0, 595.0, 842.0  # A4 por defecto


def _page_rotation(page: Any) -> int:
    """/Rotate efectivo de la página (heredable), normalizado a 0/90/180/270."""
    raw = _inherited(page, "/Rotate")
    try:
        return int(raw) % 360 // 90 * 90 if raw is not None else 0
    except (TypeError, ValueError):
        return 0


def reserve_footer_space(page: Any) -> None:
    """Amplía MediaBox (y CropBox) de la página para reservar el pie del sello.

    El contenido original no se toca ni se tapa: la franja nueva queda en el
    borde que, con el /Rotate de la página, se ve como el borde INFERIOR.
    Escribe MediaBox/CropBox directamente en la página (sobrescribe la herencia).
    """
    from pyhanko.pdf_utils.generic import ArrayObject, FloatObject

    rot = _page_rotation(page)
    for key in ("/MediaBox", "/CropBox"):
        raw = _inherited(page, key)
        if raw is None:
            if key == "/MediaBox":
                raw = [0, 0, 595, 842]
            else:
                continue
        x0, y0, x1, y1 = (float(v) for v in raw)
        x0, x1 = min(x0, x1), max(x0, x1)
        y0, y1 = min(y0, y1), max(y0, y1)
        if rot == 0:
            y0 -= STAMP_RESERVE
        elif rot == 180:
            y1 += STAMP_RESERVE
        elif rot == 90:
            x1 += STAMP_RESERVE
        else:  # 270
            x0 -= STAMP_RESERVE
        page[key] = ArrayObject(FloatObject(v) for v in (x0, y0, x1, y1))


def compute_stamp_box(page: Any) -> tuple[tuple[float, float, float, float], int, float]:
    """Caja del sello en el pie VISUAL de una página (ya con espacio reservado).

    Devuelve ((x0, y0, x1, y1) en espacio de usuario, rotación, ancho visual).
    Con /Rotate 0 es una franja horizontal; con 90/270 es una franja vertical en
    el borde que se ve como el inferior (el sello se rota para leerse horizontal).
    """
    px0, py0, px1, py1 = _page_box(page)
    rot = _page_rotation(page)
    if rot in (90, 270):
        along_lo, along_hi = py0, py1  # largo visual = alto en espacio de usuario
    else:
        along_lo, along_hi = px0, px1
    a0 = along_lo + STAMP_MARGIN_X
    a1 = max(along_hi - STAMP_MARGIN_X, a0 + 100.0)
    width = a1 - a0
    if rot == 0:
        box = (a0, py0 + STAMP_BOTTOM, a1, py0 + STAMP_BOTTOM + STAMP_HEIGHT)
    elif rot == 180:
        # el eje visual X va al revés, pero los márgenes son simétricos
        box = (a0, py1 - STAMP_BOTTOM - STAMP_HEIGHT, a1, py1 - STAMP_BOTTOM)
    elif rot == 90:
        box = (px1 - STAMP_BOTTOM - STAMP_HEIGHT, a0, px1 - STAMP_BOTTOM, a1)
    else:  # 270
        box = (px0 + STAMP_BOTTOM, a0, px0 + STAMP_BOTTOM + STAMP_HEIGHT, a1)
    return box, rot, width


_ROTATION_MATRIX = {
    90: [0, 1, -1, 0, 0, 0],
    180: [-1, 0, 0, -1, 0, 0],
    270: [0, -1, 1, 0, 0, 0],
}


def _make_rotated_style_class():
    """TextStampStyle cuyo sello se dibuja en ancho x alto VISUALES y se rota.

    El widget de la firma vive en el espacio de usuario de la página; si la
    página tiene /Rotate 90/180/270 el rectángulo queda girado. La apariencia
    se construye horizontal (BBox = ancho visual x alto) y se le agrega un
    /Matrix que la contra-rota para que se lea derecha en la página girada.
    """
    from pyhanko import stamp
    from pyhanko.pdf_utils import generic, layout

    @dataclass(frozen=True)
    class RotatedTextStampStyle(stamp.TextStampStyle):
        rotation: int = 0

        def create_stamp(self, writer, box, text_params):
            if self.rotation not in (90, 270):
                inner_box = box
            else:  # el rectángulo es alto x ancho: el contenido va transpuesto
                inner_box = layout.BoxConstraints(width=box.height, height=box.width)
            st = _RotatedTextStamp(writer=writer, style=self, box=inner_box, text_params=text_params)
            return st

    class _RotatedTextStamp(stamp.TextStamp):
        def as_form_xobject(self):
            xobj = super().as_form_xobject()
            matrix = _ROTATION_MATRIX.get(self.style.rotation)
            if matrix:
                xobj["/Matrix"] = generic.ArrayObject(generic.FloatObject(v) for v in matrix)
            return xobj

    return RotatedTextStampStyle


def _build_stamp_style(
    lines: list[str], size: float, box_width: float, font_path: Optional[Path], rotation: int = 0
):
    """TextStampStyle con borde gris fino y fondo blanco opaco."""
    from pyhanko import stamp
    from pyhanko.pdf_utils import layout
    from pyhanko.pdf_utils.content import RawContent
    from pyhanko.pdf_utils.text import TextBoxStyle

    font_factory = None
    if font_path is not None:
        try:
            from pyhanko.pdf_utils.font.opentype import GlyphAccumulatorFactory

            # font_size debe coincidir con TextBoxStyle.font_size: pyHanko lo usa
            # para los saltos de línea (Td) de los textos de varias líneas.
            font_factory = GlyphAccumulatorFactory(str(font_path), font_size=size)
        except Exception as e:  # noqa: BLE001 - falta fonttools/uharfbuzz, fuente inválida
            logger.warning("Sello visible: fuente OpenType no disponible (%s); se usa la básica.", e)
    else:
        logger.warning("Sello visible: no se encontró Nimbus Sans; se usa la fuente básica.")

    # Texto literal: el stamp_text de pyHanko se interpola con "%", así que se escapa.
    # pyHanko hace aritmética de Fraction con el alto del texto: `leading` debe
    # ser Rational (Fraction/int), no float.
    leading = Fraction(size * 1.25).limit_denominator(100)
    text = "\n".join(lines).replace("%", "%%")
    if font_factory is None:
        text = text.encode("latin-1", errors="replace").decode("latin-1")
        text_box_style = TextBoxStyle(font_size=size, leading=leading)
    else:
        text_box_style = TextBoxStyle(font=font_factory, font_size=size, leading=leading)

    white_bg = RawContent(b"q 1 1 1 rg 0 0 %f %f re f Q" % (box_width, STAMP_HEIGHT))
    return _make_rotated_style_class()(
        rotation=rotation,
        stamp_text=text,
        text_box_style=text_box_style,
        border_width=0.5,
        border_color=(0.6, 0.6, 0.6),
        background=white_bg,
        background_opacity=1.0,
        background_layout=layout.SimpleBoxLayoutRule(
            x_align=layout.AxisAlignment.ALIGN_MIN,
            y_align=layout.AxisAlignment.ALIGN_MIN,
            margins=layout.Margins.uniform(0),
        ),
        inner_content_layout=layout.SimpleBoxLayoutRule(
            x_align=layout.AxisAlignment.ALIGN_MIN,
            y_align=layout.AxisAlignment.ALIGN_MID,
            margins=layout.Margins.uniform(int(STAMP_PADDING)),
        ),
    ), font_factory is not None


# --------------------------------------------------------------------------
# Conformidad del trabajador (nombre cursivo + fecha) dibujada por el sidecar
# --------------------------------------------------------------------------
# Cuando la empresa firma con PAdES, el PDF ya no se puede reescribir (FPDI no
# abre xref streams y reescribir invalida la firma). Por eso el nombre del
# trabajador se dibuja AQUÍ, en la misma revisión incremental que la firma:
#   normalizar -> TextStamp(nombre, fecha) -> reservar pie -> firmar.
MM = 72.0 / 25.4  # puntos por milímetro

# Segoe Script (la misma que usa PdfWatermarkService en PHP), embebida en la imagen.
CONFORMITY_FONT_ENV = "SIGNER_CONFORMITY_FONT"
CONFORMITY_FONT_DEFAULT = "/app/fonts/segoesc.ttf"
CONFORMITY_FONT_CACHE_DIR = Path(os.environ.get("SIGNER_FONT_CACHE_DIR", "/tmp/signer-fonts"))
_ALIGN_FLAGS = {"L": "ALIGN_MIN", "C": "ALIGN_MID", "R": "ALIGN_MAX"}
_UPEM_TARGET = 1000


def load_conformity_font() -> Path:
    """Ruta a Segoe Script con 1000 unidades por em (reescalada y cacheada).

    `segoesc.ttf` trae 2048 upem y con eso pyHanko escribe mal los anchos (las
    letras salen separadas). Se reescala una vez a 1000 con fontTools y se
    guarda en CONFORMITY_FONT_CACHE_DIR/<sha1>.ttf. Lanza ConformityError si
    la fuente no existe o no carga (NO hay fallback a otra fuente).
    """
    src = Path(os.environ.get(CONFORMITY_FONT_ENV, CONFORMITY_FONT_DEFAULT))
    if not src.is_file():
        raise ConformityError(f"No se encontró la fuente de conformidad: {src}")
    try:
        from fontTools.ttLib import TTFont

        digest = hashlib.sha1(src.read_bytes()).hexdigest()
        cached = CONFORMITY_FONT_CACHE_DIR / f"{digest}.ttf"
        if cached.is_file() and cached.stat().st_size > 0:
            return cached
        font = TTFont(str(src))
        if font["head"].unitsPerEm != _UPEM_TARGET:
            from fontTools.ttLib.scaleUpem import scale_upem

            scale_upem(font, _UPEM_TARGET)
        CONFORMITY_FONT_CACHE_DIR.mkdir(parents=True, exist_ok=True)
        tmp = CONFORMITY_FONT_CACHE_DIR / f".{digest}.{secrets.token_hex(4)}.tmp"
        font.save(str(tmp))
        os.replace(tmp, cached)  # atómico: dos requests no se pisan
        return cached
    except Exception as e:  # noqa: BLE001
        raise ConformityError(f"No se pudo cargar la fuente de conformidad {src}: {e}") from e


def _visual_size(box: tuple[float, float, float, float], rot: int) -> tuple[float, float]:
    """(ancho, alto) VISUAL en puntos de una página con caja `box` y rotación `rot`."""
    x0, y0, x1, y1 = box
    return (y1 - y0, x1 - x0) if rot in (90, 270) else (x1 - x0, y1 - y0)


def _visual_to_user(
    box: tuple[float, float, float, float], rot: int, u: float, v: float
) -> tuple[float, float]:
    """Punto visual (u a la derecha, v hacia abajo desde el borde superior) -> espacio de usuario."""
    x0, y0, x1, y1 = box
    if rot == 90:
        return x0 + v, y0 + u
    if rot == 180:
        return x1 - u, y0 + v
    if rot == 270:
        return x1 - v, y1 - u
    return x0 + u, y1 - v


DETECT_TOL_RATIO = 0.03
DETECT_TOL_MM = 5.0


def detect_layout(conformity: dict, vw: float, vh: float) -> dict:
    """Layout a usar: si hay `layouts` + `page_dimensions_mm` (sin elección explícita de
    formato), el de la key cuyo tamaño calza con el VISUAL real de la página (tolerancia
    max(3%, 5 mm) por lado, gana el menor error); si ninguna calza, `layout`."""
    default = conformity.get("layout") or {}
    layouts = conformity.get("layouts")
    dims = conformity.get("page_dimensions_mm")
    if not layouts or not dims:
        return default
    w_mm, h_mm = vw / MM, vh / MM
    best, best_err = None, float("inf")
    for key, lay in layouts.items():
        d = dims.get(key)
        if not d or len(d) != 2 or not lay:
            continue
        dw, dh = abs(w_mm - d[0]), abs(h_mm - d[1])
        if dw <= max(d[0] * DETECT_TOL_RATIO, DETECT_TOL_MM) and dh <= max(d[1] * DETECT_TOL_RATIO, DETECT_TOL_MM):
            err = dw / d[0] + dh / d[1]
            if err < best_err:
                best, best_err = lay, err
    return best if best is not None else default


def conformity_cells(conformity: dict, vw: float, vh: float) -> dict:
    """Celdas (en puntos, coordenadas VISUALES desde arriba-izquierda) del nombre y la fecha.

    Misma geometría que PdfWatermarkService::addWatermarkToPage (config/signature.php):
    en modo 'absolute' usa x/name_y fijos; en 'auto' la esquina inferior derecha
    proporcional al tamaño de página.
    """
    lay = detect_layout(conformity, vw, vh)
    width_mm = float(lay.get("width_mm", 50))
    name_h_mm = float(lay.get("name_height_mm", 8))
    date_off_mm = float(lay.get("date_offset_y_mm", 8))
    if lay.get("mode", "absolute") == "absolute":
        # None se trata igual que clave ausente (el backend envia null): fallback de PHP
        x_raw = lay.get("x_mm")
        y_raw = lay.get("name_y_mm")
        x_mm = float(x_raw) if x_raw is not None else vw / MM - width_mm - 10
        y_mm = float(y_raw) if y_raw is not None else vh / MM - 33
    else:
        page_w_mm = vw / MM
        page_h_mm = vh / MM
        width_mm = min(width_mm, page_w_mm * 0.30)
        margin = max(5.0, 10.0 * (page_w_mm / 210.0))
        x_mm = page_w_mm - width_mm - margin
        y_mm = page_h_mm - margin - 18.0 - 5.0
    align = str(lay.get("align", "C")).upper()
    return {
        "x": x_mm * MM,
        "width": width_mm * MM,
        "name_top": y_mm * MM,
        "name_h": name_h_mm * MM,
        "date_top": (y_mm + date_off_mm) * MM,
        "date_h": 4.0 * MM,
        "name_size": float(lay.get("name_font_size", 12)),
        "date_size": float(lay.get("date_font_size", 7)),
        "align": align if align in _ALIGN_FLAGS else "C",
    }


# Métricas verticales que TCPDF usa para alinear el texto de una celda (valign 'M').
# Segoe Script: ascender/descender de `hhea` (TCPDF las guarda como Ascent/Descent al
# generar el .php con addTTFfont: 1089/495 por 1000 em). Fecha: helvetica de TCPDF
# (fonts/helvetica.php: Ascent 931, Descent -225); Nimbus Sans es su clon métrico.
HELVETICA_ASCENT = 0.931
HELVETICA_DESCENT = 0.225
# TCPDF no aplica GSUB/GPOS (kern, ligaduras, alternos contextuales); se desactivan
# para que el ancho dibujado coincida con GetStringWidth y con el que mide _TextMeter.
_PLAIN_SHAPING = {"kern": False, "liga": False, "clig": False, "calt": False, "rlig": False}


def font_vmetrics(font_path: Path) -> tuple[float, float]:
    """(ascent, descent) en em, ambos positivos, de `hhea` (lo que TCPDF toma de un TTF)."""
    from fontTools.ttLib import TTFont

    tt = TTFont(str(font_path), lazy=True)
    upem = tt["head"].unitsPerEm
    return tt["hhea"].ascent / upem, -tt["hhea"].descent / upem


def tcpdf_baseline_from_top(cell_h: float, size: float, ascent: float, descent: float) -> float:
    """Distancia (pt) del borde superior de la celda a la línea base, como TCPDF con valign 'M'.

    TCPDF::getCellCode: yt = y + (h - FontAscent - FontDescent) / 2; base = yt + FontAscent,
    con FontAscent/FontDescent = métrica(em) * tamaño. Es independiente del contenido de
    la caja que pyHanko reporte para la fuente.
    """
    return (cell_h - (ascent + descent) * size) / 2 + ascent * size


def _make_conformity_stamp_class():
    """TextStamp que dibuja UNA línea con la línea base exacta y se contra-rota con /Matrix."""
    from pyhanko import stamp
    from pyhanko.pdf_utils import generic
    from pyhanko.pdf_utils.content import ResourceType
    from pyhanko.pdf_utils.text import TextBox

    class _ExactTextBox(TextBox):
        """TextBox sin los márgenes de 10 pt ni el `%d Tf` (que truncaba el tamaño)."""

        origin = (0.0, 0.0)

        def render(self):
            self.set_resource(
                category=ResourceType.FONT,
                name=generic.pdf_name("/" + self.font_name),
                value=self.font_engine.as_resource(),
            )
            ops = [
                b"/Tx BMC q BT",
                b"/%s %g Tf" % (self.font_name.encode("latin1"), self.style.font_size),
                b"%g %g Td" % self.origin,
            ]
            ops.extend(self._wrapped_lines)
            ops.append(b"ET Q EMC")
            return b" ".join(ops)

    class _ConformityStamp(stamp.TextStamp):
        rotation = 0
        # Se fijan en _text_stamp: celda (ancho, alineación) y métricas de la fuente.
        cell_w = 0.0
        cell_h = 0.0
        pad_x = 0.0
        pad_y = 0.0
        align = "C"
        ascent = 0.0
        descent = 0.0

        def _render_inner_content(self):
            style = self.style
            size = float(style.text_box_style.font_size)
            tb = _ExactTextBox(
                style.text_box_style, writer=self.writer, resources=self.resources, box=None
            )
            text = style.stamp_text % {}
            tb.font_engine.features = _PLAIN_SHAPING  # antes de shape(): sin kern/ligaduras
            tb.content = text
            extent = tb.font_engine.shape(text).x_advance * size
            slack = self.cell_w - extent
            dx = {"L": 0.0, "C": slack / 2, "R": slack}[self.align]
            top = tcpdf_baseline_from_top(self.cell_h, size, self.ascent, self.descent)
            tb.origin = (dx, self.cell_h - top)
            return [b"q", tb.render(), b"Q"]

        def as_form_xobject(self):
            xobj = super().as_form_xobject()
            # BBox mayor que la celda (margen transparente): origen (0,0) = esquina
            # inferior-izquierda de la celda, y los glifos altos no se recortan.
            xobj["/BBox"] = generic.ArrayObject(
                generic.FloatObject(v)
                for v in (-self.pad_x, -self.pad_y, self.cell_w + self.pad_x, self.cell_h + self.pad_y)
            )
            matrix = _ROTATION_MATRIX.get(self.rotation)
            if matrix:
                xobj["/Matrix"] = generic.ArrayObject(generic.FloatObject(v) for v in matrix)
            return xobj

    return _ConformityStamp


def _text_stamp(w, text: str, font_path: Path, size: float, width: float, height: float,
                align: str, rotation: int, ascent: float, descent: float):
    """TextStamp de una celda `width` x `height` pt, sin borde ni fondo.

    La línea base cae donde la pondría TCPDF (ver tcpdf_baseline_from_top). La Segoe
    Script es alta y sus glifos sobresalen de la celda (7 mm), así que el BBox del
    XObject se agranda con un margen transparente (`pad_*`, BBox con origen negativo);
    el origen del stamp sigue siendo la esquina inferior-izquierda de la celda.
    """
    from pyhanko import stamp
    from pyhanko.pdf_utils import layout
    from pyhanko.pdf_utils.font.opentype import GlyphAccumulatorFactory
    from pyhanko.pdf_utils.text import TextBoxStyle

    pad_x = pad_y = math.ceil(2 * size)
    style = stamp.TextStampStyle(
        stamp_text=text.replace("%", "%%"),
        text_box_style=TextBoxStyle(
            font=GlyphAccumulatorFactory(str(font_path), font_size=size),
            font_size=size,
        ),
        border_width=0,
        background=None,
    )
    st = _make_conformity_stamp_class()(
        w, style, box=layout.BoxConstraints(width=width, height=height)
    )
    st.rotation, st.cell_w, st.cell_h = rotation, width, height
    st.pad_x, st.pad_y, st.align, st.ascent, st.descent = pad_x, pad_y, align, ascent, descent
    return st


def draw_conformity(w: Any, page_ref: Any, page: Any, conformity: dict) -> None:
    """Dibuja nombre (Segoe Script) y fecha (Nimbus Sans) del trabajador en la página.

    Se llama ANTES de reserve_footer_space y con el mismo IncrementalPdfFileWriter
    de la firma, así todo cae en una única revisión incremental. Las coordenadas
    se toman de la caja de página vigente (CropBox o MediaBox) y respetan /Rotate.
    Lanza ConformityError ante cualquier fallo (el llamador no escribe salida).
    """
    try:
        name = (conformity.get("name") or "").strip()
        date_text = (conformity.get("date_text") or "").strip()
        if not name:
            raise ValueError("conformity.name vacío")
        name_font = load_conformity_font()
        date_font = find_stamp_font()
        if date_font is None:
            raise ValueError("no se encontró la fuente Nimbus Sans para la fecha")

        box = _page_box(page)
        rot = _page_rotation(page)
        vw, vh = _visual_size(box, rot)
        c = conformity_cells(conformity, vw, vh)

        # Auto-ajuste igual que PHP (addSignatureText): si no cabe, se reduce.
        name_size = c["name_size"]
        sw = _TextMeter(name_font).width(name, name_size)
        if sw > 0 and sw > c["width"]:
            name_size = max(5.0, name_size * c["width"] / sw * 0.96)
        date_size = c["date_size"]
        if date_text:
            dw = _TextMeter(date_font).width(date_text, date_size)
            if dw > 0 and dw > c["width"]:
                date_size = max(5.0, date_size * c["width"] / dw * 0.96)

        name_asc, name_desc = font_vmetrics(name_font)
        cells = [(name, name_font, name_size, c["name_top"], c["name_h"], name_asc, name_desc)]
        if date_text:
            cells.append((date_text, date_font, date_size, c["date_top"], c["date_h"],
                          HELVETICA_ASCENT, HELVETICA_DESCENT))
        for text, font, size, top, height, asc, desc in cells:
            st = _text_stamp(w, text, font, size, c["width"], height, c["align"], rot, asc, desc)
            # origen del form = esquina inferior-izquierda VISUAL de la celda
            ux, uy = _visual_to_user(box, rot, c["x"], top + height)
            st.apply(-1, ux, uy)
    except ConformityError:
        raise
    except Exception as e:  # noqa: BLE001
        raise ConformityError(
            f"No se pudo dibujar la conformidad: {type(e).__name__}: {e}"
        ) from e


# --------------------------------------------------------------------------
# Base (revisión 0 normalizada) y extracción desde un PDF ya firmado
# --------------------------------------------------------------------------
def assert_unsigned_single_revision(path: Path) -> None:
    """InputValidationError si el PDF tiene firmas o más de 1 revisión (no es una base)."""
    from pyhanko.pdf_utils.reader import PdfFileReader

    try:
        with path.open("rb") as f:
            r = PdfFileReader(f)
            n_sigs = len(r.embedded_signatures)
            revs = r.xrefs.total_revisions
    except Exception as e:  # noqa: BLE001
        raise InputValidationError(f"No se pudo leer el PDF base {path}: {e}") from e
    if n_sigs != 0 or revs != 1:
        raise InputValidationError(
            f"El PDF base debe tener 1 revisión y 0 firmas (tiene {revs} revisiones, {n_sigs} firmas)."
        )


def publish_file(src: Path, dst: Path) -> None:
    """Copia `src` a `dst` de forma atómica (tmp + rename) y con permisos 0644."""
    dst.parent.mkdir(parents=True, exist_ok=True)
    tmp = dst.parent / f".{dst.name}.{secrets.token_hex(4)}.tmp"
    try:
        shutil.copyfile(src, tmp)
        os.chmod(tmp, 0o644)
        os.replace(tmp, dst)
    finally:
        if tmp.exists():
            tmp.unlink()


def extract_base(input_path: Path, output_path: Path) -> None:
    """Recupera, byte a byte, la revisión 0 de un PDF con exactamente 1 firma PAdES.

    Corta el archivo en el `%%EOF` (más su fin de línea) que cierra la revisión
    anterior a la firmada. Lanza InputValidationError si la entrada no es un PDF
    firmado una sola vez, o ExtractError si el resultado no es una base válida.
    """
    from pyhanko.pdf_utils.reader import PdfFileReader

    check_input_pdf(input_path)
    data = input_path.read_bytes()
    try:
        with input_path.open("rb") as f:
            r = PdfFileReader(f)
            sigs = r.embedded_signatures
            total = r.xrefs.total_revisions
            if len(sigs) != 1:
                raise InputValidationError(
                    f"Se esperaba exactamente 1 firma embebida y hay {len(sigs)}."
                )
            signed_rev = sigs[0].signed_revision
            if signed_rev != total - 1 or signed_rev < 1:
                raise InputValidationError(
                    "La firma no está en la última revisión o no hay revisión previa "
                    f"(signed_revision={signed_rev}, total_revisions={total})."
                )
            end = r.xrefs.get_xref_container_info(signed_rev - 1).end_location
    except PipelineError:
        raise
    except Exception as e:  # noqa: BLE001
        raise InputValidationError(f"No se pudo analizar el PDF: {type(e).__name__}: {e}") from e

    eof = data.find(b"%%EOF", end)
    if eof < 0:
        raise ExtractError("No se encontró el %%EOF de la revisión 0.")
    cut = eof + len(b"%%EOF")
    if data[cut : cut + 2] == b"\r\n":
        cut += 2
    elif data[cut : cut + 1] in (b"\n", b"\r"):
        cut += 1

    output_path.parent.mkdir(parents=True, exist_ok=True)
    tmp = output_path.parent / f".{output_path.name}.{secrets.token_hex(4)}.tmp"
    try:
        tmp.write_bytes(data[:cut])
        try:
            assert_unsigned_single_revision(tmp)
        except InputValidationError as e:
            raise ExtractError(f"La base extraída no es válida: {e}") from e
        os.chmod(tmp, 0o644)
        os.replace(tmp, output_path)
    finally:
        if tmp.exists():
            tmp.unlink()


# --------------------------------------------------------------------------
# Etapa 3: firmar en modo PAdES (con TSA opcional)
# --------------------------------------------------------------------------
def sign_pdf(
    normalized_path: Path,
    signed_path: Path,
    signer_handle: SignerHandle,
    tsa_url: Optional[str] = None,
    visible: bool = False,
    field_name: str = DEFAULT_FIELD_NAME,
    md_algorithm: str = DEFAULT_MD_ALGORITHM,
    reason: str = DEFAULT_REASON,
    location: str = DEFAULT_LOCATION,
    signer_details: Optional[dict] = None,
    conformity: Optional[dict] = None,
) -> SignResult:
    """Firma `normalized_path` (ya normalizado a PDF/A-2b) en modo PAdES.

    Con `visible=True` agrega un sello en el PIE de la última página (ancho
    de la página menos márgenes) con los datos de `signer_details` (ver
    signer_details.extract_signer_details): firmante, cargo, razón social,
    RUC, país/provincia y fecha.

    Lanza TsaError si la TSA indicada no respondió, o SigningError ante
    cualquier otro fallo de pyHanko.
    """
    from pyhanko import stamp
    from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter
    from pyhanko.sign import fields, signers
    from pyhanko.sign.fields import SigSeedSubFilter
    from pyhanko.sign.timestamps import HTTPTimeStamper, TimestampRequestError

    timestamper = HTTPTimeStamper(url=tsa_url, timeout=20) if tsa_url else None

    meta = signers.PdfSignatureMetadata(
        field_name=field_name,
        md_algorithm=md_algorithm,
        subfilter=SigSeedSubFilter.PADES,
        reason=reason,
        location=location,
    )

    try:
        with normalized_path.open("rb") as inf:
            w = IncrementalPdfFileWriter(inf)

            if conformity:
                # Nombre y fecha del trabajador en la MISMA revisión incremental
                # (antes de reservar el pie, para usar la caja original de la página).
                page_ref, _ = w.find_page_for_modification(-1)
                draw_conformity(w, page_ref, page_ref.get_object(), conformity)

            new_field_spec = None
            stamp_style = None
            if visible:
                # Reserva el pie en la MISMA revisión incremental (la firma cubre
                # el cambio): el sello va en una franja nueva y no tapa contenido.
                page_ref, _ = w.find_page_for_modification(-1)
                page = page_ref.get_object()
                reserve_footer_space(page)
                w.update_container(page)
                box, rotation, visual_width = compute_stamp_box(page)
                font_path = find_stamp_font()
                lines, size = fit_stamp_lines(
                    build_stamp_lines(signer_details or {}),
                    visual_width - 2 * STAMP_PADDING - 1.0,
                    _TextMeter(font_path),
                )
                stamp_style, _ = _build_stamp_style(lines, size, visual_width, font_path, rotation)
                new_field_spec = fields.SigFieldSpec(field_name, on_page=-1, box=box)

            pdf_signer = signers.PdfSigner(
                meta,
                signer=signer_handle.signer,
                timestamper=timestamper,
                new_field_spec=new_field_spec,
                stamp_style=stamp_style,
            )
            with signed_path.open("wb") as outf:
                pdf_signer.sign_pdf(w, output=outf)
    except ConformityError:
        if signed_path.exists():
            signed_path.unlink()
        raise
    except TimestampRequestError as e:
        if signed_path.exists():
            signed_path.unlink()
        raise TsaError(
            f"No se pudo obtener el sello de tiempo de la TSA indicada ({tsa_url}): {e}"
        ) from e
    except Exception as e:  # noqa: BLE001 - cualquier otro fallo real de firma
        if signed_path.exists():
            signed_path.unlink()
        raise SigningError(
            f"Error inesperado al firmar: {type(e).__name__}: {e}\n"
            f"{traceback.format_exc(limit=-6)}"
        ) from e

    if not signed_path.is_file() or signed_path.stat().st_size == 0:
        raise SigningError(
            "La firma 'terminó' sin excepción, pero no se generó un archivo válido."
        )

    return SignResult(
        tsa_applied=tsa_url is not None,
        stamp_applied=bool(visible),
        conformity_applied=bool(conformity),
    )


# --------------------------------------------------------------------------
# Etapa 4: verificar la firma
# --------------------------------------------------------------------------
def load_trust_material(trust_dir: Path = TRUST_DIR) -> tuple[list, list]:
    """Lee los certificados de `trust_dir` y los separa en (raíces, intermedias).

    Una raíz es un certificado autofirmado (subject == issuer); el resto se
    usa solo para construir la cadena. Un directorio inexistente o vacío
    devuelve dos listas vacías: la verificación queda como antes, contra el
    almacén del sistema.
    """
    from asn1crypto import pem, x509

    roots: list = []
    intermediates: list = []
    if not trust_dir.is_dir():
        return roots, intermediates

    for path in sorted(trust_dir.iterdir()):
        if path.suffix.lower() not in (".pem", ".crt", ".cer"):
            continue
        data = path.read_bytes()
        blobs = (
            [der for _, _, der in pem.unarmor(data, multiple=True)]
            if pem.detect(data)
            else [data]
        )
        for der in blobs:
            cert = x509.Certificate.load(der)
            # self_issued es bool; self_signed devuelve "no"/"maybe"/"yes".
            (roots if cert.self_issued else intermediates).append(cert)
    return roots, intermediates


def verify_signed_pdf(signed_path: Path) -> VerifyResult:
    """Relee `signed_path` y valida la(s) firma(s) embebidas con pyHanko.

    Confía en el almacén del sistema más las CA de TRUST_DIR (ver
    load_trust_material), sin fetching de red. Lanza VerificationError si el
    PDF no tiene firmas embebidas o si la validación en sí falla de forma
    inesperada (no confundir con `valid=False`, que es un resultado válido,
    no una excepción).
    """
    from pyhanko.pdf_utils.reader import PdfFileReader
    from pyhanko.sign.validation import validate_pdf_signature
    from pyhanko.sign.validation.status import SignatureCoverageLevel
    from pyhanko_certvalidator import ValidationContext

    try:
        with signed_path.open("rb") as f:
            r = PdfFileReader(f)
            sigs = r.embedded_signatures
            if not sigs:
                raise VerificationError(
                    "El PDF firmado no contiene ninguna firma embebida detectable."
                )
            sig = sigs[-1]
            roots, intermediates = load_trust_material()
            vc = ValidationContext(
                extra_trust_roots=roots,
                other_certs=intermediates,
                allow_fetching=False,
            )
            status = validate_pdf_signature(sig, signer_validation_context=vc)
    except VerificationError:
        raise
    except Exception as e:  # noqa: BLE001
        raise VerificationError(
            f"Error inesperado al validar: {type(e).__name__}: {e}\n"
            f"{traceback.format_exc(limit=-6)}"
        ) from e

    tsa_applied = status.timestamp_validity is not None
    tsa_time = (
        status.timestamp_validity.timestamp.isoformat()
        if tsa_applied and status.timestamp_validity.timestamp
        else None
    )
    signing_time = (
        status.signer_reported_dt.isoformat() if status.signer_reported_dt else None
    )

    return VerifyResult(
        intact=bool(status.intact),
        valid=bool(status.valid),
        trusted=bool(status.trusted),
        covers_whole_file=(status.coverage == SignatureCoverageLevel.ENTIRE_FILE),
        signer_subject=status.signing_cert.subject.human_friendly,
        signing_time=signing_time,
        tsa_applied=tsa_applied,
        tsa_time=tsa_time,
        digest_algo=status.md_algorithm,
        details=status.pretty_print_details(),
    )


# --------------------------------------------------------------------------
# Orquestador de punta a punta, usado por app.py (POST /sign).
# --------------------------------------------------------------------------
def run_sign_pipeline(
    *,
    input_path: Path,
    certificate_path: Path,
    certificate_password: Optional[str],
    output_path: Path,
    work_dir: Path,
    tsa_url: Optional[str] = None,
    gs_bin: str = "gs",
    icc_override: Optional[str] = None,
    visible: bool = False,
    field_name: str = DEFAULT_FIELD_NAME,
    md_algorithm: str = DEFAULT_MD_ALGORITHM,
    reason: str = DEFAULT_REASON,
    location: str = DEFAULT_LOCATION,
    conformity: Optional[dict] = None,
    base_output_path: Optional[Path] = None,
    skip_normalize: bool = False,
) -> dict:
    """Corre el pipeline completo normalizar -> firmar -> [TSA] -> verificar
    con un certificado REAL, y deja el PDF firmado en `output_path`.

    Lanza una subclase de PipelineError (con `.stage`) ante cualquier fallo.
    Si retorna sin excepción, `output_path` existe y contiene el PDF firmado
    y verificado. Devuelve un dict listo para serializar como el objeto
    "signature" de la respuesta HTTP de /sign; además de los campos de
    verificación incluye `stamp_applied` (bool: se dibujó el sello visible) y
    `signer_details` (name, organization, ruc, title, country, locality,
    signed_at_local; ver signer_details.py), que se devuelven aunque
    `visible=False`.
    """
    work_dir.mkdir(parents=True, exist_ok=True)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    check_input_pdf(input_path)

    base_written = False
    if skip_normalize:
        # La entrada YA es la base normalizada (revisión 0): se usa tal cual.
        assert_unsigned_single_revision(input_path)
        normalized_path = input_path
    else:
        normalized_path = work_dir / "normalized.pdfa.pdf"
        normalize_to_pdfa(input_path, normalized_path, work_dir, gs_bin, icc_override)
        if base_output_path is not None:
            # Antes de dibujar o firmar: la base es la revisión 0 sin conformidad.
            publish_file(normalized_path, base_output_path)
            base_written = True

    if conformity:
        load_conformity_font()  # falla temprano (stage 'conformity') si falta la fuente

    signer_handle = load_signer_from_pfx(certificate_path, certificate_password)

    # Hora de Lima fijada justo antes de firmar: la usan el sello y la respuesta.
    signed_at = datetime.now(timezone.utc)
    signer_details = extract_signer_details(
        signer_handle.signer.signing_cert.subject, signed_at
    )

    sign_result = sign_pdf(
        normalized_path,
        output_path,
        signer_handle,
        tsa_url=tsa_url,
        visible=visible,
        field_name=field_name,
        md_algorithm=md_algorithm,
        reason=reason,
        location=location,
        signer_details=signer_details,
        conformity=conformity,
    )

    verify_result = verify_signed_pdf(output_path)

    os.chmod(output_path, 0o644)

    if not (verify_result.intact and verify_result.valid):
        # La firma se generó pero no es íntegra/válida: esto es un fallo
        # real del pipeline (normalización o firma corrompió algo), no algo
        # esperado. No dejamos un PDF "firmado" pero inválido reemplazando
        # nada aguas arriba (el llamador decide qué hacer con output_path).
        raise VerificationError(
            "La firma generada no es íntegra/válida criptográficamente.\n"
            + verify_result.details
        )

    return {
        "intact": verify_result.intact,
        "valid": verify_result.valid,
        "trusted": verify_result.trusted,
        "covers_whole_file": verify_result.covers_whole_file,
        "signer_subject": verify_result.signer_subject,
        "signing_time": verify_result.signing_time,
        "tsa_applied": sign_result.tsa_applied and verify_result.tsa_applied,
        "tsa_time": verify_result.tsa_time,
        "digest_algo": verify_result.digest_algo,
        "sha256_of_signed_file": sha256_file(output_path),
        "stamp_applied": sign_result.stamp_applied,
        "signer_details": signer_details,
        "conformity_applied": sign_result.conformity_applied,
        "base_written": base_written,
    }
