"""Tests de /extract-base: recuperar la revisión 0 (byte a byte) de un PDF firmado una vez."""
import json
import os
import stat

import pytest
from pyhanko.pdf_utils.reader import PdfFileReader

import pipeline
from conftest import LAYOUT_A4, PFX_PASSWORD, make_pdf

CONF = {"name": "Ana Pérez", "date_text": "06/10/2026 15:30", "layout": LAYOUT_A4}


@pytest.fixture(autouse=True)
def font_cache(tmp_path, monkeypatch):
    monkeypatch.setattr(pipeline, "CONFORMITY_FONT_CACHE_DIR", tmp_path / "font-cache")


def firmar(tmp_path, pfx, name="signed.pdf", **kw):
    src = make_pdf(tmp_path / f"in-{name}")
    out = tmp_path / name
    pipeline.run_sign_pipeline(
        input_path=src, certificate_path=pfx, certificate_password=PFX_PASSWORD,
        output_path=out, work_dir=tmp_path / f"w-{name}", visible=True, **kw)
    return out


def test_firmado_devuelve_base_identica(tmp_path, pfx):
    base = tmp_path / "base.pdf"
    signed = firmar(tmp_path, pfx, base_output_path=base)
    ext = tmp_path / "sub" / "ext.pdf"
    pipeline.extract_base(signed, ext)
    r = PdfFileReader(ext.open("rb"))
    assert r.xrefs.total_revisions == 1 and len(r.embedded_signatures) == 0
    assert signed.read_bytes().startswith(ext.read_bytes())
    assert ext.read_bytes() == base.read_bytes()  # byte a byte la revisión 0 original
    assert stat.S_IMODE(ext.stat().st_mode) == 0o644


def test_sin_firmar_error_input(tmp_path):
    src = make_pdf(tmp_path / "in.pdf")
    with pytest.raises(pipeline.InputValidationError) as e:
        pipeline.extract_base(src, tmp_path / "x.pdf")
    assert e.value.stage == "input"
    assert not (tmp_path / "x.pdf").exists()


def test_dos_firmas_error(tmp_path, pfx):
    signed = firmar(tmp_path, pfx)
    twice = tmp_path / "twice.pdf"
    handle = pipeline.load_signer_from_pfx(pfx, PFX_PASSWORD)
    pipeline.sign_pdf(signed, twice, handle, field_name="Firma2")
    assert len(PdfFileReader(twice.open("rb")).embedded_signatures) == 2
    with pytest.raises(pipeline.PipelineError):
        pipeline.extract_base(twice, tmp_path / "x.pdf")
    assert not (tmp_path / "x.pdf").exists()


def test_extraer_refirmar_con_conformidad_y_verificar(tmp_path, pfx):
    signed = firmar(tmp_path, pfx)
    base = tmp_path / "base.pdf"
    pipeline.extract_base(signed, base)
    out = tmp_path / "resigned.pdf"
    res = pipeline.run_sign_pipeline(
        input_path=base, certificate_path=pfx, certificate_password=PFX_PASSWORD,
        output_path=out, work_dir=tmp_path / "w2", visible=True, skip_normalize=True, conformity=CONF)
    assert res["intact"] and res["valid"] and res["covers_whole_file"]
    assert res["conformity_applied"] is True
    r = PdfFileReader(out.open("rb"))
    assert len(r.embedded_signatures) == 1 and r.xrefs.total_revisions == 2
    assert out.read_bytes().startswith(base.read_bytes())


def test_http_extract_base(tmp_path, pfx):
    import app

    signed = firmar(tmp_path, pfx)
    ok = json.loads(app.extract_base(app.ExtractBaseRequest(
        input_path=str(signed), output_path=str(tmp_path / "e.pdf"))).body)
    assert ok == {"success": True, "output_path": str(tmp_path / "e.pdf")}
    bad = json.loads(app.extract_base(app.ExtractBaseRequest(
        input_path=str(make_pdf(tmp_path / "u.pdf")), output_path=str(tmp_path / "f.pdf"))).body)
    assert bad["success"] is False and bad["stage"] == "input"
