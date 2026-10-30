# CH-01 — Respuesta documentada a un cambio de ejemplo

**Origen:** solicitud simulada para la defensa, no una petición real del docente ni del usuario.

**Solicitud:** “Si el usuario deniega notificaciones, conservar el estado obtenido del HIS y explicar que no se envió la notificación”.

**Antes conceptual:** un único resultado global NOTIFICATION_UNAVAILABLE puede llevar a la UI a esconder la consulta o presentarla como fallida.

**Respuesta propuesta y demostrada:** mostrar consulta/frescura/notificación por separado. Con dato reconocido y permiso denegado: consulta confirmada, PENDIENTE actual y Denegada · No enviada. No solicitar permisos adicionales ni repetir consulta automáticamente.

**Impacto:** solo presentación del flujo de consulta y contrato de resultado compuesto; RF-05 y RNF-04. No cambia el estado de la orden, las reglas HIS ni la autorización. La interfaz de notificación debe seguir separada del gateway. En la demo se selecciona el permiso simulado; no se gestiona el permiso del SO.

**Aceptación:** dado DEMO-1 y HIS disponible, al denegar permiso y consultar se conserva el dato actual, se muestra notificación no enviada y no aparece fallo del HIS. Si además la red cae, el dato previo se marca obsoleto y la notificación ni se intenta.

**Evidencia:** verificacion/run.cjs cubre ambos escenarios en cuatro anchos. Capturas permiso-denegado y obsoleto en evidencia/. Estado: demostrado en la simulación web; integración real pendiente. Si llega un cambio real, registrar su origen y sustituir/ampliar esta ficha sin atribuir aprobación inexistente.