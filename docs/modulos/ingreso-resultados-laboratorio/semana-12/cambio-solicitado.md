# CH-01 — Respuesta a solicitud simulada de cambio

Origen: ejemplo propuesto para la defensa; no solicitud real del docente/usuario.

Solicitud: retirar selección manual de estado en captura/corrección y asegurar que guardar deja el resultado pendiente.

Antes: la propuesta escrita de semana 11, Pantalla C, incluía selector pendiente/corregido/rechazado, que podía confundirse con una decisión clínica y con la identidad de la versión.

Después: no existe selector de estado del resultado. Confirmación informa Pendiente de validación; captura v1 y corrección v2 se crean pendientes. “Corregido” describe la operación y el vínculo del historial, no una autorización para validar o rechazar. Un cambio externo a validado bloquea edición.

Impacto: UI, mensaje de confirmación y pruebas de estado, trazados a RF-04/05. No agrega decisiones bioquímicas ni cambia el backend previo. El selector “escenario de demostración” solo inyecta fallos externos y no es una selección del estado a guardar.

Aceptación: captura y corrección exitosas muestran pendiente, el usuario no puede elegir validado/rechazado y un validado externo no se corrige. Verificacion/run.cjs lo recorre en cuatro anchos y genera capturas. Estado: demostrado en simulación web; no aprobación de integración clínica.