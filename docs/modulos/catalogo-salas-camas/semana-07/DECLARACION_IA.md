# Declaración de uso de inteligencia artificial — Semana 7

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Entrega:** Semana 7 (tarea Odoo 2449) — Componentes backend y frontend, contratos de entrada y salida y refactor conceptual del punto de mayor acoplamiento

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Leyó el Micro-HIS, el contrato de la semana 5 y el prototipo de la semana 11. Escribió el documento, el diagrama de componentes y el script de acoplamiento, y corrió ese script y PlantUML local; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Diseñar los componentes backend y frontend del flujo, fijar sus contratos de entrada y salida, y encontrar con una medición el punto de mayor acoplamiento para refactorizarlo conceptualmente.

## 3. Prompts relevantes

El enunciado de la tarea 2449, tomado textualmente de Odoo, y la orden del estudiante de seguir con la S7 en lotes de 3, con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo la salida real: `semana-07-componentes-refactor.md`, `diagramas/` (1 `.puml` y 1 `.png`), `evidencia/` (`medir-acoplamiento.php` y su salida), esta declaración y la guía.

Supuestos:
- Los componentes de frontend salen del prototipo de la S11; `ClienteCatalogo` es el componente que el prototipo todavía no tiene, porque usa datos en memoria.
- El script mide el acoplamiento por los nombres de tipos propios que aparecen en cada clase, sin contar `bootstrap.php`, que es la raíz de composición.
- El punto de mayor acoplamiento se elige entre capas (UI y dominio), porque dentro del backend las dependencias ya apuntan hacia lo estable. Es un juicio de diseño, apoyado en la medición y en la lectura de `app.js`.
- El refactor es conceptual, como pide el enunciado: no cambia código. La medición es sobre `develop` y no incluye el cambio de capacidad de la semana 6 (PR #433, sin integrar).
- La fila de la semana 7 del `README.md` del módulo va en el PR de mantenimiento de filas pendientes.

Validación: el script corre sin errores, y PlantUML generó el diagrama, revisado visualmente. Se comprobó a mano, leyendo `EstadoCama.php` y `app.js`, que las transiciones están en los dos lugares.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Documento, diagrama y script de acoplamiento (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 29/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
