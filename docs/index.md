# WP AutoFirma

Integra PDF de la biblioteca de medios con AutoScript y AutoFirma.

[Probar en WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/erseco/wp-autofirma/main/blueprint.json)

## Flujo

1. WordPress comprueba permisos y entrega el PDF.
2. `@erseco/autofirma-client` invoca AutoScript.
3. AutoFirma firma localmente.
4. WordPress guarda el resultado como un adjunto nuevo.

## Además de firmar

El plugin marca en la biblioteca qué documentos llevan firma digital y publica
esa información donde haga falta:

- [Detección de firmas](guia/firmas-detectadas.md): qué reconoce, qué no valida
  y cuánto cuesta.
- [Shortcodes](guia/shortcodes.md): publicar el estado de firma en el sitio.
- [Servidor intermedio](guia/servidor-intermedio.md): lo que permite firmar
  desde el móvil.

El proyecto es independiente y no oficial. No incluye AutoFirma, AutoScript ni
validación criptográfica en servidor.
