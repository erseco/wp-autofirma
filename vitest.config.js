import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    include: ["tests/js/**/*.test.js"],
    // `admin.js` orquesta el navegador: sin DOM no puede ni cargarse.
    environment: "jsdom",
    coverage: {
      provider: "v8",
      reporter: ["text", "lcov"],
      // Se mide todo `assets/js`, no solo lo que hay bajo prueba: `admin.js`
      // orquesta el navegador y no se cubre con pruebas unitarias, y esconderlo
      // daría una cifra bonita en lugar de una cifra cierta.
      include: ["assets/js/**/*.js"],
    },
  },
});
