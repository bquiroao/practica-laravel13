# Issue listo para crear en GitHub

**Título:** `ASII-15 — Prescripciones electrónicas con validación de alergias`
**Etiquetas:** `asii`, `module`
**Plantilla:** `.github/ISSUE_TEMPLATE/module_task.md`

Para crearlo con GitHub CLI, desde la raíz del repositorio:

```bash
# Publica solo el cuerpo (todo lo que está debajo de la línea ---)
sed '1,/^---$/d' docs/modulos/prescripciones-alergias/semana-05/plan/issue-asii-15-prescripciones.md | \
  gh issue create --title "ASII-15 — Prescripciones electrónicas con validación de alergias" \
  --label asii --label module --body-file -
```

---

## Datos del módulo

- **Número:** ASII-15
- **Módulo:** Prescripciones electrónicas con validación de alergias
- **Estudiante:** Boris Alexander Quiroa Orellana
- **GitHub:** @bquiroao
- **Rama esperada:** `feature/asii-15-prescripciones-bquiroao`
- **Worktree esperado:** `../shi-asii-15-prescripciones`

## Objetivo

Integrar al HIS la emisión de prescripciones con **verificación previa de alergias y bloqueo seguro**, diseñada en el Micro-HIS (`docs/modulos/prescripciones-alergias/micro-his-prescripciones`):

- verificación previa sin efectos y emisión que **vuelve a verificar** en el servidor;
- bloqueo por coincidencia directa, por reactividad cruzada grave y por dosis máxima;
- advertencia justificada con permiso `prescripcion.override`;
- **fail-closed** si no se pueden leer las alergias;
- prescripción inmutable e idempotente por `Idempotency-Key`, con su verificación y su traza para Auditoría.

## Alcance mínimo

- [x] Análisis de actores, procesos y casos de uso (semana 1).
- [x] RF/RNF y criterios de aceptación (semana 2).
- [x] Diseño arquitectónico, capas y componentes (semanas 3 y 4).
- [x] Contrato API (semana 5: `micro-his-prescripciones/api/openapi.yaml` 1.0.0, validado con Redocly y con pruebas de conformidad).
- [ ] UI mínima funcional si aplica (verificar, emitir, consultar; semanas 8 a 11).
- [ ] Validaciones, roles, permisos y tenant **en la app Laravel**.
- [ ] Pruebas y evidencia contra el PostgreSQL del proyecto (suite de contrato `CON-PDO-*` e `INT-*`).
- [ ] Documentación y evidencia de integración.

## Tareas de integración (una rama y un PR por tarea)

- [ ] **T1 — Esquema HOSPITAL (slice H5, tarea 3.5 del plan del esquema):** primero `tests/Database/PrescriptionIntegrityTest.php` (RED) y después `database/migrations/hospital/2026_08_11_000500_create_medication_tables.php` a partir de `database/integracion-hospital.sql`. Incluye:
  - las tablas;
  - el trigger de integridad (episodio del paciente, prescriptor asignado, inmutable);
  - la vista `prescripcion_alergias`;
  - un *down* que borra el trigger antes de las tablas.

  Validar con `bin/validar-integracion-hospital.php`. → `feature/asii-15-prescripciones-esquema-bquiroao`
- [ ] **T2 — Núcleo:**
  - portar Dominio y Aplicación a `app/`;
  - adaptadores PDO sobre la conexión `hospital` (la fuente de alergias, sobre la vista);
  - pasar la suite de contrato contra el esquema real.

  → `feature/asii-15-prescripciones-bquiroao`
- [ ] **T3 — API:**
  - controladores Laravel sobre los mismos casos de uso;
  - rutas `/api/v1/prescripciones*` en `routes/api.php` con `jwt.auth` y `tenant`;
  - pruebas API-01..03 contra las rutas reales;
  - `X-Request-ID` y registro de acceso.

  → `feature/asii-15-prescripciones-api-bquiroao`
- [ ] **T4 — Integración:**
  - traza por `outbox_event` (H8) hacia Auditoría (ASII-22);
  - lectura de `medications` del Catálogo (ASII-14) en solo lectura;
  - acordar con Alergias (ASII-12) la vista y el significado de «sin alergias conocidas».

  → `feature/asii-15-prescripciones-integracion-bquiroao`
- [ ] **T5 — Seguridad y operación:**
  - rol `app_prescripciones` (`INSERT`/`SELECT` en sus tablas; solo `SELECT` en la vista y en el catálogo);
  - matriz OWASP API Top 10;
  - alerta si la tasa de `VERIFICACION_NO_DISPONIBLE` supera el umbral.

  → `test/asii-15-prescripciones-seguridad-bquiroao`

## Dependencias con otros módulos

- **ASII-12 Alergias clínicas:** dueño de `allergies`. Prescripciones solo **lee** por la vista `prescripcion_alergias`; el contrato *fail-closed* no cambia aunque el Expediente pase a ser un servicio.
- **ASII-14 Catálogo de medicamentos:** dueño de `medications` y de los grupos farmacológicos (`allergen_groups`). Prescripciones solo lee.
- **ASII-10 Expediente médico electrónico base:** episodios de atención (`care_episodes`) y asignaciones del médico (`care_assignments`).
- **ASII-01 Usuarios y autenticación / ASII-02 RBAC:** JWT, `X-Tenant-ID` y los permisos `prescripcion.emitir | override | consultar`.
- **ASII-22 Gobernanza y auditoría:** consume `PRESCRIPCION_EMITIDA`, `PRESCRIPCION_BLOQUEADA` y `VERIFICACION_NO_DISPONIBLE`.
- **ASII-24 Contratos API:** publica `openapi.yaml` en la documentación técnica.
- **ASII-30 API Gateway:** TLS, rate limiting y propagación de `X-Request-ID`.

## Actividades semanales relacionadas

- [x] Semana 1: casos de uso y narrativa.
- [x] Semana 2: RF/RNF, criterios de aceptación y ejemplo SOLID.
- [x] Semana 3: vista arquitectónica.
- [x] Semana 4: diseño por capas.
- [x] Semana 5: contrato API y plan de integración.
- [ ] Semana 7: componentes backend/frontend y refactorización.
- [ ] Semana 8: flujo UX.
- [ ] Semana 9: usabilidad/accesibilidad.
- [ ] Semana 10: diseño responsive/móvil.
- [ ] Semana 11: mockup o prototipo.
- [ ] Semana 13: revisión técnica formal.
- [ ] Semana 14: plan SQA.
- [ ] Semana 15: pruebas unitarias/caja blanca/caja negra.
- [ ] Semana 16: integración y primera entrega funcional (T1 a T4).
- [ ] Semana 17: seguridad y despliegue final (T5).
- [ ] Semana 18: defensa final.

## Definition of Done

- [ ] PR abierto hacia `develop` por cada tarea, con `Closes`/`Refs` a este issue.
- [ ] Evidencia de análisis y diseño adjunta (enlaces a `docs/modulos/prescripciones-alergias/`).
- [ ] Evidencia de implementación adjunta.
- [ ] Evidencia de pruebas adjunta (incluye `CON-PDO-*`, `INT-*` y `PrescriptionIntegrityTest` en verde).
- [ ] Riesgos documentados.
- [ ] Revisión solicitada.

## Notas del docente/reviewer

- La frontera de microservicio se evaluó con medición (semana 5): **no se separa**. Ni Prescripciones ni la lectura de alergias salen del monolito mientras no se cumpla un criterio C-1..C-5.
- Las reglas clínicas (bloqueo absoluto, severidad `unknown` como la peor, justificación de 20 caracteres) deben validarse con personal clínico antes de T2.
