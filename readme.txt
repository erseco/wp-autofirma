=== WP AutoFirma ===
Contributors: erseco
Tags: autofirma, digital signature, pades, media library
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sign media library PDFs with AutoFirma, the Spanish government signing application.

== Description ==

WP AutoFirma adds a signing action to PDF files in the media library. Signing
happens on the user's own computer through AutoFirma; the result is stored as a
new attachment and the original file is never modified.

The plugin bundles AutoScript, the official browser bridge, pinned to a specific
release and verified by checksum, so no manual setup is required.

This is an independent, unofficial project. It does not include the AutoFirma
desktop application and does not perform server-side cryptographic validation.

== Installation ==

1. Install and activate the plugin.
2. Install AutoFirma on the computer that will sign.
3. Open the media library in list view and use the signing action on a PDF.

== Frequently Asked Questions ==

= Does the plugin store documents? =

Yes. It reads the original from the media library and stores the signed result
as a separate attachment. Documents are never sent to any service owned by this
project.

= Is the media library private? =

Not necessarily. In a standard installation, files under uploads may be publicly
reachable even when the attachment is restricted inside WordPress.

= Does the plugin validate signatures? =

No. Cryptographic and trust validation must be performed by a backend or a
specialised service.

== Changelog ==

= 0.1.0 =

* Initial release with PAdES signing, REST endpoints, tests and Playground demo.
