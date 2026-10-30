# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 1 — Conceptos generales, orientación a objetos y UML |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Revisó el esquema y el registro de regresiones del HIS y generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Buscar en el proyecto real el punto de partida del módulo (el registro manual de regresiones de `apply-progress.md`).
- Redactar los tres diagramas UML en PlantUML y el documento con la narrativa de alcance, los actores, el modelo de objetos, las reglas y la matriz de trazabilidad.
- Redactar la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Modele el proceso «diseño de casos manuales, campaña de regresión, evidencia, defectos y trazabilidad». Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas. Entregable/Evidencia: UML de casos de uso y narrativa de alcance.
2. «puedes empezar con este módulo?»

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `diagramas/*.puml` | Fuentes completas |
| `diagramas/*.png` | Generadas con PlantUML a partir de las fuentes |
| `semana-01-uml-proceso-qa-regresion.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **El HIS no tiene tablas de QA:** el modelo es una propuesta. Su único punto de partida real es el registro manual de regresiones del esquema.
- **Los roles QA y los permisos `qa.*` no existen** todavía en el RBAC del HIS.
- **Los valores por defecto** (umbral del 95 %, tipos y tamaño de evidencia) son decisiones de diseño, no datos del proyecto.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Actores y casos de uso | | |
| Reglas R-01..R-14 | | |
| Criterio de salida (GO / NO-GO) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé los tres diagramas y su trazabilidad.
- [ ] Comparé el punto de partida con `apply-progress.md`.

**Fecha de validación:** ____________
