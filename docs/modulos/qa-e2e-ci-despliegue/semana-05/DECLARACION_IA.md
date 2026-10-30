# Declaración de uso de inteligencia artificial — Semana 5

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 5 — Cliente-servidor, contrato API y frontera de microservicio |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el contrato, las pruebas de conformidad, el cliente del pipeline, la observabilidad, el prototipo de ingesta y la medición, y los ejecutó. Generó los diagramas y el plan. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Especificar el contrato y verificarlo contra el código.
- Construir el lado cliente con resiliencia y medir la frontera de microservicio.
- Redactar el plan de issue, rama, worktree y PR, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Evolucione el MISMO «Micro-HIS QA, pruebas E2E, CI y guía de despliegue final» y el flujo «planificación de pruebas, ejecución E2E, conservación de evidencia y aplicación de quality gates» hacia un diseño cliente-servidor y evalúe una frontera de microservicio. Especifique contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia; incluya diagramas y una migración razonada. Puede entregar diseño/prototipo: no construya infraestructura de producción ni divida el sistema sin una justificación medible. Entregable/Evidencia: Contrato API y plan de issue, rama, worktree y PR.
2. «ahora estas tareas» (con `actividades2.pdf`).

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-e2e/api/openapi.yaml`, `tests/Api/*` | Contrato y conformidad |
| `micro-his-e2e/src/Cliente/*`, `tests/Cliente/*` | Reportador del pipeline |
| Cambios en `Kernel.php` y `contenedor.php` | Observabilidad |
| `bin/medir.php`, `bin/proxy-ingesta.php` | Medición y prototipo de la frontera |
| `semana-05/*` | Documento, plan, diagramas, evidencias, guía y esta declaración |

**Hechos a tener en cuenta:**
- **La primera medición de la IA era inválida** (claves cortas, todo rechazado). Se detectó por la columna de confirmados y se repitió.
- **El lint de Redocly no se ejecutó:** el entorno bloqueó la descarga del paquete.
- **El número ASII-22** del plan es el del estudiante en la tabla del README del repositorio.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Contrato 1.0.0 | | |
| No separar la ingesta | | |
| Plan de issue, rama, worktree y PR | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la suite, las mutaciones y la medición.
- [ ] Revisé el contrato.

**Fecha de validación:** ____________
