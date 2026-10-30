# Plan de issue, rama, worktree y PR — integración del reportador de CI

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final

Sigue las reglas del repositorio ([BRANCH_PROTECTION.md](../../../../../.github/BRANCH_PROTECTION.md) y la [plantilla de PR](../../../../../.github/pull_request_template.md)):
- los estudiantes trabajan en `feature/asii-XX-slug-usuario`;
- cada rama sale de un worktree basado en `origin/develop`;
- el PR va a `develop`;
- a `main` solo se integra desde `develop`.

## 1. Issue

```bash
gh issue create --title "ASII-22 — QA E2E: el pipeline reporta los resultados E2E al módulo y el gate decide la promoción" \
  --label asii --label module --assignee bquiroao \
  --body-file docs/modulos/qa-e2e-ci-despliegue/semana-05/plan/issue-reportador-ci.md
```

## 2. Worktree y rama

```bash
git fetch origin
git worktree add ../shi-asii-22-qa-e2e -b feature/asii-22-qa-e2e-reportador-ci-bquiroao origin/develop
cd ../shi-asii-22-qa-e2e
```

Por qué un worktree y no `git checkout`: el directorio principal puede seguir en otra tarea sin `stash`, y la rama nace de `origin/develop` actualizado, no del `develop` local.

## 3. Commits (pequeños y verificables)

| # | Commit | Verificación antes de hacerlo |
|---|---|---|
| 1 | `feat(qa-e2e): contrato OpenAPI 1.0.0 y pruebas de conformidad API-01..04` | `php tests/ejecutar.php --solo=API` |
| 2 | `feat(qa-e2e): ReportadorCi con reintentos idempotentes y bandeja de pendientes` | `--solo=CLI` |
| 3 | `feat(qa-e2e): X-Request-ID y registro de acceso JSON` | `--solo=OBS` |
| 4 | `ci: job E2E que construye una vez, reporta y deja la evaluación al gate` | El job en verde en el PR |

## 4. Pull request

```bash
git push -u origin feature/asii-22-qa-e2e-reportador-ci-bquiroao
gh pr create --base develop --title "ASII-22 · QA E2E: reportador de CI y contrato 1.0.0" \
  --body-file .github/pull_request_template.md       # completada con la evidencia
```

**Antes de pedir revisión:**
- suite completa con PostgreSQL y mutaciones en verde;
- la plantilla completada, con «Closes #<issue>»;
- revisión de que ningún secreto está en el diff (`git diff origin/develop --stat` y búsqueda de «token»).

**Después de la aprobación** (develop exige 1 aprobación): merge con *merge commit* y borrar la rama y el worktree:

```bash
git worktree remove ../shi-asii-22-qa-e2e && git branch -d feature/asii-22-qa-e2e-reportador-ci-bquiroao
```

## 5. Lo que este plan corrige respecto de cómo se entregaron las semanas

Las entregas semanales usaron ramas `semana-N-qa-e2e` desde el `develop` local, sin worktree ni issue. El guard `branch-protection.yml` las habría rechazado por el nombre. No lo hizo porque **nunca se ejecutó**: 0 corridas en Actions (semana 1). Este plan es el flujo correcto para un cambio de integración real. La diferencia queda anotada como riesgo del proceso: el CI declarado no protege nada mientras Actions esté deshabilitado.
