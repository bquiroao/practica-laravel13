# Declaración de uso de inteligencia artificial — Semana 14

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 14 — Plan de aseguramiento de la calidad (SQA) |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el tablero de métricas y los controles SQL con su contraprueba, y los ejecutó contra PostgreSQL. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Definir las métricas, los riesgos, los controles, los responsables y el calendario.
- Automatizar el cálculo de las métricas y probar los controles detectivos.
- Redactar la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defina el plan SQA de QA funcional, pruebas manuales y matriz de regresión: calidad de «diseño de casos manuales, campaña de regresión, evidencia, defectos y trazabilidad», confiabilidad, seguridad, trazabilidad y riesgos de campañas manuales, matriz de regresión, defectos, severidad, evidencia y trazabilidad. Asigne responsable, umbral, fuente y acción para cada métrica y control. Entregable/Evidencia: Plan SQA con 5-7 métricas, matriz de riesgos, controles preventivos/detectivos, responsables y calendario de seguimiento.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `herramientas/medir-sqa.php` | Tablero de las 6 métricas |
| `herramientas/controles-sqa.sql`, `herramientas/validar-controles.sql` | Controles detectivos y su contraprueba |
| `evidencia/*` | Salidas reales. `mutaciones.txt` es la corrida del mismo día de la semana 13, sin cambios de código en el medio |
| `semana-14-plan-sqa.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Las probabilidades e impactos de la matriz de riesgos son un juicio**, no una medición; la IA los propuso a partir de la evidencia de las semanas 11 a 13.
- **Los roles del hospital** (Líder QA, Responsable de producto, Administrador) son roles, no personas asignadas.
- **M-5 usa una campaña sintética** de 500 casos, no datos reales.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Métricas M-1..M-6 y sus umbrales | | |
| Matriz de riesgos K-01..K-11 | | |
| Controles y calendario | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `medir-sqa.php` y `validar-controles.sql`.
- [ ] Revisé la calificación de los riesgos altos.

**Fecha de validación:** ____________
