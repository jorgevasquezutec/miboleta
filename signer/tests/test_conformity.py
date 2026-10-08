"""Tests de la conformidad del trabajador dibujada por el sidecar (nombre + fecha)."""
import json
import os
import re
import stat

import pytest
from pyhanko.pdf_utils.reader import PdfFileReader

import pipeline
from conftest import LAYOUT_A4, PFX_PASSWORD, make_pdf

MM = pipeline.MM
CONF = {"name": "Jorge Luis Vásquez Ñuñez", "date_text": "06/10/2026 15:30", "layout": LAYOUT_A4}

# Layouts de config/signature.php (sizes.*), en el contrato JSON del sidecar.
def _layout(x, y, w):
    return {**LAYOUT_A4, "x_mm": x, "name_y_mm": y, "width_mm": w}


SIZES = {
    "a4": ((595.0, 842.0), _layout(137.0, 238.8, 56.0)),
    "a5": ((420.0, 595.0), _layout(96.55, 168.85, 39.47)),
    "letter": ((612.0, 792.0), _layout(140.87, 224.69, 57.57)),
    "a10": ((595.32, 419.52), _layout(137.0, 119.0, 56.0)),
}


@pytest.fixture
def handle(pfx):
    return pipeline.load_signer_from_pfx(pfx, PFX_PASSWORD)


@pytest.fixture(autouse=True)
def font_cache(tmp_path, monkeypatch):
    monkeypatch.setattr(pipeline, "CONFORMITY_FONT_CACHE_DIR", tmp_path / "font-cache")


def last_page(pdf_path):
    r = PdfFileReader(pdf_path.open("rb"))
    return r, r.root["/Pages"]["/Kids"][0].get_object()


def stamps(pdf_path):
    """[(e, f, bbox_w, bbox_h, matrix, stream_data)] de los XObjects 'Stamp' dibujados, en orden."""
    _, page = last_page(pdf_path)
    contents = page["/Contents"].get_object()
    streams = list(contents) if isinstance(contents, list) else [contents]
    text = b"\n".join(s.get_object().data for s in streams).decode("latin-1")
    xobjs = page["/Resources"].get("/XObject", {})
    out = []
    for m in re.finditer(r"1 0 0 1 ([-\d.]+) ([-\d.]+) cm /(Stamp\w+) Do", text):
        x = xobjs["/" + m.group(3)].get_object()
        bb = [float(v) for v in x["/BBox"]]
        mat = [float(v) for v in x["/Matrix"]] if "/Matrix" in x else None
        out.append((float(m.group(1)), float(m.group(2)), abs(bb[2] - bb[0]), abs(bb[3] - bb[1]), mat, x.data))
    return out, text


def sign(src, out, handle, conformity=CONF, **kw):
    return pipeline.sign_pdf(src, out, handle, conformity=conformity, **kw)


def verify_ok(path):
    v = pipeline.verify_signed_pdf(path)
    assert v.intact and v.valid and v.covers_whole_file
    return v


# ---------------------------------------------------------------------------
def test_con_conformidad_dos_revisiones_una_firma(tmp_path, pfx):
    src = make_pdf(tmp_path / "in.pdf")
    out = tmp_path / "out.pdf"
    res = pipeline.run_sign_pipeline(
        input_path=src, certificate_path=pfx, certificate_password=PFX_PASSWORD,
        output_path=out, work_dir=tmp_path / "w", visible=True, conformity=CONF,
    )
    assert res["conformity_applied"] is True and res["base_written"] is False
    assert res["intact"] and res["valid"] and res["covers_whole_file"]
    r = PdfFileReader(out.open("rb"))
    assert len(r.embedded_signatures) == 1
    # revisión 0 (Ghostscript) + 1 revisión incremental con nombre, fecha, pie y firma
    assert r.xrefs.total_revisions == 2
    assert len(stamps(out)[0]) == 2


