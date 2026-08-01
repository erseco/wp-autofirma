---
id: SDD-0003
titulo: "Servidor intermedio para firma en móvil"
estado: Implementado
fecha: 2026-08-01
adrs: [ADR-0004]
asistencia_ia:
  herramienta: "Claude Code"
  modelo: "Opus 5"
---

# SDD-0003: Servidor intermedio para firma en móvil

## Resumen

Ofrecer desde WordPress los dos servicios HTTP que AutoScript necesita cuando no
puede hablar por WebSocket con AutoFirma, de modo que la firma desde un móvil
pueda completarse.

## Objetivos

- Completar la firma en Android e iOS.
- No alterar en nada el flujo de escritorio, que ya funciona.
- No exponer un buzón anónimo abierto.
- No exigir configuración a quien instala el plugin.
- Reutilizar `erseco/autofirma-intermediate-server` en lugar de reimplementarlo.

## Fuera de alcance

Almacenamiento distribuido propio, validación de firmas, sellos de tiempo y
soporte para sitios con enlaces permanentes simples.

## Diseño

| Componente                | Responsabilidad                                                       |
| ------------------------- | --------------------------------------------------------------------- |
| `IntermediateServer`      | El protocolo. Viene de la librería; el plugin no lo reimplementa.     |
| `Transient_Store`         | `StoreInterface` sobre transients, con las claves atadas a la sesión. |
| `Intermediate_Controller` | Rutas REST, emisión de tokens y respuesta en texto plano.             |

El flujo, cuando alguien pulsa firmar:

1. El navegador pide una sesión a `POST /intermediate-sessions`, autenticada.
2. WordPress emite un token opaco, lo guarda con caducidad y devuelve las dos
   direcciones que lo incluyen.
3. El navegador construye `AutoFirmaClient` con ellas, que llama a
   `setServlets()` de AutoScript.
4. En escritorio AutoScript las ignora y usa el WebSocket local. En móvil las
   sondea, deja allí la petición y AutoFirma la recoge, firma y devuelve el
   resultado por el mismo camino.

## Seguridad y privacidad

Las rutas de almacenamiento y recuperación son públicas por necesidad: quien
llama no es solo el navegador, sino AutoFirma, que no lleva la sesión de
WordPress. Lo que las protege:

- un token opaco de 32 caracteres en la ruta, emitido solo a quien ha entrado y
  tiene `upload_files`, con caducidad;
- sin sesión válida no se acepta ni se entrega nada, salvo `op=check`, que ni
  recibe ni devuelve datos;
- las claves del almacén se derivan del token, de modo que dos sesiones nunca
  comparten dato aunque AutoScript elija el mismo identificador;
- cada dato se entrega una sola vez y caduca solo;
- tamaño máximo, vida de la sesión y vida del dato son filtrables.

El contenido que se transporta es opaco: AutoScript y AutoFirma lo cifran entre
ellos, y WordPress solo lo guarda unos minutos sin poder leerlo.

## Pruebas

- `tests/php/TransientStoreTest.php`: consumo único, aislamiento entre sesiones,
  longitud de las claves y protocolo completo.
- Verificación manual por HTTP sobre WordPress real: sondeo de ambos servicios,
  depósito, recogida, segunda recogida sin entrega, token inventado rechazado
  con 403, token mal formado sin ruta y apertura de sesión sin sesión rechazada
  con 401.

## Riesgos

Que el sitio use enlaces permanentes simples. Entonces la API REST vive tras
`?rest_route=`, AutoScript concatenaría un segundo `?` y el sondeo fallaría. El
servicio no se anuncia en ese caso, y la limitación está documentada.

Que la caché de objetos persistente no garantice la atomicidad del borrado. El
consumo único se apoya en que `delete_transient()` solo acierte una vez.
