"""Tests del sello compacto (stamp_position="bottom_center" | "bottom_left")."""
import json
from datetime import datetime, timezone

import pytest
from pyhanko.pdf_utils.reader import PdfFileReader

import pipeline
from conftest import PFX_PASSWORD, make_pdf
from pipeline import (
    COMPACT_RESERVE,
    COMPACT_STAMP_HEIGHT,
    COMPACT_STAMP_WIDTH,
    STAMP_MARGIN_X,
    _TextMeter,
    build_compact_stamp_lines,
    build_compact_stamp_runs,
    fit_stamp_runs,
    find_stamp_bold_font,
    compute_compact_stamp_box,
    find_stamp_font,
    reserve_footer_space,
)
from signer_details import extract_signer_details
from test_stamp import DETAILS, make_page

HDR = {**DETAILS, "signed_date_local": "31/08/2026"}


class _Meter:
    def __init__(self, glyphs):
        self.glyphs = glyphs

    def has_glyph(self, ch):
        return ch in self.glyphs


DET = {
    "name": "Basilio Ventura William", "title": "Representante Legal",
    "organization": "OVERHEAD MEN S.A.C.", "ruc": "20603839961", "signed_date_local": "31/08/2025",
}


def test_lineas_completas():
    assert build_compact_stamp_lines(DET, _Meter("\u2713")) == [
        "[\u2713] FIRMADO DIGITALMENTE",
        "Titular: BASILIO VENTURA WILLIAM | Cargo: Representante Legal",
        "Razón social: OVERHEAD MEN S.A.C. - RUC: 20603839961",
        "Fecha: 31/08/2025",
    ]


def test_runs_etiquetas_en_negrita_y_valores_regulares():
    rows = build_compact_stamp_runs(DET, _Meter("\u2713"))
    assert rows[0] == [("[\u2713] FIRMADO DIGITALMENTE", True)]
    assert rows[1] == [("Titular:", True), (" BASILIO VENTURA WILLIAM", False), (" | ", False),
                       ("Cargo:", True), (" Representante Legal", False)]
    assert rows[2] == [("Razón social:", True), (" OVERHEAD MEN S.A.C.", False), (" - ", False),
                       ("RUC:", True), (" 20603839961", False)]
    assert rows[3] == [("Fecha:", True), (" 31/08/2025", False)]


def test_campos_faltantes_no_imprimen_none_ni_lineas_vacias():
    m = _Meter("\u2713")
    assert build_compact_stamp_lines({}, m) == ["[\u2713] FIRMADO DIGITALMENTE"]
    lines = build_compact_stamp_lines({"name": "ana", "ruc": "2060", "signed_date_local": "01/01/2026"}, m)
    assert lines == ["[\u2713] FIRMADO DIGITALMENTE", "Titular: ANA", "RUC: 2060", "Fecha: 01/01/2026"]
    only_title = build_compact_stamp_lines({"title": "Gerente"}, m)
    assert only_title[1] == "Cargo: Gerente"
    assert all("None" not in l for l in lines + only_title)


def test_organizacion_y_cargo_no_se_fuerzan_a_mayusculas():
    lines = build_compact_stamp_lines({"organization": "Mi Empresa SAC", "title": "Jefe"}, _Meter("\u2713"))
    assert "Razón social: Mi Empresa SAC" in lines and "Cargo: Jefe" in lines


def test_fit_reduce_fuente_y_trunca_el_valor_sin_desbordar():
    reg = _TextMeter(find_stamp_font())
    bold = _TextMeter(find_stamp_bold_font())
    rows = build_compact_stamp_runs({**DET, "organization": "EMPRESA " * 20}, bold)
    out, size = fit_stamp_runs(rows, 300.0, reg, bold)
    assert size == pipeline.STAMP_FONT_MIN
    for row in out:
        assert sum((bold if b else reg).width(t, size) for t, b in row) <= 300.0
    assert out[2][1][0].endswith("…") and out[2][0] == ("Razón social:", True)
    # lo que ya cabe no se toca
    ok, size_ok = fit_stamp_runs(build_compact_stamp_runs(DET, bold), 400.0, reg, bold)
    assert size_ok == pipeline.COMPACT_FONT_MAX


