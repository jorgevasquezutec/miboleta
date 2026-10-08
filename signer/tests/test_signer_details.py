"""Tests de signer_details.extract_signer_details (función pura)."""
from datetime import datetime, timezone

from asn1crypto import x509 as asn1_x509
from cryptography import x509
from cryptography.x509.oid import NameOID, ObjectIdentifier

from signer_details import extract_signer_details

ORG_ID = ObjectIdentifier("2.5.4.97")


def make_subject(attrs):
    """attrs: lista ordenada de (oid, valor); admite OID repetidos (OU)."""
    name = x509.Name([x509.NameAttribute(oid, v) for oid, v in attrs])
    return asn1_x509.Name.load(name.public_bytes())


REAL = [
    (NameOID.STREET_ADDRESS, "AV. JOAQUIN LA MADRID NRO. 545 INT. 302"),
    (NameOID.EMAIL_ADDRESS, "over@example.com"),
    (NameOID.COMMON_NAME, "BASILIO VENTURA WILLIAM RUC:20603839961"),
    (NameOID.SERIAL_NUMBER, "RUC:20603839961 DNI:09926071"),
    (NameOID.TITLE, "GERENTE GENERAL"),
    (NameOID.ORGANIZATIONAL_UNIT_NAME, "DOCUMENTOS ELECTRONICOS"),
    (NameOID.ORGANIZATIONAL_UNIT_NAME, "Validado por Llama.pe ER"),
    (
        NameOID.ORGANIZATION_NAME,
        "OVERHEAD MEN SOCIEDAD ANONIMA CERRADA - OVERHEAD MEN S.A.C.",
    ),
    (NameOID.LOCALITY_NAME, "LIMA"),
    (NameOID.COUNTRY_NAME, "PE"),
]


def test_subject_real_llamape():
    d = extract_signer_details(make_subject(REAL))
    assert d == {
        "name": "BASILIO VENTURA WILLIAM",
        "organization": "OVERHEAD MEN SOCIEDAD ANONIMA CERRADA - OVERHEAD MEN S.A.C.",
        "ruc": "20603839961",
        "title": "GERENTE GENERAL",
        "country": "PE",
        "locality": "LIMA",
        "signed_at_local": None,
    }


def test_signed_at_local_se_convierte_a_hora_de_lima():
    # 21:24 UTC -> 16:24 en Lima (UTC-5)
    t = datetime(2026, 10, 6, 21, 24, 5, tzinfo=timezone.utc)
    d = extract_signer_details(make_subject(REAL), signed_at=t)
    assert d["signed_at_local"] == "06/10/2026 16:24"


def test_signed_at_local_cruza_de_dia():
    t = datetime(2026, 1, 1, 3, 30, tzinfo=timezone.utc)  # 22:30 del 31/12 en Lima
    d = extract_signer_details(make_subject(REAL), signed_at=t)
    assert d["signed_at_local"] == "31/12/2025 22:30"


def test_sin_title_ni_organizacion_devuelve_none():
    attrs = [
        (NameOID.COMMON_NAME, "JUAN PEREZ RUC:20111111111"),
        (NameOID.COUNTRY_NAME, "PE"),
    ]
    d = extract_signer_details(make_subject(attrs))
    assert d["title"] is None
    assert d["organization"] is None
    assert d["locality"] is None
    assert d["name"] == "JUAN PEREZ"
    assert d["ruc"] == "20111111111"


def test_ruc_en_organization_identifier():
    attrs = [
        (NameOID.COMMON_NAME, "MARIA LOPEZ"),
        (ORG_ID, "VATPE-20555555555"),
        (NameOID.ORGANIZATION_NAME, "ACME SAC"),
    ]
    d = extract_signer_details(make_subject(attrs))
    assert d["ruc"] == "20555555555"
    assert d["name"] == "MARIA LOPEZ"


def test_cn_con_ruc_con_espacio_y_dni():
    attrs = [(NameOID.COMMON_NAME, "ANA   DE LA CRUZ  RUC: 20603839961 DNI: 09926071")]
    d = extract_signer_details(make_subject(attrs))
    assert d["name"] == "ANA DE LA CRUZ"
    assert d["ruc"] == "20603839961"


def test_cn_solo_con_dni():
    attrs = [
        (NameOID.COMMON_NAME, "LUIS GARCIA DNI:09926071"),
        (NameOID.SERIAL_NUMBER, "DNI:09926071"),
    ]
    d = extract_signer_details(make_subject(attrs))
    assert d["name"] == "LUIS GARCIA"
    assert d["ruc"] is None  # un DNI de 8 dígitos no es RUC


def test_ruc_no_se_confunde_con_dni_en_serial():
    attrs = [
        (NameOID.COMMON_NAME, "PEDRO RUIZ"),
        (NameOID.SERIAL_NUMBER, "DNI:09926071 RUC:20603839961"),
    ]
    assert extract_signer_details(make_subject(attrs))["ruc"] == "20603839961"


def test_ruc_fallback_serial_number_sin_etiqueta():
    attrs = [(NameOID.COMMON_NAME, "SOFIA RAMOS"), (NameOID.SERIAL_NUMBER, "20603839961")]
    assert extract_signer_details(make_subject(attrs))["ruc"] == "20603839961"


def test_provincia_tiene_prioridad_sobre_localidad():
    attrs = [
        (NameOID.COMMON_NAME, "X Y"),
        (NameOID.LOCALITY_NAME, "MIRAFLORES"),
        (NameOID.STATE_OR_PROVINCE_NAME, "LIMA"),
    ]
    assert extract_signer_details(make_subject(attrs))["locality"] == "LIMA"


def test_sin_cn_name_none():
    attrs = [(NameOID.ORGANIZATION_NAME, "ACME SAC")]
    d = extract_signer_details(make_subject(attrs))
    assert d["name"] is None
    assert d["organization"] == "ACME SAC"
