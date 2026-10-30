# Declaración de uso de inteligencia artificial — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Entrega:** Semana 5 (tarea Odoo 2462) — Cliente-servidor, contrato API y frontera de microservicio de «solicitud, validación de disponibilidad y confirmación o cancelación de cita»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el contrato OpenAPI, la prueba de contrato, el documento y los diagramas. Ejecutó el lint y el `bundle` con `npx @redocly/cli@1.34.3`, la prueba de contrato y una prueba de mutación sobre una copia del contrato; generó los PNG con PlantUML local; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Evolucionar el Micro-HIS hacia un diseño cliente-servidor con un contrato API verificable, y evaluar una frontera de microservicio con criterios medibles: propiedad de datos, comunicación, seguridad, resiliencia, observabilidad, consistencia, migración y plan de trabajo.

## 3. Prompts relevantes

El enunciado de la tarea 2462, tomado textualmente de Odoo, y la orden del estudiante de retomar las semanas 4 y 5 pendientes en cuanto se integraran los PR de los que dependían, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-05-cliente-servidor.md`, `api/openapi.yaml`, `diagramas/` (2 `.puml` y 2 `.png`), `evidencia/` (`verificar-contrato.php` y las salidas del lint y del contrato), esta declaración, la guía y la fila de la semana 5 en el `README.md` del módulo. El Micro-HIS no se modifica: el contrato describe las rutas que ya implementa la semana 4. La estructura del documento y de la prueba de contrato es la misma de la semana 5 de Catálogo (rama `semana-5-catalogo-salas-camas`, del mismo lote); los escenarios, los criterios y las decisiones son propios de este módulo. Supuestos: confirmar y cancelar son `POST` sobre un subrecurso, como en la semana 4; E6 (409) queda pendiente por el hallazgo H-02 de la semana 13, y el contrato lo declara. JWT, `X-Tenant-ID` y `X-Request-Id` están en el contrato, pero el Micro-HIS todavía no los valida (semana 15). Los umbrales para extraer el servicio, el timeout de 2 s y el límite de 300 ms del p95 son decisiones de diseño; no hay datos de producción, y por eso la contención figura como «sin datos». Los eventos de agenda y de personal, la clave de idempotencia y el outbox son una propuesta, que se implementa solo si se extrae el servicio. La prueba de contrato lee el OpenAPI convertido a JSON con Redocly, porque PHP no trae lector de YAML; el JSON se genera en una carpeta temporal y no entra al commit. La entrega usó una rama normal y no un worktree, como dice la §6. Validación: el lint pasa sin errores ni avisos y la prueba de contrato da 12 de 12. En una copia del contrato sin la ruta de cancelación y sin el 409 de la solicitud, fallaron 5 escenarios.

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
