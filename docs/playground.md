# WordPress Playground

[Abrir la demostración](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/erseco/wp-autofirma/main/blueprint.json)

El Blueprint instala el plugin desde `main`, crea un PDF ficticio y abre la
pantalla de firma.

## Límite deliberado

La demo define `WP_AUTOFIRMA_DEMO_MODE`. En este modo el plugin simula la salida
para demostrar el flujo REST y la creación de un adjunto. El aviso permanece
visible y el resultado **no contiene una firma electrónica**.

No se envían documentos a la infraestructura de este proyecto. El estado de
Playground vive en la instancia temporal gestionada por WordPress Playground.
