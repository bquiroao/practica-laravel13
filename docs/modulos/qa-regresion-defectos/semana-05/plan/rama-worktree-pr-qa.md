# Plan de rama, worktree y PR — integración del módulo QA

Plan para trabajar los 5 slices del [issue](issue-integracion-qa.md) sin mezclar cambios entre ellos ni con otros módulos.

## 1. Un worktree por slice, siempre desde `origin/develop`

```bash
git fetch origin
git worktree add ../wt-qa-esquema   -b feat/qa-esquema-central origin/develop
git worktree add ../wt-qa-dominio   -b feat/qa-dominio         origin/develop
# … uno por slice; cada uno en su carpeta, con su propia rama
```

**Por qué worktrees.** Los slices 2 y 3 avanzan en paralelo, y un worktree evita el `git stash` entre ramas: cada carpeta tiene su propio árbol de trabajo. Así no es posible commitear por error un archivo de otro slice.

**Dependencias entre slices:**

| Slice | Depende de | Cuándo se crea su rama |
|---|---|---|
| 1 · Datos | — | Primero |
| 2 · Dominio | — | En paralelo con el 1 |
| 3 · Persistencia | 1 y 2 mergeados | Desde `origin/develop` actualizado después de mergear 1 y 2 |
| 4 · API | 2 | Después de 2 |
| 5 · Almacén | 2 | Después de 2 |

## 2. Commits

- Convención del repositorio: `feat(qa): …`, `test(qa): …`, `docs(qa): …`.
- Un commit por cambio con sentido: primero la prueba (rojo), después la implementación (verde).

## 3. PR

| Campo | Valor |
|---|---|
| Base | `develop` |
| Título | `feat(qa): slice N — <qué>` |
| Plantilla | ASII (resumen, módulo, alcance, evidencia obligatoria, validación, contrato API, riesgos, checklist) |
| Tamaño | 400 líneas como máximo; si se pasa, se divide (N-a, N-b) |
| Evidencia | La salida de las pruebas del slice, pegada en «Validación ejecutada» |
| Revisión | Se pide solo con la evidencia mínima completa |

## 4. Después del merge

```bash
git worktree remove ../wt-qa-esquema
git branch -d feat/qa-esquema-central
git fetch origin && git worktree add ../wt-qa-persistencia -b feat/qa-persistencia origin/develop
```

## 5. Cómo se verificó este plan

El repositorio ya usa ramas por entrega desde `develop` actualizado, con PR y la plantilla ASII. Este módulo lleva así sus entregas semanales (PR #301 a #304 y la de esta semana). Los worktrees son el paso siguiente para el trabajo en paralelo de los slices 2 a 5.
