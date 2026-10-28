# CH-01 — Cambio práctico de ejemplo defendido

Origen: escenario simulado para evaluación; no se atribuye solicitud ni aprobación al docente/usuario.

Solicitud: si el almacén de revocación falla, evitar confirmar que la sesión terminó y permitir reintentar con seguridad.

Decisión: mantener el caso de uso de revocación separado (SRP), usar SessionRepository/SessionRevocationStore según ejemplo y confirmar cierre solo tras persistencia. No borrar evidencia de sesión para aparentar logout. No ampliar a cerrar todas las sesiones ni a administrar otro tenant.

Impacto por capa:

- Presentation: error temporal comprensible, sin mensaje “sesión cerrada” antes de confirmación.
- Application: persistir antes de responder; excepción/falso del almacén produce error explícito.
- Domain: sesión revocada no vuelve a activa; tenant/jti y expiración pertenecen al contexto de sesión verificado.
- Persistence: revocación confirmada y consultable; falla conserva resultado anterior. En integración necesita almacenamiento duradero y prueba entre procesos.
- API propuesto: DELETE /auth/sessions/current devuelve 204 solo tras confirmar revocación; 503 si no puede persistir según contrato. Reintento de sesión conocida es idempotente, sin recrear sesión.

Antes conceptual: cliente que oculta la sesión o anuncia cierre independientemente del resultado del almacén puede comunicar éxito falso. Después defendido: distinguir REVOKED de REVOCATION_UNAVAILABLE/PersistenceFailure; reintentar solo operación segura según contrato.

Aceptación: inyectar falla de revocación → no éxito; comprobar que no quedó revocada; reintentar con almacén disponible → revocada; acceso posterior rechazado. Los ensayos de semana 2 cubren revocación exitosa/fallida en memoria y semana 3 verifica su ejemplo PDO. No prueban ruta HTTP, JWT firmado ni consistencia entre procesos reales.

Registro: estado respondido a nivel diseño/contrato con evidencia del ejemplo; implementación HTTP y pruebas integradas pendientes. Autora responsable del seguimiento; revisión independiente pendiente. No se presenta como un cambio ya desplegado.