# Issue listo para crear en GitHub

**Título:** `ASII-22 — Gobernanza y auditoría de movimientos del sistema`
**Etiquetas:** `asii`, `module`
**Plantilla:** `.github/ISSUE_TEMPLATE/module_task.md`

Para crearlo con GitHub CLI, desde la raíz del repositorio:

```bash
# Publica solo el cuerpo (todo lo que está debajo de la línea ---)
sed '1,/^---$/d' docs/modulos/gobernanza-auditoria/semana-05/plan/issue-asii-22-auditoria.md | \
  gh issue create --title "ASII-22 — Gobernanza y auditoría de movimientos del sistema" \
  --label asii --label module --body-file -
```

---

## Datos del módulo

- **Número:** ASII-22
- **Módulo:** Gobernanza y auditoría de movimientos del sistema
- **Estudiante:** Boris Alexander Quiroa Orellana
- **GitHub:** @bquiroao
- **Rama esperada:** `feature/asii-22-auditoria-bquiroao`
- **Worktree esperado:** `../shi-asii-22-auditoria`

## Objetivo

Integrar al HIS la bitácora de movimientos diseñada en el Micro-HIS Gobernanza y auditoría (`docs/modulos/gobernanza-auditoria/micro-his-auditoria`):

- `audit_logs` de solo inserción en la base central, con sello encadenado por hospital;
- registro idempotente por `event_id`, alimentado por los módulos emisores sin llamadas síncronas desde la atención;
- consulta para el super administrador y los auditores, con rango de fechas obligatorio y filtros por usuario, módulo, acción y resultado, siempre dentro del alcance;
- meta-auditoría de cada consulta;
- verificación de integridad y anclas externas.

## Alcance mínimo

- [x] Análisis de actores, procesos y casos de uso (semana 1).
- [x] RF/RNF y criterios de aceptación (semana 2).
- [x] Diseño arquitectónico, capas y componentes (semanas 3 y 4).
- [x] Contrato API (semana 5: `micro-his-auditoria/api/openapi.yaml` 1.0.0, validado con Redocly).
- [ ] UI mínima funcional si aplica (consulta, detalle y verificación en Vue, solo para super administrador y auditores).
- [ ] Validaciones, roles, permisos y tenant **en la app Laravel**.
- [ ] Pruebas y evidencia contra el PostgreSQL del proyecto (`CON-PDO-*`, `INT-*`).
- [ ] Documentación y evidencia de integración.

## Tareas de integración (una rama y un PR por tarea)

- [ ] **T1 — Esquema CENTRAL:** una migración nueva (sin editar las existentes) con `database/integracion-central.sql`: `audit_logs`, trigger, únicos, índices y la vista `auditoria_bandeja_pendiente`. Probar con el validador de la semana 4. → `feature/asii-22-auditoria-esquema-bquiroao`
- [ ] **T2 — Núcleo:** portar Dominio + Aplicación a `app/`; `PdoBitacora` sobre la conexión `central`; pasar la suite de contrato (`CON-*`) contra el esquema real. → `feature/asii-22-auditoria-bquiroao`
- [ ] **T3 — API:** controladores Laravel sobre los mismos casos de uso; rutas `/api/v1/auditoria/*` en `routes/api.php` con `jwt.auth` y `tenant`; pruebas API-01..03 contra las rutas reales; `X-Request-ID` y registro de acceso. → `feature/asii-22-auditoria-api-bquiroao`
- [ ] **T4 — Ingesta:** consumidor de `central_inbox_messages` (`event_type = auditoria.movimiento`, `FOR UPDATE SKIP LOCKED`); el hospital se toma del **nodo de origen**; `ClienteAuditoria` + `BandejaLocal` disponibles para los emisores que corran aparte. → `feature/asii-22-auditoria-ingesta-bquiroao`
- [ ] **T5 — Seguridad y operación:** rol `app_auditoria` (solo `INSERT` y `SELECT`), publicación periódica del ancla de cada cadena, matriz OWASP API Top 10. → `test/asii-22-auditoria-seguridad-bquiroao`

## Dependencias con otros módulos

- **ASII-02 RBAC:** decide `auditoria.registrar | consultar | consultar_red | verificar` (puerto `PoliticaAcceso`). Además, RBAC es emisor: `ROL_ASIGNADO`, `ROL_REVOCADO`, `ACCESO_DENEGADO`.
- **ASII-01 Usuarios, tenants y autenticación:** JWT (`JwtAuth`), `X-Tenant-ID` (`TenantMiddleware`) y el rol de super administrador.
- **Sincronización central:** dueña de `sync_nodes` y `central_inbox_messages`. Auditoría solo **consume** los mensajes de su tipo.
- **ASII-03 Pacientes, Citas y los demás emisores:** acordar el catálogo de acciones y la cuenta de servicio de cada uno.
- **ASII-27 Reportes operativos exportables:** si exporta la bitácora, debe hacerlo por la API (la exportación también se audita).
- **ASII-30 API Gateway:** TLS, rate limiting y propagación de `X-Request-ID`.

## Actividades semanales relacionadas

- [x] Semana 1: casos de uso y narrativa.
- [x] Semana 2: RF/RNF, criterios de aceptación y ejemplo SOLID.
- [x] Semana 3: vista arquitectónica.
- [x] Semana 4: diseño por capas.
- [x] Semana 5: contrato API y plan de integración.
- [ ] Semana 16: integración y primera entrega funcional (T1 a T4).
- [ ] Semana 17: seguridad y despliegue final (T5).

## Definition of Done

- [ ] PR abierto hacia `develop` por cada tarea, con `Closes`/`Refs` a este issue.
- [ ] Evidencia de análisis y diseño adjunta (enlaces a `docs/modulos/gobernanza-auditoria/`).
- [ ] Evidencia de implementación adjunta.
- [ ] Evidencia de pruebas adjunta (incluye `CON-PDO-*` e `INT-*` en verde).
- [ ] Riesgos documentados.
- [ ] Revisión solicitada.
