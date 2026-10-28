# Declaración de uso de inteligencia artificial — Semana 10

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Entrega:** Semana 10 (tarea Odoo 2437) — Diseño móvil de «navegación del dashboard con estados de carga, vacío, error y resumen inicial»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento y las 5 pantallas móviles en PlantUML (Salt), generó los PNG con PlantUML local, los revisó visualmente, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Adaptar el dashboard a móvil (320–430 px): jerarquía de datos y acciones, navegación, formularios, confirmaciones, reglas de breakpoint y recuperación ante conexión limitada, con dos escenarios.

## 3. Prompts relevantes

El enunciado de la tarea 2437, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-10/semana-10-diseno-movil.md`, `semana-10/diagramas/` (5 pantallas, con `.puml` y `.png`), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: la base son la semana 8 (PR #205) y el backlog de la semana 9 (PR #222), aún no integrados; si cambian, se ajustan las pantallas. Los 44 px de objetivo táctil, el corte de 376 px para dos columnas, el orden de las tarjetas por rol y el aviso 2 minutos antes de expirar son decisiones de diseño. Los textos de las tarjetas son los de `Dominio/Dashboard.php` del Micro-HIS. Salt no fija el ancho en píxeles, así que los anchos reales se validan en el prototipo de la semana 11. La fila de la semana 10 en el `README.md` del módulo se agrega cuando se integre el PR #200, que también edita esa tabla. Al revisar los PNG, Claude Code corrigió dos errores propios: M1 y M3 daban cifras de camas contradictorias para el mismo hospital, y la trazabilidad citaba B-01 para el foco de los diálogos, que en la semana 9 es B-02.

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
