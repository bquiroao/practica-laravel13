# Declaración de uso de inteligencia artificial — Semana 8

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 8 — User flow por rol y wireframes anotados |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar los flujos por rol y los 6 wireframes a partir de la API 1.1.0 y de los componentes de la semana 7.
- Generar los wireframes (pptxgenjs, exportados con PowerPoint) y los flujos (PlantUML).
- Redactar el catálogo de mensajes, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe el flujo UX de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias por sus roles autorizados. Incluya inicio, decisión, confirmación, estados vacío/carga/éxito, errores recuperables, ayuda contextual y protección de datos relacionados con prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro. Entregable/Evidencia: User flow por rol y 4–6 wireframes anotados con estados, mensajes, validaciones y reglas de interacción.
2. «continua por favor».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-08/wireframes/*`, `semana-08/diagramas/*` | Borradores. Se revisaron en imagen y se corrigieron 5 llamadas que tapaban texto (W3 y W6) |
| `semana-08/*.md` | Borradores |

**Hechos a tener en cuenta:**
- **Mensajes y contrato:** cada mensaje se ligó a un código real del contrato. La IA señaló la brecha M-07 (override) en lugar de suponer un dato que la API no entrega.
- **Los textos de los mensajes son una propuesta** y conviene validarlos con médicos y farmacia.
- **Datos ficticios:** los pacientes, los medicamentos, las cifras y los nombres de hospital de los wireframes son ficticios.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Flujos por rol | | |
| Wireframes W1–W6 | | |
| Catálogo de mensajes y brecha M-07 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé que cada wireframe corresponda a respuestas reales de la API.
- [ ] Recorrí los dos flujos con los wireframes.

**Fecha de validación:** ____________
