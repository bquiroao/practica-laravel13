# Declaración de uso de inteligencia artificial — Semana 4

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 4 — Capas, MVC, Repository y repositorio compartido |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el MVC, la suite de contrato, la integración con el repositorio compartido, las pruebas y las mutaciones, y las ejecutó contra PostgreSQL. Generó los diagramas. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Reorganizar la entrada en MVC y verificar que los controladores no tienen SQL ni reglas.
- Probar los dos adaptadores con una misma suite.
- Analizar y construir la integración con un repositorio compartido.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Continúe el MISMO «Micro-HIS QA, pruebas E2E, CI y guía de despliegue final» y el flujo «planificación de pruebas, ejecución E2E, conservación de evidencia y aplicación de quality gates». Organice la entrada con MVC; defina una interfaz Repository en el límite apropiado y adaptadores PDO e InMemory. El controlador no tendrá SQL ni reglas de negocio. Además, analice y diagrame cómo un repositorio de datos compartido integraría este módulo. Entregable/Evidencia: Capas, responsabilidades y objetos reutilizables.
2. «ahora estas tareas» (con `actividades2.pdf`).

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-e2e/src/Presentacion/*` | MVC |
| `micro-his-e2e/database/integracion-compartida.sql` | Repositorio compartido |
| `micro-his-e2e/tests/{Contratos,Mvc}/*`, ARQ-06, INT-08/09, M17–M21 | Pruebas y mutaciones |
| `semana-04/*` | Documento, diagramas, evidencias, guía y esta declaración |

**Hechos a tener en cuenta:**
- **ARQ-06 la escribió la IA vacía** (H-1). La detectó una violación sembrada, no la prueba.
- **El repositorio compartido es una propuesta:** el esquema `compartido` no existe hoy en el HIS; lo administraría la plataforma central.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| MVC | | |
| Alternativa C (publicar hechos) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la suite y las mutaciones con PostgreSQL.
- [ ] Revisé la política de seguridad por filas.

**Fecha de validación:** ____________
