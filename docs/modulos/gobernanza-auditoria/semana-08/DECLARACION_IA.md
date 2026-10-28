# Declaración de uso de inteligencia artificial — Semana 8

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 8 — User flow por rol y wireframes anotados |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar los flujos por rol y los 6 wireframes a partir de la API y los componentes ya existentes.
- Generar los wireframes (pptxgenjs, exportados con PowerPoint) y los flujos (PlantUML).
- Redactar el catálogo de mensajes, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe el flujo UX de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema por sus roles autorizados. Incluya inicio, decisión, confirmación, estados vacío/carga/éxito, errores recuperables, ayuda contextual y protección de datos relacionados con audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas. Entregable/Evidencia: User flow por rol y 4–6 wireframes anotados con estados, mensajes, validaciones y reglas de interacción.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-08/wireframes/*`, `semana-08/diagramas/*` | Borradores; se revisaron en imagen y se corrigieron 4 superposiciones de llamadas y texto |
| `semana-08/*.md` | Borradores |

**Hechos a tener en cuenta:**
- **Los textos M-01 y M-02 son los del servidor** (`FiltroRangoFechas`), copiados tal cual.
- **El resto de los mensajes es propuesta** y conviene validarlo con usuarios reales.
- **Los nombres de hospital y las cifras de los wireframes son ficticios.**

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Flujos por rol | | |
| Wireframes W1–W6 | | |
| Catálogo de mensajes | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé que cada wireframe corresponda a respuestas reales de la API.
- [ ] Recorrí los dos flujos con los wireframes.

**Fecha de validación:** ____________
