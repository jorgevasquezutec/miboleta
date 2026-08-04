/*
 * Versión y fecha de la documentación — fuente única.
 * ---------------------------------------------------------------------------
 * Estos dos datos estaban repetidos en generate-pdf.js, generate-word.js y
 * generate-covers.js, además de en la cabecera de cada .md. Al actualizar la
 * documentación se cambiaban unos y otros no, y el resultado eran PDFs cuya
 * PORTADA decía "v1.0.0 — Enero 2026" mientras la tabla de la primera página
 * decía "v1.1.0 — Agosto 2026". El mismo documento contradiciéndose.
 *
 * Al publicar una versión nueva se cambia aquí y en la cabecera de los .md.
 */
module.exports = {
  version: '1.1.0',
  date: 'Agosto 2026',
};
