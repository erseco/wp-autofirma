import { describe, expect, it } from "vitest";
import { createSignedFilename } from "../../assets/js/filename.js";

describe("createSignedFilename", () => {
  it("añade el sufijo antes de la extensión", () => {
    expect(createSignedFilename("resolucion.PDF")).toBe(
      "resolucion-firmado.pdf",
    );
  });

  it("admite nombres sin extensión", () => {
    expect(createSignedFilename("documento")).toBe("documento-firmado");
  });

  it("conserva nombres que empiezan por punto", () => {
    expect(createSignedFilename(".documento")).toBe(".documento-firmado");
  });
});
