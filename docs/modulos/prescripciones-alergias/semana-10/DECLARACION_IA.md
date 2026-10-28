# Declaración de uso de inteligencia artificial — Semana 10

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 10 — Diseño para movilidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó el verificador responsive (Chrome sin interfaz), la medición de reintentos y el armado de los escenarios. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar y escribir el prototipo responsive (4 pantallas en HTML y CSS) a partir de las semanas 8 y 9.
- Escribir las herramientas de verificación (`verificar-responsive.cjs`), de medición (`reintento.php`) y de escenarios (`escenarios.py`).
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Adapte «creación de prescripción con verificación previa de alergias» de Prescripciones electrónicas con validación de alergias a móvil (320–430 px) priorizando contenido, reducción de carga cognitiva, navegación, tablas/formularios, confirmaciones y recuperación ante conexión limitada para prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro. Entregable/Evidencia: Propuesta responsive de 3–5 pantallas, reglas de breakpoint y dos escenarios móviles con decisiones de contenido y error.
2. «continua por favor».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*` | Código completo; la base CSS se reutilizó de Auditoría, semana 10 |
| `herramientas/*` | Código completo; el verificador se adaptó del de Auditoría, con R-P1 y R-B1 nuevas |
| `capturas/*`, `escenarios/*`, `evidencia/*` | Salidas reales de las herramientas |
| `semana-10-movil-prescripciones.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La tabla de reintentos (§6.3) es una medición real contra el kernel del Micro-HIS.** El 409 de la anulación repetida no estaba previsto: salió de la medición.
- **La franja de alergias corrida se encontró mirando las capturas,** no con el instrumento. La regla se corrigió y se probó con un control.
- **Los nombres de pacientes, médicos y hospitales son ficticios.**

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pantallas M1–M4 y breakpoints | | |
| Decisiones de reintento (§6.3) | | |
| Escenarios 1 y 2 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí el prototipo en el teléfono o en el emulador del navegador.
- [ ] Ejecuté `verificar-responsive.cjs` y `reintento.php`.

**Fecha de validación:** ____________
