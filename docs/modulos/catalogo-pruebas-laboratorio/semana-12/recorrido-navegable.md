# Guion del recorrido navegable

Prototipo: [`../semana-11/prototipo/index.html`](../semana-11/prototipo/index.html) (abrir en Chrome). Duración aproximada: 4 minutos.

| # | Acción | Qué se muestra | Decisión que se defiende |
|---|---|---|---|
| 1 | Abrir el prototipo con rol «Jefe de laboratorio» | Catálogo al 2027-01-15 con `GLU-001` y `HEM-001` | La consulta siempre es a una fecha (RN-06) |
| 2 | Cambiar la fecha a 2027-01-03 | «No hay pruebas vigentes para esa fecha.» | Estado vacío explícito (S8) |
| 3 | Volver a 2027-01-15 y abrir `GLU-001` | Preparación y muestra arriba | Jerarquía para el médico (S10) |
| 4 | Ir a «Nueva prueba» y borrar el volumen | Resumen «Hay 1 campo con error», foco en el resumen | H-02 corregido (S9) |
| 5 | Poner 1 mL y «Revisar y confirmar» | Aviso «La versión 1 quedará vigente hasta el 2027-01-31» | Confirmación antes de cerrar una vigencia (H-03, RN-05) |
| 6 | «Confirmar y guardar» | «Guardando…» y luego «Prueba registrada» | Evitar el doble envío (H-07) |
| 7 | Repetir el alta con la misma fecha desde | Error de vigencia superpuesta en el campo «Vigente desde» | Error crítico recuperable (S11) |
| 8 | Cambiar el ancho a 375 px (DevTools) | Tarjetas y botones a todo el ancho | Breakpoint de 768 px (S10) |
| 9 | Cambiar el rol a «Médico» e ir a `#nueva` | «Su rol no puede registrar pruebas.» | Permisos por rol (RN-01) |
| 10 | Pulsar Tab desde el inicio | Aparece «Saltar al contenido» y el foco es visible | H-06 corregido (S9) |
