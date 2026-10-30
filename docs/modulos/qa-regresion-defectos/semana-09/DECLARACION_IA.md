# Declaración de uso de inteligencia artificial — Semana 9

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 9 — Evaluación heurística y WCAG 2.2 AA |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Evaluó los wireframes, escribió y ejecutó los scripts de contraste y de recortes, y redactó la checklist y el backlog. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Evaluar el diseño con heurísticas y WCAG y medir el contraste.
- Preparar la evidencia de cada hallazgo y el backlog con criterios verificables.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Evalúe el diseño con heurísticas y WCAG: teclado, foco, contraste, etiquetas, mensajes y prevención de errores. Entregable/Evidencia: Checklist completado, evidencia de al menos 6 hallazgos y backlog priorizado con criterio verificable.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `herramientas/contraste.py`, `herramientas/recortes.py` | Scripts |
| `evidencia/*` | Salida real de `contraste.py` y recortes de los wireframes |
| `checklist-evaluacion.md`, `semana-09-evaluacion-ux-qa.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La evaluación la hizo la misma herramienta que diseñó los wireframes**, sin usuarios reales ni un evaluador independiente. Tiene ese sesgo.
- **El recorrido de teclado es simulado sobre el diseño**: todavía no hay una interfaz construida.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Hallazgos y severidades | | |
| Backlog y prioridades | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `contraste.py`.
- [ ] Revisé cada recorte de hallazgo.

**Fecha de validación:** ____________
