# Borrador del PR de integración (tarea T2/T3 del issue ASII-02)

> Plantilla para cuando se integre RBAC a la app Laravel (semana 16). Sigue `.github/pull_request_template.md`.
> Los resultados se completan con la salida real de los comandos en ese momento.

**Título:** `feat(rbac): integrar autorización por rol y PDP en el HIS (ASII-02)`
**Rama:** `feature/asii-02-rbac-bquiroao` → `develop`

```bash
# Desde el worktree
git push -u origin feature/asii-02-rbac-bquiroao
# Publica solo el cuerpo (todo lo que está debajo de la línea ---)
sed '1,/^---$/d' docs/modulos/rbac/semana-05/plan/pr-integracion-rbac.md | \
  gh pr create --base develop --head feature/asii-02-rbac-bquiroao \
  --title "feat(rbac): integrar autorización por rol y PDP en el HIS (ASII-02)" --body-file -
```

---

# PR de módulo ASII

## Resumen

Integra en la app del HIS el núcleo del Micro-HIS RBAC:

- asignación de roles sobre `hospital_memberships`;
- autorización de rutas por permiso mediante middleware;
- endpoint de decisión `POST /api/v1/autorizaciones/decisiones`;
- denegación trazable en `local_access_audit`.

El contrato es `docs/modulos/rbac/micro-his-rbac/api/openapi.yaml`.

## Módulo asignado

- Módulo ASII: `ASII-02 — RBAC: roles, permisos y protección de rutas`
- Estudiante: Boris Alexander Quiroa Orellana
- GitHub: @bquiroao
- Rama: `feature/asii-02-rbac-bquiroao`
- Destino del PR: `develop`
- Issue relacionado: Closes #<número del issue ASII-02>

## Alcance

### Incluido

- Dominio y Aplicación de RBAC portados sin cambios de reglas.
- Adaptadores PDO sobre CENTRAL/HOSPITAL que pasan `contratoRepositorios()`.
- Middleware de rutas y endpoint PDP.

### Fuera de alcance

- Outbox y eventos (T4).
- Pruebas de amenazas (T5).
- UI de administración.

## Evidencia obligatoria

- [ ] El PR viene desde un worktree creado desde `origin/develop`.
- [ ] No trabajé sobre `main`.
- [ ] No mezclé cambios de otro módulo.
- [ ] El issue del módulo está vinculado.
- [ ] Incluí RF/RNF y criterios de aceptación.
- [ ] Incluí evidencia de diseño.
- [ ] Documenté endpoints, payloads, respuestas, errores y permisos.
- [ ] Adjunté capturas o demo si el módulo tiene UI.
- [ ] Ejecuté validaciones locales y pegué los resultados abajo.
- [ ] Revisé roles, permisos, tenant y datos clínicos sensibles.
- [ ] Dejé notas de integración con otros módulos o riesgos pendientes.

## Validación ejecutada

```bash
# comando: php artisan test
# resultado:
# comando: npx @redocly/cli lint docs/modulos/rbac/micro-his-rbac/api/openapi.yaml
# resultado:
# comando: php artisan route:list --path=api
# resultado:
```

## Contrato API si aplica

| Método | Ruta | Permiso/Rol | Descripción |
|---|---|---|---|
| POST | `/api/v1/roles/asignaciones` | `roles.asignar` | Asigna un rol (201/403/404/409/422) |
| POST | `/api/v1/autorizaciones/decisiones` | autenticado | Decide PERMITIDO/DENEGADO para el dueño del token |
| GET | `/api/v1/auditoria/eventos/{correlationId}` | `auditoria.consultar` | Evento del hospital del token |

## Riesgos y pendientes

- Consistencia eventual de `local_user_refs` hasta completar T4.
- Rate limiting del PDP depende del API Gateway (ASII-30).

## Checklist final del estudiante

- [ ] El PR es pequeño y revisable.
- [ ] El código compila o las limitaciones están explicadas.
- [ ] La documentación del módulo está actualizada.
- [ ] No hay credenciales, tokens ni datos sensibles reales en el PR.
- [ ] Solicité revisión solo después de completar la evidencia mínima.
