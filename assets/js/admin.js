import { AutoFirmaClient } from "@erseco/autofirma-client";
import { createSignedFilename } from "./filename.js";

const settings = window.wpAutoFirmaSettings;
const button = document.querySelector("#wp-autofirma-sign");
const status =
  document.querySelector("#wp-autofirma-message") ||
  document.querySelector("#wp-autofirma-status");
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
 * Abre una sesión del servidor intermedio.
 *
 * AutoScript solo usa estos servicios cuando no puede hablar por WebSocket con
 * AutoFirma, que es siempre el caso en móvil. En escritorio los ignora, así que
 * configurarlos no cambia nada de lo que ya funciona.
 *
 * Si la sesión no puede abrirse, se firma igual: en escritorio saldrá bien y en
 * móvil AutoScript dará su propio aviso, que es más claro que interrumpir aquí.
 *
 * @returns {Promise<object>} Direcciones de los dos servicios, o vacío.
 */
async function openIntermediateSession() {
  if (!settings.intermediate) {
    return {};
  }

  try {
    const session = await request("/intermediate-sessions", { method: "POST" });

    return {
      storageUrl: session.storageUrl,
      retrieveUrl: session.retrieveUrl,
    };
  } catch (error) {
    return {};
  }
}

/**
 * Lee un número de un campo del formulario.
 *
 * @param {string} id Identificador del campo.
 * @returns {number|null} Valor, o null si no hay campo o no es un número.
 */
function readNumber(id) {
  const field = document.querySelector(`#${id}`);
  const value = field ? Number.parseInt(field.value, 10) : Number.NaN;

  return Number.isFinite(value) ? value : null;
}

/**
 * Construye los parámetros del sello visible.
 *
 * Los nombres son los que espera AutoFirma, tomados de `PdfExtraParams` en el
 * código oficial. Una firma PAdES es invisible salvo que se le dé el rectángulo
 * donde dibujarse, así que sin las cuatro coordenadas no se manda nada: mandar
 * el texto solo produciría una firma sin sello y la impresión de que falla.
 *
 * @returns {object} Parámetros para AutoFirma, o vacío si no hay sello.
 */
function watermarkParameters() {
  const enabled = document.querySelector("#wp-autofirma-watermark");

  if (!enabled || !enabled.checked) {
    return {};
  }

  const field = document.querySelector("#wp-autofirma-layer2-text");
  const text = field ? field.value.trim() : "";

  if (text === "") {
    throw new Error(settings.strings.emptyWatermark);
  }

  const corners = {
    signaturePositionOnPageLowerLeftX: readNumber("wp-autofirma-left"),
    signaturePositionOnPageLowerLeftY: readNumber("wp-autofirma-bottom"),
    signaturePositionOnPageUpperRightX: readNumber("wp-autofirma-right"),
    signaturePositionOnPageUpperRightY: readNumber("wp-autofirma-top"),
  };

  if (Object.values(corners).some((value) => value === null)) {
    throw new Error(settings.strings.incompleteWatermark);
  }

  const page = readNumber("wp-autofirma-page");

  return {
    layer2Text: text,
    signaturePage: page === null ? 1 : page,
    ...corners,
  };
}

/**
 * Firma uno o varios PDF con la misma configuración de sello.
 *
 * @param {object[]} documents Documentos descargados.
 * @param {object} parameters Parámetros comunes, leídos antes de firmar.
 * @returns {Promise<object>} Resultados individuales del lote.
 */
async function signDocuments(documents, parameters) {
  const client = new AutoFirmaClient(await openIntermediateSession());
  client.initialize();
  if (documents.length === 1) {
    const signed = await client.sign({
      data: documents[0].data,
      format: "PAdES",
      parameters,
    });
    return {
      signs: [
        {
          id: String(documents[0].attachmentId),
          result: "DONE_AND_SAVED",
          signature: signed.signature,
        },
      ],
    };
  }
  return client.signBatch({
    documents: documents.map(({ attachmentId, data }) => ({
      id: String(attachmentId),
      data,
    })),
    format: "PAdES",
    parameters,
    stopOnError: false,
  });
}

/**
 * Orquesta lectura, firma y guardado; conserva descargas si falla WordPress.
 */
async function handleSign() {
  button.disabled = true;
  result.hidden = true;
  result.replaceChildren();

  try {
    const parameters = { mode: "implicit", ...watermarkParameters() };
    const ids = settings.attachmentIds ?? [settings.attachmentId];
    const documents = [];
    status.textContent = settings.strings.loading;
    // La API comprueba el permiso de cada documento antes de lanzar AutoFirma.
    for (const id of ids) {
      documents.push(await request(`/documents/${id}`));
    }
    status.textContent = settings.strings.signing;
    const batch = await signDocuments(documents, parameters);

    // Ya hubo respuesta nativa: no repetir el lote y duplicar firmas guardadas.
    button.hidden = true;
    document
      .querySelector(".wp-autofirma__watermark")
      ?.setAttribute("hidden", "");
    result.hidden = false;
    status.textContent = settings.strings.saving;
    let completed = 0;
    const resultsById = new Map(batch.signs.map((item) => [item.id, item]));

    for (const documentData of documents) {
      const item = document.createElement("p");
      if (documents.length > 1) {
        const name = document.createElement("strong");
        name.textContent = documentData.filename;
        item.append(name, ": ");
      }
      result.append(item);
      const signed = resultsById.get(String(documentData.attachmentId));
      if (!signed || signed.result !== "DONE_AND_SAVED" || !signed.signature) {
        item.append(
          settings.strings.notSigned,
          " ",
          signed?.description ??
            signed?.result ??
            settings.strings.unknownError,
        );
        continue;
      }

      try {
        const filename = createSignedFilename(documentData.filename);
        const download = document.createElement("a");
        download.href = URL.createObjectURL(toPdfBlob(signed.signature));
        download.download = filename;
        download.textContent = settings.strings.download;
        item.append(download);

        const saved = await request("/signatures", {
          method: "POST",
          body: JSON.stringify({
            originalAttachmentId: documentData.attachmentId,
            filename,
            signature: signed.signature,
          }),
        });
        completed += 1;
        if (saved.editUrl) {
          const edit = document.createElement("a");
          edit.href = saved.editUrl;
          edit.textContent = settings.strings.edit;
          item.append(" · ", edit);
        }
      } catch (error) {
        item.append(
          " · ",
          settings.strings.notSaved,
          " ",
          error instanceof Error
            ? error.message
            : settings.strings.unknownError,
        );
      }
    }

    if (completed === documents.length) {
      status.textContent =
        documents.length === 1
          ? settings.strings.completed
          : settings.strings.batchCompleted;
      document.querySelector("#wp-autofirma-check")?.removeAttribute("hidden");
    } else {
      status.textContent = settings.strings.batchPartial;
    }
    result.focus();
  } catch (error) {
    status.textContent =
      error instanceof Error ? error.message : settings.strings.unknownError;
  } finally {
    button.disabled = false;
  }
}

button?.addEventListener("click", handleSign);

// La casilla habilita el grupo entero: los campos se ven siempre, para saber
// qué se puede configurar antes de activarlo.
const watermark = document.querySelector("#wp-autofirma-watermark");

watermark?.addEventListener("change", () => {
  const fields = document.querySelector("#wp-autofirma-watermark-fields");

  if (fields) {
    fields.disabled = !watermark.checked;
  }
});
