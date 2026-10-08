#!/usr/bin/env python3
"""
signer/signer_details.py - MiBoleta - Datos limpios del firmante.

Función PURA (sin I/O) que, a partir del subject X.509 del certificado
(`asn1crypto.x509.Name`, el que expone pyHanko en `signing_cert.subject`),
extrae los pocos campos que se muestran al usuario y en el sello visible del
pie del documento: representante, razón social, RUC, cargo, país y
provincia/localidad.

La lógica del RUC replica a propósito la del backend
(App\\Services\\Signature\\CertificateInspector::extractRuc) para que ambos
lados coincidan: prioriza el patrón "RUC<hasta 8 no-dígitos><11 dígitos>" (para
no confundirlo con el DNI) y recién después usa los fallbacks.
"""
from __future__ import annotations

import re
from datetime import datetime, timedelta, timezone
from typing import Any, Optional

# Perú no tiene horario de verano: UTC-5 fijo todo el año.
LIMA_TZ = timezone(timedelta(hours=-5), name="America/Lima")

# Nombre que asn1crypto da a cada atributo del subject. organizationIdentifier
# (2.5.4.97) sale como "organization_identifier" en asn1crypto >= 1.5 y como el
# OID punteado en versiones que no lo conocen; se normaliza a un solo nombre.
_ORG_IDENTIFIER_OID = "organization_identifier"
_ORG_IDENTIFIER_ALIASES = {"2.5.4.97": _ORG_IDENTIFIER_OID}
_RUC_PRIORITY = [
    "serial_number",
    _ORG_IDENTIFIER_OID,
    "common_name",
    "organizational_unit_name",
    "title",
]

_RE_RUC_LABELED = re.compile(r"RUC\D{0,8}?(?<!\d)(\d{11})(?!\d)", re.IGNORECASE)
_RE_RUC_ORG_ID = re.compile(r"^[A-Z]{3}PE-?(\d{11})$", re.IGNORECASE)
_RE_RUC_SERIAL = re.compile(r"^\D{0,6}((?:10|15|16|17|20)\d{9})$")
# Sufijo identificatorio que algunas ER agregan al CN: "NOMBRE RUC:2060..." /
# "NOMBRE DNI: 0992...". Se corta desde la primera etiqueta RUC/DNI.
_RE_CN_ID_SUFFIX = re.compile(r"\s+(?:RUC|DNI)\b.*$", re.IGNORECASE)


def _collect_attributes(subject: Any) -> dict[str, list[str]]:
    """Agrupa los atributos del subject por tipo, conservando el orden y los
    valores repetidos (p.ej. varios OU)."""
    attrs: dict[str, list[str]] = {}
    for rdn in subject.chosen:
        for type_and_value in rdn:
            key = type_and_value["type"].native
            key = _ORG_IDENTIFIER_ALIASES.get(key, key)
            try:
                value = type_and_value["value"].native
            except Exception:  # noqa: BLE001 - valor con tipo no decodificable
                continue
            if isinstance(value, bytes):
                value = value.decode("utf-8", errors="ignore")
            if not isinstance(value, str):
                continue
            value = value.strip()
            if value:
                attrs.setdefault(key, []).append(value)
    return attrs


def _first(attrs: dict[str, list[str]], key: str) -> Optional[str]:
    values = attrs.get(key)
    return values[0] if values else None


def _collapse(text: str) -> str:
    return re.sub(r"\s+", " ", text).strip()


def _clean_common_name(cn: Optional[str]) -> Optional[str]:
    if not cn:
        return None
    name = _collapse(_RE_CN_ID_SUFFIX.sub("", cn))
    return name or None


def _extract_ruc(attrs: dict[str, list[str]]) -> Optional[str]:
    candidates: list[str] = []
    for key in _RUC_PRIORITY:
        candidates.extend(attrs.get(key, []))
    for key, values in attrs.items():
        if key not in _RUC_PRIORITY:
            candidates.extend(values)

    for candidate in candidates:
        m = _RE_RUC_LABELED.search(candidate)
        if m:
            return m.group(1)

    org_id = _first(attrs, _ORG_IDENTIFIER_OID)
    if org_id:
        m = _RE_RUC_ORG_ID.match(org_id.strip())
        if m:
            return m.group(1)

    serial = _first(attrs, "serial_number")
    if serial:
        m = _RE_RUC_SERIAL.match(serial.strip())
        if m:
            return m.group(1)

    return None


def format_local_datetime(moment: datetime) -> str:
    """dd/mm/yyyy HH:MM en hora de Lima (UTC-5). `moment` debe ser aware."""
    return moment.astimezone(LIMA_TZ).strftime("%d/%m/%Y %H:%M")


def extract_signer_details(subject: Any, signed_at: Optional[datetime] = None) -> dict:
    """Devuelve los datos limpios del firmante a partir de un `Name` de asn1crypto.

    Claves: name, organization, ruc, title, country, locality, signed_at_local.
    Todo lo que no esté en el certificado sale como None (nunca "None" como
    texto). `signed_at_local` es None si no se pasa `signed_at`.
    """
    attrs = _collect_attributes(subject)
    return {
        "name": _clean_common_name(_first(attrs, "common_name")),
        "organization": _first(attrs, "organization_name"),
        "ruc": _extract_ruc(attrs),
        "title": _first(attrs, "title"),
        "country": _first(attrs, "country_name"),
        "locality": _first(attrs, "state_or_province_name")
        or _first(attrs, "locality_name"),
        "signed_at_local": format_local_datetime(signed_at) if signed_at else None,
    }
