import { AutoFirmaClient } from "@erseco/autofirma-client";
import { createSignedFilename } from "./filename.js";

const settings = window.wpAutoFirmaSettings;
const button = document.querySelector("#wp-autofirma-sign");
const status = document.querySelector("#wp-autofirma-status");
const result = document.querySelector("#wp-autofirma-result");

/**
 * Ejecuta una petición REST autenticada.
 *
 * @param {string} path Ruta relativa.
 * @param {RequestInit} options Opciones de fetch.
 * @returns {Promise<object>} Respuesta JSON.
 */
async function request(path, options = {}) {
  const response = await fetch(`${settings.restUrl}${path}`, {
    ...options,
    headers: {
      "Content-Type": "application/json",
      "X-WP-Nonce": settings.nonce,
      ...options.headers,
    },
  });
  const payload = await response.json();

  if (!response.ok) {
    throw new Error(payload.message || settings.strings.unknownError);
  }

  return payload;
}

/**
 * Convierte el PDF firmado en un Blob descargable.
 *
 * El resultado ya está en el navegador, así que no hace falta ir a buscarlo a
 * la URL del adjunto. En WordPress Playground esa URL ni siquiera resuelve: el
 * sistema de ficheros es virtual y lo sirve un service worker, de modo que
 * abrirla en otra pestaña devuelve una página de error en vez del documento.
 *
 * @param {string} base64 Documento firmado en Base64.
 * @returns {Blob} Contenido binario del PDF.
 */
function toPdfBlob(base64) {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);

  for (let index = 0; index < binary.length; index += 1) {
    bytes[index] = binary.charCodeAt(index);
  }

  return new Blob([bytes], { type: "application/pdf" });
}

/**
 * Firma el PDF con AutoFirma.
 *
 * @param {string} data Documento Base64.
 * @returns {Promise<string>} PDF firmado en Base64.
 */
async function sign(data) {
  // AutoScript se encola siempre desde el propio plugin, así que el objeto
  // global existe; si faltara, el constructor lanza AutoScriptUnavailableError.
  const client = new AutoFirmaClient();
  client.initialize();
  const signed = await client.sign({
    data,
    format: "PAdES",
    parameters: {
      mode: "implicit",
    },
  });

  return signed.signature;
}

/**
 * Orquesta descarga, firma local y guardado.
 */
async function handleSign() {
  button.disabled = true;
  result.hidden = true;

  try {
    status.textContent = settings.strings.loading;
    const documentData = await request(`/documents/${settings.attachmentId}`);

    status.textContent = settings.strings.signing;
    const signature = await sign(documentData.data);

    status.textContent = settings.strings.saving;
    const saved = await request("/signatures", {
      method: "POST",
      body: JSON.stringify({
        originalAttachmentId: documentData.attachmentId,
        filename: createSignedFilename(documentData.filename),
        signature,
      }),
    });

    status.textContent = settings.strings.completed;
    result.hidden = false;
    result.replaceChildren();

    const filename = createSignedFilename(documentData.filename);
    const download = document.createElement("a");
    download.href = URL.createObjectURL(toPdfBlob(signature));
    download.download = filename;
    download.textContent = settings.strings.download;
    result.append(download);

    if (saved.editUrl) {
      const edit = document.createElement("a");
      edit.href = saved.editUrl;
      edit.textContent = settings.strings.edit;
      result.append(" · ", edit);
    }
  } catch (error) {
    status.textContent =
      error instanceof Error ? error.message : settings.strings.unknownError;
  } finally {
    button.disabled = false;
  }
}

button?.addEventListener("click", handleSign);
