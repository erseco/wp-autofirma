#!/usr/bin/env bash

set -euo pipefail

version="$(node -p "require('./package.json').version")"
header_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' wp-autofirma.php)"

if [[ "${version}" != "${header_version}" ]]; then
  echo "package.json y wp-autofirma.php tienen versiones distintas." >&2
  exit 1
fi

mkdir -p dist
git archive \
  --format=zip \
  --prefix=wp-autofirma/ \
  --output="dist/wp-autofirma-${version}.zip" \
  HEAD

printf 'Paquete creado: dist/wp-autofirma-%s.zip\n' "${version}"
