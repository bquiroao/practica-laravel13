# Issue listo para crear en GitHub

**Título:** `ASII-02 — RBAC: roles, permisos y protección de rutas`
**Etiquetas:** `asii`, `module`
**Plantilla:** `.github/ISSUE_TEMPLATE/module_task.md`

Para crearlo con GitHub CLI, desde la raíz del repositorio:

```bash
# Publica solo el cuerpo (todo lo que está debajo de la línea ---)
sed '1,/^---$/d' docs/modulos/rbac/semana-05/plan/issue-asii-02-rbac.md | \
  gh issue create --title "ASII-02 — RBAC: roles, permisos y protección de rutas" \
  --label asii --label module --body-file -
```

---

## Datos del módulo

- **Número:** ASII-02
- **Módulo:** RBAC: roles, permisos y protección de rutas
- **Estudiante:** Boris Alexander Quiroa Orellana
- **GitHub:** @bquiroao
- **Rama esperada:** `feature/asii-02-rbac-bquiroao`
- **Worktree esperado:** `../shi-asii-02-rbac`

## Objetivo

Integrar al HIS el control de acceso basado en roles diseñado en el Micro-HIS RBAC (`docs/modulos/rbac/micro-his-rbac`):

- asignación de roles por hospital con vigencia;
- autorización de cada ruta por su permiso declarado;
- un punto de decisión (PDP) para los demás módulos;
- denegación trazable con `correlation_id` en una bitácora append-only.

## Alcance mínimo

- [x] Análisis de actores, procesos y casos de uso (semana 1).
- [x] RF/RNF y criterios de aceptación (semana 2).
- [x] Diseño arquitectónico, capas y componentes (semanas 3 y 4).
- [x] Contrato API (semana 5: `micro-his-rbac/api/openapi.yaml`).
- [ ] UI mínima funcional si aplica (asignación de roles y consulta de denegaciones).
- [ ] Validaciones, roles, permisos y tenant **en la app Laravel**.
- [ ] Pruebas y evidencia contra PostgreSQL real (`CON-PDO`, `INT-*`).
- [ ] Documentación y evidencia de integración.

## Tareas de integración (una rama y un PR por tarea)

- [ ] **T1 — Esquema compartido:** migrar en CENTRAL las tablas del catálogo (Spatie: `roles`, `permissions`, `role_has_permissions`) y en HOSPITAL la entidad H8 (`local_access_audit` append-only, `outbox_event`). Resolver el soporte multirrol en `local_user_refs`. → `feature/asii-02-rbac-esquema-bquiroao`
- [ ] **T2 — Núcleo:** portar Dominio + Aplicación; adaptadores PDO sobre `hospital_memberships`, `central_users`, el catálogo y `local_access_audit`; pasar `contratoRepositorios()` contra el esquema real. → `feature/asii-02-rbac-bquiroao`
- [ ] **T3 — Protección de rutas y PDP:** middleware de Laravel que llama a `AutorizarOperacion` con el permiso de cada ruta; endpoint `POST /api/v1/autorizaciones/decisiones`; pruebas API-01 a 03 contra `routes/api.php`. → `feature/asii-02-rbac-pdp-bquiroao`
- [ ] **T4 — Eventos:** outbox `rbac.membresia.asignada|revocada` → `central_inbox_messages` → `local_user_refs`; consumidor idempotente. → `feature/asii-02-rbac-eventos-bquiroao`
- [ ] **T5 — Seguridad:** pruebas de permisos y tenant, matriz OWASP API Top 10, sin secretos en logs. → `test/asii-02-rbac-seguridad-bquiroao`

## Dependencias con otros módulos

- **ASII-01 Usuarios/tenants:** dueño de `central_users` y del JWT (`JwtAuth`).
- **ASII-22 Gobernanza y auditoría:** consume los eventos `ACCESO_DENEGADO` y `ROL_ASIGNADO`.
- **ASII-30 API Gateway:** rate limiting y propagación de `X-Request-ID`.
- **Módulos clínicos:** consultan el PDP; no leen tablas de permisos.

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
- [ ] Evidencia de análisis y diseño adjunta (enlaces a `docs/modulos/rbac/`).
- [ ] Evidencia de implementación adjunta.
- [ ] Evidencia de pruebas adjunta (incluye `CON-PDO` e `INT-*` en verde).
- [ ] Riesgos documentados.
- [ ] Revisión solicitada.

## Notas del docente/reviewer

<!-- Espacio para acuerdos, restricciones o decisiones de integración. -->
