# Plan de issue, rama, worktree y PR

## 1. Issue / tarea

| Campo | Valor |
|---|---|
| Seguimiento | Tarea de Odoo «Semana N» del módulo Catálogo de pruebas de laboratorio |
| Estados | Pendiente → Desarrollo (al abrir el primer PR) → Terminada (al mergear el último) |
| Vínculo con el PR | El título lleva «semana N» para que el sistema lo asocie; la descripción menciona la tarea sin *Closes* |

## 2. Rama

Una rama por semana, siempre desde `develop` actualizado:

```powershell
git switch develop
git pull origin develop
git switch -c semana-5-catalogo-pruebas-laboratorio-erick-ramazzini
git branch --show-current
```

Si la semana pasa de 400 líneas: `semana-5-catalogo-pruebas-laboratorio-parte-a-erick-ramazzini`, `...-parte-b-...`. Si una parte depende de otra que no está mergeada, se crea desde la anterior y su PR apunta a esa rama, con «Depende de #N».

## 3. Worktree

En el repositorio del grupo se trabaja en la carpeta clonada, sin worktree. Si hiciera falta trabajar dos semanas en paralelo (por ejemplo, corregir la semana 4 mientras avanza la 5), el worktree sería:

```powershell
git worktree add ..\shi-catalogo-lab-semana-4 semana-4-catalogo-pruebas-laboratorio-erick-ramazzini
git worktree list
git worktree remove ..\shi-catalogo-lab-semana-4   # al terminar
```

## 4. PR

| Campo | Valor |
|---|---|
| Destino | `develop` |
| Título | `docs(catalogo-pruebas-laboratorio): semana N — <tema>` (y `(parte A)` si va en partes) |
| Descripción | Plantilla «PR de módulo ASII» completa |
| Reviewer | bquiroao |
| Tamaño | Menos de 400 líneas; se verifica con `git diff --stat origin/develop...HEAD` |
| Commits | Uno por archivo o pieza: `Semana N: Catálogo de pruebas de laboratorio - <pieza>` |

## 5. Plan aplicado a esta semana

| PR | Rama | Contenido |
|---|---|---|
| 5A | `semana-5-catalogo-pruebas-laboratorio-parte-a-erick-ramazzini` | Código de la API en el Micro-HIS y sus pruebas (T-13 a T-16) |
| 5B | `semana-5-catalogo-pruebas-laboratorio-parte-b-erick-ramazzini` | Contrato API, frontera de microservicio, este plan y los diagramas |
