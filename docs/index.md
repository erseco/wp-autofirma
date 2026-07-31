# WP AutoFirma

Integra PDF de la biblioteca de medios con AutoScript y AutoFirma.

[Probar en WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/erseco/wp-autofirma/main/blueprint.json)

## Flujo

1. WordPress comprueba permisos y entrega el PDF.
2. `@erseco/autofirma-client` invoca AutoScript.
3. AutoFirma firma localmente.
4. WordPress guarda el resultado como un adjunto nuevo.

El proyecto es independiente y no oficial. No incluye AutoFirma, AutoScript ni
validación criptográfica en servidor.
