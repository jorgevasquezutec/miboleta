#!/usr/bin/env python3
"""
spike_sign.py - MiBoleta - CLI de spike/diagnóstico de firma digital legal.

Valida, de punta a punta y sobre un PDF REAL, la viabilidad (o el estado de
salud) del pipeline:

    normalizar (Ghostscript -> PDF/A-2b)
        -> firmar (pyHanko, PAdES, incremental)
            -> [opcional] sellar con una TSA (RFC 3161)
                -> verificar la firma resultante

sin necesitar el certificado real del cliente: si no se pasa --pfx, el script
genera al vuelo un certificado autofirmado (RSA 2048) de PRUEBA, lo usa para
firmar, y luego DEMUESTRA que la firma es íntegra y criptográficamente válida
aunque la cadena de confianza no sea de fiar (justamente porque es
autofirmado). Ese es el resultado ESPERADO al correr sin --pfx, no un error.

NOTA: la lógica del pipeline en sí (normalizar/firmar/verificar) vive en
signer/pipeline.py, compartida con la API HTTP productiva (signer/app.py).
Este script es solo un envoltorio de CLI que arma un reporte legible en
español paso a paso; es útil para diagnóstico manual dentro del contenedor
`signer` sin tener que levantar la API.

Uso basico:
    python spike_sign.py /var/www/html/storage/app/documents/1/boleta_remuneraciones/2026-01/72391682.pdf

Con sello de tiempo (requiere Internet desde el contenedor):
    python spike_sign.py <pdf> --tsa-url https://freetsa.org/tsr

Con un certificado real (.pfx/.p12) en vez del autofirmado de prueba:
    python spike_sign.py <pdf> --pfx /var/www/html/storage/app/certificates/empresa.pfx \\
        --pfx-password "$SIGNER_PFX_PASSWORD"

Ver signer/README.md para más detalle sobre cómo interpretar el reporte.
"""
from __future__ import annotations

import argparse
import logging
import sys
import traceback
from dataclasses import dataclass, field
from datetime import datetime
from pathlib import Path
from typing import Optional

import pipeline


# --------------------------------------------------------------------------
# Reporte: cada etapa se imprime en vivo y además queda registrada para el
# resumen final. status in {"OK", "WARN", "FAIL", "SKIP"}.
# --------------------------------------------------------------------------
@dataclass
class StepResult:
    number: int
    title: str
    status: str
    detail: str = ""


@dataclass
class Report:
    steps: list = field(default_factory=list)

    def add(self, number: int, title: str, status: str, detail: str = "") -> StepResult:
        result = StepResult(number, title, status, detail)
        self.steps.append(result)
        icon = {"OK": "[OK]  ", "WARN": "[WARN]", "FAIL": "[FAIL]", "SKIP": "[SKIP]"}.get(
            status, "[??]  "
        )
        print(f"\n{icon} Paso {number}: {title}")
        if detail:
            for line in detail.rstrip().splitlines():
                print(f"        {line}")
        return result

    def has_failure(self) -> bool:
        return any(s.status == "FAIL" for s in self.steps)

    def print_summary(self) -> None:
        print("\n" + "=" * 78)
        print("RESUMEN DEL SPIKE")
        print("=" * 78)
        for s in self.steps:
            print(f"  [{s.status:<4}] Paso {s.number}: {s.title}")
        print("=" * 78)


# --------------------------------------------------------------------------
# Etapa 0: validar el PDF de entrada
# --------------------------------------------------------------------------
def stage_check_input(report: Report, input_path: Path) -> bool:
    try:
        size_bytes = pipeline.check_input_pdf(input_path)
    except pipeline.InputValidationError as e:
        report.add(0, "Validar PDF de entrada", "FAIL", str(e))
        return False
    report.add(
        0,
        "Validar PDF de entrada",
        "OK",
        f"Archivo: {input_path}\nTamaño: {size_bytes / 1024:.1f} KB",
    )
    return True


