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
 * Firma el PDF real o simula el resultado dentro de Playground.
 *
 * @param {string} data Documento Base64.
 * @returns {Promise<string>} PDF firmado en Base64.
 */
async function sign(data) {
  if (settings.demoMode) {
    return data;
  }

  if (!settings.hasAutoScript) {
    throw new Error(settings.strings.missing);
  }

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

    const link = document.createElement("a");
    link.href = saved.editUrl || saved.url;
    link.textContent = saved.editUrl
      ? "Editar el documento firmado"
      : "Ver el documento firmado";
    result.append(link);
  } catch (error) {
    status.textContent =
      error instanceof Error ? error.message : settings.strings.unknownError;
  } finally {
    button.disabled = false;
  }
}

button?.addEventListener("click", handleSign);
