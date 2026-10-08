#!/usr/bin/env python3
"""
signer/app.py - MiBoleta - API HTTP interna del sidecar de firma digital.

Expone el pipeline de signer/pipeline.py (normalizar -> firmar PAdES ->
[TSA] -> verificar) como una API HTTP pequeña, pensada para ser consumida
SOLO desde dentro de la red interna de Docker (`miboleta_network`) por el
backend Laravel (servicio `app`/`horizon`, vía el Job `SignDocument`).

Endpoints:
    GET  /health   -> {"status": "ok"}
    POST /sign     -> firma un PDF con un certificado .pfx/.p12 REAL
    POST /verify   -> verifica la(s) firma(s) embebidas en un PDF ya firmado
    POST /extract-base -> recupera la revisión 0 (sin firma) de un PDF firmado

Seguridad (trade-off documentado, ver signer/README.md):
    - Este servicio NO publica puerto al host (`expose:` en docker-compose,
      no `ports:`): solo es alcanzable por otros contenedores de
      `miboleta_network` (app, horizon).
    - La contraseña del certificado viaja en el body JSON de /sign. Al no
      salir de la red interna de Docker (sin publicar al host, sin TLS
      intermedio necesario dentro del mismo host Docker), se considera
      aceptable para este caso de uso; si en el futuro el signer corre en
      OTRO host físico, esto debe revisarse (mTLS o, como mínimo, un shared
      secret adicional en un header).
    - Todas las rutas de entrada/salida (input_path, output_path,
      certificate_path) se reciben como rutas absolutas ya resueltas por
      Laravel dentro del volumen compartido `./backend:/var/www/html`; este
      servicio NO resuelve rutas relativas a un tenant ni conoce el modelo
      de datos de la app.

Ejecuta con:
    uvicorn app:app --host 0.0.0.0 --port 8000
"""
from __future__ import annotations

import logging
import os
import shutil
import uuid
from pathlib import Path
from typing import Optional

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from fastapi.exceptions import RequestValidationError
from pydantic import BaseModel, Field

import pipeline

logging.basicConfig(
    level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s"
)
logger = logging.getLogger("signer.app")

# Todo archivo que escribe el sidecar (root) debe poder leerlo Laravel (www-data).
os.umask(0o022)

# Ruido esperado: pyHanko registra a nivel WARNING (con traceback) cuando no
# puede construir una cadena de confianza para un certificado que no
# encadena a una CA conocida por el sistema. Eso NO es un error del pipeline
# (ver pipeline.verify_signed_pdf / campo "trusted" de la respuesta), así
# que se silencia puntualmente para no ensuciar los logs del servicio.
logging.getLogger("pyhanko.sign.validation.generic_cms").setLevel(logging.ERROR)

app = FastAPI(
    title="MiBoleta Signer",
    description="API HTTP interna de firma digital PAdES (Ghostscript + pyHanko).",
    version="1.0.0",
)


# --------------------------------------------------------------------------
# Modelos de request/response
# --------------------------------------------------------------------------
class SignRequest(BaseModel):
    input_path: str = Field(..., description="Ruta absoluta del PDF a firmar (dentro del volumen compartido).")
    output_path: str = Field(..., description="Ruta absoluta donde escribir el PDF firmado.")
    certificate_path: str = Field(..., description="Ruta absoluta al certificado .pfx/.p12 real.")
    certificate_password: Optional[str] = Field(None, description="Contraseña del certificado, si aplica.")
    tsa_url: Optional[str] = Field(None, description="URL de una TSA RFC 3161. Si se omite, no se sella el tiempo.")
    visible: bool = Field(False, description="Si True, agrega un sello de firma visible en el pie de la última página (firmante, cargo, razón social, RUC, país/provincia, fecha).")
    field_name: str = Field(pipeline.DEFAULT_FIELD_NAME, description="Nombre del campo de firma PDF.")
    md_algorithm: str = Field(pipeline.DEFAULT_MD_ALGORITHM, description="Algoritmo de digest (default: sha256).")
    reason: str = Field(pipeline.DEFAULT_REASON, description="Motivo de firma embebido en la firma PAdES.")
    location: str = Field(pipeline.DEFAULT_LOCATION, description="Ubicación de firma embebida.")
    gs_bin: str = Field("gs", description="Nombre/ruta del binario de Ghostscript.")
    icc_profile: Optional[str] = Field(None, description="Ruta explícita a un perfil ICC (por defecto se autodetecta).")
    conformity: Optional[Conformity] = Field(None, description="Si se envía, dibuja nombre y fecha del trabajador en la última página, en la misma revisión de la firma.")
    base_output_path: Optional[str] = Field(None, description="Si se envía, copia aquí la revisión 0 normalizada (antes de dibujar/firmar).")
    skip_normalize: bool = Field(False, description="True: input_path ya es la base normalizada (1 revisión, 0 firmas) y se usa tal cual.")


