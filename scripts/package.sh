#!/usr/bin/env bash

set -euo pipefail

version="$(node -p "require('./package.json').version")"
header_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' wp-autofirma.php)"

if [[ "${version}" != "${header_version}" ]]; then
  echo "package.json y wp-autofirma.php tienen versiones distintas." >&2
  exit 1
fi

# Los catálogos compilados no se versionan, así que se generan aquí: `git
# archive` solo empaqueta lo commiteado y los dejaría fuera del ZIP.
make mo

rm -rf dist/wp-autofirma
mkdir -p dist/wp-autofirma
rsync -a --exclude-from=.distignore ./ dist/wp-autofirma/
( cd dist && zip -qr "wp-autofirma-${version}.zip" wp-autofirma && rm -rf wp-autofirma )

printf 'Paquete creado: dist/wp-autofirma-%s.zip\n' "${version}"
