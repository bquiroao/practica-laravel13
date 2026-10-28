# Declaración de uso de inteligencia artificial — Semana 10

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) · **Entrega:** Semana 10 (tarea Odoo 2482) — Diseño móvil de «publicación y consulta de documentación y manuales diferenciados por rol»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento y las 5 pantallas móviles en PlantUML (Salt), generó los PNG con PlantUML local, los revisó visualmente, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Adaptar el flujo del centro de documentación a móvil (320–430 px): jerarquía de datos y acciones, navegación, formularios, confirmaciones, reglas de breakpoint y recuperación ante conexión limitada, con dos escenarios.

## 3. Prompts relevantes

El enunciado de la tarea 2482, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-10/semana-10-diseno-movil.md`, `semana-10/diagramas/` (5 pantallas, con `.puml` y `.png`), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: la base son la semana 8 (PR #204) y el backlog de la semana 9 (PR #210), aún no integrados; si cambian, se ajustan las pantallas. Los 44 px de objetivo táctil, los 140 px por botón y la publicación en 2 pasos, el límite de 10 MB y la decisión de no guardar aprobaciones para enviarlas después son decisiones de diseño; las rutas de M2 se tomaron del front controller de Citas del PR #195, aún no integrado. Salt no fija el ancho en píxeles, así que los anchos reales se validan en el prototipo de la semana 11. La fila de la semana 10 en el `README.md` del módulo se agrega cuando se integre el PR #196, que también edita esa tabla. Al revisar los PNG, Claude Code corrigió tres errores propios: M2 listaba rutas que no existen (las comparó con `Rutas.php` del PR #195), M3 cambiaba la audiencia de citas-api sin motivo e inventaba un código de error, y Salt partía las rutas con `{id}` hasta que se escaparon las llaves.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Documento y pantallas móviles (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 28/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
