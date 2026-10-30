# Backlog priorizado de correcciones

Prioridad = impacto en medicamentos, presentaciones, vigencia, búsqueda y unicidad del catálogo,
y luego esfuerzo. Cada ítem tiene un criterio que se puede comprobar.

| Prio. | ID | Hallazgo | Corrección propuesta | Criterio verificable | Esfuerzo |
|---|---|---|---|---|---|
| 1 | B-01 | H-03 | Confirmación antes de eliminar: escribir el código y botón «Eliminar definitivamente» (W6). | Sin el código escrito, el POST responde 422 y el registro sigue (prueba de controlador). | Bajo |
| 2 | B-02 | H-05 | `FichaMedicamento::validar` valida el formato del valor aunque falte la unidad. | Con `concentracion_valor=0` y sin unidad, el 422 trae errores en **ambos** campos (prueba `[DEFECTO D-01]` en verde). | Bajo |
| 3 | B-03 | H-02 | Tras un 422: foco en el resumen (`tabindex="-1"`) y enlaces `#campo` a cada error. | Playwright: `activeElement` = resumen; número de enlaces = número de errores. | Bajo |
| 4 | B-04 | H-01 | Foco `#1d4ed8` de 3 px con separación de 2 px. | `calcular-contraste.php`: 6,70:1 (≥ 3:1). | Bajo |
| 5 | B-05 | H-06 | Búsqueda sin acentos con `unaccent` en PostgreSQL y la misma normalización en `CriterioBusqueda::coincide`. | Contrato PDO = InMemory: «capsula» encuentra MED-000102 en ambos. | Medio |
| 6 | B-06 | H-09 | Si la baja sería el mismo día del alta, mostrar «Este registro empezó hoy: use Eliminar registro erróneo» con el botón. | Prueba de controlador: el 422 incluye la acción sugerida. | Bajo |
| 7 | B-07 | H-04 | Marcar obligatorios con «*», leyenda y `aria-required="true"`. | Playwright: 7 campos con `aria-required`; leyenda visible. | Bajo |
| 8 | B-08 | H-10 | Agregar `creado_por` (opcional) a la vista y a la API v1 para el auditor. | Prueba de API: el historial trae `creado_por` en cada versión. | Bajo |
| 9 | B-09 | H-07 | Etiquetas legibles («Solución inyectable») manteniendo el código como valor. | Los selectores muestran etiquetas; el valor enviado sigue siendo el código. | Bajo |
| 10 | B-10 | H-08 | Enlace «Saltar al contenido» visible al recibir el foco. | Primer Tab enfoca el enlace y Enter mueve el foco a `#contenido`. | Bajo |
| 11 | B-11 | H-11 | En móvil, tarjetas en lugar de tabla (semana 10). | A 375 px no hay desplazamiento horizontal ni en la página ni en la lista. | Medio |

## Dónde se atienden

- B-01, B-03, B-04, B-06, B-07, B-09, B-10 y B-11: en el prototipo navegable (semana 11).
- B-02: registrado como defecto D-01 con su prueba en la semana 15.
- B-05 y B-08: cambios compatibles del Micro-HIS/API; se proponen en la revisión técnica (semana 13).
