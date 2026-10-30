# Declaración de uso de inteligencia artificial — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 5 (tarea Odoo 2507) — Cliente-servidor, contrato API y frontera de microservicio de «apertura y consulta autorizada del expediente longitudinal»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el contrato OpenAPI, la prueba de contrato, el documento y los diagramas. Ejecutó el lint y el `bundle` con `npx @redocly/cli@1.34.3`, y la prueba de contrato sobre una copia temporal del commit de la semana 4, con una prueba de mutación sobre una copia del contrato. Generó los PNG con PlantUML local, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Evolucionar el Micro-HIS hacia un diseño cliente-servidor con un contrato API verificable, y evaluar una frontera de microservicio con criterios medibles: propiedad de datos, comunicación, seguridad, resiliencia, observabilidad, consistencia, migración y plan de trabajo.

## 3. Prompts relevantes

El enunciado de la tarea 2507, tomado textualmente de Odoo, y la orden del estudiante de retomar las semanas 4 y 5 pendientes en cuanto se integraran los PR de los que dependían, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-05-cliente-servidor.md`, `api/openapi.yaml`, `diagramas/` (2 `.puml` y 2 `.png`), `evidencia/` (`verificar-contrato.php` y las salidas del lint y del contrato), esta declaración y la guía. La estructura sigue la semana 5 de los otros cinco módulos (PR #350, #351 y #354 a #356, aún sin integrar). **Dependencia:** el contrato documenta las rutas de la semana 4 de este módulo (PR #349, aún sin integrar), sin copiar su código. Por eso, en esta rama la prueba de contrato avisa que falta la entrada MVC y sale con 1. La salida guardada se obtuvo copiando el commit `0fd94ce` del PR #349 con `git archive` a una carpeta temporal, fuera del repositorio. La fila de la semana 5 en el `README.md` del módulo no entra en este PR, porque el #349 edita la misma tabla; va en el PR de mantenimiento del módulo. Supuestos: el profesional y el hospital llegan en cabeceras hasta la semana 15. El 403 E4 sin asignación se documenta tal como responde hoy (hallazgo H-01 de la semana 13). La frontera evaluada es el índice central y no el expediente, porque el índice es lo que comparten los hospitales. Los umbrales, el timeout de 2 s, la caché de 60 s y el mTLS son decisiones de diseño, sin datos de producción. Validación: el lint pasa sin errores ni avisos, y la prueba de contrato da 11 de 11 sobre la copia de `0fd94ce`. En una copia del contrato sin el 403 de la consulta local y sin el 503 de la externa, fallaron 2 escenarios. Al revisar el documento, Claude Code corrigió una cita propia: atribuía a FHIR un recurso «Patient Master Index» que no existe con ese nombre, y ahora cita el recurso Consent.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Contrato, prueba, documento y diagramas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 29/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