def test_fallback_de_glifo():
    assert build_compact_stamp_lines({}, _Meter("✓√"))[0].startswith("[✓]")
    assert build_compact_stamp_lines({}, _Meter("√"))[0].startswith("[√]")
    assert build_compact_stamp_lines({}, _Meter(""))[0].startswith("[X]")


def test_la_fuente_real_tiene_la_marca_elegida():
    meter = _TextMeter(find_stamp_font())
    check = build_compact_stamp_lines({}, meter)[0][1]
    assert check == "X" or meter.has_glyph(check)


def test_fecha_local_de_lima():
    from asn1crypto import x509 as a509

    name = a509.Name.build({"common_name": "X"})
    # 02:00 UTC del 1/09 todavía es 31/08 en Lima (UTC-5)
    d = extract_signer_details(name, datetime(2026, 9, 1, 2, 0, tzinfo=timezone.utc))
    assert d["signed_date_local"] == "31/08/2026"
    assert extract_signer_details(name)["signed_date_local"] is None


@pytest.mark.parametrize("rot", [0, 90, 180, 270])
def test_reserva_y_caja_en_la_franja_inferior_centrada(rot):
    page = make_page((0, 0, 595, 842), rotate=rot, inherit=True)
    reserve_footer_space(page, COMPACT_RESERVE)
    mb = [float(v) for v in page["/MediaBox"]]
    assert mb == {
        0: [0, -COMPACT_RESERVE, 595, 842],
        180: [0, 0, 595, 842 + COMPACT_RESERVE],
        90: [0, 0, 595 + COMPACT_RESERVE, 842],
        270: [-COMPACT_RESERVE, 0, 595, 842],
    }[rot]
    (x0, y0, x1, y1), r, width = compute_compact_stamp_box(page)
    assert r == rot and width == COMPACT_STAMP_WIDTH
    # dentro de la franja nueva (no sobre el contenido original) y centrada en el ancho visual
    if rot == 0:
        assert y1 <= 0 and y0 >= mb[1] and (x0 + x1) / 2 == 595 / 2
    elif rot == 180:
        assert y0 >= 842 and y1 <= mb[3] and (x0 + x1) / 2 == 595 / 2
    elif rot == 90:
        assert x0 >= 595 and x1 <= mb[2] and (y0 + y1) / 2 == 842 / 2
    else:
        assert x1 <= 0 and x0 >= mb[0] and (y0 + y1) / 2 == 842 / 2
    assert sorted((x1 - x0, y1 - y0)) == sorted((COMPACT_STAMP_WIDTH, COMPACT_STAMP_HEIGHT))


def test_ancho_se_recorta_en_paginas_angostas():
    box, _, width = compute_compact_stamp_box(make_page((0, 0, 300, 400)))
    assert width == 300 - 2 * STAMP_MARGIN_X
    assert box[0] == STAMP_MARGIN_X and box[2] == 300 - STAMP_MARGIN_X


# --- integración -----------------------------------------------------------
@pytest.fixture
def handle(pfx):
    return pipeline.load_signer_from_pfx(pfx, PFX_PASSWORD)


def _widget(path, page_index=0):
    r = PdfFileReader(path.open("rb"))
    page = r.root["/Pages"]["/Kids"][page_index].get_object()
    return r, page, page["/Annots"][0].get_object()


