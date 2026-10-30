# Declaración de uso de inteligencia artificial — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Entrega:** Semana 6 (tarea Odoo 2493) — Primera evaluación parcial: defensa de la arquitectura de «consolidación y consulta de ocupación por sala y estado de cama» y cambio práctico

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió la presentación, la matriz, el registro del cambio, el diagrama y el conteo de estancias en el Micro-HIS con su prueba. Corrió las pruebas y el guion de consistencia antes y después, la prueba de contrato de la semana 5, PlantUML local y Marp (`npx @marp-team/marp-cli@4.0.0`) para comprobar las 8 diapositivas; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender la arquitectura del módulo conectando actores, UML, RF/RNF, ISP, capas, Repository y contrato API, y responder un cambio práctico de procedencia de datos (dos estancias abiertas en una cama) con evidencia ejecutable de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2493, tomado textualmente de Odoo, y la orden del estudiante de hacer la S6 en lotes de 3, con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-06-defensa-arquitectura.md`, `semana-06-diapositivas.md`, `diagramas/` (1 `.puml` y 1 `.png`), `evidencia/` (`probar-consistencia.php` y las salidas de antes, de después y de las pruebas), esta declaración, la guía y, en `micro-his-dashboard-ocupacion/`, `Ocupacion.php`, el comentario del puerto, los dos adaptadores y `pruebas.php`.

Supuestos: el cambio práctico lo eligió Claude Code entre los temas del enunciado (estados de cama y procedencia de datos), porque R-04 ya estaba en la S1 y el hallazgo H-03 de la S13 mostraba que no se cumplía; no lo pidió un evaluador. InMemory acepta `true` y `false` como 1 y 0 para no romper los guiones de las semanas 5 y 13. El tablero marca y no corrige (R-09). En PostgreSQL, la exclusión de `bed_stays` ya evita estancias solapadas; la marca cubre datos llegados por otra vía. La fila de la semana 6 del `README.md` del módulo va en el PR de mantenimiento de las filas pendientes.

Validación: las pruebas pasan de 29 a 31, en verde. El guion de consistencia pasa de `[]` a `[A1]` con las tablas reales en PDO, y Marp generó las 8 diapositivas. La prueba de contrato de la S5 sigue cumpliéndose, y la S13 da H-03 «no reproducido». Claude Code corrigió un error propio: la primera versión de la prueba nueva consolidaba sobre la misma base que usan las pruebas de persistencia siguientes, y rompía «se conserva el corte anterior». Se pasó a una base aparte.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Presentación, matriz, registro, diagrama y cambio en el Micro-HIS (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 29/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
