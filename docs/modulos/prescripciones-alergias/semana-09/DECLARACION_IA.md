# Declaración de uso de inteligencia artificial — Semana 9

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 9 — Evaluación del diseño, usabilidad y accesibilidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó `contraste.py` y `recortes.py`. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Aplicar el checklist (Nielsen, WCAG 2.2 AA y los ítems propios del módulo) a los wireframes de la semana 8.
- Escribir las herramientas para medir el contraste y marcar los hallazgos.
- Redactar los hallazgos, el backlog, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Evalúe el flujo UX de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias con heurísticas de usabilidad y WCAG: teclado, foco, contraste, etiquetas, mensajes y prevención de errores. Priorice correcciones vinculadas con prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro. Entregable/Evidencia: Checklist completado, evidencia de al menos 6 hallazgos y backlog priorizado con corrección propuesta y criterio verificable.
2. «continua por favor».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `herramientas/contraste.py`, `herramientas/recortes.py` | Código completo |
| `evidencia/contraste-wcag.txt`, `evidencia/hallazgos/*.png` | Salidas reales de las herramientas |
| `checklist-evaluacion.md`, `semana-09-evaluacion-ux-prescripciones.md` | Borradores completos |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Los colores medidos son los del generador de wireframes de la semana 8.** No se estimaron a ojo.
- **H-10 salió de comparar W4 con el contrato 1.1.0:** la regla prometía una operación que no existe.
- **La evaluación es de un solo evaluador** sobre wireframes. Está declarado como limitación.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Checklist (40 ítems) | | |
| Hallazgos H-01..H-15 y severidades | | |
| Backlog B-01..B-15 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `contraste.py` y revisé los recortes.
- [ ] Revisé las severidades y las prioridades.

**Fecha de validación:** ____________
