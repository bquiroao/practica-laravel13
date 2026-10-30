# Declaración de uso de inteligencia artificial — Semana 9

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 9 — Evaluación heurística y WCAG, checklist, hallazgos y backlog |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Evaluó los wireframes de la semana 8 y contrastó los mensajes con el código del servidor. Escribió los scripts de contraste y de recortes y los ejecutó. Redactó el checklist, los hallazgos y el backlog. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Aplicar las heurísticas y los criterios WCAG a cada wireframe.
- Medir el contraste.
- Encontrar incoherencias entre el diseño y el servidor.
- Priorizar el backlog.

## 3. Prompts relevantes

1. Instrucción de la tarea (semana 9 de `actividades2.pdf`): evaluación con heurísticas y WCAG, checklist completo, al menos 6 hallazgos y backlog priorizado con criterios verificables.
2. «ahora estas tareas» (con `actividades2.pdf`) y «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-09/herramientas/*` | Script de contraste y de recortes |
| `semana-09/evidencia/*` | Salida del contraste y recortes de los hallazgos |
| `semana-09/*.md` | Checklist, evaluación, guía y esta declaración |

**Hechos a tener en cuenta:**
- **La evaluación la hizo la misma herramienta que diseñó los wireframes** en la semana 8. Es una evaluación por un experto, no con usuarios. Conviene que otra persona revise los hallazgos de severidad 3.
- **El recorrido de teclado es simulado sobre el diseño**, no sobre una interfaz real. Se repite con el prototipo de la semana 11.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Checklist | | |
| Hallazgos y severidades | | |
| Backlog y prioridades | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `contraste.py` y revisé los recortes.
- [ ] Revisé las severidades de H-01, H-02 y H-09.

**Fecha de validación:** ____________
