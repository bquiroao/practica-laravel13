# Declaración de uso de inteligencia artificial — Semana 8

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 8 — User flows por rol y wireframes anotados |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Generó los flujos con PlantUML y los wireframes con pptxgenjs, los exportó a PDF con PowerPoint y los revisó visualmente. Verificó los códigos, permisos y pruebas citados contra el código. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar los flujos por rol y las pantallas de la consola de release.
- Armar el catálogo de mensajes desde los códigos del contrato.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea (semana 8 de `actividades2.pdf`): user flow por rol y 4–6 wireframes anotados con estados, mensajes, validaciones y reglas de interacción.
2. «ahora estas tareas» (con `actividades2.pdf`) y «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-08/diagramas/*` | Los 4 user flows |
| `semana-08/wireframes/*` | Los 6 wireframes (PPTX, PDF e imágenes) |
| `semana-08/*.md` | Documento, guía y esta declaración |

**Hechos a tener en cuenta:**
- **Las pantallas son un diseño.** No hay código de UI esta semana. Los datos de ejemplo (versión 2.5.0, ejecución #14, horas) son ficticios.
- **Las brechas B-1…B-3 son reales:** el contrato 1.1.0 tiene una sola operación `GET`.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| User flows | | |
| Wireframes | | |
| Catálogo de mensajes | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé cada wireframe contra el flujo de su rol.
- [ ] Revisé que los mensajes correspondan a los códigos del contrato.

**Fecha de validación:** ____________