def test_sin_conformidad_no_dibuja_nada(tmp_path, pfx):
    src = make_pdf(tmp_path / "in.pdf")
    out = tmp_path / "out.pdf"
    res = pipeline.run_sign_pipeline(
        input_path=src, certificate_path=pfx, certificate_password=PFX_PASSWORD,
        output_path=out, work_dir=tmp_path / "w", visible=True,
    )
    assert res["conformity_applied"] is False and res["base_written"] is False
    assert stamps(out)[0] == []
    verify_ok(out)


@pytest.mark.parametrize("size", list(SIZES))
def test_posicion_del_nombre_por_tamano(tmp_path, handle, size):
    (w, h), layout = SIZES[size]
    src = make_pdf(tmp_path / "in.pdf", media_box=(0, 0, w, h))
    out = tmp_path / "out.pdf"
    sign(src, out, handle, {**CONF, "layout": layout}, visible=True)
    (name, date), _ = stamps(out)[0], None
    # origen del form = esquina inferior-izquierda de la celda, medida desde ARRIBA de la página
    assert name[0] == pytest.approx(layout["x_mm"] * MM, abs=0.01)
    assert name[1] == pytest.approx(h - (layout["name_y_mm"] + layout["name_height_mm"]) * MM, abs=0.01)
    assert date[1] == pytest.approx(h - (layout["name_y_mm"] + layout["date_offset_y_mm"] + 4) * MM, abs=0.01)
    # el borde superior NO cambia al reservar el pie: solo baja y0
    _, page = last_page(out)
    mb = [float(v) for v in page["/MediaBox"]]
    assert mb[3] == pytest.approx(h) and mb[1] == pytest.approx(-pipeline.STAMP_RESERVE)
    verify_ok(out)


def test_cropbox_desplazada_usa_su_origen(tmp_path, handle):
    src = make_pdf(tmp_path / "in.pdf", media_box=(0, 0, 700, 900), crop_box=(10, 20, 610, 820))
    out = tmp_path / "out.pdf"
    sign(src, out, handle, visible=True)
    name = stamps(out)[0][0]
    # misma regla que FPDI (CropBox): x0 + x_mm, y1 - (name_y + alto)
    assert name[0] == pytest.approx(10 + 137.0 * MM, abs=0.01)
    assert name[1] == pytest.approx(820 - (238.8 + 7) * MM, abs=0.01)


def test_contenido_con_cm_sin_balancear(tmp_path, handle):
    src = make_pdf(tmp_path / "in.pdf", content=b"0.5 0 0 0.5 100 100 cm 0 0 10 10 re f")
    out = tmp_path / "out.pdf"
    sign(src, out, handle, visible=True)
    stamp_list, text = stamps(out)
    assert len(stamp_list) == 2
    # Mini intérprete de la CTM: al llegar a cada sello, la cm del contenido
    # original (0.5 0 0 0.5 100 100) ya fue cerrada por su Q.
    ctm, stack, at_stamp = [1.0, 0, 0, 1.0, 0, 0], [], []
    for m in re.finditer(r"(q|Q)\b|((?:[-\d.]+ ){6})cm|/Stamp\w+ Do", text):
        if m.group(1) == "q":
            stack.append(list(ctm))
        elif m.group(1) == "Q":
            ctm = stack.pop()
        elif m.group(2):
            a, b, c, d, e, f = (float(v) for v in m.group(2).split())
            ctm = [a * ctm[0], 0, 0, d * ctm[3], e * ctm[0] + ctm[4], f * ctm[3] + ctm[5]]
        else:
            at_stamp.append(ctm)
    assert len(at_stamp) == 2
    for c in at_stamp:  # solo la traslación propia del sello (escala 1, sin la cm de 100,100 * 0.5)
        assert c[0] == 1.0 and c[3] == 1.0
        assert c[4] == pytest.approx(137.0 * MM, abs=0.01)
    name = stamp_list[0]
    assert name[0] == pytest.approx(137.0 * MM, abs=0.01)


