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
    if (normalizedType.includes("cancel") || normalizedMessage.includes("cancel")) {
      return new AutoFirmaError(
        "La operaci\xF3n de firma fue cancelada.",
        "USER_CANCELLED",
        nativeType,
        nativeMessage
      );
    }
    if (normalizedType.includes("outofmemory")) {
      return new AutoFirmaError(
        "El fichero excede la memoria disponible de AutoFirma.",
        "DATA_TOO_LARGE",
        nativeType,
        nativeMessage
      );
    }
    if (normalizedType.includes("timeout")) {
      return new AutoFirmaError(
        "AutoFirma no respondi\xF3 a tiempo.",
        "NATIVE_TIMEOUT",
        nativeType,
        nativeMessage
      );
    }
    return new AutoFirmaError(
      "AutoFirma no pudo completar la operaci\xF3n.",
      "NATIVE_ERROR",
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
        return this.unsupported("selectCertificate");
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
     * Pide a AutoFirma que guarde datos en un fichero elegido por la persona
     * usuaria.
     */
    saveDataToFile(options) {
      const operation = this.autoScript.saveDataToFile;
      if (!operation) {
        return this.unsupported("saveDataToFile");
      }
      return new Promise((resolve, reject) => {
        operation(
          options.data,
          options.title,
          options.filename,
          options.extension,
          options.description,
          () => resolve(),
          (type, message) => reject(fromNativeError(type, message))
        );
      });
    }
    /**
     * Comprueba la sincronía del reloj del equipo contra un servidor.
     *
     * AutoScript lo implementa con una petición XHR **síncrona** que bloquea el
     * hilo principal (`xhr.open('GET', url, false)`) hasta obtener respuesta.
     * Si no se indica `checkUrl`, la petición se envía contra
     * `document.URL + '/' + Math.random()`: una URL inventada contra el propio
     * origen de la página, un acceso de red no documentado en ningún otro sitio
     * y que este método no evita ni controla, pese a que esta librería no hace
     * ningún acceso de red propio.
     *
     * El único efecto observable de un desfase es un `alert()` nativo; AutoScript
     * captura y silencia cualquier error (por ejemplo, que la petición falle).
     * Por eso la promesa devuelta nunca informa del resultado de la
     * comprobación: se resuelve siempre que la operación exista, haya o no
     * desfase y haya o no error de red.
     *
     * Con `checkType: "CT_OBLIGATORY"` y un desfase detectado, AutoScript marca
     * un estado interno (`severeTimeDelay`) que hace que su función de carga
     * (`cargarAppAfirma`, a la que invoca `initialize()`) registre un aviso y
     * retorne sin hacer nada la siguiente vez que se ejecute. El orden de
     * llamadas importa y no está documentado: invocar
     * `checkTime({ checkType: "CT_OBLIGATORY" })` antes de `initialize()` puede
     * convertir `initialize()` en un no-op silencioso; invocarlo después de
     * `initialize()` no afecta a una carga que ya se ha iniciado.
     *
     * Si no se indica `maxMillis`, se reenvía tal cual: AutoScript aplica
     * entonces su propio valor por defecto (300000 ms, 5 minutos) en vez de uno
     * impuesto aquí.
     */
    checkTime(options = {}) {
      const operation = this.autoScript.checkTime;
      if (!operation) {
        return this.unsupported("checkTime");
      }
      operation(
        options.checkType ?? "CT_RECOMMENDED",
        options.maxMillis,
        options.checkUrl
      );
      return Promise.resolve();
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
        return this.unsupported(name);
      }
      return this.execute(operation, options);
    }
    /**
     * Rechazo homogéneo para operaciones ausentes en la versión fijada.
     */
    unsupported(name) {
      return Promise.reject(
        new AutoFirmaError(
          `Esta versi\xF3n de AutoScript no expone ${name}.`,
          "UNSUPPORTED_OPERATION"
        )
      );
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