# --------------------------------------------------------------------------
# Etapa 1: normalizar a PDF/A-2b con Ghostscript
# --------------------------------------------------------------------------
def stage_normalize_pdfa(
    report: Report,
    input_path: Path,
    output_path: Path,
    work_dir: Path,
    gs_bin: str,
    icc_override: Optional[str],
) -> bool:
    try:
        result = pipeline.normalize_to_pdfa(
            input_path, output_path, work_dir, gs_bin, icc_override
        )
    except pipeline.NormalizationError as e:
        report.add(1, "Normalizar a PDF/A-2b (Ghostscript)", "FAIL", str(e))
        return False

    if result.icc_profile is None:
        report.add(
            1,
            "Normalizar a PDF/A-2b (Ghostscript)",
            "WARN",
            "No se encontró ningún perfil ICC (se esperaba "
            "/usr/share/color/icc/sRGB.icc del paquete Debian "
            "'icc-profiles-free'). Se continuó SIN OutputIntent: el PDF "
            "resultante NO es PDF/A-2b conforme en sentido estricto, aunque "
            "sí quedó reescrito por 'pdfwrite'. Revisa signer/Dockerfile.\n"
            f"Salida: {output_path} ({output_path.stat().st_size / 1024:.1f} KB)",
        )
        return True

    conformance_note = (
        "PDF/A-2b con OutputIntent (sRGB) incrustado."
        if result.conformant
        else "PDF/A-2b PARCIAL (degradación reportada por Ghostscript, ver stderr)."
    )
    status = "OK" if result.conformant else "WARN"
    report.add(
        1,
        "Normalizar a PDF/A-2b (Ghostscript)",
        status,
        f"Salida: {output_path} ({output_path.stat().st_size / 1024:.1f} KB)\n"
        f"Perfil ICC usado: {result.icc_profile}\n"
        f"Conformidad: {conformance_note}"
        + (f"\n--- stderr (últimas líneas) ---\n{result.stderr_tail}" if result.stderr_tail else ""),
    )
    return True


# --------------------------------------------------------------------------
# Etapa 2: obtener el firmante (SimpleSigner) - self-signed o .pfx real
# --------------------------------------------------------------------------
def stage_get_signer(
    report: Report,
    work_dir: Path,
    pfx_path: Optional[str],
    pfx_password: Optional[str],
) -> Optional[pipeline.SignerHandle]:
    try:
        if pfx_path:
            handle = pipeline.load_signer_from_pfx(Path(pfx_path), pfx_password)
            report.add(
                2,
                "Preparar firmante (certificado)",
                "OK",
                f"Certificado REAL cargado desde {pfx_path}\nSujeto: {handle.subject}",
            )
            return handle

        handle = pipeline.generate_test_signer(work_dir)
        report.add(
            2,
            "Preparar firmante (certificado)",
            "OK",
            "Certificado AUTOFIRMADO de PRUEBA generado (RSA 2048, SHA-256, "
            "validez 365 días).\n"
            f"Guardado en: {work_dir / 'self-signed-test.pfx'}\n"
            f"Sujeto: {handle.subject}\n"
            "Este certificado NO es de confianza para ningún verificador real "
            "(a propósito): sirve para validar el pipeline, no la identidad.",
        )
        return handle
    except pipeline.SignerLoadError as e:
        report.add(2, "Preparar firmante (certificado)", "FAIL", str(e))
        return None


# --------------------------------------------------------------------------
# Etapa 3: firmar en modo PAdES (con TSA opcional)
# --------------------------------------------------------------------------
def stage_sign(
    report: Report,
    normalized_path: Path,
    signed_path: Path,
    signer_handle: pipeline.SignerHandle,
    tsa_url: Optional[str],
    visible: bool,
    field_name: str,
    md_algorithm: str,
    reason: str,
    location: str,
) -> bool:
    try:
        result = pipeline.sign_pdf(
            normalized_path,
            signed_path,
            signer_handle,
            tsa_url=tsa_url,
            visible=visible,
            field_name=field_name,
            md_algorithm=md_algorithm,
            reason=reason,
            location=location,
        )
    except pipeline.TsaError as e:
        report.add(
            3,
            "Firmar PDF (PAdES, pyHanko)",
            "FAIL",
            f"{e}\n¿Hay salida a Internet desde el contenedor 'signer'? Puedes "
            "reintentar sin --tsa-url para validar el resto del pipeline.",
        )
        return False
    except pipeline.SigningError as e:
        report.add(3, "Firmar PDF (PAdES, pyHanko)", "FAIL", str(e))
        return False

    tsa_note = f"Sellado de tiempo: SI, vía {tsa_url}" if tsa_url else (
        "Sellado de tiempo: NO se solicitó (--tsa-url no indicado). "
        "La firma es válida sin TSA, pero sin sello de tiempo confiable de "
        "un tercero."
    )
    report.add(
        3,
        "Firmar PDF (PAdES, pyHanko)",
        "OK",
        f"Salida: {signed_path} ({signed_path.stat().st_size / 1024:.1f} KB)\n"
        f"Campo de firma: {field_name}\n"
        f"SubFilter: ETSI.CAdES.detached (PAdES)\n"
        f"Digest: {md_algorithm}\n"
        f"{tsa_note}\n"
        f"Apariencia visible: {'sí' if visible else 'no (firma invisible)'}",
    )
    return True


