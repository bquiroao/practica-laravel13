# Declaración de uso de inteligencia artificial — Semana 13

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 13 — Revisión técnica formal |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Leyó el código del alcance contra el checklist y escribió la sonda que reproduce los hallazgos. Ejecutó la suite y las mutaciones con PostgreSQL y generó el diagrama con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Redactar el plan, el checklist, los roles, los criterios y el formato de hallazgos.
- Ejecutar una revisión piloto del código para probar el plan, y reproducir cada hallazgo con un comando.
- Redactar la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Prepare una revisión técnica formal de QA funcional, pruebas manuales y matriz de regresión, enfocada en reglas de dominio, contrato API, permisos/tenant, integridad de campañas manuales, matriz de regresión, defectos, severidad, evidencia y trazabilidad y dependencias estrictamente necesarias para «diseño de casos manuales, campaña de regresión, evidencia, defectos y trazabilidad». Entregable/Evidencia: Plan de revisión, checklist de 12-15 ítems, roles de autor/moderador/revisores, criterios de entrada/salida y formato de hallazgos.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `herramientas/sonda-revision.php` | Sonda con 6 escenarios |
| `evidencia/*` | Salidas reales: suite, mutaciones y sonda |
| `diagramas/*` | Proceso de revisión |
| `semana-13-revision-tecnica-formal.md`, `checklist-revision.md`, `registro-hallazgos.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La revisión piloto no tuvo moderador ni revisores reales.** La IA leyó el código que ella misma ayudó a escribir en semanas anteriores, el mismo problema de independencia que tiene un autor humano.
- **Las severidades las propuso la IA** según la definición del formato; en una revisión real las decide la reunión.
- **No se modificó el código del Micro-HIS** esta semana.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Checklist C-01..C-14 | | |
| Severidad de RT-01..RT-07 | | |
| Roles y calendario | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la sonda y leí cada hallazgo en el código.
- [ ] Estoy de acuerdo con la severidad de RT-01.

**Fecha de validación:** ____________
