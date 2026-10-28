# Declaración de uso de inteligencia artificial — Semana 8

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 8 — Diseño de experiencia de usuario |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar los user flows por rol (admisión, clínico y supervisor) como diagramas de actividad.
- Generar los 6 wireframes anotados con un script de `pptxgenjs` y exportarlos con PowerPoint a PDF y PNG.
- Redactar el catálogo de mensajes a partir de los códigos de error reales del Micro-HIS, las reglas de interacción, la matriz de estados, la ayuda contextual y la protección de datos.
- Redactar la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe el flujo UX de «registro, búsqueda y actualización segura de un paciente» por sus roles autorizados. Incluya inicio, decisión, confirmación, estados vacío/carga/éxito, errores recuperables, ayuda contextual y protección de datos vinculados con identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad. Entregable/Evidencia: User flow por rol y 4–6 wireframes anotados con estados, mensajes, validaciones y reglas de interacción.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana es de diseño: no se modificó código.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-08-ux-pacientes.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo (PlantUML) |
| `wireframes/semana-08-wireframes-pacientes.pptx`, `.pdf` y `*.png` | Generados con un script escrito por la IA (basado en el de los wireframes de RBAC); exportados con PowerPoint |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- Dos diagramas no compilaban al principio. Una línea de texto que terminaba en `]` o `}` cerraba la actividad antes de tiempo, porque PlantUML usa esos caracteres como fin de forma. Se reescribieron esas líneas.
- La revisión visual de los wireframes encontró varios problemas, que se corrigieron antes de volver a exportar:
  - llamadas encima de texto en W1 y W5;
  - un código de mensaje partido en dos líneas en W3;
  - anotaciones de W2, W3 y W5 que se encimaban con la siguiente.
- Al diseñar se detectaron tres brechas del contrato (B-1..B-3, §11). Se documentan y **no** se resuelven esta semana.
- Los wireframes son diseño. No se implementó ninguna pantalla ni se modificó el Micro-HIS.
- Todos los pacientes y datos de los wireframes son ficticios.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| User flows por rol | | |
| Wireframes W1–W6 | | |
| Catálogo de mensajes | | |
| Reglas de interacción y matriz de estados | | |
| Brechas B-1..B-3 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé las 6 pantallas y sus anotaciones en el `.pptx`.
- [ ] Verifiqué que cada mensaje corresponde a un código real del backend.
- [ ] Puedo explicar por qué la búsqueda no va en la URL.
- [ ] Puedo recorrer en voz alta el flujo de cada rol.

**Fecha de validación:** ____________