# --------------------------------------------------------------------------
# Etapa 4: verificar la firma
# --------------------------------------------------------------------------
def stage_verify(report: Report, signed_path: Path) -> bool:
    try:
        status = pipeline.verify_signed_pdf(signed_path)
    except pipeline.VerificationError as e:
        report.add(4, "Verificar la firma (pyHanko)", "FAIL", str(e))
        return False

    if not (status.intact and status.valid):
        report.add(
            4,
            "Verificar la firma (pyHanko)",
            "FAIL",
            "La firma NO es íntegra/válida criptográficamente. Esto SÍ es un "
            "error real del pipeline (normalización, firma o generación del "
            "PDF corrompieron algo).\n\n" + status.details,
        )
        return False

    if status.trusted:
        trust_line = (
            "La cadena de confianza VALIDÓ (inesperado con un certificado "
            "autofirmado salvo que hayas usado --pfx con un certificado real "
            "encadenado a una CA del sistema)."
        )
    else:
        trust_line = (
            "La cadena de confianza NO validó -> ESPERADO con un certificado "
            "autofirmado de prueba. Lo relevante para este spike es que la "
            "firma esté INTACTA y sea VÁLIDA (lo está); un lector como Adobe "
            "Reader mostraría 'firma válida, pero el certificado del firmante "
            "no es de confianza', que es exactamente la situación que "
            "corresponde resolver luego con el certificado real del cliente."
        )

    ts_line = ""
    if status.tsa_applied:
        ts_line = f"\nSello de tiempo: aplicado, timestamp={status.tsa_time}"

    report.add(
        4,
        "Verificar la firma (pyHanko)",
        "OK",
        f"Firma intacta: {status.intact}\n"
        f"Firma criptográficamente válida: {status.valid}\n"
        f"Cadena de confianza: {status.trusted}\n"
        f"Cubre todo el archivo: {status.covers_whole_file}\n"
        f"{trust_line}"
        f"{ts_line}\n\n"
        f"--- Detalle pyHanko ---\n{status.details}",
    )
    return True


# --------------------------------------------------------------------------
# main
# --------------------------------------------------------------------------
def build_arg_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Spike/diagnóstico de firma digital legal de boletas (Ghostscript + pyHanko).",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument("input_pdf", help="Ruta al PDF de entrada (boleta real de prueba).")
    parser.add_argument(
        "-o",
        "--output",
        help="Ruta del PDF firmado final. Por defecto: <work-dir>/signed.pdf",
    )
    parser.add_argument(
        "--work-dir",
        help=(
            "Directorio donde se dejan los artefactos intermedios (PDF/A, "
            "PDFA_def.ps, .pfx autofirmado, PDF firmado). Por defecto: "
            "storage/app/private/signing-spike/<timestamp>/ si existe "
            "storage/app en el cwd, si no, junto al PDF de entrada."
        ),
    )
    parser.add_argument(
        "--tsa-url",
        help="URL de una TSA RFC 3161 (p.ej. https://freetsa.org/tsr). Opcional.",
    )
    parser.add_argument(
        "--pfx",
        help="Ruta a un .pfx/.p12 real para firmar en vez del autofirmado de prueba.",
    )
    parser.add_argument(
        "--pfx-password",
        help="Contraseña del .pfx indicado en --pfx. También puede venir de "
        "la variable de entorno SIGNER_PFX_PASSWORD.",
    )
    parser.add_argument(
        "--visible",
        action="store_true",
        help="Agrega una apariencia de firma visible (sello de texto) en la última página.",
    )
    parser.add_argument(
        "--field-name", default=pipeline.DEFAULT_FIELD_NAME, help="Nombre del campo de firma PDF."
    )
    parser.add_argument(
        "--md-algorithm",
        default=pipeline.DEFAULT_MD_ALGORITHM,
        help="Algoritmo de digest para la firma (default: sha256).",
    )
    parser.add_argument(
        "--reason",
        default="Prueba de viabilidad tecnica - firma digital de boletas (SPIKE)",
        help="Motivo de firma embebido en la firma PAdES.",
    )
    parser.add_argument(
        "--location", default="MiBoleta - entorno de spike", help="Ubicacion de firma."
    )
    parser.add_argument(
        "--gs-bin", default="gs", help="Nombre/ruta del binario de Ghostscript (default: gs)."
    )
    parser.add_argument(
        "--icc-profile",
        help="Ruta explícita a un perfil ICC a usar como OutputIntent de PDF/A "
        "(por defecto se autodetecta /usr/share/color/icc/sRGB.icc).",
    )
    return parser


