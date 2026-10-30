# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 2 — RF/RNF, criterios de aceptación y DIP |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el ejemplo antes/después, los datos, los criterios ejecutables, la medición de dependencias y las mutaciones, y los ejecutó. Generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Definir RF, RNF y criterios de aceptación trazados a la semana 1.
- Construir y verificar el ejemplo de DIP.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Aplique DIP al diseño del flujo «planificación de pruebas, ejecución E2E, conservación de evidencia y aplicación de quality gates». Defina RF/RNF y criterios de aceptación; muestre el diseño antes/después, justifique responsabilidades y dependencias y aporte evidencia verificable. Entregable/Evidencia: RF/RNF, criterios de aceptación y ejemplo SOLID.
2. «ahora estas tareas» (con `actividades2.pdf`).

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `ejemplo-dip/*` | Código, datos y verificaciones |
| `evidencia/*` | Salidas reales |
| `diagramas/*` | Antes y después |
| `semana-02-dip-requerimientos-diseno.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La primera versión de la regla tenía un agujero** (omitir subía la tasa), y su criterio pasaba igual porque probaba el caso equivocado. Se corrigió y se documentó (§9).
- `ReporteJson` imita el estilo de exportación de otro proveedor de CI; **no es el formato oficial de ningún producto**.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| RF/RNF y criterios | | |
| Diseño después de DIP | | |
| OMITIDO como no aprobado | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `verificar.php`, `medir-dip.php` y `mutaciones.php`.
- [ ] Revisé el antes y el después.

**Fecha de validación:** ____________
