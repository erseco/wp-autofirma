# Uso

1. Abre Medios → Biblioteca.
2. Activa la vista de lista.
3. Busca un PDF accesible para la cuenta actual.
4. Pulsa «Firmar con AutoFirma».
5. Revisa el documento y pulsa «Firmar PDF».
6. Completa la selección del certificado en AutoFirma.

El original permanece intacto. El resultado se crea con el sufijo `-firmado` y
metadatos que relacionan ambos adjuntos.

La primera versión solo admite PAdES sobre PDF y limita el original a 10 MiB y
el resultado a 15 MiB. Ambos límites tienen filtros.

## Sello visible en el PDF

Una firma PAdES es válida aunque no se vea. El sello visible es una capa que
AutoFirma dibuja encima del documento para que, al abrirlo, se lea quién firmó y
cuándo.

En la pantalla de firma hay un bloque «Sello visible» con una casilla. Mientras
esté sin marcar se firma sin sello, que es el comportamiento anterior; al
marcarla se habilitan los campos, ya rellenos con **el mismo texto que AutoFirma
usa por omisión**, de modo que el sello sale igual que firmando con la
aplicación de escritorio y puedes probarlo sin escribir nada.

### El texto

Admite variables que AutoFirma sustituye en el momento de firmar, tomadas de su
ayuda oficial:

| Variable               | Se sustituye por                       |
| ---------------------- | -------------------------------------- |
| `$$SUBJECTCN$$`        | Nombre del titular del certificado     |
| `$$ISSUERCN$$`         | Autoridad que lo emitió                |
| `$$CERTSERIAL$$`       | Número de serie del certificado        |
| `$$SIGNDATE=PATTERN$$` | Fecha de la firma, con formato de Java |
| `$$ORGANIZATION$$`     | Organización del certificado           |
| `$$OU$$`               | Unidad organizativa                    |
| `$$SURNAME$$`          | Apellidos                              |
| `$$TITLE$$`            | Cargo                                  |
| `$$REASON$$`           | Motivo de la firma                     |
| `$$LOCATION$$`         | Lugar                                  |
| `$$CONTACT$$`          | Contacto                               |

En `$$SIGNDATE=PATTERN$$`, `PATTERN` es un formato de fecha de Java:
`$$SIGNDATE=dd/MM/yyyy HH:mm$$` produce «02/08/2026 19:30».

### Las coordenadas

El sello necesita un rectángulo donde dibujarse, y **sin las cuatro coordenadas
no se dibuja nada**: por eso el plugin avisa en lugar de firmar sin sello.

Van en puntos PDF desde la **esquina inferior izquierda** de la página: 72
puntos equivalen a una pulgada y un A4 mide 595 × 842. Los valores iniciales
—de (40, 40) a (260, 110)— dejan el sello abajo a la izquierda de la primera
página.

Para cambiar los valores de partida de todo el sitio:

```php
add_filter(
    'wp_autofirma_visible_signature_defaults',
    static function ( $defaults ) {
        $defaults['text'] = 'Registrado por $$SUBJECTCN$$';
        $defaults['page'] = 1;

        return $defaults;
    }
);
```
