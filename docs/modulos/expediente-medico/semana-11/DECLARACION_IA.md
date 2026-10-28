# Declaración de uso de inteligencia artificial — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 11 (tarea Odoo 2513) — Prototipo navegable desktop y móvil de la apertura y consulta autorizada del expediente

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el prototipo, el mapa de navegación y el documento; ejecutó `capturar.mjs` con Edge sin interfaz, revisó las capturas, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Construir un prototipo navegable de escritorio y móvil del flujo principal, con su camino feliz y un error crítico, y documentarlo con un mapa de navegación y capturas reales.

## 3. Prompts relevantes

El enunciado de la tarea 2513, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: el documento, `prototipo/` (`app.js` propio; `estilos.css` e `index.html` adaptados de la semana 11 de Catálogo), `evidencia/` (`capturar.mjs`, copia idéntica del script de Catálogo, PR #282, aún sin integrar, y su salida), 12 capturas, el mapa de navegación, esta declaración y la guía. Supuestos: el prototipo usa datos ficticios en memoria, no se conecta al Micro-HIS y no usa código del PR #191. La auditoría se muestra al usuario, pero no se registra en ningún lado. El diseño sale de las semanas 8 a 10 (PR #207, #224 y #281), aún no integradas. La fila de la semana 11 en el `README.md` del módulo se agrega cuando se integre el PR #191. Al revisar las capturas, Claude Code corrigió dos errores propios: la acción de cada paciente quedaba fuera de su tarjeta y parecía de la siguiente, y `aria-label` en un `<span>` genérico no se anunciaría, así que se cambió por texto visible.

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