def test_nombre_largo_se_reduce_y_cabe(tmp_path, handle):
    long_name = "María del Carmen Guadalupe Fernández Ñuñez"
    src = make_pdf(tmp_path / "in.pdf")
    out = tmp_path / "out.pdf"
    sign(src, out, handle, {**CONF, "name": long_name}, visible=True)
    data = stamps(out)[0][0][5].decode("latin-1")
    size = float(re.search(r"([\d.]+) Tf", data).group(1))
    assert 5 <= size < 13
    meter = pipeline._TextMeter(pipeline.load_conformity_font())
    assert meter.width(long_name, size) <= 56.0 * MM + 0.5


def test_nombre_corto_conserva_el_tamano(tmp_path, handle):
    src = make_pdf(tmp_path / "in.pdf")
    out = tmp_path / "out.pdf"
    sign(src, out, handle, {**CONF, "name": "Ana"}, visible=True)
    data = stamps(out)[0][0][5].decode("latin-1")
    assert float(re.search(r"([\d.]+) Tf", data).group(1)) == pytest.approx(13.0)


@pytest.mark.parametrize("rot,matrix,expected", [
    (90, [0, 1, -1, 0, 0, 0], lambda w, h, x, ny, nh: (ny + nh, x)),
    (180, [-1, 0, 0, -1, 0, 0], lambda w, h, x, ny, nh: (w - x, ny + nh)),
    (270, [0, -1, 1, 0, 0, 0], lambda w, h, x, ny, nh: (w - (ny + nh), h - x)),
])
def test_pagina_rotada_el_nombre_se_ve_horizontal(tmp_path, handle, rot, matrix, expected):
    """En /Rotate 90/180/270 el sello lleva la matriz que lo deja horizontal y bien ubicado."""
    w, h = 595.0, 842.0
    src = make_pdf(tmp_path / "in.pdf", media_box=(0, 0, w, h), rotate=rot)
    out = tmp_path / "out.pdf"
    sign(src, out, handle, visible=True)
    name = stamps(out)[0][0]
    assert name[4] == matrix
    x_mm, ny_mm, nh_mm = 137.0 * MM, 238.8 * MM, 7 * MM
    ex, ey = expected(w, h, x_mm, ny_mm, nh_mm)
    assert (name[0], name[1]) == (pytest.approx(ex, abs=0.01), pytest.approx(ey, abs=0.01))
    verify_ok(out)


def test_fuente_reescalada_a_1000_upem(tmp_path, handle):
    from fontTools.ttLib import TTFont

    font = pipeline.load_conformity_font()
    tt = TTFont(str(font))
    assert tt["head"].unitsPerEm == 1000
    src = TTFont(os.environ.get(pipeline.CONFORMITY_FONT_ENV, pipeline.CONFORMITY_FONT_DEFAULT))
    assert src["head"].unitsPerEm == 2048  # la original es la que sale mal en pyHanko
    gid = src.getBestCmap()[ord("a")]
    assert tt["hmtx"][gid][0] == pytest.approx(src["hmtx"][gid][0] * 1000 / 2048, abs=1.5)

    # /W del PDF en escala 1000 (con 2048 upem los anchos saldrían ~2x)
    out = tmp_path / "out.pdf"
    sign(make_pdf(tmp_path / "in.pdf"), out, handle, {**CONF, "name": "aaaaaaaa"}, visible=True)
    assert pipeline.load_conformity_font() == font  # segunda carga sale de la caché
    _, page = last_page(out)
    flat = []

    def walk(o):
        o = o.get_object() if hasattr(o, "get_object") else o
        if hasattr(o, "__iter__") and not isinstance(o, (str, bytes)):
            for i in o:
                walk(i)
        else:
            flat.append(float(o))

    name_form = None
    for k, v in page["/Resources"]["/XObject"].items():
        fonts = v.get_object()["/Resources"].get("/Font")
        if fonts and name_form is None:
            name_form = list(fonts.values())[0].get_object()
    walk(name_form["/DescendantFonts"][0].get_object()["/W"])
    widths = flat[1:]
    assert any(w > 300 for w in widths) and max(widths) < 1200


