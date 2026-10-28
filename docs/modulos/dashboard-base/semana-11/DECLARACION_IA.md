# Declaración de uso de inteligencia artificial — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Entrega:** Semana 11 (tarea Odoo 2438) — Prototipo navegable desktop y móvil del dashboard con estados de carga, vacío y error

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el prototipo, el mapa de navegación y el documento; ejecutó `capturar.mjs` con Edge sin interfaz, revisó las capturas, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Construir un prototipo navegable de escritorio y móvil del flujo principal, con su camino feliz y un error crítico, y documentarlo con un mapa de navegación y capturas reales.

## 3. Prompts relevantes

El enunciado de la tarea 2438, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: el documento, `prototipo/` (`app.js` propio; `estilos.css` e `index.html` adaptados de la semana 11 de Catálogo), `evidencia/` (`capturar.mjs`, copia idéntica del script de Catálogo, PR #282, aún sin integrar, y su salida), 12 capturas, el mapa de navegación, esta declaración y la guía. Supuestos: el prototipo simula la API, con datos ficticios y respuestas de 100 ms, y no se conecta al Micro-HIS. Los textos de las tarjetas son los de `Dominio/Dashboard.php`, ya integrado. El diseño sale de las semanas 8 a 10 (PR #205, #222 y #279), aún no integradas. La fila de la semana 11 en el `README.md` del módulo se agrega cuando se integre el PR #200. Al revisar las capturas, Claude Code corrigió dos errores propios: el error decía «No pudimos cargar citas de hoy», cuando el Micro-HIS dice «las citas de hoy», y el texto oculto de carga reutilizaba la clase del enlace de salto. También quitó la región de estado, que quedó sin uso.

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
