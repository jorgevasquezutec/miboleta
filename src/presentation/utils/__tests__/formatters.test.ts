import { describe, it, expect, beforeAll, afterAll } from "vitest";
import { formatDate, formatDateTime } from "../formatters";

// El bug original solo se manifestaba en zonas horarias DETRÁS de UTC
// (p.ej. Perú, UTC-5): `new Date('YYYY-MM-DD')` interpreta esa fecha como
// medianoche UTC y, al convertirla a la zona local, retrocede un día.
//
// Si dejáramos que estos tests corran con la TZ que tenga la máquina/CI,
// pasarían "por accidente" en UTC o en cualquier zona ADELANTADA a UTC
// (donde el bug no se manifiesta) aunque el fix se rompiera. Por eso fijamos
// process.env.TZ a America/Lima explícitamente para TODO el archivo (hook a
// nivel de módulo, no dentro de un describe, para que también cubra
// formatDateTime más abajo): Node/V8 relee process.env.TZ de forma
// dinámica (confirmado: cambiarlo en caliente afecta a `Date`/`Intl` de
// inmediato, sin reiniciar el proceso), así que esto garantiza que el
// escenario que reportó el bug se ejercite siempre, sin importar dónde se
// ejecute la suite.
const originalTZ = process.env.TZ;

beforeAll(() => {
  process.env.TZ = "America/Lima";
});

afterAll(() => {
  process.env.TZ = originalTZ;
});

describe("formatDate", () => {
  it("formatea una fecha sin hora (YYYY-MM-DD) como ese mismo día local, sin retroceder", () => {
    const result = formatDate("2026-07-16");
    expect(result).toContain("16");
    expect(result).not.toContain("15");
  });

  it("no rompe año en el límite de fin de año (31 dic)", () => {
    const result = formatDate("2026-12-31");
    expect(result).toContain("31");
    expect(result).toContain("dic");
    expect(result).toContain("2026");
    expect(result).not.toContain("2027");
  });

  it("no rompe mes en el límite de fin de mes (31 jul)", () => {
    const result = formatDate("2026-07-31");
    expect(result).toContain("31");
    expect(result).toContain("jul");
  });

  it("sigue convirtiendo un timestamp completo con offset a la hora local (comportamiento existente, no debe cambiar)", () => {
    // 2026-07-16T23:30:00-05:00 ya está expresado en UTC-5 (Lima), así que
    // debe mostrarse tal cual, sin ningún ajuste adicional.
    const result = formatDate("2026-07-16T23:30:00-05:00", {
      includeTime: true,
    });
    expect(result).toContain("16/07/2026");
    expect(result).toContain("11:30");
  });

  it("sigue convirtiendo un timestamp UTC ('Z') a la hora local correcta", () => {
    // 2026-07-16T14:30:00Z en America/Lima (UTC-5) es 09:30 del mismo día.
    const result = formatDate("2026-07-16T14:30:00Z", { includeTime: true });
    expect(result).toContain("16/07/2026");
    expect(result).toContain("09:30");
  });

  it('retorna "-" para null', () => {
    expect(formatDate(null)).toBe("-");
  });

  it('retorna "-" para string vacío', () => {
    expect(formatDate("")).toBe("-");
  });

  it('retorna "-" para una fecha inválida en vez de reventar', () => {
    expect(() => formatDate("esto-no-es-una-fecha")).not.toThrow();
    expect(formatDate("esto-no-es-una-fecha")).toBe("-");
  });
});

describe("formatDateTime", () => {
  it("convierte un timestamp completo a fecha y hora local", () => {
    const result = formatDateTime("2026-07-16T14:30:00Z");
    expect(result).toContain("16/07/2026");
    expect(result).toContain("09:30");
  });

  it('retorna "-" para null', () => {
    expect(formatDateTime(null)).toBe("-");
  });
});
