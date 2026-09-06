---
id: ADR-0006
titulo: "Firma local por lotes"
estado: Propuesto
fecha: 2026-09-06
---

# ADR-0006: Firma local por lotes

## Contexto y decisión

Se necesita firmar varios PDF con la misma marca visible en una operación.
Usar las casillas y acciones múltiples nativas de la lista de medios. Firmar el lote mediante `autofirma-client`, con un sello común. Reutilizar las rutas REST individuales para lectura y guardado, sin añadir almacenamiento de lotes ni servicios trifásicos.

## Consecuencias

Se conserva la firma individual. Los fallos parciales no anulan los documentos
correctos: se usa `stopOnError: false`. Cada resultado se asocia por ID, nunca
por su posición. Los lotes viajan completos en memoria y deben respetar los
límites de tamaño y tiempo del transporte. No se añade servidor trifásico.

## Evidencia y validación

- [AutoScript fijado](https://github.com/ctt-gob-es/clienteafirma/blob/b4fe147c322932ebdd11e25db3134af934e0e832/afirma-ui-miniapplet-deploy/src/main/webapp/js/autoscript.js).
- [Formato local de respuesta](https://github.com/ctt-gob-es/clienteafirma/blob/b4fe147c322932ebdd11e25db3134af934e0e832/afirma-simple/src/main/java/es/gob/afirma/standalone/protocol/JSONBatchManager.java).
- Pruebas de errores parciales, cancelaciones y correspondencia por ID.
- SDD-0005 describe la integración y sus comprobaciones.
