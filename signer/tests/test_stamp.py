"""Tests del sello visible: líneas, ajuste de ancho y geometría en el pie."""
from datetime import datetime, timedelta, timezone

import pytest
from pyhanko.pdf_utils.generic import ArrayObject, DictionaryObject, NameObject, NumberObject

import pipeline
from pipeline import (
    STAMP_FONT_MAX,
    STAMP_FONT_MIN,
    STAMP_RESERVE,
    compute_stamp_box,
    reserve_footer_space,
    _TextMeter,
    build_stamp_lines,
    find_stamp_font,
    fit_stamp_lines,
)

DETAILS = {
    "name": "BASILIO VENTURA WILLIAM",
    "organization": "OVERHEAD MEN SOCIEDAD ANONIMA CERRADA - OVERHEAD MEN S.A.C.",
    "ruc": "20603839961",
    "title": "GERENTE GENERAL",
    "country": "PE",
    "locality": "LIMA",
    "signed_at_local": "06/10/2026 16:24",
}


def test_lineas_completas():
    assert build_stamp_lines(DETAILS) == [
        "Firmado digitalmente por: BASILIO VENTURA WILLIAM · Cargo: GERENTE GENERAL",
        "Razón social: OVERHEAD MEN SOCIEDAD ANONIMA CERRADA - OVERHEAD MEN S.A.C. · RUC: 20603839961",
        "País/Provincia: Perú / LIMA · Fecha: 06/10/2026 16:24",
    ]


def test_campos_faltantes_se_omiten_sin_none():
    lines = build_stamp_lines({"name": "ANA", "country": "CL", "signed_at_local": "01/01/2026 10:00"})
    assert lines == [
        "Firmado digitalmente por: ANA",
        "País/Provincia: CL · Fecha: 01/01/2026 10:00",
    ]
    assert all("None" not in l for l in lines)


def test_sin_datos_solo_titulo():
    assert build_stamp_lines({}) == ["Firmado digitalmente"]


def test_nunca_desborda_el_ancho():
    meter = _TextMeter(find_stamp_font())
    long = [DETAILS["organization"] * 3]
    lines, size = fit_stamp_lines(long, 300.0, meter)
    assert size == STAMP_FONT_MIN
    assert meter.width(lines[0], size) <= 300.0
    assert lines[0].endswith("…")


def test_reduce_fuente_antes_de_truncar():
    meter = _TextMeter(find_stamp_font())
    lines = build_stamp_lines(DETAILS)
    # 360pt: a 8pt no entra la línea larga, pero a >=6pt sí (sin truncar).
    assert max(meter.width(l, STAMP_FONT_MAX) for l in lines) > 360.0
    out, size = fit_stamp_lines(lines, 360.0, meter)
    assert STAMP_FONT_MIN <= size < STAMP_FONT_MAX
    assert not any(l.endswith("…") for l in out)
    assert out == lines
    assert all(meter.width(l, size) <= 360.0 for l in out)


def make_page(box, rotate=None, inherit=False, cropbox=None):
    """Página mínima como diccionario (con /Parent si inherit=True)."""
    arr = ArrayObject(NumberObject(v) for v in box)
    page = DictionaryObject({NameObject("/Type"): NameObject("/Page")})
    if inherit:
        parent = DictionaryObject({NameObject("/MediaBox"): arr})
        if rotate is not None:
            parent[NameObject("/Rotate")] = NumberObject(rotate)
        page[NameObject("/Parent")] = parent
    else:
        page[NameObject("/MediaBox")] = arr
        if rotate is not None:
            page[NameObject("/Rotate")] = NumberObject(rotate)
    if cropbox:
        page[NameObject("/CropBox")] = ArrayObject(NumberObject(v) for v in cropbox)
    return page


@pytest.mark.parametrize(
    "w,h",
    [(595, 842), (612, 792), (842, 595)],  # A4, Letter, A4 apaisado
)
def test_caja_en_el_pie(w, h):
    box, rot, vw = compute_stamp_box(make_page((0, 0, w, h)))
    assert rot == 0
    assert box == (36, 18, w - 36, 76)
    assert vw == w - 72


def test_mediabox_heredada_de_pages():
    box, _, _ = compute_stamp_box(make_page((0, 0, 612, 792), inherit=True))
    assert box == (36, 18, 576, 76)


def test_cropbox_tiene_prioridad_y_origen_no_cero():
    box, _, vw = compute_stamp_box(make_page((0, 0, 700, 900), cropbox=(10, 20, 610, 820)))
    assert box == (46, 38, 574, 96)
    assert vw == 528


