# WordPress Playground

[Abrir la demostración](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/erseco/wp-autofirma/main/blueprint.json)

El Blueprint instala el plugin desde `main`, sube un PDF ficticio y abre la
biblioteca de medios. Desde ahí, la acción «Firmar con AutoFirma» sobre ese PDF
ejecuta el flujo completo.

## La firma es real

La demostración **firma de verdad**: requiere AutoFirma instalada en tu equipo y
un certificado válido, y produce un PDF con una firma electrónica auténtica.
WordPress corre entero dentro de tu navegador, así que el documento no viaja a
ningún servidor de este proyecto; la firma la realiza tu AutoFirma local.

El estado de Playground vive en la instancia temporal que gestiona WordPress
Playground y desaparece al cerrar la pestaña.

## Qué no demuestra

Que la firma sea válida jurídicamente. La validación criptográfica, de cadena de
confianza y de revocación corresponde a un servicio especializado, no a esta
pantalla ni a la librería.
