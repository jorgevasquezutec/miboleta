import { describe, it, expect } from "vitest";
import { formatSignerDetails, formatCountry } from "../signerDetails";

const full = {
  name: "BASILIO VENTURA WILLIAM",
  organization: "OVERHEAD MEN S.A.C.",
  ruc: "20603839961",
  title: "GERENTE GENERAL",
  country: "PE",
  locality: "LIMA",
  signed_at_local: "06/10/2026 16:24",
};

describe("formatSignerDetails", () => {
  it("devuelve solo las filas pedidas, con Perú / LIMA", () => {
    const rows = formatSignerDetails(full);
    expect(rows.map((r) => r.label)).toEqual([
      "Representante", "Razón social", "RUC", "Cargo", "País/Provincia",
    ]);
    expect(rows[4].value).toBe("Perú / LIMA");
  });

  it("omite filas vacías", () => {
    const rows = formatSignerDetails({ ...full, title: null, organization: " ", country: null, locality: "LIMA" });
    expect(rows.map((r) => r.label)).toEqual(["Representante", "RUC", "País/Provincia"]);
    expect(rows[2].value).toBe("LIMA");
  });

  it("devuelve [] si todos los campos son null o vacíos, o no hay detalles", () => {
    const empty = { name: null, organization: null, ruc: null, title: null, country: null, locality: null, signed_at_local: null };
    expect(formatSignerDetails(empty)).toEqual([]);
    expect(formatSignerDetails({ ...empty, name: "  ", locality: "" })).toEqual([]);
    expect(formatSignerDetails(null)).toEqual([]);
    expect(formatSignerDetails(undefined)).toEqual([]);
  });

  it("deja códigos de país desconocidos tal cual", () => {
    expect(formatCountry("CL")).toBe("CL");
    expect(formatCountry(null)).toBeNull();
  });
});
