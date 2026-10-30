# Declaración de uso de inteligencia artificial — Semana 13

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 13 — Calidad del software y revisión técnica formal |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó los criterios de entrada (que corren la suite) y la verificación de la autorrevisión contra el kernel del Micro-HIS. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Redactar el plan, el checklist, el formato de hallazgos y las plantillas, adaptando la estructura de la semana 13 de Auditoría.
- Hacer la autorrevisión del Micro-HIS y escribir la herramienta que reproduce cada hallazgo.
- Adaptar la herramienta de criterios de entrada; redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Prepare una revisión técnica formal de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias, enfocada en reglas de dominio, contrato API, permisos/tenant, integridad de prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro y dependencias estrictamente necesarias. Entregable/Evidencia: Plan de revisión, checklist de 12–15 ítems, roles de autor/moderador/revisores, criterios de entrada/salida y formato de hallazgos.
2. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `plan-revision-tecnica.md`, `checklist-revision.md`, `formato-hallazgos.md`, `plantillas/*` | Borradores |
| `autorrevision-autor.md` | Borrador. Los 7 hallazgos se reprodujeron contra el kernel real |
| `herramientas/*` | Código. `criterios-entrada.php` se adaptó del de Auditoría; `verificar-autorrevision.php` es nuevo |
| `evidencia/*`, `diagramas/*.png` | Salidas reales de las herramientas |
| `semana-13-revision-tecnica-prescripciones.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **AR-02 y AR-03 eran desconocidos** antes de esta autorrevisión. Ninguna prueba del Micro-HIS los cubre.
- **No se modificó el Micro-HIS** en esta entrega: los hallazgos quedan abiertos para el retrabajo.
- **Los revisores son «por confirmar»:** la reunión no se hizo.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Checklist (15 ítems) | | |
| Severidades de AR-01..AR-07 | | |
| Roles y calendario | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `criterios-entrada.php` y `verificar-autorrevision.php`.
- [ ] Revisé las severidades con un criterio clínico.

**Fecha de validación:** ____________
