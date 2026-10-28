# Matriz decisión → evidencia

| Decisión | RF/RNF | Actor / componente | Evidencia y límite |
|---|---|---|---|
| Consulta HIS por puerto | RF01/02 RNF05 | Operador / Evaluate | Semana 3 tests, gateway doble; HTTP pendiente. |
| Estado reconocido antes de notificar | RF03/04 RNF04 | Evaluate / Evaluation | Semana 3 rechaza estado desconocido y ausencia. |
| Capacidad distinta de consulta | RF05 RNF02 | SO / Notifier | CLI devuelve UNAVAILABLE; cambio conserva RECORDED. |
| Privacidad del mensaje | RF07 RNF03 | Notifier | Aserción sin DEMO-1, sin datos clínicos. |
| ISP / contratos pequeños | RNF01 | Gateway, Notifier, Repository | Model.php y UML; exportación no implementada en semana 3. |
| Persistencia íntegra de evidencia | RNF04/05 | Repository PDO | Trigger falla segundo INSERT, rollback verificado en semana 3. |
| Tenant se autoriza en servidor | RNF03/06 | API futura / HIS | OpenAPI y diseño semana 5; no prueba runtime de identidad. |
| Refresco de lectura sin cola clínica | RNF02/06 | Gateway futuro | Diseño semana 5; red/timeout pendientes de integración. |
| Revisar permiso cada ejecución | C06-01 / RF05 | ConsentNotifier | 12 aserciones, permiso retirado no delega. Doble local, no SO real. |
| No concluir viabilidad multiplataforma | RNF06 | Evaluador/revisor | Registro de limitaciones y tipos CONTRACT_*; faltan pruebas por destino. |