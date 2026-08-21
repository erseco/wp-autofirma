=== WP AutoFirma ===
Contributors: erseco
Tags: autofirma, digital signature, pades, media library
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sign media library PDFs with AutoFirma, the Spanish government signing application.

== Description ==

WP AutoFirma adds a signing action to PDF files in the media library. Signing
happens on the user's own computer through AutoFirma; the result is stored as a
new attachment and the original file is never modified.

The media library also marks which documents carry a digital signature and
describes what that signature declares. Shortcodes publish the same information
on the site. This is detection, not validation: it reports that a signature is
present, never that it is valid.

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

= What does the signature mark in the media library mean? =

That the file contains a signature structure, and nothing more. A document
modified after being signed still shows the mark. Use a specialised validation
service to check whether a signature is actually valid.

= Can documents be signed from a phone? =

Yes. Mobile devices cannot reach AutoFirma over a local WebSocket, so the
protocol relies on two intermediate HTTP services. The plugin provides them with
no configuration required; the site must use pretty permalinks.

= Which shortcodes are available? =

`[autofirma_signature_status]` reports whether an attachment is signed,
`[autofirma_signature_info]` describes the signature, and
`[autofirma_signed_documents]` lists signed attachments. None of them display
anything to visitors who cannot view the attachment.

== Changelog ==

= 0.1.0 =

* Initial release with PAdES signing, REST endpoints, tests and Playground demo.
* Signature detection in the media library, attachment details and shortcodes.
