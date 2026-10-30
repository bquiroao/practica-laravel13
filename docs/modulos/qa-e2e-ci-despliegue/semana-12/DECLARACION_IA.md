# Declaración de uso de inteligencia artificial — Semana 12

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 12 — Presentación, recorrido navegable, matriz y cambio solicitado |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Implementó el cambio con la prueba primero (caso de uso, ruta, contrato 1.2.0, APP-11 y M34) y lo conectó al prototipo. Ejecutó la suite, las mutaciones y el recorrido. Generó la presentación con pptxgenjs. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Implementar y probar el cambio.
- Armar la matriz y la presentación corta.

## 3. Prompts relevantes

1. Instrucción de la tarea (semana 12 de `actividades2.pdf`): presentación corta, recorrido navegable, matriz decisión → RF/RNF → evidencia y cambio solicitado documentado.
2. «Ok, continua, haz las actividades no muy extensas, con el fin de no consumir demasiado la IA de Claude.»

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-e2e/` (`EvaluarGate::previsualizar`, ruta, controlador, `openapi.yaml` 1.2.0, APP-11, M34) | El cambio |
| `semana-11/prototipo/app.js`, `semana-11/herramientas/recorrido.cjs` | La vista previa en la consola y sus comprobaciones |
| `semana-12/*` | Presentación, capturas, evidencia, documento, guía y esta declaración |

**Hechos a tener en cuenta:**
- **El pedido de cambio es el hallazgo H-09 de la semana 9**, no un pedido de un usuario real.
- **Las capturas de la semana 11 siguen mostrando la versión anterior del gate.** Las actuales están en `semana-12/capturas`.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Cambio (vista previa) | | |
| Matriz | | |
| Presentación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ensayé la presentación.
- [ ] Ejecuté la suite y el recorrido.

**Fecha de validación:** ____________
