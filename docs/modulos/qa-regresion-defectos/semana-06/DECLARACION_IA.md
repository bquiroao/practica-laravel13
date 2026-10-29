# Declaración de uso de inteligencia artificial — Semana 6

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 6 — Primera evaluación parcial: defensa de la arquitectura |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió las pruebas del cambio antes que el código, lo implementó y corrió las pruebas y mutaciones contra un PostgreSQL 18 local. Generó la presentación con pptxgenjs, la exportó a PDF con PowerPoint y generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Elegir e implementar el cambio práctico (R-15) con pruebas primero.
- Construir la matriz decisión→evidencia, el diagrama trazable y la presentación con sus notas del orador.
- Redactar el registro del cambio, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Primera evaluación parcial: Defienda la arquitectura del módulo conectando actores, UML, RF/RNF, principio SOLID, capas, Repository y contrato API. Responda un cambio práctico sobre campañas, matriz, defectos, severidad, evidencia y trazabilidad. Entregable/Evidencia: 6–8 diapositivas, diagrama trazable, matriz decisión→evidencia y registro del cambio práctico.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios de `micro-his-qa/` (R-15, `tests/Cambio`, `bin/demo-r15.php`, mutaciones M30–M33, OpenAPI 1.1.0) | Código y pruebas |
| `presentacion/*` | Presentación y PDF, con notas del orador |
| `diagramas/*`, `matriz-decision-evidencia.md`, `registro-cambio-practico.md` | Borradores |
| `evidencia/*` | Salidas reales |
| `semana-06-defensa-arquitectura.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **El pedido del cambio es simulado:** no hubo una versión 1.4.0 real con ese defecto. La secuencia que lo provoca sí es real y la reproduce `demo-r15.php`.
- **Las salidas en rojo son reales:** las pruebas del cambio se corrieron antes de escribir el código, y la mutación M32 sobrevivió en la primera corrida.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del cambio R-15 | | |
| Presentación y notas | | |
| Matriz decisión → evidencia | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ensayé la presentación con las notas.
- [ ] Ejecuté `demo-r15.php` y las pruebas del cambio.
- [ ] Revisé cada fila de la matriz.

**Fecha de validación:** ____________
