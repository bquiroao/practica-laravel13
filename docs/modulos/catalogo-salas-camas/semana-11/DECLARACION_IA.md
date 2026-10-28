# Declaración de uso de inteligencia artificial — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Entrega:** Semana 11 (tarea Odoo 2453) — Prototipo navegable desktop y móvil del cambio de estado operativo de una cama

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el prototipo (HTML, CSS y JavaScript), el script de captura `capturar.mjs`, el mapa de navegación y el documento; ejecutó el script con Edge sin interfaz, revisó las capturas, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Construir un prototipo navegable de escritorio y móvil del flujo principal, con su camino feliz y un error crítico, y documentarlo con un mapa de navegación y capturas reales.

## 3. Prompts relevantes

El enunciado de la tarea 2453, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-11/semana-11-prototipo.md`, `semana-11/prototipo/` (`index.html`, `estilos.css`, `app.js`), `semana-11/evidencia/` (`capturar.mjs` y su salida), `semana-11/capturas/` (5 de escritorio y 5 de móvil, generadas por el script), `semana-11/diagramas/` (mapa de navegación), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: el prototipo usa datos ficticios en memoria y no se conecta al Micro-HIS, así que no depende de código sin integrar; el diseño sale de las semanas 8 a 10 (PR #202, #208 y #225), aún no integradas. La simulación del conflicto está en un panel marcado como del prototipo. La fila de la semana 11 en el `README.md` del módulo se agrega cuando se integre el PR #194. Al construirlo, Claude Code corrigió tres errores propios:

- el enlace «Saltar al contenido» chocaba con las rutas por hash;
- la primera medición de ancho con `--window-size` daba 504 px en vez de 360, porque el modo nuevo sin interfaz tiene un ancho mínimo, y se cambió a la emulación de DevTools;
- una sustitución de `perl` dejó vacío el nombre de la ruta en la salida, y se corrigió antes de medir;
- el script usaba un puerto de depuración fijo, que un Edge huérfano ocupó; ahora Edge elige un puerto libre, y las capturas se repitieron y salieron idénticas.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Prototipo, script, capturas y documento (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 28/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
