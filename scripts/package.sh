#!/usr/bin/env bash

set -euo pipefail

version="$(node -p "require('./package.json').version")"
header_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' wp-autofirma.php)"

if [[ "${version}" != "${header_version}" ]]; then
  echo "package.json y wp-autofirma.php tienen versiones distintas." >&2
  exit 1
fi

mkdir -p dist
staging="$(mktemp -d)"
trap 'rm -rf "${staging}"' EXIT

# Se parte de `git archive` para que el paquete contenga exactamente lo
# commiteado. Las exclusiones viven en .gitattributes como `export-ignore`.
git archive --format=tar --prefix=wp-autofirma/ HEAD | tar -x -C "${staging}"

# El plugin depende en ejecución de erseco/autofirma-intermediate-server, y
# quien instala un plugin de WordPress no ejecuta Composer: las dependencias
# tienen que viajar dentro del ZIP. Los manifiestos se sacan de HEAD y no del
# árbol de trabajo, porque están marcados `export-ignore` y el paquete debe
# corresponder al commit, no a lo que haya sin commitear.
git show HEAD:composer.json > "${staging}/wp-autofirma/composer.json"
git show HEAD:composer.lock > "${staging}/wp-autofirma/composer.lock"

composer install \
  --working-dir="${staging}/wp-autofirma" \
  --no-dev \
  --no-interaction \
  --no-progress \
  --optimize-autoloader \
  --quiet

# Los manifiestos ya han cumplido su función: lo que se distribuye es `vendor/`.
rm -f "${staging}/wp-autofirma/composer.json" "${staging}/wp-autofirma/composer.lock"

rm -f "dist/wp-autofirma-${version}.zip"
( cd "${staging}" && zip -qr "wp-autofirma-${version}.zip" wp-autofirma )
mv "${staging}/wp-autofirma-${version}.zip" "dist/wp-autofirma-${version}.zip"

printf 'Paquete creado: dist/wp-autofirma-%s.zip\n' "${version}"
