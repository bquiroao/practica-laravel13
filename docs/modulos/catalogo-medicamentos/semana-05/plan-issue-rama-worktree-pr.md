# Plan de issue, rama, worktree y PR — semana 5

## 1. Issue (tarea de Odoo «Semana 5»)

**Título:** Catálogo de medicamentos — semana 5: API REST, contrato y frontera de microservicio

**Descripción propuesta:**
- Exponer CU-01…CU-06 y RF-09 como API REST JSON en el Micro-HIS, sobre los mismos casos de uso.
- Permisos por rol con tokens ficticios; tenant tomado del token; formato único de errores; `X-Request-Id`.
- Contrato API v1 documentado; frontera de microservicio con gatillos medibles y migración por etapas.

**Criterios de cierre:** pruebas `pruebas/api/api.php` en verde; `scripts/probar-api.php` con 9 de 9
respuestas esperadas; los PR de la semana mergeados en `develop`.

**Estados en Odoo:** Pendiente → Desarrollo (al abrir el primer PR de la semana) → Terminada (al
mergear el último). Los PR **no** usan *Closes*: el módulo sigue en desarrollo.

## 2. Ramas

Una rama por parte, siempre desde `develop` actualizado, con el formato del repositorio:

| Parte | Rama | Contenido |
|---|---|---|
| A | `semana-5-catalogo-de-medicamentos-parte-a-erick-ramazzini` | Identidad, autenticación, permisos, formato JSON y controlador API |
| B | `semana-5-catalogo-de-medicamentos-parte-b-erick-ramazzini` | Kernel API, rutas, configuración, front controller y pruebas |
| C | `semana-5-catalogo-de-medicamentos-parte-c-erick-ramazzini` | Prueba en vivo, contrato API y diagrama cliente-servidor |
| D | `semana-5-catalogo-de-medicamentos-parte-d-erick-ramazzini` | Frontera de microservicio, migración, este plan y README |

A y B son código en cadena: si A no está mergeada, B sale de la rama de A, su base es esa rama, va
como **Draft** con «Depende de #N» y se verifica con `git diff --stat <rama-A>...HEAD`. C y D son
documentación y salen de `develop`.

## 3. Worktree

En el repositorio del grupo **se trabaja en la carpeta clonada, sin worktree**
(`C:\Users\erick\sistema-hospitalario-integrado-SistenasII-2026-G1`). Cada parte se hace con
`git switch` en esa misma carpeta.

Como referencia, si en algún momento se necesitara trabajar dos partes a la vez sin cambiar de rama
(por ejemplo, revisar la parte A mientras se escribe la D), un worktree se crearía así y se
eliminaría al terminar:

```powershell
git fetch origin
git worktree add ..\catalogo-s5-parte-d -b semana-5-catalogo-de-medicamentos-parte-d-erick-ramazzini origin/develop
# ... trabajar y hacer commits en ..\catalogo-s5-parte-d ...
git worktree remove ..\catalogo-s5-parte-d
```

No se usa en esta entrega: con una sola persona por módulo y partes pequeñas, cambiar de rama es
suficiente y evita tener dos carpetas con estados distintos.

## 4. PR

| Parte | Título | Base | Tipo |
|---|---|---|---|
| A | `docs(catalogo-medicamentos): semana 5 — … (parte A)` | `develop` | Normal |
| B | `docs(catalogo-medicamentos): semana 5 — … (parte B)` | `develop` o la rama de A | Draft si A no está mergeada |
| C | `docs(catalogo-medicamentos): semana 5 — … (parte C)` | `develop` | Normal |
| D | `docs(catalogo-medicamentos): semana 5 — … (parte D)` | `develop` | Normal |

Reglas que aplican a los cuatro:
- Menos de 400 líneas (sin contar `.png`); se verifica con `git diff --stat origin/develop...HEAD` antes del push.
- Un commit por pieza con `Commit-Pieza`, desde la raíz del repo.
- Plantilla «PR de módulo ASII» completa; revisor: Boris Quiroa (@bquiroao).
- Nunca *Squash* en una cadena; las ramas mergeadas se borran al final, todas juntas.
