# Plan de issue, rama, worktree y PR · Reportes y analytics básicos

Cómo pasa el contrato API v1 del prototipo al repositorio real del SHI (Laravel 12 + Vue 3), en cambios pequeños y revisables.

## Issue

| Campo | Contenido |
|---|---|
| Título | `Reportes y analytics básicos: API v1 de indicadores trazables (ocupación)` |
| Descripción | Implementar `GET /api/v1/reportes/{tipo}`, `/desglose` y `/registros` para `ocupacion`, según `docs/modulos/reportes-analytics-basicos/semana-05/contrato-api.md` |
| Criterios de aceptación | CA-01 a CA-08 de la semana 2; además: errores con la forma común, `X-Correlation-Id` devuelto, menos de 2 s para un mes (RNF-01) |
| Fuera de alcance | Exportar PDF/CSV (Reportes exportables), dashboard en tiempo real (Dashboard de ocupación), escritura en tablas de otros módulos |
| Etiquetas | `modulo:reportes-analytics-basicos`, `api`, `solo-lectura` |
| Seguimiento | Tarea en Odoo Projects: Pendiente → Desarrollo → Terminada |

## Rama y worktree

La rama sale siempre de `develop` actualizado. El worktree deja trabajar el módulo en su propia carpeta sin mezclar cambios de otras ramas:

```bash
git fetch origin
git worktree add ../shi-reportes-api -b reportes-analytics-basicos-api-v1-erick-ramazzini origin/develop
cd ../shi-reportes-api
# ... trabajo, commits pequeños ...
git push -u origin reportes-analytics-basicos-api-v1-erick-ramazzini
# al terminar y mergear:
cd ../sistema-hospitalario-integrado-SistenasII-2026-G1
git worktree remove ../shi-reportes-api
```

Nota: en las entregas semanales del grupo se trabaja en la carpeta clonada con una rama por semana; el worktree se propone para el desarrollo real del módulo, que corre en paralelo con esas entregas.

## Cortes del trabajo en PR (máximo 400 líneas cada uno)

| PR | Contenido | Pruebas (TDD: primero en rojo) |
|---|---|---|
| 1 | Dominio: `ContratoReporte`, objetos de valor, `ReportRegistry`, `ReporteOcupacion`, interfaz `OcupacionRepository` | Unitarias del contrato (RN-02, RN-05, RN-07) |
| 2 | `OcupacionPgRepository` sobre la conexión `hospital` (solo lectura) | Integración en PostgreSQL de prueba (puertos 55431/55433) |
| 3 | `ReporteController`, `FormRequest` de filtros, rutas `api/v1/reportes/*`, permisos con Spatie y JWT | Feature: 200, 403, 422 y 503 |
| 4 | Vista Vue: filtros, indicador, desglose y registros | Pruebas de componente y revisión manual |

## Pull request

| Campo | Valor |
|---|---|
| Título | `feat(reportes-analytics-basicos): API v1 — <corte>` |
| Destino | `develop` |
| Plantilla | «PR de módulo ASII» con validación ejecutada (`composer test`) y contrato API |
| Revisión | `bquiroao` revisa y une; el autor no une su propio PR A main|
| Antes de pedir revisión | `git diff --stat origin/develop...HEAD` debe dar menos de 400 líneas |
