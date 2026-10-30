# Declaración de uso de inteligencia artificial — Semana 7

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 7 — Diagrama de componentes, contratos de entrada/salida y refactor por capas |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió la medición de acoplamiento, los 35 escenarios de caracterización y su grabación, el refactor y la reubicación de las mutaciones. Ejecutó la suite contra PostgreSQL y las mutaciones. Dibujó el diagrama con PlantUML y redactó los documentos. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Medir el acoplamiento y elegir qué refactorizar.
- Grabar el comportamiento antes del refactor y refactorizar sin cambiarlo.
- Preparar el diagrama de componentes, los contratos, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea (semana 7 de `actividades2.pdf`): diagrama de componentes, contratos de entrada/salida y un refactor concreto antes/después, justificado, que separe UI, aplicación, dominio y persistencia sin agrandar el módulo.
2. «ahora estas tareas» (con `actividades2.pdf`) y «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-e2e/bin/acoplamiento.php`, `bin/caracterizar.php`, `tests/Caracterizacion/*` | La medición y la caracterización |
| `micro-his-e2e/src/Dominio/Modelo/ReporteResultado.php`, cambios en `Ejecucion`, `ResultadoEscenario`, `Despliegue`, `ReportarResultado` y `RegistrarDespliegue` | El refactor |
| `micro-his-e2e/tests/mutaciones.php` | Reubicación de M6, M10–M12 y M26. Nuevas M32 y M33 |
| `semana-07/*` | Diagrama, contratos, documento del refactor, evidencias, guía y esta declaración |

**Hechos a tener en cuenta:**
- **Las métricas son propias del módulo:** `acoplamiento.php` cuenta tokens, no es una herramienta estándar como phpmetrics. Sus definiciones están en el documento.
- **La caracterización es tan buena como sus escenarios.** Un comportamiento que ninguno de los 35 ejercita no queda protegido por CAR-01, solo por el resto de la suite.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Diagrama de componentes | | |
| Contratos de entrada/salida | | |
| Refactor de `ReportarResultado` | | |
| Refactor de `RegistrarDespliegue` | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Leí el código antes y después del refactor.
- [ ] Ejecuté la suite, CAR-01 y las mutaciones.

**Fecha de validación:** ____________
