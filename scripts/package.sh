#!/usr/bin/env bash

set -euo pipefail

version="$(node -p "require('./package.json').version")"
header_version="$(sed -n 's/^ \* Version:[[:space:]]*//p' wp-autofirma.php)"

if [[ "${version}" != "${header_version}" ]]; then
  echo "package.json y wp-autofirma.php tienen versiones distintas." >&2
  exit 1
fi

# El plugin depende en ejecución de erseco/autofirma-intermediate-server, y quien
# instala un plugin de WordPress no ejecuta Composer. La librería viaja por eso
# dentro de includes/vendor/, donde la deja `copy-runtime-dependencies` al
# instalar o actualizar las dependencias. Si no está, el paquete saldría sin
# servidor intermedio y sin avisar.
if [[ ! -d includes/vendor/autofirma-intermediate-server/src ]]; then
  echo "Falta includes/vendor/autofirma-intermediate-server. Ejecuta 'composer install'." >&2
  exit 1
fi

mkdir -p dist

# `--force` no vacía el ZIP anterior: la versión 3.1 de dist-archive delega en el
# binario `zip`, que AÑADE a un archivo existente. Sin este borrado, un fichero
# que una regla nueva de .distignore deje fuera seguiría dentro del paquete de
# una construcción anterior.
rm -f "dist/wp-autofirma-${version}.zip"

# Las exclusiones viven en .distignore, que `wp dist-archive` lee del árbol de
# trabajo. --plugin-dirname es lo que hace que el ZIP se extraiga como
# wp-autofirma/ aunque el fichero se llame de otra forma: sin él WordPress
# nombraría la carpeta del plugin según el nombre del ZIP.
./vendor/bin/wp dist-archive . "$(pwd)/dist/wp-autofirma-${version}.zip" \
  --plugin-dirname=wp-autofirma --force

printf 'Paquete creado: dist/wp-autofirma-%s.zip\n' "${version}"
