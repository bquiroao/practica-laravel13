# Recorrido navegable — guion de la demostración

**Estudiante:** Joshua Eduardo García Reyes · **Prototipo:** [../semana-11/prototipo/index.html](../semana-11/prototipo/index.html) (doble clic, sin servidor). Para la vista móvil: F12 → modo dispositivo → 360 × 740. Recargar la página reinicia los datos.

El mismo recorrido lo hace [recorrido.cjs](../semana-11/herramientas/recorrido.cjs) con clics y teclado, en escritorio y en móvil: **28 comprobaciones, 0 fallas, axe 0**. Las capturas citadas son las que tomó ese script.

## Parte A · Camino feliz (2 min)

| # | Acción | Qué demuestra | Resultado esperado | Captura |
|---|---|---|---|---|
| A1 | Ingresar → **Dra. Méndez** | Roles: lo que ve sale del rol | Lista con «+ Nueva nota»; tabla en escritorio, tarjetas en móvil | [escritorio](../semana-11/capturas/escritorio/cf-02-notas.png) · [móvil](../semana-11/capturas/movil/cf-02-notas.png) |
| A2 | «+ Nueva nota» → escribir S y O | Paciente siempre visible (D-27) | Barra «Carlos R. · Expediente exp-carlos · Episodio epi-100» | [móvil](../semana-11/capturas/movil/cf-03-paso1.png) |
| A3 | Paso 3: escribir «neumon», `Tab`, `Enter` en J18.9 | Reconocer en vez de recordar (BL-06); teclado | J18.9 · Neumonía no especificada elegido | [móvil](../semana-11/capturas/movil/cf-04-buscar-cie10.png) |
| A4 | Revisar → Guardar nota | Estado borrador; foco en el aviso | «✓ Nota guardada como borrador» | [móvil](../semana-11/capturas/movil/cf-06-guardada.png) |
| A5 | Firmar nota | Confirmación que nombra la nota (D-27); fondo `inert` | «¿Firmar la nota versión 1 de Carlos R.?», foco en «Cancelar» | [escritorio](../semana-11/capturas/escritorio/cf-07-hoja-firma.png) · [móvil](../semana-11/capturas/movil/cf-07-hoja-firma.png) |
| A6 | Firmar nota → Demo → Cambiar de persona → **Ana** → Ver nota | Estados con icono + texto; enfermería solo lee | «✓ Firmada», sin botones | [móvil](../semana-11/capturas/movil/cf-09-enfermeria-lee.png) |

## Parte B · Error crítico: se pierde la respuesta (2 min)

| # | Acción | Qué demuestra | Resultado esperado | Captura |
|---|---|---|---|---|
| B1 | Recargar, Dra. Méndez, nueva nota hasta el paso 4 → Demo → «Perder la próxima respuesta» → Guardar | Resultado incierto sin reintento automático (D-29) | «No sabemos si la nota se guardó» + «Reintentar» | [móvil](../semana-11/capturas/movil/ec-01-guardado-incierto.png) |
| B2 | Reintentar | Misma `Idempotency-Key`: no se duplica | Nota guardada; el script cuenta **una** nota nueva | [móvil](../semana-11/capturas/movil/ec-02-reintento-misma-nota.png) |
| B3 | Demo → «Perder la próxima respuesta» → Firmar → Firmar nota | La firma no se repite sola (I-06) | Solo «Consultar estado de la nota» | [móvil](../semana-11/capturas/movil/ec-03-firma-incierta.png) |
| B4 | Consultar estado | El servidor manda | «✓ La firma sí llegó» | [móvil](../semana-11/capturas/movil/ec-04-firma-confirmada.png) |
| B5 | Cambiar a **Ana** y escribir `#/notas/nueva` en la dirección | Ruta protegida; 403 genérico (D-23) | «No tiene permiso para hacer esto», sin datos clínicos | [móvil](../semana-11/capturas/movil/ec-05-ruta-protegida.png) |

## Parte C · Cambio solicitado (1 min, en la terminal)

La corrección no está en el prototipo navegable (su diseño es M4 de la semana 10). El cambio B-3 se muestra con sus pruebas:

```bash
cd docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap
php tests/ejecutar.php      # CID-01..05 y API-05 en verde · Total: 124  Pasan: 123  Fallan: 0  Omitidas: 1
```

Mostrar CID-01: el reintento de una corrección con la misma clave devuelve 201 y la misma versión. Antes del cambio devolvía 409 (paso 1 del [registro](registro-cambio-solicitado.md)).

**Mapa de navegación:** [semana 11, §4](../semana-11/semana-11-prototipo-notas-soap.md#4-mapa-de-navegación). El cambio no agrega rutas de interfaz.
