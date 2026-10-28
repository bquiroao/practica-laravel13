# CH-01 — No confirmar cierre ante fallo de revocación

Origen: solicitud simulada para defensa; no atribuida al usuario/docente.

Solicitud: si el almacén de revocación no confirma escritura, mantener estado de sesión no cerrada y ofrecer reintento explícito.

Antes conceptual: ocultar la vista o limpiar un token visual puede aparentar logout aunque la sesión continúe siendo válida.

Después demostrado: el fallo muestra “No se confirmó la revocación”, conserva estado activo y una comprobación H1 todavía permite acceso. Tras quitar el fallo, cerrar confirma revocación y comprobar devuelve rechazo. No se anuncia éxito anticipado.

Impacto: UX de cierre y contrato de resultado del almacén; RF-08/10 y RNF-06. No agrega privilegios ni garantiza revocación JWT persistente. El servidor real debe comprobar su almacén y el cliente no puede asumir cierre por borrar estado local.

Aceptación: falla mantiene activa sin mensaje de cierre; éxito posterior muestra revocada y deniega acceso. Verificacion/run.cjs y capturas fallo-revocacion/revocada muestran el recorrido en cuatro anchos. Estado: demostrado solo en simulación de memoria; integración real pendiente.