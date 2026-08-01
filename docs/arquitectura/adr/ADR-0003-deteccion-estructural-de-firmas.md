---
id: ADR-0003
titulo: "Detectar firmas por estructura y cachear el resultado"
estado: Aceptado
fecha: 2026-08-01
relacionados:
  issues: []
  prs: []
  sdds: [SDD-0002]
  adrs: [ADR-0001]
sustituye: []
sustituido_por: []
asistencia_ia:
  herramienta: "Claude Code"
  modelo: "Opus 5"
---

# ADR-0003: Detectar firmas por estructura y cachear el resultado

## Contexto

La biblioteca de medios no distingue un documento firmado de uno que no lo está.
Quien la usa tiene que abrir cada fichero con un visor para saberlo, y el plugin
ya sabe firmar pero no sabe reconocer lo que ha firmado.

## Problema

Averiguar si un adjunto lleva firma exige leerlo. Hacerlo en cada pintado de la
lista significaría una lectura de disco por fila y por visita. Además, «tiene
firma» y «la firma es válida» son preguntas distintas, y confundirlas sería
peligroso: quien lea un ✅ no debe creer que alguien ha comprobado nada.

## Opciones consideradas

1. **Validación criptográfica en servidor.** Verificar el resumen, construir la
   cadena de confianza y consultar revocación. Da una respuesta de verdad, pero
   exige gestionar almacenes de confianza y listas de revocación, y equivocarse
   ahí produce un «válido» falso, que es el peor resultado posible.
2. **Delegar en un servicio externo** como VALIDe o @firma. Correcto para
   validar, pero manda el documento fuera en cada consulta y ata el plugin a un
   servicio con credenciales y disponibilidad propias.
3. **Detección estructural, sin validar.** Reconocer la estructura de firma en
   los bytes y leer lo que declara. No responde si la firma es válida, pero sí
   la pregunta que se hizo: si el documento está firmado.

## Evidencias

El detector se contrastó contra 142 PDF reales: los 5 firmados se reconocieron
con su firmante y su formato, y de los 137 sin firma no hubo ni un falso
positivo. Aparecieron los dos perfiles en uso, `ETSI.CAdES.detached` —el que
emite AutoFirma— y `adbe.pkcs7.detached`.

El coste medido es de 0,03 a 3 ms por fichero, incluido uno de 10 MB. En
WordPress, la primera consulta de un adjunto tardó 3,42 ms y la siguiente, ya
cacheada, 0,09 ms.

## Decisión

Se detecta la estructura y no se valida nada. `Signature_Detector` reconoce
PAdES, CAdES, XAdES y los contenedores ODF y OOXML, y extrae el firmante del
certificado que viaja **dentro del fichero**, nunca de lo que declare el
navegador.

El resultado se guarda en post meta: `_wp_autofirma_signature` con el detalle y
`_wp_autofirma_is_signed` con una marca consultable, porque dentro de un array
serializado no se puede buscar con `meta_query`. Se calcula al subir el adjunto
y, para los que ya existían, la primera vez que alguien los mira.

Todo lo que se muestre va acompañado de una advertencia explícita de que no se
ha validado.

## Consecuencias

- La biblioteca distingue de un vistazo lo firmado de lo que no lo está.
- El coste se paga una vez por adjunto y no se repite.
- La detección puede mejorar sin invalidar lo guardado: `Signature_Detector::VERSION`
  acompaña a cada resultado y al subirla se recalcula solo.
- Un documento manipulado tras firmarlo sigue apareciendo como firmado. Es la
  limitación inherente a no validar, y por eso se advierte.
- Los ficheros que superan el límite de lectura se examinan por ventanas, así
  que en ellos el firmante puede no llegar a extraerse.

## Validación

`tests/php/SignatureDetectorTest.php` cubre cada formato, el PDF sin firmar, el
certificado suelto, los contenidos que no son firmas, el fichero inexistente y
el examen por ventanas de un fichero grande con la firma al final.

## Referencias

- `includes/class-signature-detector.php`
- `includes/class-signature-index.php`
- [SDD-0002](../sdd/SDD-0002-senalizacion-de-firmas.md)
