=== WP AutoFirma ===
Contributors: erseco
Tags: autofirma, firma electrónica, pades, media library
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Firma PDF de la biblioteca de medios mediante AutoScript y AutoFirma.

== Description ==

WP AutoFirma añade una acción para firmar PDF de la biblioteca de medios. El
resultado se guarda como un adjunto nuevo y el original no se modifica.

Este proyecto es independiente y no oficial. No incluye AutoFirma, AutoScript ni
validación criptográfica en servidor.

== Installation ==

1. Instala y activa el plugin.
2. Sirve una copia oficial de autoscript.js desde tu infraestructura.
3. Define WP_AUTOFIRMA_AUTOSCRIPT_URL con la URL del fichero.
4. Abre la biblioteca de medios en vista de lista.

== Frequently Asked Questions ==

= ¿El plugin almacena documentos? =

Sí. Lee el original de la biblioteca y guarda el resultado firmado como otro
adjunto. No envía documentos a un servicio propio del proyecto.

= ¿La biblioteca de medios es privada? =

No necesariamente. En una instalación estándar, los ficheros de uploads pueden
ser públicos aunque el adjunto tenga restricciones en WordPress.

= ¿El plugin valida jurídicamente la firma? =

No. La validación criptográfica y de confianza debe hacerse en un backend o
servicio especializado.

== Changelog ==

= 0.1.0 =

* Esqueleto inicial con firma PAdES, REST, pruebas, Playground y documentación.
