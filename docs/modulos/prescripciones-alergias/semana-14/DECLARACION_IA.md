# Declaración de uso de inteligencia artificial — Semana 14

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 14 — Confiabilidad y aseguramiento de la calidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó la medición de las métricas (con PostgreSQL y mutaciones), la inyección de defectos en copias temporales del Micro-HIS y los controles SQL sobre una base temporal. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar las 7 métricas, el corpus clínico de M3, los controles y la matriz de riesgos, adaptando la estructura de la semana 14 de Auditoría.
- Escribir las herramientas de medición, de detección y los controles SQL, y probarlos.
- Redactar el plan, la matriz, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defina el plan SQA de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias: calidad, confiabilidad, seguridad, trazabilidad y riesgos de prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro. Asigne responsable, umbral, fuente y acción para cada métrica y control. Entregable/Evidencia: Plan SQA con 5–7 métricas, matriz de riesgos, controles preventivos/detectivos, responsables y calendario de seguimiento.
2. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `plan-sqa-prescripciones.md`, `matriz-riesgos.md`, `plantillas/tablero-seguimiento.csv` | Borradores |
| `herramientas/*` | Código. `medir-sqa.php` y `probar-deteccion.php` se adaptaron de Auditoría; el corpus de M3 y los controles SQL son nuevos |
| `evidencia/*` | Salidas reales de las herramientas |
| `semana-14-sqa-prescripciones.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **El corpus de M3 lo escribió la IA,** no un farmacéutico: hay que validarlo clínicamente.
- **M5 dio un falso rojo** en la primera versión por un error de la herramienta. Se reprodujo aparte, se corrigió y quedó documentado.
- **`SERVICIO_NO_DISPONIBLE` sin emisor** lo encontró la medición de M6; no se conocía antes.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Las 7 métricas y sus umbrales | | |
| Corpus de M3 | | |
| Matriz de riesgos | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `medir-sqa.php` y `probar-deteccion.php`.
- [ ] Revisé el corpus y los umbrales con un criterio clínico.

**Fecha de validación:** ____________