@pytest.mark.parametrize("rotate", [0, 90, 180, 270])
def test_firma_valida_con_sello_abajo_al_centro(tmp_path, handle, rotate):
    from pyhanko.sign.validation import validate_pdf_signature

    src, out = tmp_path / "in.pdf", tmp_path / "out.pdf"
    make_pdf(src, rotate=rotate)
    res = pipeline.sign_pdf(src, out, handle, visible=True, signer_details=HDR, stamp_position="bottom_center")
    assert res.stamp_applied
    r, page, widget = _widget(out)
    rect = [float(v) for v in widget["/Rect"]]
    mb = [float(v) for v in page["/MediaBox"]]
    assert mb == {
        0: [0, -COMPACT_RESERVE, 595, 842],
        180: [0, 0, 595, 842 + COMPACT_RESERVE],
        90: [0, 0, 595 + COMPACT_RESERVE, 842],
        270: [-COMPACT_RESERVE, 0, 595, 842],
    }[rotate]
    dims = (COMPACT_STAMP_WIDTH, COMPACT_STAMP_HEIGHT)
    assert (rect[2] - rect[0], rect[3] - rect[1]) == (dims if rotate in (0, 180) else dims[::-1])
    status = validate_pdf_signature(r.embedded_signatures[0])
    assert status.intact and status.valid
    # fuentes regular y negrita EMBEBIDAS (PDF/A-2b), nada de Helvetica sin embeber
    fonts = widget["/AP"]["/N"].get_object()["/Resources"]["/Font"]
    assert set(fonts.keys()) == {"/FR", "/FB"}
    for f in fonts.values():
        desc = f.get_object()["/DescendantFonts"][0].get_object()["/FontDescriptor"].get_object()
        assert "/FontFile3" in desc or "/FontFile2" in desc


def test_sello_en_la_ultima_pagina_de_un_documento_multipagina(tmp_path, handle):
    from pyhanko.pdf_utils.generic import StreamObject
    from pyhanko.pdf_utils.writer import PageObject, PdfFileWriter

    w = PdfFileWriter()
    for _ in range(3):
        w.insert_page(PageObject(contents=w.add_object(StreamObject(stream_data=b"q Q")), media_box=(0, 0, 595, 842)))
    src, out = tmp_path / "in.pdf", tmp_path / "out.pdf"
    with src.open("wb") as f:
        w.write(f)
    pipeline.sign_pdf(src, out, handle, visible=True, signer_details=HDR, stamp_position="bottom_center")
    r = PdfFileReader(out.open("rb"))
    kids = r.root["/Pages"]["/Kids"]
    assert "/Annots" in kids[2].get_object() and "/Annots" not in kids[0].get_object()
    assert float(kids[2].get_object()["/MediaBox"][1]) == -COMPACT_RESERVE
    assert [float(v) for v in kids[0].get_object()["/MediaBox"]] == [0, 0, 595, 842]


def test_sin_fuente_bold_cae_a_regular_con_warning(tmp_path, handle, monkeypatch, caplog):
    monkeypatch.setattr(pipeline, "find_stamp_bold_font", lambda: None)
    src, out = tmp_path / "in.pdf", tmp_path / "out.pdf"
    make_pdf(src)
    pipeline.sign_pdf(src, out, handle, visible=True, signer_details=HDR, stamp_position="bottom_center")
    r, page, widget = _widget(out)
    fonts = widget["/AP"]["/N"].get_object()["/Resources"]["/Font"]
    assert set(fonts.keys()) == {"/FR"}
    assert "sin negrita" in caplog.text
    assert pipeline.verify_signed_pdf(out).valid


def test_footer_sigue_siendo_el_default(tmp_path, handle):
    src, out = tmp_path / "in.pdf", tmp_path / "out.pdf"
    make_pdf(src)
    pipeline.sign_pdf(src, out, handle, visible=True, signer_details=HDR)
    page = _widget(out)[1]
    assert float(page["/MediaBox"][1]) == -pipeline.STAMP_RESERVE
    assert float(page["/MediaBox"][3]) == 842


