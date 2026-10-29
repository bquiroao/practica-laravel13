# Declaración de uso de inteligencia artificial — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Entrega:** Semana 5 (tarea Odoo 2492) — Cliente-servidor, contrato API y frontera de microservicio de «consolidación y consulta de ocupación por sala y estado de cama»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el contrato OpenAPI, la prueba de contrato, el documento y los diagramas. Ejecutó el lint y el `bundle` con `npx @redocly/cli@1.34.3`, la prueba de contrato y una prueba de mutación sobre una copia del contrato; generó los PNG con PlantUML local; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Evolucionar el Micro-HIS hacia un diseño cliente-servidor con un contrato API verificable, y evaluar una frontera de microservicio con criterios medibles: propiedad de datos, comunicación, seguridad, resiliencia, observabilidad, consistencia, migración y plan de trabajo.

## 3. Prompts relevantes

El enunciado de la tarea 2492, tomado textualmente de Odoo, y la orden del estudiante de retomar las semanas 4 y 5 pendientes en cuanto se integraran los PR de los que dependían, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-05-cliente-servidor.md`, `api/openapi.yaml`, `diagramas/` (2 `.puml` y 2 `.png`), `evidencia/` (`verificar-contrato.php` y las salidas del lint y del contrato), esta declaración, la guía y la fila de la semana 5 en el `README.md` del módulo. El Micro-HIS no se modifica: el contrato describe las rutas que ya implementa la semana 4. La estructura del documento y de la prueba de contrato es la misma de la semana 5 de Catálogo (PR #350, aún sin integrar); los escenarios, los criterios y las decisiones son propios de este módulo. Supuestos: el permiso de ocupación y el hospital llegan con RBAC en la semana 15 (hallazgo H-01 de la semana 13), y el 403 de consolidar se documenta sin código de error hasta corregir H-02. Los códigos E3 y E8 se documentan tal como responden hoy. El temporizador de 5 minutos es una propuesta: hoy la consolidación se pide a mano. La frontera evaluada es un modelo de lectura alimentado por eventos, y el primer criterio depende de la decisión de la semana 5 de Catálogo (PR #350, aún sin integrar). Los umbrales para extraer el servicio, la semana de proyección en sombra y la reconciliación diaria son decisiones de diseño, sin datos de producción. Los eventos, el proyector y el outbox son una propuesta, que se implementa solo si se extrae el servicio. La prueba de contrato lee el OpenAPI convertido a JSON con Redocly, porque PHP no trae lector de YAML; el JSON se genera en una carpeta temporal y no entra al commit. La entrega usó una rama normal y no un worktree, como dice la §6. Validación: el lint pasa sin errores ni avisos y la prueba de contrato da 9 de 9. En una copia del contrato sin la ruta de cortes y sin el 422 de la consulta, fallaron 4 escenarios. El primer lint avisó que consolidar no tenía ninguna respuesta 4xx; se documentó el 403 del permiso de ocupación, que sí llegará, en vez de silenciar la regla.

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
