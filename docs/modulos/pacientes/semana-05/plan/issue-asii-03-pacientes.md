# Issue listo para crear en GitHub

**Título:** `ASII-03 — Pacientes: registro, edición, búsqueda y detalle`
**Etiquetas:** `asii`, `module`
**Plantilla:** `.github/ISSUE_TEMPLATE/module_task.md`

Para crearlo con GitHub CLI, desde la raíz del repositorio:

```bash
# Publica solo el cuerpo (todo lo que está debajo de la línea ---)
sed '1,/^---$/d' docs/modulos/pacientes/semana-05/plan/issue-asii-03-pacientes.md | \
  gh issue create --title "ASII-03 — Pacientes: registro, edición, búsqueda y detalle" \
  --label asii --label module --body-file -
```

---

## Datos del módulo

- **Número:** ASII-03
- **Módulo:** Pacientes: registro, edición, búsqueda y detalle
- **Estudiante:** Boris Alexander Quiroa Orellana
- **GitHub:** @bquiroao
- **Rama esperada:** `feature/asii-03-pacientes-bquiroao`
- **Worktree esperado:** `../shi-asii-03-pacientes`

## Objetivo

Integrar al HIS la gestión de la ficha del paciente diseñada en el Micro-HIS Pacientes (`docs/modulos/pacientes/micro-his-pacientes`):

- registro sin duplicados (documento único en la red, coincidencia probable con confirmación del supervisor);
- búsqueda acotada al hospital con datos mínimos;
- detalle con versión (`ETag`);
- actualización segura (`If-Match`, motivo para la identidad, auditoría antes/después);
- reintentos seguros (`Idempotency-Key`) y trazabilidad (`X-Request-ID`).

## Alcance mínimo

- [x] Análisis de actores, procesos y casos de uso (semana 1).
- [x] RF/RNF y criterios de aceptación (semana 2).
- [x] Diseño arquitectónico, capas y componentes (semanas 3 y 4).
- [x] Contrato API (semana 5: `micro-his-pacientes/api/openapi.yaml`, validado con Redocly).
- [ ] UI mínima funcional si aplica (registro, búsqueda, detalle y edición en Vue).
- [ ] Validaciones, roles, permisos y tenant **en la app Laravel**.
- [ ] Pruebas y evidencia contra PostgreSQL real (`CON-PDO-*`, `INT-*`).
- [ ] Documentación y evidencia de integración.

## Tareas de integración (una rama y un PR por tarea)

- [ ] **T1 — Esquema HOSPITAL:** migraciones nuevas (sin editar las existentes) para `local_patients.version`, `local_access_audit` (append-only por trigger), `outbox_event` e `idempotencia_registros`. Probar con `composer test` (grupo `database-integrity`). → `feature/asii-03-pacientes-esquema-bquiroao`
- [ ] **T2 — Núcleo:** portar Dominio + Aplicación a `app/`; adaptadores PDO sobre `local_patients` (HOSPITAL) y lectura del MPI (CENTRAL, solo lectura); pasar `contratoRepositorios()` contra el esquema real. → `feature/asii-03-pacientes-bquiroao`
- [ ] **T3 — API:** controladores Laravel sobre los mismos casos de uso; rutas `/api/v1/pacientes` en `routes/api.php` con `tenant` + `jwt.auth`; pruebas API-01..03 contra las rutas reales; `X-Request-ID` y registro de acceso. → `feature/asii-03-pacientes-api-bquiroao`
- [ ] **T4 — Eventos:** outbox `pacientes.paciente.registrado|actualizado` → `central_inbox_messages` → MPI (`mpi_patients`, `mpi_identifiers`, `patient_hospital_links`); consumidor idempotente. → `feature/asii-03-pacientes-eventos-bquiroao`
- [ ] **T5 — Seguridad:** pruebas de permisos y tenant, matriz OWASP API Top 10, registro de acceso sin datos personales. → `test/asii-03-pacientes-seguridad-bquiroao`

## Dependencias con otros módulos

- **ASII-02 RBAC:** decide `pacientes.registrar | buscar | leer | editar | confirmar_duplicado | editar_restringido` (puerto `PoliticaAcceso`).
- **ASII-01 Usuarios/tenants:** JWT (`JwtAuth`) y `X-Tenant-ID` (`TenantMiddleware`).
- **Sincronización / MPI (CENTRAL):** dueño de `mpi_*`; consume los eventos de Pacientes.
- **ASII-30 API Gateway:** rate limiting, TLS y propagación de `X-Request-ID`.
- **Admisión, citas, expediente, laboratorio:** consumen `local_patients` (FK) y la API; usan `ClientePacientes` si alguno se despliega aparte.

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
- [ ] Evidencia de análisis y diseño adjunta (enlaces a `docs/modulos/pacientes/`).
- [ ] Evidencia de implementación adjunta.
- [ ] Evidencia de pruebas adjunta (incluye `CON-PDO-*` e `INT-*` en verde).
- [ ] Riesgos documentados.
- [ ] Revisión solicitada.
