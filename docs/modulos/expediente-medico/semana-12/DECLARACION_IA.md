# Declaración de uso de inteligencia artificial — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 12 (tarea Odoo 2514) — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad con el prototipo, y respuesta a un cambio solicitado

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, la presentación y el cambio en el prototipo (`app.js` y `estilos.css` de la semana 11). Corrió `node --check`, el `capturar.mjs` de la semana 11 con Edge sin interfaz para las 8 capturas y la medición, y Marp para las 5 diapositivas. Revisó a la vista cinco capturas; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender con el prototipo las decisiones de componentes, UX, accesibilidad y movilidad del flujo, y responder un cambio solicitado con evidencia de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2514, tomado textualmente de Odoo, y la orden del estudiante de seguir con la S12 en lotes de 3, con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-12-defensa-prototipo.md`, `semana-12-presentacion.md`, `capturas/` (4 rutas en escritorio y móvil, 8 PNG), `evidencia/salida-medicion.txt`, esta declaración, la guía y el cambio en `semana-11/prototipo/` (`app.js` y `estilos.css`).

Supuestos:
- El cambio solicitado (acceso de emergencia sin consentimiento) lo propuso Claude Code como un pedido plausible de emergencias, porque CU-04, RF-04, W4 de la S8 y B-05 de la S9 lo piden y la S11 no lo tenía; no lo pidió un evaluador real.
- El paciente A. Pérez Gil (HC-000105, atención E-80, sin consentimiento) es ficticio y se agregó para este caso. La captura 01 de la S11 ahora tendría una tarjeta más; las demás siguen válidas.
- La hora de cierre usa un reloj fijo (10:40 → 11:40); en producción la impone el backend.
- La fila D-01 cita la S7 (PR #462) y la §4 cita la S6 (PR #448), ambas sin integrar, sin copiar su contenido.
- La fila de la semana 12 del `README.md` del módulo va en el PR de mantenimiento de filas pendientes.

Validación: `node --check app.js` sin errores. 12 de 12 mediciones sin scroll horizontal; el foco queda en `H1`, salvo en la justificación corta, donde va al `TEXTAREA` a propósito; Marp generó las 5 diapositivas. **Corrección propia antes del commit:** la primera versión de Claude Code ponía la emergencia en la pantalla «sin atención activa» (HC-000098). Al contrastarla con R-07 y CU-04 de la S1, vio que la emergencia reemplaza el consentimiento, no la atención activa. Descartó esa versión y sus capturas, y la rehízo sobre la historia externa sin consentimiento.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Primera versión del cambio (emergencia sin atención activa) | Descartado | Contradecía R-07; Claude Code la descartó antes del commit. |
| Documento, presentación, capturas y cambio en el prototipo (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 30/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
