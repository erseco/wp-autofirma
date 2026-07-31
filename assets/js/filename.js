/**
 * Crea el nombre sugerido para un documento firmado.
 *
 * @param {string} filename Nombre original.
 * @returns {string} Nombre con sufijo.
 */
export function createSignedFilename(filename) {
  const lastDot = filename.lastIndexOf(".");

  if (lastDot <= 0) {
    return `${filename}-firmado`;
  }

  return `${filename.slice(0, lastDot)}-firmado${filename
    .slice(lastDot)
    .toLowerCase()}`;
}
