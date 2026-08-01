#!/usr/bin/env bash
# Genera los ficheros de prueba del detector de firmas.
#
# Las firmas reales llevan el nombre y el DNI de quien firma, así que aquí no
# entra ninguna: todo se fabrica con un certificado autofirmado de usar y tirar.
# Las firmas resultantes no son válidas criptográficamente, y da igual, porque
# lo que se prueba es la detección de la estructura, no su validez.
#
# Uso: ./generate.sh
set -euo pipefail

cd "$(dirname "$0")"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

openssl req -x509 -newkey rsa:2048 -keyout "$work/key.pem" -out "$work/cert.pem" \
    -days 3650 -nodes -subj "/CN=FIRMANTE DE PRUEBA/O=WP AutoFirma" 2>/dev/null

printf 'contenido de prueba\n' > "$work/plain.txt"

# CAdES suelto, como el .csig de AutoFirma.
openssl smime -sign -binary -in "$work/plain.txt" -signer "$work/cert.pem" \
    -inkey "$work/key.pem" -outform DER -nodetach -out signed.csig 2>/dev/null

# El mismo PKCS#7 embebido en un PDF, como hace PAdES.
hex="$(xxd -p -c 100000 signed.csig)"
{
    printf '%%PDF-1.7\n'
    printf '1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [4 0 R] /SigFlags 3 >> >>\nendobj\n'
    printf '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n'
    printf '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n'
    printf '4 0 obj\n<< /Type /Annot /Subtype /Widget /FT /Sig /T (Firma1) /V 5 0 R >>\nendobj\n'
    printf '5 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /ETSI.CAdES.detached '
    printf '/M (D:20260801120000+02'"'"'00'"'"') /ByteRange [0 1000 2000 3000] /Contents <%s> >>\nendobj\n' "$hex"
    printf 'trailer\n<< /Root 1 0 R >>\n%%%%EOF\n'
} > signed.pdf

# PDF sin firma: el caso que nunca debe dar positivo.
{
    printf '%%PDF-1.7\n'
    printf '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n'
    printf '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n'
    printf '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>\nendobj\n'
    printf 'trailer\n<< /Root 1 0 R >>\n%%%%EOF\n'
} > unsigned.pdf

# XAdES: firma XML con propiedades ETSI.
cat > signed.xsig <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<documento>
  <ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Id="Signature1">
    <ds:SignedInfo />
    <ds:SignatureValue>QUJDREVG</ds:SignatureValue>
    <ds:Object>
      <xades:QualifyingProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#">
        <xades:SignedProperties>
          <xades:SignedSignatureProperties>
            <xades:SigningTime>2026-08-01T12:00:00Z</xades:SigningTime>
          </xades:SignedSignatureProperties>
        </xades:SignedProperties>
      </xades:QualifyingProperties>
    </ds:Object>
  </ds:Signature>
</documento>
XML

# OpenDocument con firma: basta la entrada del contenedor.
mkdir -p "$work/odf/META-INF"
printf 'application/vnd.oasis.opendocument.text' > "$work/odf/mimetype"
printf '<?xml version="1.0"?><office:document-signatures />' > "$work/odf/META-INF/documentsignatures.xml"
( cd "$work/odf" && zip -qr - . ) > signed.odt

echo "Generados: signed.pdf unsigned.pdf signed.csig signed.xsig signed.odt"
