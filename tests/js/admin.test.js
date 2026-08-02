import { describe, expect, it, vi } from "vitest";

/**
 * Pruebas de la orquestación del navegador.
 *
 * `admin.js` es lo que enlaza los tres pasos de una firma: pedir el documento a
 * WordPress, pasarlo a AutoFirma y devolver el resultado. Como se ejecuta al
 * importarse —lee la configuración y engancha el botón—, cada prueba monta el
 * DOM y los globales antes de cargarlo, y lo recarga desde cero.
 */

const sign = vi.fn();
const initialize = vi.fn();

vi.mock("@erseco/autofirma-client", () => ({
  AutoFirmaClient: class {
    constructor(options) {
      this.options = options;
      construidoCon.push(options);
    }

    initialize() {
      initialize();
    }

    sign(options) {
      return sign(options);
    }
  },
}));

let construidoCon = [];

/**
 * Monta la página, la configuración y las respuestas del servidor.
 *
 * @param {object} opciones Ajustes de la prueba.
 * @returns {Promise<object>} Elementos del DOM y espías.
 */
async function montar({
  intermediate = false,
  respuestas = {},
  sello = {},
} = {}) {
  vi.resetModules();
  construidoCon = [];
  sign.mockReset();
  initialize.mockReset();
  // El valor por defecto se fija aquí, después del reset: en un `beforeEach`
  // lo borraría esta misma función al montar.
  sign.mockResolvedValue({ signature: btoa("%PDF-1.7 firmado") });

  document.body.innerHTML = `
    <button id="wp-autofirma-sign"></button>
    <input type="checkbox" id="wp-autofirma-watermark" ${sello.activo ? "checked" : ""} />
    <fieldset id="wp-autofirma-watermark-fields" ${sello.activo ? "" : "disabled"}>
    <textarea id="wp-autofirma-layer2-text">${sello.text ?? "Firmado por $$SUBJECTCN$$"}</textarea>
    <input id="wp-autofirma-page" value="${sello.page ?? "1"}" />
    <input id="wp-autofirma-left" value="${sello.left ?? "40"}" />
    <input id="wp-autofirma-bottom" value="${sello.bottom ?? "40"}" />
    <input id="wp-autofirma-right" value="${sello.right ?? "260"}" />
    <input id="wp-autofirma-top" value="${sello.top ?? "110"}" />
    </fieldset>
    <fieldset class="wp-autofirma__watermark"></fieldset>
    <p id="wp-autofirma-status"><span id="wp-autofirma-check" hidden></span><span id="wp-autofirma-message"></span></p>
    <p id="wp-autofirma-result" hidden></p>
  `;

  window.wpAutoFirmaSettings = {
    attachmentId: "42",
    nonce: "un-nonce",
    restUrl: "https://example.org/wp-json/wp-autofirma/v1",
    intermediate,
    strings: {
      cancelled: "Cancelado",
      completed: "Completado",
      download: "Descargar",
      edit: "Editar",
      loading: "Cargando",
      saving: "Guardando",
      signing: "Firmando",
      unknownError: "Error desconocido",
      incompleteWatermark: "Faltan coordenadas",
      emptyWatermark: "Falta el texto",
    },
  };

  const porDefecto = {
    "/documents/42": {
      ok: true,
      body: { attachmentId: 42, filename: "contrato.pdf", data: "ZGF0b3M=" },
    },
    "/signatures": {
      ok: true,
      body: { editUrl: "https://example.org/editar" },
    },
    "/intermediate-sessions": {
      ok: true,
      body: {
        storageUrl: "https://example.org/storage",
        retrieveUrl: "https://example.org/retrieve",
      },
    },
  };

  const rutas = { ...porDefecto, ...respuestas };

  const fetchSpy = vi.fn(async (url) => {
    const ruta = Object.keys(rutas).find((clave) => url.endsWith(clave));
    const respuesta = rutas[ruta] ?? { ok: false, body: {} };

    return {
      ok: respuesta.ok,
      json: async () => respuesta.body,
    };
  });

  window.fetch = fetchSpy;
  globalThis.fetch = fetchSpy;
  globalThis.URL.createObjectURL = vi.fn(() => "blob:resultado");

  await import("../../assets/js/admin.js");

  return {
    boton: document.querySelector("#wp-autofirma-sign"),
    estado: document.querySelector("#wp-autofirma-message"),
    resultado: document.querySelector("#wp-autofirma-result"),
    fetchSpy,
  };
}

