# Declaración de uso de inteligencia artificial — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Entrega:** Semana 12 (tarea Odoo 2469) — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad con el prototipo, y respuesta a un cambio solicitado

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, la presentación y el cambio en el prototipo (`app.js` y `estilos.css` de la semana 11). Corrió `node --check`, el `capturar.mjs` de la semana 11 con Edge sin interfaz para las 10 capturas y la medición, un cálculo de contraste con la fórmula de WCAG y Marp para las 5 diapositivas. Revisó a la vista dos capturas; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender con el prototipo las decisiones de componentes, UX, accesibilidad y movilidad del flujo, y responder un cambio solicitado con evidencia de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2469, tomado textualmente de Odoo, y la orden del estudiante de seguir con la S12 en lotes de 3, con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales:
- `semana-12-defensa-prototipo.md` y `semana-12-presentacion.md`.
- `capturas/` (5 rutas en escritorio y móvil, 10 PNG) y `evidencia/salida-medicion.txt`.
- Esta declaración, la guía y el cambio en `semana-11/prototipo/` (`app.js` y `estilos.css`).

Supuestos:
- El cambio solicitado (cancelar desde la agenda) lo propuso Claude Code como un pedido plausible de recepción, porque el flujo incluye CU-03 y el prototipo de la S11 no lo tenía; no lo pidió un evaluador real.
- Se modifica el prototipo de la S11 en su lugar, porque el enunciado pide demostrar con ese prototipo. Sus capturas de la S11 siguen válidas para los pasos 1 a 5, salvo que la agenda ahora tiene la columna «Acción».
- Las reglas siguen en el backend; el prototipo solo las muestra.
- El texto «error E4» del panel no se cambió: la semana 6 (PR #435, sin integrar) define E6 para el horario tomado.
- La fila de la semana 12 del `README.md` del módulo va en el PR de mantenimiento de filas pendientes.

Validación:
- `node --check app.js` sin errores.
- 15 de 15 mediciones sin scroll horizontal, con el foco en `TEXTAREA` cuando falta el motivo.
- Contraste del botón de peligro: 6,47:1.
- Marp generó las 5 diapositivas.

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