def test_sin_fuente_error_de_conformidad_y_sin_salida(tmp_path, handle, pfx, monkeypatch):
    monkeypatch.setenv(pipeline.CONFORMITY_FONT_ENV, str(tmp_path / "no-existe.ttf"))
    src = make_pdf(tmp_path / "in.pdf")
    out = tmp_path / "out.pdf"
    with pytest.raises(pipeline.ConformityError) as e:
        sign(src, out, handle, visible=True)
    assert e.value.stage == "conformity"
    assert not out.exists()
    with pytest.raises(pipeline.ConformityError):
        pipeline.run_sign_pipeline(
            input_path=src, certificate_path=pfx, certificate_password=PFX_PASSWORD,
            output_path=out, work_dir=tmp_path / "w", conformity=CONF,
        )
    assert not out.exists()


def test_base_output_path_y_skip_normalize(tmp_path, pfx):
    src = make_pdf(tmp_path / "in.pdf")
    base = tmp_path / ".originals" / "doc.pdfa.pdf"
    out1, out2 = tmp_path / "s1.pdf", tmp_path / "s2.pdf"
    common = dict(certificate_path=pfx, certificate_password=PFX_PASSWORD, visible=True, conformity=CONF)
    r1 = pipeline.run_sign_pipeline(
        input_path=src, output_path=out1, work_dir=tmp_path / "w1", base_output_path=base, **common)
    assert r1["base_written"] is True
    rb = PdfFileReader(base.open("rb"))
    assert rb.xrefs.total_revisions == 1 and len(rb.embedded_signatures) == 0
    assert out1.read_bytes().startswith(base.read_bytes())  # la base es prefijo del firmado

    r2 = pipeline.run_sign_pipeline(
        input_path=base, output_path=out2, work_dir=tmp_path / "w2", skip_normalize=True, **common)
    assert r2["base_written"] is False and r2["conformity_applied"] is True
    assert out2.read_bytes().startswith(base.read_bytes())
    verify_ok(out2)


def test_skip_normalize_rechaza_pdf_firmado(tmp_path, pfx):
    src = make_pdf(tmp_path / "in.pdf")
    signed = tmp_path / "signed.pdf"
    pipeline.run_sign_pipeline(
        input_path=src, certificate_path=pfx, certificate_password=PFX_PASSWORD,
        output_path=signed, work_dir=tmp_path / "w")
    with pytest.raises(pipeline.InputValidationError):
        pipeline.run_sign_pipeline(
            input_path=signed, certificate_path=pfx, certificate_password=PFX_PASSWORD,
            output_path=tmp_path / "x.pdf", work_dir=tmp_path / "w2", skip_normalize=True)


def test_permisos_0644_con_umask_restrictivo(tmp_path, pfx):
    old = os.umask(0o077)
    try:
        src = make_pdf(tmp_path / "in.pdf")
        base, out = tmp_path / "b" / "base.pdf", tmp_path / "out.pdf"
        pipeline.run_sign_pipeline(
            input_path=src, certificate_path=pfx, certificate_password=PFX_PASSWORD,
            output_path=out, work_dir=tmp_path / "w", base_output_path=base, conformity=CONF)
    finally:
        os.umask(old)
    for p in (out, base):
        assert stat.S_IMODE(p.stat().st_mode) == 0o644


def test_http_envelope_conformity(tmp_path, pfx):
    """POST /sign: conformity_applied/base_written y error con stage='conformity'."""
    import app

    src = make_pdf(tmp_path / "in.pdf")
    body = dict(
        input_path=str(src), output_path=str(tmp_path / "o.pdf"), certificate_path=str(pfx),
        certificate_password=PFX_PASSWORD, visible=True,
        base_output_path=str(tmp_path / ".originals" / "o.pdfa.pdf"),
        conformity={"name": "Ana Pérez", "date_text": "06/10/2026 15:30", "layout": LAYOUT_A4},
    )
    res = json.loads(app.sign(app.SignRequest(**body)).body)
    assert res["success"] is True
    assert res["signature"]["conformity_applied"] is True and res["signature"]["base_written"] is True

    body.pop("conformity"); body.pop("base_output_path")
    res = json.loads(app.sign(app.SignRequest(**body)).body)
    assert res["signature"]["conformity_applied"] is False and res["signature"]["base_written"] is False


