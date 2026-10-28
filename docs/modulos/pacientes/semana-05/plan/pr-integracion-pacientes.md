# Borrador del PR de integración (tarea T2 del issue ASII-03)

Se abre desde el worktree `../shi-asii-03-pacientes`, rama `feature/asii-03-pacientes-bquiroao`, hacia `develop`. Las casillas se marcan cuando la evidencia exista de verdad.

**Título:** `feat(pacientes): núcleo de Pacientes en el HIS con adaptadores sobre local_patients y MPI`

---

# PR de módulo ASII

## Resumen

Integra al HIS el núcleo del módulo Pacientes diseñado en `docs/modulos/pacientes/micro-his-pacientes`: Dominio (reglas R-01..R-10) y Aplicación (registrar, buscar, consultar, actualizar con idempotencia), con adaptadores PDO sobre `local_patients` (HOSPITAL) y lectura del MPI (CENTRAL). Es la base para que admisión, citas y expediente trabajen sobre una ficha única y versionada.

## Módulo asignado

- Módulo ASII: `ASII-03 — Pacientes: registro, edición, búsqueda y detalle`
- Estudiante: Boris Alexander Quiroa Orellana
- GitHub: @bquiroao
- Rama: `feature/asii-03-pacientes-bquiroao`
- Destino del PR: `develop`
- Issue relacionado: Closes #<número del issue ASII-03>

## Alcance

### Incluido

- `app/Pacientes/Dominio` y `app/Pacientes/Aplicacion` (portados sin cambios de reglas).
- Adaptadores `PdoLocalPatients`, `PdoIndiceMpi` (solo lectura), `PdoRegistroAuditoria` (`local_access_audit`), `PdoAlmacenIdempotencia`, `PdoTransaccion` (conexión `hospital`).
- Suite de contrato `contratoRepositorios()` contra el esquema real.

### Fuera de alcance

- Controladores y rutas (T3), eventos outbox (T4), pruebas de seguridad (T5).

## Evidencia obligatoria

- [ ] El PR viene desde un worktree creado desde `origin/develop`.
- [ ] No trabajé sobre `main`.
- [ ] No mezclé cambios de otro módulo.
- [ ] El issue del módulo está vinculado.
- [ ] Incluí RF/RNF y criterios de aceptación.
- [ ] Incluí evidencia de diseño: UML, C4, componentes, capas o flujo UX según corresponda.
- [ ] Documenté endpoints, payloads, respuestas, errores y permisos si el módulo expone API.
- [ ] Adjunté capturas o demo si el módulo tiene UI.
- [ ] Ejecuté validaciones locales y pegué los resultados abajo.
- [ ] Revisé roles, permisos, tenant y datos clínicos sensibles cuando aplica.
- [ ] Dejé notas de integración con otros módulos o riesgos pendientes.

## Validación ejecutada

```bash
# comando:
composer test
php docs/modulos/pacientes/micro-his-pacientes/tests/ejecutar.php
npx @redocly/cli lint docs/modulos/pacientes/micro-his-pacientes/api/openapi.yaml
php artisan route:list --path=api
# resultado:
# (pegar la salida real)
```

## Contrato API si aplica

Sin rutas nuevas en este PR (T3). Contrato de referencia: `docs/modulos/pacientes/micro-his-pacientes/api/openapi.yaml`.

## Evidencia visual si aplica

No aplica (sin UI en esta tarea).

## Riesgos y pendientes

- Depende de T1 (migraciones `version`, `local_access_audit`, `outbox_event`, `idempotencia_registros`).
- La unicidad del documento entre hospitales es eventual (MPI): los conflictos llegan como `identity_match_candidates`.
- Si CENTRAL no responde, el registro devuelve 503 hasta que se decida la degradación (registrar con `mpi_link_status = pending`).

## Checklist final del estudiante

- [ ] El PR es pequeño y revisable.
- [ ] El código compila o las limitaciones están explicadas.
- [ ] La documentación del módulo está actualizada.
- [ ] No hay credenciales, tokens ni datos sensibles reales en el PR.
- [ ] Solicité revisión solo después de completar la evidencia mínima.