class ExtractBaseRequest(BaseModel):
    input_path: str = Field(..., description="Ruta absoluta de un PDF firmado exactamente una vez.")
    output_path: str = Field(..., description="Ruta absoluta donde escribir la revisión 0.")


class ExtractBaseResponse(BaseModel):
    success: bool
    output_path: Optional[str] = None
    error: Optional[str] = None
    stage: Optional[str] = None


class ConformityLayout(BaseModel):
    """Layout del nombre/fecha del trabajador (mm), tal como sale de config/signature.php."""

    mode: str = Field("absolute", description="'absolute' (x/name_y fijos) o 'auto' (esquina inferior derecha).")
    x_mm: Optional[float] = None
    name_y_mm: Optional[float] = None
    width_mm: float = 50
    align: str = "C"
    name_font_size: float = 12
    name_height_mm: float = 8
    date_offset_y_mm: float = 8
    date_font_size: float = 7


class Conformity(BaseModel):
    name: str = Field(..., description="Nombre del trabajador (se dibuja en Segoe Script).")
    date_text: str = Field("", description="Fecha ya formateada, se dibuja debajo del nombre.")
    layout: ConformityLayout = Field(default_factory=ConformityLayout)
    layouts: Optional[dict[str, ConformityLayout]] = Field(
        None,
        description="Opcional: layout por formato (key -> layout). Si viene junto con page_dimensions_mm, el sidecar elige la key cuyo tamaño calza con el de la página; si ninguna calza usa `layout`.",
    )
    page_dimensions_mm: Optional[dict[str, list[float]]] = Field(
        None, description="Opcional: key -> [ancho, alto] en mm de cada formato de `layouts`."
    )


class SignResponse(BaseModel):
    success: bool
    output_path: Optional[str] = None
    signature: Optional[dict] = None
    error: Optional[str] = None
    stage: Optional[str] = None


class VerifyRequest(BaseModel):
    pdf_path: str = Field(..., description="Ruta absoluta de un PDF ya firmado a verificar.")


class VerifyResponse(BaseModel):
    success: bool
    verification: Optional[dict] = None
    error: Optional[str] = None
    stage: Optional[str] = None


class HealthResponse(BaseModel):
    status: str


# --------------------------------------------------------------------------
# Manejo de errores: SIEMPRE se responde con el envelope {success, error,
# stage?}, tanto para errores de validación de request (422) como para
# excepciones no esperadas (500). Los fallos "de negocio" del pipeline
# (certificado inválido, Ghostscript falló, TSA no respondió, firma no
# íntegra) se devuelven con HTTP 200 y success=false: son resultados válidos
# de la operación "intentar firmar", no errores de transporte.
# --------------------------------------------------------------------------
@app.exception_handler(RequestValidationError)
async def validation_error_handler(request: Request, exc: RequestValidationError) -> JSONResponse:
    logger.warning("Request inválido en %s: %s", request.url.path, exc.errors())
    return JSONResponse(
        status_code=422,
        content={
            "success": False,
            "error": f"Request inválido: {exc.errors()}",
            "stage": "request_validation",
        },
    )


@app.exception_handler(Exception)
async def unhandled_exception_handler(request: Request, exc: Exception) -> JSONResponse:
    logger.exception("Error no manejado en %s", request.url.path)
    return JSONResponse(
        status_code=500,
        content={
            "success": False,
            "error": f"Error interno del firmador: {type(exc).__name__}: {exc}",
            "stage": "internal",
        },
    )


# --------------------------------------------------------------------------
# GET /health
# --------------------------------------------------------------------------
@app.get("/health", response_model=HealthResponse)
def health() -> dict:
    return {"status": "ok"}


