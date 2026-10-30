# Issue propuesto — Integrar el módulo QA de regresión al HIS

> Borrador para crear en GitHub cuando el equipo apruebe la integración. **No está creado**: esta entrega es el plan.

**Título:** `[ASII][QA] Integrar campañas de regresión, defectos y evidencias al HIS`
**Etiquetas:** `modulo:qa-regresion`, `tipo:feature`, `asii`
**Asignado:** @bquiroao
**Rama base:** `develop`

## Contexto

El Micro-HIS (`docs/modulos/qa-regresion-defectos/micro-his-qa`) implementa y prueba el flujo «diseño de casos manuales → campaña de regresión → evidencia → defectos → trazabilidad»:
- 94 pruebas;
- 29 mutaciones detectadas;
- contrato OpenAPI con lint limpio.

Este issue lo lleva al HIS real **sin cambiar el contrato**.

## Alcance

| Slice | Qué entra | Rama | Criterio de aceptación (verificable) |
|---|---|---|---|
| 1 · Datos | Migración central `qa_*` + `qa_miembro_activo()` + vista `qa_usuarios`; `role_code` `qa_tester`, `qa_lead`, `qa_analyst` acordados con Identidad | `feat/qa-esquema-central` | `validar-integracion-central.php` pasa 18/18 contra las migraciones del HIS; rollback de un paso deja C0–C4 intactos |
| 2 · Dominio y casos de uso | `CasoPrueba`, `Campana`, `Intento`, `Defecto` y los 9 casos de uso, detrás de los mismos puertos | `feat/qa-dominio` | DOM-01..09 y APP-01..15 en verde dentro del HIS |
| 3 · Persistencia | Repositorios Eloquent/PDO del HIS para los 3 puertos | `feat/qa-persistencia` | La **suite de contrato** CON-01..07 en verde contra el adaptador nuevo |
| 4 · API | Rutas `/api/v1/qa/*` y middleware de JWT y tenant del HIS | `feat/qa-api` | API-01..03 en verde contra `api/openapi.yaml` sin cambios |
| 5 · Almacén | Configurar el almacén de objetos del HIS detrás de `AlmacenEvidencias` | `feat/qa-almacen` | AHT-01..04 contra el almacén real; con el almacén caído, 503 y nada escrito |

## Fuera de alcance

- La SPA (semanas 8–11).
- Separar QA como microservicio: no se cumplen los criterios C-1..C-5 (semana 5, §5.3).

## Riesgos

| Riesgo | Mitigación |
|---|---|
| Los `role_code` de QA no existen en Identidad | Slice 1 bloqueado hasta acordarlos; el validador lo hace explícito |
| La función `SECURITY DEFINER` da más acceso del debido | Devuelve solo un booleano; el validador prueba que el rol no lee `central_users` |
| Un PR demasiado grande | Un PR por slice, de 400 líneas como máximo |

## Definición de terminado

- [ ] Cada slice tiene su PR a `develop` con la plantilla ASII completa.
- [ ] Las pruebas de cada slice corren en CI.
- [ ] `api/openapi.yaml` no cambió, o el cambio está versionado.
- [ ] La declaración de IA está actualizada.
