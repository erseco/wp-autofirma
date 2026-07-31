---
id: ADR-0001
titulo: "Conservar el original y crear otro adjunto"
estado: Aceptado
fecha: 2026-07-31
relacionados:
  issues: []
  prs: []
  sdds: [SDD-0001]
  adrs: []
sustituye: []
sustituido_por: []
asistencia_ia:
  herramienta: "Codex"
  modelo: "GPT-5"
---

# ADR-0001: Conservar el original y crear otro adjunto

## Contexto

Una firma genera una nueva representación del documento. Sobrescribir el
original dificultaría auditoría, repetición y comparación.

## Decisión

Cada resultado se guarda con `wp_upload_bits()` y `wp_insert_attachment()` como
un adjunto distinto. El post meta enlaza resultado, original, cuenta, fecha y
huella.

## Consecuencias

- Se conserva evidencia del original.
- Pueden existir varias firmas del mismo documento.
- Aumenta el uso de almacenamiento.
- La metadata informa del flujo, pero no demuestra validez criptográfica.

## Validación

Pruebas de nombres y datos; futuras pruebas de integración REST y Media Library.

## Referencias

- `includes/class-signature-repository.php`
- `includes/class-signature-data.php`
