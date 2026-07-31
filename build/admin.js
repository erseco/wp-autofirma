var WPAutoFirmaAdmin = (() => {
  // node_modules/@erseco/autofirma-client/dist/index.js
  var AutoFirmaError = class extends Error {
    code;
    nativeType;
    nativeMessage;
    constructor(message, code = "AUTOFIRMA_ERROR", nativeType, nativeMessage) {
      super(message);
      this.name = "AutoFirmaError";
      this.code = code;
      this.nativeType = nativeType;
      this.nativeMessage = nativeMessage;
    }
  };
  var AutoScriptUnavailableError = class extends AutoFirmaError {
    constructor() {
      super(
        "AutoScript no est\xE1 disponible en la p\xE1gina.",
        "AUTOSCRIPT_UNAVAILABLE"
      );
      this.name = "AutoScriptUnavailableError";
    }
  };
  function fromNativeError(nativeType, nativeMessage) {
    const normalizedType = nativeType.toLowerCase();
    const normalizedMessage = nativeMessage.toLowerCase();
    const cancelled = normalizedType.includes("cancel") || normalizedMessage.includes("cancel");
    return new AutoFirmaError(
      cancelled ? "La operaci\xF3n de firma fue cancelada." : "AutoFirma no pudo completar la operaci\xF3n.",
      cancelled ? "USER_CANCELLED" : "NATIVE_ERROR",
      nativeType,
      nativeMessage
    );
  }
  function resolveAutoScript(injected) {
    if (injected) {
      return injected;
    }
    if (typeof window !== "undefined" && window.AutoScript) {
      return window.AutoScript;
    }
    throw new AutoScriptUnavailableError();
  }
  function invokeSignatureOperation(operation, data, algorithm, format, parameters) {
    return new Promise((resolve, reject) => {
      operation(
        data,
        algorithm,
        format,
        parameters,
        (signature, certificate, extraData) => {
          resolve({
            signature,
            ...certificate ? { certificate } : {},
            ...extraData ? { extraData } : {}
          });
        },
        (errorType, errorMessage) => {
          reject(fromNativeError(errorType, errorMessage));
        }
      );
    });
  }
  function serializeParameters(parameters = {}) {
    return Object.entries(parameters).filter((entry) => {
      return entry[1] !== void 0 && entry[1] !== null;
    }).map(([key, value]) => {
      if (/[\r\n=]/u.test(key)) {
        throw new TypeError(`Nombre de par\xE1metro no v\xE1lido: ${key}`);
      }
      const serializedValue = String(value).replace(/[\r\n]+/gu, " ");
      return `${key}=${serializedValue}`;
    }).join("\n");
  }
  async function toBase64(data) {
    if (typeof data === "string") {
      return data;
    }
    if (typeof Blob !== "undefined" && data instanceof Blob) {
      return bytesToBase64(new Uint8Array(await data.arrayBuffer()));
    }
    if (data instanceof ArrayBuffer) {
      return bytesToBase64(new Uint8Array(data));
    }
    if (data instanceof Uint8Array) {
      return bytesToBase64(data);
    }
    throw new TypeError("Tipo de dato no compatible.");
  }
  function bytesToBase64(bytes) {
    let binary = "";
    const chunkSize = 32768;
    for (let offset = 0; offset < bytes.length; offset += chunkSize) {
      binary += String.fromCharCode(
        ...bytes.subarray(offset, offset + chunkSize)
      );
    }
    if (typeof btoa === "function") {
      return btoa(binary);
    }
    throw new Error("El entorno no proporciona una funci\xF3n Base64 compatible.");
  }
  var DEFAULT_ALGORITHM = "SHA256withRSA";
  var AutoFirmaClient = class {
    autoScript;
    constructor(options = {}) {
      this.autoScript = resolveAutoScript(options.autoScript);
      if (options.storageUrl && options.retrieveUrl && this.autoScript.setServlets) {
        this.autoScript.setServlets(options.storageUrl, options.retrieveUrl);
      }
    }
    /**
     * Solicita a AutoScript que prepare o abra AutoFirma.
     */
    initialize() {
      this.autoScript.cargarAppAfirma?.();
    }
    /**
     * Firma los datos proporcionados.
     */
    sign(options) {
      return this.execute(this.autoScript.sign, options);
    }
    /**
     * Añade una firma al mismo nivel cuando AutoScript expone la operación.
     */
    coSign(options) {
      return this.executeRequired("coSign", this.autoScript.coSign, options);
    }
    /**
     * Contrafirma cuando AutoScript expone la operación.
     */
    counterSign(options) {
      return this.executeRequired(
        "counterSign",
        this.autoScript.counterSign,
        options
      );
    }
    /**
     * Abre la selección de certificado sin iniciar una firma.
     */
    selectCertificate(parameters = {}) {
      if (!this.autoScript.selectCertificate) {
        return Promise.reject(
          new AutoFirmaError(
            "Esta versi\xF3n de AutoScript no expone selectCertificate.",
            "UNSUPPORTED_OPERATION"
          )
        );
      }
      return new Promise((resolve, reject) => {
        this.autoScript.selectCertificate?.(
          serializeParameters(parameters),
          (certificate) => resolve({ certificate }),
          (type, message) => reject(fromNativeError(type, message))
        );
      });
    }
    /**
     * Devuelve el objeto oficial para casos que el wrapper todavía no cubra.
     */
    get raw() {
      return this.autoScript;
    }
    /**
     * Valida que exista la operación opcional antes de ejecutarla.
     */
    executeRequired(name, operation, options) {
      if (!operation) {
        return Promise.reject(
          new AutoFirmaError(
            `Esta versi\xF3n de AutoScript no expone ${name}.`,
            "UNSUPPORTED_OPERATION"
          )
        );
      }
      return this.execute(operation, options);
    }
    /**
     * Normaliza datos y parámetros antes de delegar en AutoScript.
     */
    async execute(operation, options) {
      return invokeSignatureOperation(
        operation,
        await toBase64(options.data),
        options.algorithm ?? DEFAULT_ALGORITHM,
        options.format,
        serializeParameters(options.parameters)
      );
    }
  };

  // assets/js/filename.js
  function createSignedFilename(filename) {
    const lastDot = filename.lastIndexOf(".");
    if (lastDot <= 0) {
      return `${filename}-firmado`;
    }
    return `${filename.slice(0, lastDot)}-firmado${filename.slice(lastDot).toLowerCase()}`;
  }

  // assets/js/admin.js
  var settings = window.wpAutoFirmaSettings;
  var button = document.querySelector("#wp-autofirma-sign");
  var status = document.querySelector("#wp-autofirma-status");
  var result = document.querySelector("#wp-autofirma-result");
  async function request(path, options = {}) {
    const response = await fetch(`${settings.restUrl}${path}`, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": settings.nonce,
        ...options.headers
      }
    });
    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.message || settings.strings.unknownError);
    }
    return payload;
  }
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
        mode: "implicit"
      }
    });
    return signed.signature;
  }
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
          signature
        })
      });
      status.textContent = settings.strings.completed;
      result.hidden = false;
      result.replaceChildren();
      const link = document.createElement("a");
      link.href = saved.editUrl || saved.url;
      link.textContent = saved.editUrl ? "Editar el documento firmado" : "Ver el documento firmado";
      result.append(link);
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : settings.strings.unknownError;
    } finally {
      button.disabled = false;
    }
  }
  button?.addEventListener("click", handleSign);
})();
//# sourceMappingURL=admin.js.map
