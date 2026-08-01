# Servidor intermedio

Es lo que permite firmar desde el móvil.

## Por qué hace falta

En un ordenador, AutoScript habla con AutoFirma por un WebSocket local. En un
móvil ese WebSocket no existe, así que el protocolo usa otro camino: la página
deja la petición en un servicio HTTP, la aplicación de AutoFirma la recoge,
firma, y devuelve el resultado por el mismo sitio.

Sin ese servicio, AutoScript prueba unas direcciones por omisión que en un
WordPress no existen, el sondeo falla y declara el trámite incompatible. Es la
ventana sin salida que aparece al intentar firmar desde Android.

El plugin publica esos dos servicios. **No hay nada que configurar.**

## Qué cambia en el escritorio

Nada. AutoScript elige el modo por plataforma: en un ordenador con WebSocket ni
mira estos servicios. Activarlos no puede alterar lo que ya funciona.

## Requisito: enlaces permanentes

El sitio necesita tener enlaces permanentes bonitos (cualquier ajuste distinto
de «Simple» en `Ajustes → Enlaces permanentes`).

El motivo es concreto: AutoScript comprueba que los servicios responden
añadiendo `?op=check` al final de la dirección, sin mirar si ya había una cadena
de consulta. Con enlaces simples la API REST vive en `?rest_route=…`, saldrían
dos interrogantes y la comprobación fallaría.

Cuando eso ocurre el plugin **no anuncia** los servicios, porque ofrecer uno
roto sería peor: AutoScript daría el trámite por incompatible en vez de
limitarse a no encontrarlo.

## Cómo funciona

1. Al pulsar firmar, el navegador pide una sesión. La petición va autenticada y
   exige permiso para subir ficheros.
2. WordPress emite un token opaco con caducidad y devuelve dos direcciones que
   lo incluyen:

   ```
   /wp-json/wp-autofirma/v1/intermediate/<token>/storage
   /wp-json/wp-autofirma/v1/intermediate/<token>/retrieve
   ```

3. Esas direcciones se le pasan a AutoScript. En móvil las usa; en escritorio no.

## Seguridad

Las dos rutas son públicas, y no es un descuido: quien las llama no es solo el
navegador, sino también AutoFirma, que no lleva la sesión de WordPress. No
pueden exigir cookie.

Lo que las protege:

- **Un token opaco en la ruta**, de 32 caracteres, emitido solo a quien ya ha
  entrado y tiene `upload_files`. Sin sesión válida no se acepta ni se entrega
  nada.
- **Consumo único**: cada dato se entrega una sola vez y desaparece.
- **Caducidad**: la sesión dura 15 minutos y cada dato, 5.
- **Aislamiento**: las claves se derivan del token, así que dos personas
  firmando a la vez nunca se cruzan los documentos.
- **Tamaño limitado**: 20 MB por dato.

El contenido que viaja es opaco. AutoScript y AutoFirma lo cifran entre ellos;
WordPress solo lo guarda unos minutos y no puede leerlo.

La única llamada que se atiende sin sesión es `op=check`, el sondeo de
disponibilidad, que ni recibe ni devuelve datos y tiene que responder antes de
que exista sesión alguna.

## Dónde se guardan los datos

En transients. No hay que elegir ni proteger ningún directorio, funciona en
cualquier alojamiento y, si el sitio tiene varios nodos con la base de datos o
la caché compartidas, funciona también ahí.

WordPress retira solo lo caducado.

## Filtros

```php
// Retirar el servicio por completo: no se registran las rutas ni se anuncia.
add_filter( 'wp_autofirma_enable_intermediate_server', '__return_false' );

// Cuánto dura una sesión de firma. Cubre desde que se pulsa firmar hasta que
// AutoFirma devuelve el resultado, incluido lo que tarde quien firma en
// desbloquear el teléfono y elegir certificado.
add_filter(
    'wp_autofirma_intermediate_session_lifetime',
    static function () {
        return 30 * MINUTE_IN_SECONDS;
    }
);

// Cuánto espera un dato a ser recogido.
add_filter(
    'wp_autofirma_intermediate_payload_lifetime',
    static function () {
        return 10 * MINUTE_IN_SECONDS;
    }
);

// Tamaño máximo de un dato en tránsito.
add_filter(
    'wp_autofirma_intermediate_max_payload',
    static function () {
        return 40 * MB_IN_BYTES;
    }
);
```

## Otro almacenamiento

`Transient_Store` implementa `StoreInterface` de la librería. Para Redis u otro
sistema, basta con otra implementación cuyo `consume()` sea atómico: ningún
resultado puede entregarse dos veces.

## Comprobar que responde

```bash
curl "https://tu-sitio/wp-json/wp-autofirma/v1/intermediate/<token>/storage?op=check"
```

Debe contestar `OK` con código 200. Es exactamente lo que hace AutoScript antes
de intentar nada.
