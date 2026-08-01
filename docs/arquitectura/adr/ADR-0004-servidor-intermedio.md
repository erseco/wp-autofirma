---
id: ADR-0004
titulo: "Publicar el servidor intermedio sobre REST y transients"
estado: Aceptado
fecha: 2026-08-01
relacionados:
  issues: []
  prs: []
  sdds: [SDD-0003]
  adrs: [ADR-0002]
sustituye: []
sustituido_por: []
asistencia_ia:
  herramienta: "Claude Code"
  modelo: "Opus 5"
---

# ADR-0004: Publicar el servidor intermedio sobre REST y transients

## Contexto

AutoScript habla con AutoFirma por un WebSocket local. En móvil ese WebSocket no
existe, así que el protocolo usa otra vía: la página deja la petición en un
servicio HTTP, la aplicación la recoge, firma, y devuelve el resultado por el
mismo camino.

Sin ese servicio, AutoScript recurre a unas direcciones por omisión
(`/afirma-signature-storage/StorageService`) que en un WordPress no existen. El
sondeo falla y AutoScript declara el trámite incompatible: es la ventana sin
salida que aparecía al intentar firmar desde Android.

## Problema

Hay que ofrecer los dos servicios sin convertirlos en un buzón anónimo abierto.
No pueden depender de la cookie de sesión, porque quien llama no es solo el
navegador: también AutoFirma, que no arrastra sesión de WordPress.

## Opciones consideradas

1. **Implementar el protocolo en el plugin.** Repetiría en WordPress lo que ya
   existe probado en `erseco/autofirma-intermediate-server`, contra la
   separación que fijó el ADR-0002.
2. **`FilesystemStore` de la librería.** Exige elegir y proteger un directorio
   fuera de la raíz pública, algo que no se puede garantizar en un alojamiento
   cualquiera, y no sirve si el sitio tiene varios nodos.
3. **Transients de WordPress.** Sin configuración, funcionan en cualquier
   alojamiento y acompañan al sitio si comparte base de datos o caché.

Para el transporte se consideró una regla de reescritura propia frente a la API
REST. Ambas necesitan enlaces permanentes bonitos, así que se eligió REST por
ser lo nativo y estar ya en uso en el plugin.

## Evidencias

AutoScript decide el modo por plataforma, no por si hay servlets configurados
(`build/autoscript.js`): en escritorio con WebSocket ni los consulta. Configurar
los servicios no puede, por tanto, alterar el flujo que ya funciona.

El sondeo que lanza es `GET <dirección>?op=check` y solo mira el código HTTP. El
depósito es un `POST` con `application/x-www-form-urlencoded` y cuerpo
`op=put&v=1_0&id=…&dat=…`.

Comprobado sobre WordPress real: sondeo, depósito, recogida y segunda recogida
—que ya no entrega nada—, más el rechazo con token inventado.

## Decisión

Se consume la librería con Composer y se expone sobre la API REST en
`wp-autofirma/v1/intermediate/<token>/{storage,retrieve}`, con un
`StoreInterface` propio sobre transients.

Las rutas son públicas porque no pueden exigir cookie. Lo que autoriza es un
token opaco de 32 caracteres, emitido solo a quien ya ha entrado y puede subir
ficheros, con vida limitada. Sin sesión válida no se acepta ni se entrega nada;
la única excepción es `op=check`, que ni recibe ni devuelve datos y ha de
responder antes de que exista sesión alguna.

Las respuestas se sirven en texto plano mediante `rest_pre_serve_request`: el
dato recuperado es binario cifrado y envolverlo en JSON lo corrompería.

Si la API REST del sitio vive tras una cadena de consulta —enlaces permanentes
simples—, el servicio no se anuncia. AutoScript concatena `?op=check` sin
comprobar nada, y una segunda interrogación rompería el sondeo: es preferible no
ofrecer el servicio que ofrecer uno roto.

Como el plugin pasa a tener una dependencia de ejecución y quien instala un
plugin no ejecuta Composer, el empaquetado instala `vendor/` dentro del ZIP.

## Consecuencias

- La firma desde móvil deja de ser imposible.
- En escritorio no cambia nada: AutoScript no usa estos servicios ahí.
- El plugin gana una dependencia de ejecución y el ZIP, un directorio `vendor/`.
- El sitio necesita enlaces permanentes bonitos para ofrecer el servicio.
- El consumo único depende de que `delete_transient()` solo acierte una vez. Con
  una caché de objetos persistente esa atomicidad la decide el backend.
- Quien no quiera exponer las rutas puede retirarlas con un filtro.

## Validación

`tests/php/TransientStoreTest.php` cubre el consumo único, el aislamiento entre
sesiones, la longitud de las claves y el protocolo de principio a fin.
Verificación manual por HTTP en `wp-env`, incluido el rechazo sin sesión.

## Referencias

- `includes/class-intermediate-controller.php`
- `includes/class-transient-store.php`
- [SDD-0003](../sdd/SDD-0003-servidor-intermedio.md)
- [erseco/autofirma-intermediate-server](https://github.com/erseco/autofirma-intermediate-server)