@pytest.mark.parametrize("layout", [
    {"mode": "absolute", "width_mm": 56},
    {"mode": "absolute", "width_mm": 56, "x_mm": None, "name_y_mm": None},
])
def test_layout_absolute_sin_x_ni_name_y_usa_fallback(layout):
    # Fallback de PHP: x = ancho - width - 10, y = alto - 33 (mm)
    vw, vh = 595.0, 842.0
    cells = pipeline.conformity_cells({"name": "Ana", "layout": layout}, vw, vh)
    mm = pipeline.MM
    assert cells["x"] == pytest.approx((vw / mm - 56 - 10) * mm)
    assert cells["name_top"] == pytest.approx((vh / mm - 33) * mm)


# ---------------------------------------------------------------------------
# Detección del formato por el tamaño real de la página (conformity.layouts)
# ---------------------------------------------------------------------------
DIMS = {"a10": [210.0, 148.0], "a4": [210.0, 297.0], "a5": [148.0, 210.0], "letter": [215.9, 279.4]}
LAYOUTS = {k: SIZES[k][1] for k in SIZES}
DEFAULT_L = _layout(1.0, 2.0, 30.0)  # layout por defecto reconocible


def _detect_conf():
    return {**CONF, "layout": DEFAULT_L, "layouts": LAYOUTS, "page_dimensions_mm": DIMS}


@pytest.mark.parametrize("size", ["a4", "a5", "letter", "a10"])
def test_detecta_layout_por_tamano_de_pagina(size):
    (w, h), layout = SIZES[size]
    assert pipeline.detect_layout(_detect_conf(), w, h) == layout


def test_tamano_raro_usa_layout_por_defecto():
    assert pipeline.detect_layout(_detect_conf(), 300.0, 300.0) == DEFAULT_L


def test_solo_layout_igual_que_antes():
    conf = {**CONF, "layout": DEFAULT_L}
    assert pipeline.detect_layout(conf, 595.0, 842.0) == DEFAULT_L


def test_a4_sin_eleccion_dibuja_con_layout_a4(tmp_path, handle):
    src = make_pdf(tmp_path / "in.pdf", media_box=(0, 0, 595, 842))
    out = tmp_path / "out.pdf"
    sign(src, out, handle, _detect_conf(), visible=True)
    name = stamps(out)[0][0]
    assert name[0] == pytest.approx(137.0 * MM, abs=0.01)
    assert name[1] == pytest.approx(842 - (238.8 + 7) * MM, abs=0.01)


# ---------------------------------------------------------------------------
# Posición vertical igual que TCPDF (Cell(..., 'T', 'M')) en PdfWatermarkService
# ---------------------------------------------------------------------------
def _baseline_local(stamp_tuple):
    """(tamaño Tf, x, y de la línea base) en el espacio local del XObject (origen = celda)."""
    data = stamp_tuple[5].decode("latin-1")
    size = float(re.search(r"/\w+ ([\d.]+) Tf", data).group(1))
    x, y = (float(v) for v in re.search(r"([-\d.]+) ([-\d.]+) Td", data).groups())
    return size, x, y


def test_tcpdf_baseline_valores_esperados():
    # Cell(56, 7, ..., 'T','M') con Segoe Script 16 pt: (7mm - 1.584*16pt)/2 + 1.089*16pt
    nm = pipeline.tcpdf_baseline_from_top(7 * MM, 16, 1.089, 0.495)
    assert nm / MM == pytest.approx(5.18, abs=0.01)
    # Cell(56, 4, ..., 'T','M') con helvetica 8 pt
    dt = pipeline.tcpdf_baseline_from_top(4 * MM, 8, pipeline.HELVETICA_ASCENT, pipeline.HELVETICA_DESCENT)
    assert dt / MM == pytest.approx(3.00, abs=0.01)


