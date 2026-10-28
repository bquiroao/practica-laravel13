# Declaración de uso de inteligencia artificial — Semana 12

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 12 — Segunda evaluación parcial |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó las pruebas del Micro-HIS (con y sin PostgreSQL), las mutaciones, el lint del contrato, la paridad, el recorrido (Chrome sin interfaz y axe-core) y la exportación de la presentación con PowerPoint. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Implementar el cambio solicitado en el Micro-HIS, con las pruebas escritas primero, y actualizar el contrato.
- Escribir la herramienta de re-aprobación controlada de la caracterización.
- Llevar el cambio al prototipo, a la paridad y al recorrido.
- Generar la presentación y redactar la matriz, el registro, el recorrido navegable, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defienda las decisiones de componentes, UX, accesibilidad y movilidad de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias. Demuestre con el prototipo cómo el módulo trata roles, estados, errores y riesgos de prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro. Entregable/Evidencia: Presentación breve, recorrido navegable, matriz decisión→RF/RNF→evidencia y respuesta documentada a un cambio solicitado.
2. «Ok, continua con las demás semanas».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-prescripciones/src/...` (`PoliticaEmision`, `VerificarAlergiasPrevia`), `api/openapi.yaml`, `tests/Cambio/JustificarPrueba.php`, `tests/mutaciones.php`, `tests/ejecutar.php`, `README.md` | Cambios de código, de contrato y de pruebas |
| `micro-his-prescripciones/tests/Caracterizacion/esperado.json` | Una clave nueva, regrabada por la herramienta |
| `semana-12/prototipo/*`, `semana-12/herramientas/*` | Adaptados de la semana 11; la re-aprobación es nueva |
| `presentacion/*` | Generada con un script (pptxgenjs) y exportada a PDF |
| `capturas/*`, `evidencia/*` | Salidas reales de las herramientas |
| Documentos `.md` de la carpeta | Borradores |

**Hechos a tener en cuenta:**
- **El escenario del cambio («la Dra. Ruiz escribió y la rechazaron») es ficticio,** pero el problema es real: es la brecha M-07 de la semana 8 y el hallazgo H-11 de la semana 9.
- **Que CAR-01 fallara fue esperado y se documentó.** La caracterización se volvió a aprobar solo después de comprobar que la única diferencia era la clave nueva.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Cambio solicitado elegido | | |
| Re-aprobación de la caracterización | | |
| Presentación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ensayé la presentación con el recorrido en vivo.
- [ ] Ejecuté `tests/ejecutar.php`, `paridad.cjs` y `recorrido.cjs`.

**Fecha de validación:** ____________