def resolve_work_dir(explicit: Optional[str], input_path: Path) -> Path:
    if explicit:
        return Path(explicit)
    run_id = datetime.now().strftime("%Y%m%d-%H%M%S")
    storage_app = Path.cwd() / "storage" / "app"
    if storage_app.is_dir():
        return storage_app / "private" / "signing-spike" / run_id
    return input_path.resolve().parent / "_signing_spike_output" / run_id


def main() -> int:
    import os

    logging.basicConfig(level=logging.WARNING, format="%(levelname)s %(name)s: %(message)s")
    # Con un certificado autofirmado, pyHanko registra (a nivel WARNING, con
    # traceback incluido) que no pudo construir un camino de confianza. Es
    # RUIDO ESPERADO -ver Paso 4 del reporte, que lo explica en español-, así
    # que se silencia puntualmente ese logger para no confundirlo con un
    # error real de nuestro propio script.
    logging.getLogger("pyhanko.sign.validation.generic_cms").setLevel(logging.ERROR)

    args = build_arg_parser().parse_args()
    input_path = Path(args.input_pdf)
    work_dir = resolve_work_dir(args.work_dir, input_path)
    work_dir.mkdir(parents=True, exist_ok=True)

    normalized_path = work_dir / "normalized.pdfa.pdf"
    signed_path = Path(args.output) if args.output else work_dir / "signed.pdf"
    signed_path.parent.mkdir(parents=True, exist_ok=True)

    pfx_password = args.pfx_password or os.environ.get("SIGNER_PFX_PASSWORD")

    print("=" * 78)
    print("SPIKE: firma digital legal de boletas (Ghostscript + pyHanko)")
    print("=" * 78)
    print(f"Entrada:    {input_path}")
    print(f"Work dir:   {work_dir}")
    print(f"Salida:     {signed_path}")
    print(f"TSA:        {args.tsa_url or '(sin TSA)'}")
    print(f"Certificado:{' .pfx real -> ' + args.pfx if args.pfx else ' autofirmado de prueba'}")

    report = Report()

    if not stage_check_input(report, input_path):
        report.print_summary()
        return 1

    if not stage_normalize_pdfa(
        report, input_path, normalized_path, work_dir, args.gs_bin, args.icc_profile
    ):
        report.print_summary()
        return 1

    signer_handle = stage_get_signer(report, work_dir, args.pfx, pfx_password)
    if signer_handle is None:
        report.print_summary()
        return 1

    signed_ok = stage_sign(
        report,
        normalized_path,
        signed_path,
        signer_handle,
        args.tsa_url,
        args.visible,
        args.field_name,
        args.md_algorithm,
        args.reason,
        args.location,
    )
    if not signed_ok:
        report.print_summary()
        return 1

    verified_ok = stage_verify(report, signed_path)

    report.print_summary()

    if report.has_failure():
        print(
            "\nRESULTADO: hay al menos una etapa con FAIL real. Revisa el "
            "detalle arriba antes de sacar conclusiones sobre viabilidad."
        )
        return 1

    print(
        "\nRESULTADO: el pipeline normalizar -> firmar -> verificar funcionó "
        "de punta a punta sobre un PDF real. La 'no confianza' del "
        "certificado autofirmado (si aplica) es esperada, no un fallo."
    )
    return 0 if verified_ok else 1


if __name__ == "__main__":
    sys.exit(main())
