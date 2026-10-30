# Declaración de uso de inteligencia artificial — Semana 7

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 7 — Componentes backend y frontend, refactor del punto más acoplado |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Midió el acoplamiento y grabó la caracterización antes del refactor. Hizo el refactor y corrió las pruebas, las mutaciones y las demos contra un PostgreSQL 18 local. Generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir la medición de acoplamiento y los escenarios de caracterización.
- Elegir el corte del refactor, implementarlo y verificar que no cambia el comportamiento.
- Describir los componentes y sus contratos de entrada/salida.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Identifique componentes backend y frontend del módulo y refactorice conceptualmente el punto más acoplado sin ampliar el módulo. Entregable/Evidencia: Diagrama de componentes, contratos de entrada/salida y refactor antes/después concreto y justificado.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-qa/bin/{acoplamiento,caracterizar}.php`, `tests/Caracterizacion/*`, `src/Dominio/Modelo/SolicitudResultado.php`, cambios en `RegistrarResultado` y `Salida`, mutaciones M34–M35 y reubicación de M3–M6 y M11 | Código y pruebas |
| `semana-07/diagramas/*`, `refactor/antes/*` | Diagramas y copia del código anterior |
| `semana-07/evidencia/*` | Salidas reales |
| `semana-07-componentes-refactor.md`, `contratos-entrada-salida.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La métrica de acoplamiento es propia:** las marcas de «decisión de QA» y de «preocupación» se eligieron a mano. Es una aproximación útil para comparar antes y después, no una métrica estándar.
- **`esperado.json` se grabó una sola vez, antes del refactor**, y no se regeneró.
- **Los componentes del frontend (F1–F4) están diseñados, no construidos.** Se construyen en las semanas 8–11.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Corte del refactor (SolicitudResultado) | | |
| Métrica de acoplamiento | | |
| Contratos de entrada/salida | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `acoplamiento.php` y CAR-01.
- [ ] Comparé el código antes y después.

**Fecha de validación:** ____________
