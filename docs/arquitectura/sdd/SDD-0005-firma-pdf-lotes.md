---
id: SDD-0005
titulo: "Firma de varios PDF con sello común"
estado: Implementado
fecha: 2026-09-06
---

# SDD-0005: Firma de varios PDF con sello común

## Alcance

Usar las casillas y acciones múltiples nativas de la lista de medios. Firmar el lote mediante `autofirma-client`, con un sello común. Reutilizar las rutas REST individuales para lectura y guardado, sin añadir almacenamiento de lotes ni servicios trifásicos.

## Flujo y errores

1. Validar selección y preparar datos antes de abrir AutoFirma.
2. Configurar una sola vez formato PAdES, texto, página y rectángulo.
3. Enviar documentos con identificadores únicos en una operación local.
4. Procesar resultados por ID. Mostrar los errores individuales y conservar
   las firmas correctas. Un éxito nativo no implica persistencia en servidor.

No ejecutar firmas en paralelo. El modo local vuelve a desactivarse incluso
tras cancelaciones. En WordPress, comprobar permisos por documento, conservar
los originales y permitir descargar cada PDF firmado aunque falle su guardado.
La pantalla no vuelve a firmar automáticamente un lote ya procesado.

## Pruebas

`make check` comprueba PHP, JavaScript y build. `make test-integration` verifica los hooks múltiples, la selección y los permisos con WordPress real. Las pruebas JavaScript comprueban un solo lote, sello común, respuestas desordenadas y conservación de descargas ante fallos de guardado.

La comprobación manual final requiere AutoFirma: seleccionar dos PDF, activar
sello común, firmar y abrir ambos resultados para comprobar página y posición.
Repetir con un PDF inválido y con cancelación. No se afirma compatibilidad móvil
sin probarla en el dispositivo de destino.
