# Guía de defensa oral — Semana 8

## Presentación breve

«Diseñé seis wireframes para la recepción de muestras: inicio por orden e ítem, verificación de identidad y condición, confirmación de aceptación, rechazo con motivo, recepción y cadena de custodia, y recuperación de errores. La UX distingue un rechazo confirmado de una falla técnica, conserva la trazabilidad y evita duplicar muestras al reintentar. El código interno identifica al recipiente, no reemplaza la orden o al paciente, y la etiqueta muestra solo los datos mínimos por aprobar.»

## Preguntas para explicar el diseño

1. **¿Qué identifica el código interno?** Un recipiente/muestra concreta. Es distinto de paciente, orden, ítem, examen y código externo.
2. **¿Qué ocurre si no puede consultarse la orden?** Se deja sin confirmar, se conserva la entrada y se reintenta o escala; no se presenta como rechazo clínico.
3. **¿Cuándo se confirma aceptación?** Después de verificar el vínculo, revisar el recipiente y persistir la decisión, el identificador y el evento inicial requerido.
4. **¿Qué hace un rechazo controlado?** Pide motivo, conserva el registro y no muestra la muestra como apta para procesamiento.
5. **¿Cómo se evita duplicar al reintentar?** Verificando el resultado de la operación/idempotencia antes de repetir un envío incierto.
6. **¿Qué incluye la cadena de custodia?** Eventos sucesivos inmutables asociados al recipiente, actor, ubicación y hora; el detalle final requiere acuerdo institucional.
7. **¿La impresión está implementada?** No. Es una integración propuesta; un fallo de impresión se muestra como pendiente técnico, no crea otra muestra.

## Recorrido de evidencia

- [Informe UX](semana-08-ux-recepcion-muestras.md)
- [WF-01 Inicio](wireframes/01-iniciar-recepcion.svg)
- [WF-02 Verificación](wireframes/02-verificar-muestra.svg)
- [WF-03 Aceptación](wireframes/03-confirmar-aceptacion.svg)
- [WF-04 Rechazo](wireframes/04-rechazar-muestra.svg)
- [WF-05 Custodia](wireframes/05-confirmacion-custodia.svg)
- [WF-06 Recuperación](wireframes/06-errores-recuperables.svg)
