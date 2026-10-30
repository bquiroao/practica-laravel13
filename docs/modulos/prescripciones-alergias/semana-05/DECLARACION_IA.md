# Declaración de uso de inteligencia artificial — Semana 5

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 5 — Cliente-servidor, contrato API, evaluación de la frontera de microservicio y plan de integración |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó las pruebas, el lint del contrato y la medición con PostgreSQL 18 y `php -S` locales. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir el contrato OpenAPI y las pruebas de conformidad contrato = código.
- Escribir el cliente con reintentos idempotentes, el adaptador HTTP del Expediente con límite de tiempo y circuito, el Expediente simulado y la observabilidad del kernel.
- Escribir y ejecutar la medición de la frontera.
- Redactar el análisis (propiedad de datos, comunicación, seguridad, resiliencia, observabilidad, consistencia y migración), el issue, la plantilla del PR, los diagramas y la guía.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Evolucione el MISMO «Micro-HIS Prescripciones electrónicas con validación de alergias» y el flujo «creación de prescripción con verificación previa de alergias» hacia un diseño cliente-servidor y evalúe una frontera de microservicio. Especifique contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia; incluya diagramas y una migración razonada. Puede entregar diseño/prototipo: no construya infraestructura de producción ni divida el sistema sin una justificación medible. Entregable/Evidencia: Contrato API y plan de issue, rama, worktree y PR.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-prescripciones/api/openapi.yaml` | Completo |
| Cambios en `micro-his-prescripciones/` (`Cliente`, `Persistencia/Http`, observabilidad, `Rutas`, pruebas, mutaciones, `bin/medir.php`, `bin/expediente-simulado.php`) | Código completo |
| `semana-05-cliente-servidor-microservicios.md`, `plan/*.md` | Borradores completos |
| `diagramas/*.puml` y `*.png` | Borrador completo; imágenes generadas con PlantUML |
| `evidencia/*.txt` | Salidas reales de la ejecución |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- La medición es real, pero se hizo con `php -S` en una laptop; los milisegundos de HTTP no representan producción. La disponibilidad del 99,5 % es un **supuesto** para ilustrar el cálculo.
- La corrección sobre la propiedad de `medications` (ASII-14) surgió al revisar el listado de módulos del README: la propuesta de la semana 4 lo había dejado dentro de Prescripciones.
- El issue **no** se creó en GitHub: queda listo, con el comando, para que el estudiante lo cree cuando corresponda.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisión de no separar el servicio y criterios C-1..C-5 | | |
| Cliente sin bandeja local (CONFIRMADO / RECHAZADO / INCIERTO) | | |
| Issue ASII-15 y su división en T1..T5 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la medición, las pruebas, las mutaciones y el lint y obtuve resultados equivalentes.
- [ ] Revisé el issue antes de crearlo en GitHub.

**Fecha de validación:** ____________
