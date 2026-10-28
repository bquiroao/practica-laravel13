# Declaración de uso de inteligencia artificial — Semana 8

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) · **Entrega:** Semana 8 (tarea Odoo 2480) — Flujo UX por rol y wireframes anotados de «publicación y consulta de documentación y manuales diferenciados por rol»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, el user flow y los 5 wireframes en PlantUML (Salt), generó los PNG con PlantUML local, los revisó visualmente, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Diseñar el flujo UX por rol autorizado y sus wireframes anotados con estados, mensajes, validaciones, reglas de interacción, ayuda contextual y protección de datos.

## 3. Prompts relevantes

El enunciado de la tarea 2480, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-08/semana-08-flujo-ux.md`, `semana-08/diagramas/` (user flow y 5 wireframes, con `.puml` y `.png`), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: los roles, casos de uso y reglas salen de la semana 1; el lint de OpenAPI, la búsqueda de datos reales y la vista de diferencias son diseños propuestos que el Micro-HIS aún no implementa; los textos de error siguen los códigos E2–E8 de la semana 3; las rutas del front controller vienen del PR #196 (semana 4), aún no integrado; la fila de la semana 8 en el `README.md` del módulo se agrega cuando se integre el PR #196, que también edita esa tabla, para no crear un conflicto.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Documento, user flow y wireframes (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 27/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