def test_metricas_de_la_fuente_son_las_que_usa_tcpdf():
    asc, desc = pipeline.font_vmetrics(pipeline.load_conformity_font())
    assert (asc, desc) == (pytest.approx(1.089), pytest.approx(0.495))  # segoesc.php de TCPDF


@pytest.mark.parametrize("size", ["a4", "a10"])
@pytest.mark.parametrize("name", ["Ana Paz", "Jorge Luis Vásquez", "María Fernanda de los Ángeles Quispe Huamán de la Cruz"])
def test_linea_base_como_tcpdf_y_fecha_no_tapada(tmp_path, handle, size, name):
    (w, h), layout = SIZES[size]
    layout = {**layout, "name_font_size": 16, "name_height_mm": 7, "date_offset_y_mm": 5, "date_font_size": 8}
    src = make_pdf(tmp_path / "in.pdf", media_box=(0, 0, w, h))
    out = tmp_path / "out.pdf"
    sign(src, out, handle, {"name": name, "date_text": "2026-10-06T19:45:02-05:00", "layout": layout}, visible=True)
    (nm, dt), _ = stamps(out)[0], None
    n_size, n_x, n_y = _baseline_local(nm)
    d_size, d_x, d_y = _baseline_local(dt)

    # Línea base ABSOLUTA (en la página) vs la que dibuja TCPDF; error <= 0.5 mm
    asc, desc = pipeline.font_vmetrics(pipeline.load_conformity_font())
    y0, off = layout["name_y_mm"], layout["date_offset_y_mm"]
    exp_name = h - (y0 * MM + pipeline.tcpdf_baseline_from_top(7 * MM, n_size, asc, desc))
    exp_date = h - ((y0 + off) * MM + pipeline.tcpdf_baseline_from_top(4 * MM, d_size, 0.931, 0.225))
    assert (nm[1] + n_y) == pytest.approx(exp_name, abs=0.5 * MM)
    assert (dt[1] + d_y) == pytest.approx(exp_date, abs=0.5 * MM)
    assert d_size == pytest.approx(8.0)
    assert n_size <= 16.0

    # El BBox contiene la celda (sin recortar glifos altos) y el origen sigue siendo la celda
    for st, cell_h in ((nm, 7 * MM), (dt, 4 * MM)):
        assert st[3] > cell_h and st[2] > 56.0 * MM

    # La fecha NO queda tapada: la línea base del nombre está por encima del tope de
    # las mayúsculas de la fecha (alto de mayúscula de Nimbus/Helvetica ~0.72 em).
    name_baseline_y = nm[1] + n_y
    date_cap_top_y = dt[1] + d_y + 0.72 * d_size
    assert name_baseline_y > date_cap_top_y
    verify_ok(out)


def test_nombre_largo_no_desborda_mas_que_fpdi(tmp_path, handle):
    """Ancho dibujado == ancho TCPDF (sin kern/ligaduras): size = 16*W/strW*0.96."""
    name = "María Fernanda de los Ángeles Quispe Huamán de la Cruz"
    src = make_pdf(tmp_path / "in.pdf", media_box=(0, 0, 595.32, 419.52))
    out = tmp_path / "out.pdf"
    layout = {**SIZES["a10"][1], "name_font_size": 16}
    sign(src, out, handle, {"name": name, "date_text": "x", "layout": layout}, visible=True)
    n_size = _baseline_local(stamps(out)[0][0])[0]
    meter = pipeline._TextMeter(pipeline.load_conformity_font())
    expected = max(5.0, 16 * (56.0 * MM) / meter.width(name, 16) * 0.96)
    assert n_size == pytest.approx(expected, rel=1e-3)
    # con el mínimo de 5 pt puede sobrar ancho, igual que en FPDI (misma fórmula y mismo mínimo)
