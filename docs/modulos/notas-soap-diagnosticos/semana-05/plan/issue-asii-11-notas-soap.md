# Issue listo para crear en GitHub

**Título:** `ASII-11 — Notas SOAP y diagnósticos`
**Etiquetas:** `asii`, `module`
**Plantilla:** `.github/ISSUE_TEMPLATE/module_task.md`

Se puede crear desde la página del repositorio (Issues → New issue → plantilla del módulo) copiando todo lo que está debajo de la línea `---`. Con GitHub CLI, desde la raíz del repositorio:

```bash
# Publica solo el cuerpo (todo lo que está debajo de la línea ---)
sed '1,/^---$/d' docs/modulos/notas-soap-diagnosticos/semana-05/plan/issue-asii-11-notas-soap.md | \
  gh issue create --title "ASII-11 — Notas SOAP y diagnósticos" \
  --label asii --label module --body-file -
```

---

## Datos del módulo

- **Número:** ASII-11
- **Módulo:** Notas SOAP y diagnósticos
- **Estudiante:** Joshua Eduardo García Reyes
- **GitHub:** @jgarciar73-svg
- **Rama esperada:** `feature/asii-11-notas-soap-jgarciar73-svg`
- **Worktree esperado:** `../shi-asii-11-notas-soap`

## Objetivo

Integrar al HIS el registro de notas clínicas SOAP con su diagnóstico CIE-10, diseñado y probado en el micro-HIS (`docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap`):

- registro de la nota con sus cuatro secciones y al menos un diagnóstico, en una sola transacción;
- firma por el autor y nota firmada inmutable;
- consulta de la nota y listado por expediente (resúmenes sin texto clínico);
- reintento seguro del registro con `Idempotency-Key`;
- evento `NotaSoapFirmada` hacia CENTRAL por la bandeja de salida.

## Alcance mínimo

- [x] Análisis de actores, procesos y casos de uso (semana 1).
- [x] RF/RNF y criterios de aceptación (semana 2).
- [x] Diseño arquitectónico, capas y componentes (semanas 3 y 4).
- [x] Contrato API (semana 5: `micro-his-notas-soap/api/openapi.yaml`).
- [ ] UI mínima funcional: formulario SOAP y línea de tiempo en el expediente.
- [ ] Validaciones, roles, permisos y tenant **en la app Laravel**.
- [ ] Pruebas y evidencia contra PostgreSQL real (`REP-*`, `INT-*`, `API-*`).
- [ ] Documentación y evidencia de integración.

## Tareas de integración (una rama y un PR por tarea)

- [ ] **T1 — Esquema:** migraciones en HOSPITAL para `idempotency_keys` y `outbox_events`. Acordar `audit_events` con ASII-22 y confirmar los triggers que impiden modificar una nota firmada o borrar notas. → `feature/asii-11-notas-soap-esquema-jgarciar73-svg`
- [ ] **T2 — Núcleo:** portar Dominio + Aplicación sin cambiar reglas. Adaptadores PDO sobre `clinical_notes`, `diagnoses`, `medical_records` y `care_assignments`. Las pruebas `REP-01` a `REP-08` deben pasar contra el esquema real. → `feature/asii-11-notas-soap-jgarciar73-svg`
- [ ] **T3 — API:** rutas `/api/v1/notas-soap*` y `/api/v1/expedientes/{id}/notas-soap` con su permiso (`notas_soap.registrar`, `notas_soap.firmar`, `notas_soap.consultar`) usando el middleware de RBAC. Las pruebas `API-01` a `API-04` deben pasar contra `routes/api.php`. → `feature/asii-11-notas-soap-api-jgarciar73-svg`
- [ ] **T4 — UI:** formulario SOAP en la SPA (Vue) que envía `Idempotency-Key`, conserva lo escrito ante 422 o `NO_DISPONIBLE`, y muestra la línea de tiempo en el expediente. → `feature/asii-11-notas-soap-ui-jgarciar73-svg`
- [ ] **T5 — Seguridad:** pruebas de permisos, tenant y datos sensibles según la matriz OWASP API 2023 de la semana 5. Comprobar que no haya texto clínico en los logs. → `test/asii-11-notas-soap-seguridad-jgarciar73-svg`

## Dependencias con otros módulos

- **ASII-01 Usuarios/tenants:** identidad (JWT) y `local_user_refs`.
- **ASII-02 RBAC:** decide los permisos `notas_soap.*` por rol.
- **ASII-10 Expediente:** dueño de `medical_records` (regla R-03); consume el listado para la línea de tiempo.
- **Admisión / asignaciones:** dueño de `care_episodes` y `care_assignments` (regla R-02). El módulo dueño está por confirmar.
- **ASII-22 Gobernanza y auditoría:** recibe `NOTA_REGISTRADA` y `NOTA_FIRMADA`.
- **ASII-24 Contratos API:** incorpora `openapi.yaml` a la documentación general.
- **ASII-30 API Gateway:** límite de peticiones y propagación de `X-Request-ID`.

## Actividades semanales relacionadas

- [x] Semana 1: casos de uso y narrativa.
- [x] Semana 2: RF/RNF, criterios de aceptación y ejemplo SOLID (SRP).
- [x] Semana 3: vista arquitectónica.
- [x] Semana 4: diseño por capas y repositorio.
- [x] Semana 5: contrato API y plan de integración.
- [ ] Semana 16: integración y primera entrega funcional (T1 a T4).
- [ ] Semana 17: seguridad y despliegue final (T5).

## Definition of Done

- [ ] PR abierto hacia `develop` por cada tarea, con `Closes`/`Refs` a este issue.
- [ ] Evidencia de análisis y diseño adjunta (enlaces a `docs/modulos/notas-soap-diagnosticos/`).
- [ ] Evidencia de implementación adjunta.
- [ ] Evidencia de pruebas adjunta (incluye `REP-*`, `API-*` e `INT-*` en verde).
- [ ] Riesgos documentados.
- [ ] Revisión solicitada.

## Notas del docente/reviewer

<!-- Espacio para acuerdos, restricciones o decisiones de integración. -->
