# Declaración de uso de inteligencia artificial — Semana 1

**Estudiante:** Josué David Morales Ramírez (carné 1890-23-10545) · **Módulo:** Dashboard base UI/UX e implementación transversal · **Entrega:** Semana 1 (tarea Odoo 2428) — UML del proceso «navegación del dashboard con estados de carga, vacío, error y resumen inicial»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Revisó el frontend existente, escribió los borradores, generó los PNG con PlantUML local (`-charset UTF-8`), creó la rama, hizo el commit y, tras la aprobación del estudiante, hizo el push y abrió el PR con `gh`. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Basar el modelo en el código real (`AppLayout.vue`, `HomePage.vue`, `router/guards.js`, `plugins/axios.js`, `stores/auth.js`) y redactar la narrativa, las reglas, los casos de uso, la matriz, los tres diagramas, esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2428, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo, tomando la opción más segura ante lo ambiguo y anotándola como supuesto.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-01/semana-01-uml-navegacion-dashboard.md`, `semana-01/diagramas/` (3 `.puml` y 3 `.png`), `DECLARACION_IA.md`, `GUIA_DEFENSA_ORAL.md`, el README del módulo y la fila del módulo en `docs/README.md`. No se modificó código. Supuestos propuestos por la IA: un actor «Usuario del hospital» con dos especializaciones (personal clínico y administrador, como en el módulo RBAC); tarjetas iniciales de pacientes, citas del día y camas disponibles; un endpoint de conteo por tarjeta (`GET /dashboard/cards/{tarjeta}`, preliminar hasta la Semana 5); el límite de espera de 10 s; y las reglas R-03 a R-05, R-08 y R-09 como decisiones de diseño.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Narrativa, diagramas y reglas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 25/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
