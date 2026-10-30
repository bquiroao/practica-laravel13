# Registro C06-01 — Permiso local antes de invocar notificación

Cambio planteado para el ejercicio de defensa, sin atribuir solicitud al docente/cliente: “La consulta debe seguir siendo válida si no se concede notificación; no intentar adaptador SO sin permiso”. Alcance: capacidad local del mismo flujo demo, no cambios clínicos.

Antes: el puerto Notifier delega directamente al adaptador y este decide disponibilidad. Después: ConsentNotifier aplica chequeo explícito mediante NotificationPermission y solo delega si está concedido. Doble de prueba primero muestra invocación directa; luego demuestra permiso denegado sin invocación. Consulta y evidencia conservan RECORDED con notificación UNAVAILABLE.

Decisión: decorador al componer, sin editar semanas anteriores. Alternativa de añadir permiso al gateway mezcla HIS con SO; bloquear toda consulta por permiso local impide lectura válida; flag del usuario no es permiso confiable. NotificationPermission será implementado con mecanismo del destino elegido, no se presume API NativePHP concreta.

Aceptación ejecutada: denegado → cero llamadas; concedido → una llamada; permiso retirado en ejecución siguiente → no llamada adicional; mensaje sin ID; error PDO → no efecto y cero filas parciales. Doce aserciones con SQLite real en memoria y dobles de SO/permiso.

Riesgos: existe carrera entre chequeo y envío; adaptador debe devolver resultado veraz o lanzar error que Evaluate traduce a UNKNOWN. No se garantiza entrega exactamente una vez, persistencia del resultado SO o revocación inmediata durante envío. No hay reintento automático de efecto desconocido. Rollback del cambio: retirar decorador de composición, sin migración DB ni alteración de contratos previos.

No cierre de issue institucional ni aprobación independiente: cambio demostrado en entorno local, viabilidad nativa pendiente.