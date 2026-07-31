---
id: SDD-0001
titulo: "Firma PAdES desde la biblioteca"
estado: Implementado
fecha: 2026-07-31
adrs: [ADR-0001, ADR-0002]
asistencia_ia:
  herramienta: "Codex"
  modelo: "GPT-5"
---

# SDD-0001: Firma PAdES desde la biblioteca

## Resumen

Añadir a la vista de lista de medios un flujo autenticado para descargar un PDF,
firmarlo localmente y guardar el resultado.

## Objetivos

- Acción visible solo en PDF.
- Rutas REST con capacidades.
- Firma mediante el paquete genérico.
- Original inmutable.
- Resultado relacionado y auditable.
- Demo segura y explícitamente simulada.

## Fuera de alcance

Validación criptográfica, almacenamiento privado, cofirma, firmas visibles,
servicios intermedios PHP y procedimientos administrativos concretos.

## Diseño

`Media_Page` entrega configuración al bundle. `Rest_Controller` comprueba
capacidades y separa HTTP de `Document_Service` y `Signature_Repository`.
AutoFirma Client contiene la adaptación del navegador.

## Seguridad y privacidad

Nonce REST, capacidades, Base64 estricto, PDF, límites y no sobrescritura. La
documentación advierte que uploads puede ser público y que guardar no valida.

## Pruebas

PHPUnit sobre transformaciones, Vitest sobre nombres, PHPCS, build y futura
cobertura de integración WordPress.

## Despliegue

ZIP por tag. Playground usa el build versionado de `main` y activa únicamente el
modo simulado.

## Riesgos

Consumo de memoria por Base64, falsa percepción de validación y exposición
pública de uploads. Los límites y avisos reducen, pero no eliminan, esos riesgos.
