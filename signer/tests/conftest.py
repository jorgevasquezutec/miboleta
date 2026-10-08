import sys
from pathlib import Path

# Los módulos del signer viven en signer/ (no es un paquete instalable).
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))


# --------------------------------------------------------------------------
# Utilidades compartidas por los tests de firma (conformidad / extract-base)
# --------------------------------------------------------------------------
import pytest  # noqa: E402

PFX_PASSWORD = "pw-de-prueba"

LAYOUT_A4 = {
    "mode": "absolute", "x_mm": 137.0, "name_y_mm": 238.8, "width_mm": 56.0, "align": "C",
    "name_font_size": 13, "name_height_mm": 7, "date_offset_y_mm": 5, "date_font_size": 7,
}


def make_pfx(path, password=PFX_PASSWORD):
    """Certificado autofirmado de prueba (.pfx) en `path`."""
    from datetime import datetime, timedelta, timezone

    from cryptography import x509
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import rsa
    from cryptography.hazmat.primitives.serialization import pkcs12
    from cryptography.x509.oid import NameOID

    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([
        x509.NameAttribute(NameOID.COMMON_NAME, "EMPRESA PRUEBA"),
        x509.NameAttribute(NameOID.ORGANIZATION_NAME, "EMPRESA PRUEBA SAC"),
    ])
    now = datetime.now(timezone.utc)
    cert = (
        x509.CertificateBuilder()
        .subject_name(name).issuer_name(name).public_key(key.public_key())
        .serial_number(1).not_valid_before(now - timedelta(days=1))
        .not_valid_after(now + timedelta(days=30)).sign(key, hashes.SHA256())
    )
    path.write_bytes(pkcs12.serialize_key_and_certificates(
        b"t", key, cert, None, serialization.BestAvailableEncryption(password.encode())))
    return path


def make_pdf(path, media_box=(0, 0, 595, 842), crop_box=None, rotate=0, content=b"q Q"):
    """PDF de 1 página mínimo (sin normalizar) con la caja/rotación pedidas."""
    from pyhanko.pdf_utils.generic import ArrayObject, NameObject, NumberObject, StreamObject
    from pyhanko.pdf_utils.writer import PageObject, PdfFileWriter

    w = PdfFileWriter()
    page = PageObject(contents=w.add_object(StreamObject(stream_data=content)), media_box=media_box)
    ref = w.insert_page(page)
    if crop_box:
        ref.get_object()[NameObject("/CropBox")] = ArrayObject(NumberObject(v) for v in crop_box)
    if rotate:
        ref.get_object()[NameObject("/Rotate")] = NumberObject(rotate)
    with path.open("wb") as f:
        w.write(f)
    return path


@pytest.fixture
def pfx(tmp_path):
    return make_pfx(tmp_path / "cert.pfx")