def test_conformidad_y_sello_compacto_en_una_pagina(tmp_path, pfx):
    from test_conformity import CONF, stamps, verify_ok

    src = make_pdf(tmp_path / "in.pdf")
    outs = {}
    for pos in ("footer", "bottom_center"):
        outs[pos] = tmp_path / f"{pos}.pdf"
        res = pipeline.run_sign_pipeline(
            input_path=src, certificate_path=pfx, certificate_password=PFX_PASSWORD,
            output_path=outs[pos], work_dir=tmp_path / f"w-{pos}", visible=True, conformity=CONF,
            stamp_position=pos,
        )
        assert res["conformity_applied"] and res["intact"] and res["valid"]
    verify_ok(outs["bottom_center"])
    # nombre y fecha del trabajador: misma posición en espacio de usuario en ambos modos
    sf, st = stamps(outs["footer"])[0], stamps(outs["bottom_center"])[0]
    assert len(st) == 2 and [s[:4] for s in st] == [s[:4] for s in sf]


def test_http_sign_acepta_stamp_position(tmp_path, pfx):
    import app

    out = tmp_path / "out.pdf"
    body = dict(input_path=str(make_pdf(tmp_path / "in.pdf")), output_path=str(out),
                certificate_path=str(pfx), certificate_password=PFX_PASSWORD, visible=True)
    ok = json.loads(app.sign(app.SignRequest(**body, stamp_position="bottom_center")).body)
    assert ok["success"] is True
    assert float(_widget(out)[1]["/MediaBox"][1]) == -COMPACT_RESERVE
    assert app.SignRequest(**body).stamp_position == "footer"
    with pytest.raises(Exception):
        app.SignRequest(**body, stamp_position="middle")


def test_razon_social_usa_las_siglas_del_certificado():
    lines = pipeline.build_compact_stamp_lines({
        "organization": "OVERHEAD MEN SOCIEDAD ANONIMA CERRADA - OVERHEAD MEN S.A.C.",
        "ruc": "20603839961",
    })
    assert lines[1] == "Razón social: OVERHEAD MEN S.A.C. - RUC: 20603839961"
    assert pipeline.short_organization("EMPRESA SAC") == "EMPRESA SAC"
    assert pipeline.short_organization(None) is None


@pytest.mark.parametrize("rot", [0, 90, 180, 270])
def test_caja_a_la_izquierda_en_la_franja_inferior(rot):
    page = make_page((0, 0, 595, 842), rotate=rot, inherit=True)
    reserve_footer_space(page, COMPACT_RESERVE)
    (x0, y0, x1, y1), r, width = compute_compact_stamp_box(page, "left")
    assert r == rot and width == COMPACT_STAMP_WIDTH
    m = STAMP_MARGIN_X
    # izquierda VISUAL: con 180/270 el eje visual X corre al revés en espacio de usuario
    if rot == 0:
        assert y1 <= 0 and x0 == m
    elif rot == 180:
        assert y0 >= 842 and x1 == 595 - m
    elif rot == 90:
        assert x0 >= 595 and y0 == m
    else:
        assert x1 <= 0 and y1 == 842 - m
    assert sorted((x1 - x0, y1 - y0)) == sorted((COMPACT_STAMP_WIDTH, COMPACT_STAMP_HEIGHT))


def test_firma_valida_con_sello_abajo_a_la_izquierda(tmp_path, handle, pfx):
    import app
    from pyhanko.sign.validation import validate_pdf_signature

    src, out = tmp_path / "in.pdf", tmp_path / "out.pdf"
    make_pdf(src)
    res = pipeline.sign_pdf(src, out, handle, visible=True, signer_details=HDR, stamp_position="bottom_left")
    assert res.stamp_applied
    r, page, widget = _widget(out)
    rect = [float(v) for v in widget["/Rect"]]
    assert rect[0] == STAMP_MARGIN_X and rect[3] <= 0
    assert rect[2] - rect[0] == COMPACT_STAMP_WIDTH
    status = validate_pdf_signature(r.embedded_signatures[0])
    assert status.intact and status.valid
    body = dict(input_path=str(src), output_path=str(out), certificate_path=str(pfx),
                certificate_password=PFX_PASSWORD, visible=True)
    assert app.SignRequest(**body, stamp_position="bottom_left").stamp_position == "bottom_left"