/**
 * Espera a que se vacíe la cola de microtareas.
 *
 * @returns {Promise<void>} Promesa resuelta.
 */
function esperar() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

describe("orquestación de la firma", () => {
  it("recorre los tres pasos y ofrece la descarga", async () => {
    const { boton, estado, resultado, fetchSpy } = await montar();

    boton.click();
    await esperar();

    expect(fetchSpy.mock.calls[0][0]).toContain("/documents/42");
    expect(sign).toHaveBeenCalledWith(
      expect.objectContaining({ format: "PAdES", data: "ZGF0b3M=" }),
    );
    expect(estado.textContent).toBe("Completado");
    expect(resultado.hidden).toBe(false);

    const enlaces = resultado.querySelectorAll("a");
    expect(enlaces[0].textContent).toBe("Descargar");
    expect(enlaces[0].download).toBe("contrato-firmado.pdf");
    expect(enlaces[1].textContent).toBe("Editar");
  });

  it("manda a guardar el documento firmado con su nombre nuevo", async () => {
    const { boton, fetchSpy } = await montar();

    boton.click();
    await esperar();

    const guardado = fetchSpy.mock.calls.find(([url]) =>
      url.endsWith("/signatures"),
    );
    const cuerpo = JSON.parse(guardado[1].body);

    expect(cuerpo.originalAttachmentId).toBe(42);
    expect(cuerpo.filename).toBe("contrato-firmado.pdf");
    expect(guardado[1].headers["X-WP-Nonce"]).toBe("un-nonce");
  });

  it("no pide sesión intermedia si el servidor no la ofrece", async () => {
    const { boton, fetchSpy } = await montar({ intermediate: false });

    boton.click();
    await esperar();

    expect(
      fetchSpy.mock.calls.some(([url]) =>
        url.includes("intermediate-sessions"),
      ),
    ).toBe(false);
    expect(construidoCon[0]).toEqual({});
  });

  it("pasa las direcciones del servidor intermedio cuando las hay", async () => {
    const { boton } = await montar({ intermediate: true });

    boton.click();
    await esperar();

    expect(construidoCon[0]).toEqual({
      storageUrl: "https://example.org/storage",
      retrieveUrl: "https://example.org/retrieve",
    });
  });

  it("firma igualmente si la sesión intermedia falla", async () => {
    const { boton, estado } = await montar({
      intermediate: true,
      respuestas: { "/intermediate-sessions": { ok: false, body: {} } },
    });

    boton.click();
    await esperar();

    expect(construidoCon[0]).toEqual({});
    expect(estado.textContent).toBe("Completado");
  });

  it("muestra el mensaje del servidor cuando la lectura falla", async () => {
    const { boton, estado } = await montar({
      respuestas: {
        "/documents/42": { ok: false, body: { message: "Sin permiso" } },
      },
    });

    boton.click();
    await esperar();

    expect(estado.textContent).toBe("Sin permiso");
    expect(boton.disabled).toBe(false);
  });

  it("recurre al mensaje genérico si el servidor no explica nada", async () => {
    const { boton, estado } = await montar({
      respuestas: { "/documents/42": { ok: false, body: {} } },
    });

    boton.click();
    await esperar();

    expect(estado.textContent).toBe("Error desconocido");
  });

  it("muestra el error que devuelve AutoFirma", async () => {
    const { boton, estado } = await montar();
    sign.mockRejectedValue(new Error("Operación cancelada"));

    boton.click();
    await esperar();

    expect(estado.textContent).toBe("Operación cancelada");
  });

  it("vuelve a habilitar el botón aunque algo falle sin ser un Error", async () => {
    const { boton, estado } = await montar();
    sign.mockRejectedValue("un fallo suelto");

    boton.click();
    await esperar();

    expect(estado.textContent).toBe("Error desconocido");
    expect(boton.disabled).toBe(false);
  });

  it("no ofrece enlace de edición si WordPress no lo devuelve", async () => {
    const { boton, resultado } = await montar({
      respuestas: { "/signatures": { ok: true, body: {} } },
    });

    boton.click();
    await esperar();

    expect(resultado.querySelectorAll("a")).toHaveLength(1);
  });

  it("firma sin sello mientras la casilla esté sin marcar", async () => {
    const { boton } = await montar();

    boton.click();
    await esperar();

    const parametros = sign.mock.calls[0][0].parameters;
    expect(parametros).toEqual({ mode: "implicit" });
  });

  it("manda el texto y el rectángulo con los nombres que espera AutoFirma", async () => {
    const { boton } = await montar({
      sello: {
        activo: true,
        text: "Firmado por $$SUBJECTCN$$",
        page: "3",
        left: "10",
        bottom: "20",
        right: "300",
        top: "120",
      },
    });

    boton.click();
    await esperar();

    expect(sign.mock.calls[0][0].parameters).toEqual({
      mode: "implicit",
      layer2Text: "Firmado por $$SUBJECTCN$$",
      signaturePage: 3,
      signaturePositionOnPageLowerLeftX: 10,
      signaturePositionOnPageLowerLeftY: 20,
      signaturePositionOnPageUpperRightX: 300,
      signaturePositionOnPageUpperRightY: 120,
    });
  });

  it("avisa si falta una coordenada en vez de firmar sin sello", async () => {
    const { boton, estado } = await montar({
      sello: { activo: true, text: "Sello", right: "" },
    });

    boton.click();
    await esperar();

    expect(sign).not.toHaveBeenCalled();
    expect(estado.textContent).toBe("Faltan coordenadas");
  });

  it("avisa si se marca el sello y se borra el texto", async () => {
    const { boton, estado } = await montar({
      sello: { activo: true, text: "" },
    });

    boton.click();
    await esperar();

    expect(sign).not.toHaveBeenCalled();
    expect(estado.textContent).toBe("Falta el texto");
  });

  it("la casilla habilita y deshabilita el grupo de campos", async () => {
    await montar();

    const casilla = document.querySelector("#wp-autofirma-watermark");
    const campos = document.querySelector("#wp-autofirma-watermark-fields");

    expect(campos.disabled).toBe(true);

    casilla.checked = true;
    casilla.dispatchEvent(new Event("change"));
    expect(campos.disabled).toBe(false);

    casilla.checked = false;
    casilla.dispatchEvent(new Event("change"));
    expect(campos.disabled).toBe(true);
  });

  it("al terminar retira el formulario y el botón y deja la descarga", async () => {
    const { boton, resultado } = await montar();

    boton.click();
    await esperar();

    expect(document.querySelector("#wp-autofirma-check").hidden).toBe(false);
    expect(boton.hidden).toBe(true);
    expect(
      document.querySelector(".wp-autofirma__watermark").hasAttribute("hidden"),
    ).toBe(true);
    expect(resultado.hidden).toBe(false);
    expect(resultado.querySelectorAll("a").length).toBeGreaterThan(0);
  });

  it("si falla, el formulario y el botón siguen disponibles", async () => {
    const { boton } = await montar({
      respuestas: { "/documents/42": { ok: false, body: {} } },
    });

    boton.click();
    await esperar();

    expect(boton.hidden).toBe(false);
    expect(document.querySelector("#wp-autofirma-check").hidden).toBe(true);
    expect(
      document.querySelector(".wp-autofirma__watermark").hasAttribute("hidden"),
    ).toBe(false);
  });
});
