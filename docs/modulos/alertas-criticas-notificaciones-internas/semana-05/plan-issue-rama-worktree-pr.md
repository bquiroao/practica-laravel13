# Semana 5 (parte C) — Plan de issue, rama, worktree y PR

## Issue (tarea de Odoo)
- **Título:** «Semana 5: Alertas críticas y notificaciones internas — API REST y frontera de microservicio».
- **Estados:** Pendiente → Desarrollo (al abrir el primer PR de la semana) → Terminada (al mergear el último).
- **Criterios de cierre:** contrato API documentado, pruebas de API en verde, frontera y migración documentadas.

## Ramas y PRs de la semana 5

| PR | Rama | Base | Contenido |
|---|---|---|---|
| 05A | `semana-5-alertas-criticas-y-notificaciones-internas-parte-a-erick-ramazzini` | `develop` (o la rama de 04C si no está mergeada, como Draft) | Código de la API y pruebas |
| 05B | `…-parte-b-erick-ramazzini` | `develop` | Contrato API, cliente-servidor y evidencia |
| 05C | `…-parte-c-erick-ramazzini` | `develop` | Frontera, migración y este plan |

Reglas: una rama por parte, siempre desde `develop` actualizado (`git switch develop`, `git pull origin develop`, `git switch -c <rama>`); menos de 400 líneas por PR; commits por pieza con `Commit-Pieza`; verificación con `git diff --stat origin/develop...HEAD` antes del push.

## Worktree (alternativa documentada)

La convención del grupo es trabajar en la **carpeta clonada, sin worktree**. Si hiciera falta trabajar dos partes a la vez (por ejemplo, corregir 05A mientras se escribe 05C), el plan sería:

```powershell
git worktree add ..\shi-semana5-c -b semana-5-alertas-criticas-y-notificaciones-internas-parte-c-erick-ramazzini develop
cd ..\shi-semana5-c         # trabajar y hacer commits aquí
git push -u origin semana-5-alertas-criticas-y-notificaciones-internas-parte-c-erick-ramazzini
cd ..\sistema-hospitalario-integrado-SistenasII-2026-G1
git worktree remove ..\shi-semana5-c
```

Riesgo del worktree: olvidar en qué carpeta se está trabajando. Por eso la convención sigue siendo la carpeta única y se documenta aquí solo como alternativa.

## Revisión
- Autor del PR: @ErickRamazzini. Revisa y une: Boris Quiroa (bquiroao).
- Las ramas mergeadas se borran al final, todas juntas.
