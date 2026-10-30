# Declaración de uso de inteligencia artificial — Semana 6

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 6 — Primera evaluación parcial |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió las pruebas del cambio y su implementación, y las mutaciones, y las ejecutó contra PostgreSQL. Generó la presentación con pptxgenjs, el diagrama trazable con PlantUML, la matriz y el registro, y revisó las diapositivas renderizadas. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Implementar el cambio práctico con la prueba primero.
- Preparar la presentación, el diagrama trazable, la matriz y el registro.
- Redactar la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defienda la arquitectura propuesta para «planificación de pruebas, ejecución E2E, conservación de evidencia y aplicación de quality gates» en QA, pruebas E2E, CI y guía de despliegue final: conecte actores, UML, RF/RNF, principio SOLID, capas, Repository y contrato API. Responda un cambio práctico considerando ejecuciones E2E, CI, quality gates, versiones, evidencia y despliegue reproducible. Entregable/Evidencia: Presentación de 6–8 diapositivas, diagrama trazable, matriz decisión→evidencia y registro del cambio práctico defendido.
2. «ahora estas tareas» (con `actividades2.pdf`) y «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-e2e/` (Despliegue, RegistrarDespliegue, adaptadores, esquema, contrato 1.1.0, pruebas CAM, CON-08, INT-10, PER-05 y mutaciones M26–M31) | El cambio y sus pruebas |
| `semana-06/*` | Presentación, diagrama, matriz, registro, evidencias, documento, guía y esta declaración |

**Hechos a tener en cuenta:**
- **El pedido de cambio es un escenario planteado para el ejercicio** (la 2.4.0 instalada con otro digest), no un incidente real del HIS.
- **El defecto del almacén** lo escribió la IA en la semana 3 y lo encontró una prueba intermitente esta semana.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Cambio práctico (R-11) | | |
| Presentación | | |
| Matriz decisión → evidencia | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ensayé la presentación con el tiempo de la guía.
- [ ] Ejecuté la suite y las mutaciones.

**Fecha de validación:** ____________
