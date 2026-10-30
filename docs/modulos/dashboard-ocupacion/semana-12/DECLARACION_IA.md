# Declaración de uso de inteligencia artificial — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Entrega:** Semana 12 (tarea Odoo 2499) — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad con el prototipo, y respuesta a un cambio solicitado

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, la presentación y el cambio en el prototipo (`app.js` y `estilos.css` de la semana 11). Corrió `node --check`, el `capturar.mjs` de la semana 11 con Edge sin interfaz para las 6 capturas y la medición, y Marp para las 5 diapositivas. Revisó a la vista tres capturas y con eso ajustó la jerarquía de encabezados; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender con el prototipo las decisiones de componentes, UX, accesibilidad y movilidad del flujo, y responder un cambio solicitado con evidencia de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2499, tomado textualmente de Odoo, y la orden del estudiante de seguir con la S12 en lotes de 3, con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-12-defensa-prototipo.md`, `semana-12-presentacion.md`, `capturas/` (3 rutas en escritorio y móvil, 6 PNG), `evidencia/salida-medicion.txt`, esta declaración, la guía y el cambio en `semana-11/prototipo/` (`app.js` y `estilos.css`).

Supuestos:
- El cambio solicitado (ver las camas inconsistentes y dónde se corrigen) lo propuso Claude Code como un pedido plausible de jefatura, porque une R-04, B-08 de la S9 y la detección de la S6; no lo pidió un evaluador real.
- Las camas A/104 y B/203 son datos ficticios del prototipo, con la misma forma que el campo `inconsistentes` del corte.
- Se modifica el prototipo de la S11 en su lugar; sus capturas siguen válidas para los pasos 1 a 6, salvo que el detalle de sala ahora agrega la sección nueva.
- La fila D-01 cita la S7 (PR #459) y la §4 cita la S6 (PR #447), ambas sin integrar, sin copiar su contenido.
- La fila de la semana 12 del `README.md` del módulo va en el PR de mantenimiento de filas pendientes.

Validación: `node --check app.js` sin errores. 9 de 9 mediciones sin scroll horizontal y con el foco en `H1`; Marp generó las 5 diapositivas. Claude Code corrigió un detalle propio: el `<h2>` nuevo heredaba un tamaño mayor que el `<h1>`. Lo vio en la captura y lo ajustó en `estilos.css` antes de la medición final.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Documento, presentación, capturas y cambio en el prototipo (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 30/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
