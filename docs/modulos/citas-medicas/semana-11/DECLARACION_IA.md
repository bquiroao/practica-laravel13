# Declaración de uso de inteligencia artificial — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Entrega:** Semana 11 (tarea Odoo 2468) — Prototipo navegable desktop y móvil de la solicitud y confirmación de una cita

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el prototipo, el mapa de navegación y el documento; ejecutó `capturar.mjs` con Edge sin interfaz, revisó las capturas, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Construir un prototipo navegable de escritorio y móvil del flujo principal, con su camino feliz y un error crítico, y documentarlo con un mapa de navegación y capturas reales.

## 3. Prompts relevantes

El enunciado de la tarea 2468, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: el documento, `prototipo/` (`app.js` propio; `estilos.css` e `index.html` adaptados de la semana 11 de Catálogo), `evidencia/` (`capturar.mjs`, copia del script de Catálogo de la rama `semana-11-catalogo-salas-camas`, aún sin integrar, y su salida), 10 capturas, el mapa de navegación, esta declaración y la guía. Supuestos: el prototipo usa datos ficticios en memoria y no se conecta al Micro-HIS. El diseño sale de las semanas 8 a 10 (PR #203, #209 y #226), aún no integradas. La simulación de la concurrencia está en el panel del prototipo. La fila de la semana 11 en el `README.md` del módulo se agrega cuando se integre el PR #195. Al revisar el código, Claude Code corrigió dos fallos propios antes de capturar: una ruta del paso 3 sin hora rompía la página, y el envío fallaba si no quedaban horarios libres. El script se actualizó después a un puerto de depuración libre (ver la semana 11 de Contratos); las capturas se repitieron y salieron idénticas.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Prototipo, capturas y documento (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 28/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
