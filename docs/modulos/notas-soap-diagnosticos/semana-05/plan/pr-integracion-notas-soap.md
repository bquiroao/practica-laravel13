# Borrador del PR de integración (tareas T2 y T3 del issue ASII-11)

> Es la plantilla para cuando el módulo se integre a la app Laravel (semana 16). Sigue `.github/pull_request_template.md`.
> Los resultados de la sección «Validación ejecutada» se llenan con la salida real de los comandos en ese momento.

**Título:** `feat(notas-soap): integrar registro, firma y consulta de notas SOAP en el HIS (ASII-11)`
**Rama:** `feature/asii-11-notas-soap-jgarciar73-svg` → `develop`

```bash
# Desde el worktree ../shi-asii-11-notas-soap
git push -u origin feature/asii-11-notas-soap-jgarciar73-svg
# Publica solo el cuerpo (todo lo que está debajo de la línea ---)
sed '1,/^---$/d' docs/modulos/notas-soap-diagnosticos/semana-05/plan/pr-integracion-notas-soap.md | \
  gh pr create --base develop --head feature/asii-11-notas-soap-jgarciar73-svg \
  --title "feat(notas-soap): integrar registro, firma y consulta de notas SOAP en el HIS (ASII-11)" --body-file -
```

---

# PR de módulo ASII

## Resumen

Este PR integra en la app del HIS el núcleo del micro-HIS Notas SOAP:

- registro de la nota SOAP con su diagnóstico CIE-10 en una sola transacción, con reintento seguro por `Idempotency-Key`;
- firma por el autor, después de la cual la nota no se puede modificar;
- consulta de la nota y listado por expediente, con resúmenes sin texto clínico;
- rutas `/api/v1` con permiso declarado por ruta.

El contrato es `docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap/api/openapi.yaml`.

## Módulo asignado

- Módulo ASII: `ASII-11 — Notas SOAP y diagnósticos`
- Estudiante: Joshua Eduardo García Reyes
- GitHub: @jgarciar73-svg
- Rama: `feature/asii-11-notas-soap-jgarciar73-svg`
- Destino del PR: `develop`
- Issue relacionado: Closes #<número del issue ASII-11>

## Alcance

### Incluido

- Dominio y Aplicación de Notas SOAP, portados sin cambiar las reglas R-01 a R-08.
- Adaptadores PDO sobre `clinical_notes`, `diagnoses`, `medical_records`, `care_assignments` e `idempotency_keys`, que pasan el contrato `REP-01` a `REP-08`.
- Rutas y controlador. El permiso de cada ruta lo decide el middleware de RBAC.

### Fuera de alcance

- Migraciones (T1, en su propio PR).
- Formulario en la SPA (T4).
- Pruebas de amenazas (T5).
- Corrección de notas firmadas mediante una versión nueva (`supersedes_id`).

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
# comando: php artisan test
# resultado:
# comando: php docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap/tests/ejecutar.php
# resultado:
# comando: npx @redocly/cli@1.34.3 lint docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap/api/openapi.yaml
# resultado:
# comando: php artisan route:list --path=api
# resultado:
```

## Contrato API si aplica

| Método | Ruta | Permiso/Rol | Descripción |
|---|---|---|---|
| POST | `/api/v1/notas-soap` | `notas_soap.registrar` (médico asignado al episodio) | Registra la nota con su diagnóstico (201/400/401/403/409/422/500/503) |
| GET | `/api/v1/notas-soap/{notaId}` | `notas_soap.consultar` | Nota con sus diagnósticos, del hospital del token (200/404) |
| POST | `/api/v1/notas-soap/{notaId}/firma` | `notas_soap.firmar` (solo el autor) | Firma la nota (200/403/404/409) |
| GET | `/api/v1/expedientes/{expedienteId}/notas-soap` | `notas_soap.consultar` | Resúmenes sin texto clínico; filtro `estado` (200/400) |

## Evidencia visual si aplica

No aplica en este PR (la UI va en T4).

## Riesgos y pendientes

- El dueño de `care_assignments` está por confirmar. Mientras tanto se lee por el puerto `VerificadorAcceso`.
- El listado por expediente todavía no aplica los consentimientos entre hospitales de CENTRAL; eso le corresponde a ASII-10.
- Las claves de idempotencia no caducan; falta una tarea programada que las borre después de 24 horas.
- El límite de peticiones depende del API Gateway (ASII-30).

## Checklist final del estudiante

- [ ] El PR es pequeño y revisable.
- [ ] El código compila o las limitaciones están explicadas.
- [ ] La documentación del módulo está actualizada.
- [ ] No hay credenciales, tokens ni datos sensibles reales en el PR.
- [ ] Solicité revisión solo después de completar la evidencia mínima.
