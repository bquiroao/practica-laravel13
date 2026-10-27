# Plan de issues propuesto - Semana 5

Este desglose se prepara para crear issues después de confirmar prioridad y responsables. **No son issues abiertos**. Las dependencias indican requisitos técnicos reales; el orden no autoriza a desplegar componentes sin revisión de seguridad.

| ID | Issue propuesto | Entregable y criterios de aceptación | Depende de |
|---|---|---|---|
| AUTH-51 | Aprobar contrato OpenAPI v1 de identidad y sesión | OpenAPI valida; rutas, scopes, errores y tenant-context están aprobados; cambios incompatibles requieren nueva versión. | - |
| AUTH-52 | Autorizar invitaciones por tenant | Solo administrador con `users:invite` en tenant actual puede invitar; rol fuera de su delegación devuelve 403; invitación y membresía pendiente se crean una sola vez. | AUTH-51 |
| AUTH-53 | Aceptar invitación y establecer credencial | Token aleatorio, hash-only, tenant-bound, expira y se consume una vez; contraseña solo hasheada; operación atómica. | AUTH-51, AUTH-52 |
| AUTH-54 | Implementar sesión opaca tenant-bound | Login valida tenant, estado, membresía y credencial; token aleatorio se entrega una vez y solo su SHA-256 se persiste; expiración de ocho horas. | AUTH-51, AUTH-53 |
| AUTH-55 | Integrar middleware de sesión y revocación | `GET /auth/me` y rutas protegidas verifican hash, expiry, revokedAt y tenant en cada solicitud; fallo de almacén cierra acceso con 503. | AUTH-54 |
| AUTH-56 | Entregar logout idempotente | `DELETE /auth/sessions/current` persiste revocación antes de 204; no revoca otras sesiones; reintento no reactiva sesión. | AUTH-55 |
| AUTH-57 | Añadir pruebas de contrato, aislamiento y concurrencia | Tests cubren 201/202/204, 401 genérico, 403, 404, 409, 422, 429 y 503; dos tenants; carrera de invitación; revocación inmediata. | AUTH-52, AUTH-53, AUTH-54, AUTH-55, AUTH-56 |
| AUTH-58 | Instrumentar auditoría segura y resiliencia | `requestId`, métricas de latencia/errores y alertas sin token, contraseña ni PII; límites y timeout probados; outbox sin duplicar invitación. | AUTH-52, AUTH-54, AUTH-56 |
| AUTH-59 | Evaluar criterio de extracción de servicio | Medir SLO, carga, ownership y fallas; decisión documentada mantiene monolito modular o presenta plan de extracción con presupuesto operativo y propiedad de datos. | AUTH-57, AUTH-58 |

## Cortes de entrega

1. **Contrato y threat review:** AUTH-51.
2. **Alta segura:** AUTH-52 y AUTH-53.
3. **Sesión completa:** AUTH-54 a AUTH-56.
4. **Gate de calidad:** AUTH-57 y AUTH-58 antes de piloto.
5. **Decisión arquitectónica basada en medición:** AUTH-59; no bloquea la API v1 mientras la arquitectura modular cumpla sus objetivos.

## Dependencias externas y riesgos

- El propietario institucional debe resolver qué identificador representa tenant/hospital y qué sistema entrega la invitación.
- El equipo RBAC debe especificar permisos y roles delegables; ningún valor enviado por el cliente los sustituye.
- Se requiere un proveedor de correo/notificación aprobado para entrega real; el contrato solo contempla `deliveryStatus: queued`.
- La política final de contraseña, retención de invitación y límites de rate-limit requiere aprobación de seguridad.
- El cliente web debe decidir con el equipo de seguridad entre cookie `HttpOnly` + CSRF o bearer solo en memoria antes de producción.
