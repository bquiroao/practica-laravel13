# CH-01 — Comprobar operación antes de reemitir

Origen: solicitud simulada para la defensa; no atribuida al usuario/docente.

Solicitud: cuando una confirmación no llega, evitar que Reintentar genere otra orden y permitir comprobar la referencia del intento.

Antes conceptual: un error genérico con reenvío inmediato puede duplicar orden/partidas si el servidor guardó y perdió la respuesta.

Después demostrado: la pantalla indica confirmación desconocida, conserva selección y bloquea nueva emisión. Comprobar operación confirma la misma orden persistida en la simulación. El listado no muestra esa orden como confirmada hasta comprobar. No se reenvía ni se agrega otra orden al recuperar.

Impacto: UX de error, contrato de operación/idempotencia y pruebas de recuperación; RF-04/06. No modifica reglas clínicas ni añade permisos. El endpoint real no existe en esta demo y debe acordarse antes de integración.

Aceptación: timeout tras guardar muestra desconocido, cero éxito anticipado, sin botón de reenvío; comprobar produce una sola orden con las mismas partidas y referencia. Verificacion/run.cjs verifica el conteo y las capturas timeout/recuperado en cuatro anchos.

Estado: demostrado en simulación web de guardado con respuesta perdida. No cubre operación realmente no guardada ni aún en curso; en integración esas respuestas deben permanecer diferenciadas antes de decidir un reintento.