def test_rotate_90_franja_vertical_derecha():
    box, rot, vw = compute_stamp_box(make_page((0, 0, 595, 842), rotate=90))
    assert rot == 90
    assert box == (595 - 18 - 58, 36, 595 - 18, 842 - 36)
    assert vw == 842 - 72


def test_rotate_270_heredado_franja_vertical_izquierda():
    box, rot, vw = compute_stamp_box(make_page((0, 0, 595, 842), rotate=270, inherit=True))
    assert rot == 270
    assert box == (18, 36, 18 + 58, 842 - 36)
    assert vw == 842 - 72


def test_reserva_de_espacio_extiende_el_borde_visual_inferior():
    cases = {  # rotación -> MediaBox esperado tras reservar
        0: [0, -STAMP_RESERVE, 595, 842],
        180: [0, 0, 595, 842 + STAMP_RESERVE],
        90: [0, 0, 595 + STAMP_RESERVE, 842],
        270: [-STAMP_RESERVE, 0, 595, 842],
    }
    for rot, expected in cases.items():
        page = make_page((0, 0, 595, 842), rotate=rot, inherit=True)
        reserve_footer_space(page)
        assert [float(v) for v in page["/MediaBox"]] == expected  # escrito en la página
        # el sello cae DENTRO de la franja nueva, no sobre el contenido original
        (x0, y0, x1, y1), _, _ = compute_stamp_box(page)
        if rot == 0:
            assert y1 <= 0
        elif rot == 180:
            assert y0 >= 842
        elif rot == 90:
            assert x0 >= 595
        else:
            assert x1 <= 0


def _make_signer(tmp_path):
    from cryptography import x509
    from cryptography.hazmat.primitives import hashes, serialization
    from cryptography.hazmat.primitives.asymmetric import rsa
    from cryptography.hazmat.primitives.serialization import pkcs12
    from cryptography.x509.oid import NameOID

    key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    name = x509.Name([x509.NameAttribute(NameOID.COMMON_NAME, "PRUEBA ROTADA")])
    now = datetime.now(timezone.utc)
    cert = (
        x509.CertificateBuilder()
        .subject_name(name).issuer_name(name).public_key(key.public_key())
        .serial_number(1).not_valid_before(now - timedelta(days=1))
        .not_valid_after(now + timedelta(days=30)).sign(key, hashes.SHA256())
    )
    pfx = tmp_path / "t.pfx"
    pfx.write_bytes(pkcs12.serialize_key_and_certificates(
        b"t", key, cert, None, serialization.BestAvailableEncryption(b"pw")))
    return pipeline.load_signer_from_pfx(pfx, "pw")


def _pdf_con_pagina(path, rotate):
    """PDF A4 de una página con /Rotate (sin normalizar: basta para firmar)."""
    from pyhanko.pdf_utils.generic import StreamObject
    from pyhanko.pdf_utils.writer import PageObject, PdfFileWriter

    w = PdfFileWriter()
    page = PageObject(contents=w.add_object(StreamObject(stream_data=b"q Q")), media_box=(0, 0, 595, 842))
    ref = w.insert_page(page)
    if rotate:
        ref.get_object()["/Rotate"] = NumberObject(rotate)
    with path.open("wb") as f:
        w.write(f)


@pytest.mark.parametrize("rotate", [0, 90, 270])
def test_firma_visible_en_pagina_rotada(tmp_path, rotate):
    """Integración: el sello va en la franja reservada y la firma sigue válida."""
    from pyhanko.pdf_utils.reader import PdfFileReader
    from pyhanko.sign.validation import validate_pdf_signature

    src, out = tmp_path / "in.pdf", tmp_path / "out.pdf"
    _pdf_con_pagina(src, rotate)
    pipeline.sign_pdf(src, out, _make_signer(tmp_path), visible=True, signer_details=DETAILS)

    with out.open("rb") as f:
        r = PdfFileReader(f)
        page = r.root["/Pages"]["/Kids"][0].get_object()
        mb = [float(v) for v in page["/MediaBox"]]
        expected = (595, 842 + STAMP_RESERVE) if rotate == 0 else (595 + STAMP_RESERVE, 842)
        assert (mb[2] - mb[0], mb[3] - mb[1]) == expected
        widget = page["/Annots"][0].get_object()
        rect = [float(v) for v in widget["/Rect"]]
        if rotate == 0:
            assert (rect[2] - rect[0], rect[3] - rect[1]) == (595 - 72, 58)
            assert "/Matrix" not in widget["/AP"]["/N"].get_object()
        else:
            assert (rect[2] - rect[0], rect[3] - rect[1]) == (58, 842 - 72)
            assert "/Matrix" in widget["/AP"]["/N"].get_object()
        status = validate_pdf_signature(r.embedded_signatures[0])
        assert status.intact and status.valid
