# Plan de issue, rama, worktree y PR — semana 5

## Issue (tarea de Odoo)

**Título:** Semana 5: Alergias clínicas y alerta visual en expediente — API REST, contrato y frontera de microservicio

**Descripción:**
- **Objetivo:** exponer CU-01, CU-03 y CU-05 como API REST v1 sobre los mismos casos de uso; documentar el contrato y evaluar la frontera con medición.
- **Criterios de cierre:**
  - T-35 a T-44 pasan con PostgreSQL;
  - el contrato coincide con el código;
  - la medición de la frontera está pegada en el README;
  - todos los PR están mergeados en `develop`.
- **Estados en Odoo:** Pendiente → Desarrollo (al abrir el primer PR) → Terminada (al mergear el último).

## Ramas

| Parte | Rama | Sale de | Base del PR |
|---|---|---|---|
| A — código de la API | `semana-5-alergias-clinicas-y-alerta-visual-en-expediente-parte-a-erick-ramazzini` | `develop` actualizado (si la semana 4 ya está mergeada) | `develop` |
| B — contrato, medición, frontera y este plan | `semana-5-alergias-clinicas-y-alerta-visual-en-expediente-parte-b-erick-ramazzini` | `develop` (o la rama A si no está mergeada: Draft + «Depende de #N») | `develop` o la rama A |

## Worktree

En el repositorio del grupo se trabaja en la carpeta clonada, **sin worktree**. Esta es la regla acordada y la que se siguió.

Como plan, por si alguna vez hace falta trabajar dos partes a la vez sin cambiar de rama (por ejemplo, corregir la parte A mientras se escribe la B), sería así:

```powershell
git switch develop
git pull origin develop
git worktree add ..\shi-semana5-b -b semana-5-alergias-clinicas-y-alerta-visual-en-expediente-parte-b-erick-ramazzini
cd ..\shi-semana5-b          # se trabaja y se hace commit aquí
cd ..\sistema-hospitalario-integrado-SistenasII-2026-G1
git worktree remove ..\shi-semana5-b   # después del merge
```

Riesgo del worktree en este equipo: Docker y `php -S` usan el puerto 55442 y el 8042. Dos copias corriendo a la vez chocarían, así que solo una puede tener el servidor arriba.

## PR

- **Título:** `docs(alergias-clinicas-alerta-visual-expediente): semana 5 — <tema> (parte X)`.
- **Plantilla:** «PR de módulo ASII» completa. «Validación ejecutada» lleva `php pruebas/ejecutar.php --solo=semana05` y `git diff --stat origin/develop...HEAD`.
- **Revisión:** Boris Quiroa (bquiroao). Sin «Squash» si las partes van en cadena.
- **Al terminar:** borrar las ramas mergeadas todas juntas.