# --------------------------------------------------------------------------
# POST /sign
# --------------------------------------------------------------------------
@app.post("/sign", response_model=SignResponse)
def sign(req: SignRequest) -> JSONResponse:
    input_path = Path(req.input_path)
    output_path = Path(req.output_path)
    certificate_path = Path(req.certificate_path)

    # Directorio de trabajo temporal, único por request, junto al output
    # solicitado (mismo filesystem/volumen que Laravel), para que los
    # artefactos intermedios (PDFA_def.ps, normalized.pdfa.pdf) no choquen
    # entre requests concurrentes. Se borra siempre al terminar.
    work_dir = output_path.parent / f".signing-work-{uuid.uuid4().hex}"

    logger.info(
        "POST /sign input=%s output=%s cert=%s tsa=%s",
        input_path, output_path, certificate_path, bool(req.tsa_url),
    )

    try:
        signature = pipeline.run_sign_pipeline(
            input_path=input_path,
            certificate_path=certificate_path,
            certificate_password=req.certificate_password,
            output_path=output_path,
            work_dir=work_dir,
            tsa_url=req.tsa_url,
            gs_bin=req.gs_bin,
            icc_override=req.icc_profile,
            visible=req.visible,
            field_name=req.field_name,
            md_algorithm=req.md_algorithm,
            reason=req.reason,
            location=req.location,
            conformity=req.conformity.model_dump() if req.conformity else None,
            base_output_path=Path(req.base_output_path) if req.base_output_path else None,
            skip_normalize=req.skip_normalize,
        )
    except pipeline.PipelineError as e:
        logger.error("Fallo firmando %s en etapa '%s': %s", input_path, e.stage, e)
        # Si algo quedó a medio escribir en output_path, no dejarlo: el
        # llamador (Laravel) nunca debe leer un PDF "firmado" a medias.
        if output_path.exists():
            try:
                output_path.unlink()
            except OSError:
                pass
        return JSONResponse(
            status_code=200,
            content={
                "success": False,
                "error": str(e),
                "stage": e.stage,
            },
        )
    finally:
        shutil.rmtree(work_dir, ignore_errors=True)

    logger.info("Firma OK para %s -> %s", input_path, output_path)
    return JSONResponse(
        status_code=200,
        content={
            "success": True,
            "output_path": str(output_path),
            "signature": signature,
        },
    )


# --------------------------------------------------------------------------
# POST /extract-base
# --------------------------------------------------------------------------
@app.post("/extract-base", response_model=ExtractBaseResponse)
def extract_base(req: ExtractBaseRequest) -> JSONResponse:
    input_path = Path(req.input_path)
    output_path = Path(req.output_path)
    logger.info("POST /extract-base input=%s output=%s", input_path, output_path)
    try:
        pipeline.extract_base(input_path, output_path)
    except pipeline.PipelineError as e:
        logger.warning("Fallo extrayendo base de %s en etapa '%s': %s", input_path, e.stage, e)
        return JSONResponse(
            status_code=200,
            content={"success": False, "error": str(e), "stage": e.stage},
        )
    return JSONResponse(
        status_code=200, content={"success": True, "output_path": str(output_path)}
    )


# --------------------------------------------------------------------------
# POST /verify
# --------------------------------------------------------------------------
@app.post("/verify", response_model=VerifyResponse)
def verify(req: VerifyRequest) -> JSONResponse:
    pdf_path = Path(req.pdf_path)

    logger.info("POST /verify pdf=%s", pdf_path)

    try:
        pipeline.check_input_pdf(pdf_path)
        result = pipeline.verify_signed_pdf(pdf_path)
    except pipeline.PipelineError as e:
        logger.warning("Fallo verificando %s en etapa '%s': %s", pdf_path, e.stage, e)
        return JSONResponse(
            status_code=200,
            content={
                "success": False,
                "error": str(e),
                "stage": e.stage,
            },
        )

    return JSONResponse(
        status_code=200,
        content={
            "success": True,
            "verification": {
                "intact": result.intact,
                "valid": result.valid,
                "trusted": result.trusted,
                "covers_whole_file": result.covers_whole_file,
                "signer_subject": result.signer_subject,
                "signing_time": result.signing_time,
                "tsa_applied": result.tsa_applied,
                "tsa_time": result.tsa_time,
                "digest_algo": result.digest_algo,
            },
        },
    )
