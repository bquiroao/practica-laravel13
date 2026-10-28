# Declaración de uso de inteligencia artificial — Semana 6

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 6 — Primera evaluación parcial: defensa de la arquitectura y cambio práctico |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó las pruebas, las mutaciones, el lint y la exportación del deck (PowerPoint). Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Proponer el cambio práctico (anulación con motivo, CU-07 de la semana 1) y escribir sus pruebas antes que el código.
- Implementar el cambio en las cuatro capas, el esquema, el contrato 1.1.0 y las mutaciones.
- Generar la presentación de 8 diapositivas con notas del orador, el diagrama trazable, la matriz decisión → evidencia y el registro del cambio.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defienda la arquitectura propuesta para «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias: conecte actores, UML, RF/RNF, principio SOLID, capas, Repository y contrato API. Responda un cambio práctico considerando prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro. Entregable/Evidencia: Presentación de 6–8 diapositivas, diagrama trazable, matriz decisión→evidencia y registro del cambio práctico defendido.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `micro-his-prescripciones/` (anulación en las cuatro capas, esquema, contrato 1.1.0, pruebas ANU, CON-*-08, INT-10, mutaciones M31..M33, `bin/demo-anulacion.php`) | Código completo |
| `presentacion/*.pptx` y `*.pdf` | Generados con pptxgenjs y exportados con PowerPoint |
| `semana-06-defensa-arquitectura.md`, `matriz-decision-evidencia.md`, `registro-cambio-practico.md` | Borradores completos |
| `diagramas/*.puml` y `*.png` | Borrador completo; imágenes generadas con PlantUML |
| `evidencia/*.txt` | Salidas reales de la ejecución |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- La elección del cambio práctico la propuso la IA. Se eligió porque CU-07 estaba modelado desde la semana 1 y pendiente de implementar.
- Las pruebas ANU-01..07 se ejecutaron **antes** de implementar el cambio y las 7 fallaron; esa salida se guardó como evidencia.
- La semana 6 se entregó después de la 7 porque sus instrucciones llegaron más tarde. Está anotado en el documento.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Cambio práctico (anulación) y la asimetría del bloqueo seguro | | |
| Matriz D-01..D-23 | | |
| Presentación y notas del orador | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ensayé la presentación con las notas del orador.
- [ ] Ejecuté el demo y las pruebas del cambio.

**Fecha de validación:** ____________
