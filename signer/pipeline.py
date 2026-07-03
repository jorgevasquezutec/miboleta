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

import hashlib
import secrets
import shutil
import subprocess
import traceback
from dataclasses import dataclass, field
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Optional


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
) -> SignResult:
    """Firma `normalized_path` (ya normalizado a PDF/A-2b) en modo PAdES.

    Lanza TsaError si la TSA indicada no respondió, o SigningError ante
    cualquier otro fallo de pyHanko.
    """
    from pyhanko import stamp
    from pyhanko.pdf_utils.incremental_writer import IncrementalPdfFileWriter
    from pyhanko.sign import fields, signers
    from pyhanko.sign.fields import SigSeedSubFilter
    from pyhanko.sign.timestamps import HTTPTimeStamper, TimestampRequestError

    timestamper = HTTPTimeStamper(url=tsa_url, timeout=20) if tsa_url else None

    new_field_spec = None
    stamp_style = None
    if visible:
        stamp_style = stamp.TextStampStyle(
            stamp_text="Firmado digitalmente - MiBoleta\nFecha: %(ts)s"
        )
        new_field_spec = fields.SigFieldSpec(field_name, on_page=-1, box=(30, 25, 260, 90))

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
            pdf_signer = signers.PdfSigner(
                meta,
                signer=signer_handle.signer,
                timestamper=timestamper,
                new_field_spec=new_field_spec,
                stamp_style=stamp_style,
            )
            with signed_path.open("wb") as outf:
                pdf_signer.sign_pdf(w, output=outf)
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
            f"{traceback.format_exc(limit=6)}"
        ) from e

    if not signed_path.is_file() or signed_path.stat().st_size == 0:
        raise SigningError(
            "La firma 'terminó' sin excepción, pero no se generó un archivo válido."
        )

    return SignResult(tsa_applied=tsa_url is not None)


# --------------------------------------------------------------------------
# Etapa 4: verificar la firma
# --------------------------------------------------------------------------
def verify_signed_pdf(signed_path: Path) -> VerifyResult:
    """Relee `signed_path` y valida la(s) firma(s) embebidas con pyHanko.

    A propósito NO agrega ningún certificado como trust root: reporta
    `trusted` tal cual lo determine pyHanko contra el ValidationContext por
    defecto (sin fetching de red). Lanza VerificationError si el PDF no
    tiene firmas embebidas o si la validación en sí falla de forma
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
            vc = ValidationContext(allow_fetching=False)
            status = validate_pdf_signature(sig, signer_validation_context=vc)
    except VerificationError:
        raise
    except Exception as e:  # noqa: BLE001
        raise VerificationError(
            f"Error inesperado al validar: {type(e).__name__}: {e}\n"
            f"{traceback.format_exc(limit=6)}"
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
) -> dict:
    """Corre el pipeline completo normalizar -> firmar -> [TSA] -> verificar
    con un certificado REAL, y deja el PDF firmado en `output_path`.

    Lanza una subclase de PipelineError (con `.stage`) ante cualquier fallo.
    Si retorna sin excepción, `output_path` existe y contiene el PDF firmado
    y verificado. Devuelve un dict listo para serializar como el objeto
    "signature" de la respuesta HTTP de /sign.
    """
    work_dir.mkdir(parents=True, exist_ok=True)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    check_input_pdf(input_path)

    normalized_path = work_dir / "normalized.pdfa.pdf"
    normalize_to_pdfa(input_path, normalized_path, work_dir, gs_bin, icc_override)

    signer_handle = load_signer_from_pfx(certificate_path, certificate_password)

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
    )

    verify_result = verify_signed_pdf(output_path)

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
    }
