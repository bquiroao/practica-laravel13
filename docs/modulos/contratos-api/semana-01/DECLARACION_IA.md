# Declaración de uso de inteligencia artificial — Semana 1

**Estudiante:** Josué David Morales Ramírez (carné 1890-23-10545) · **Módulo:** Contratos API (adaptación: centro de documentación y manuales por rol) · **Entrega:** Semana 1 (tarea Odoo 2473) — UML del proceso «publicación y consulta de documentación y manuales diferenciados por rol»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Revisó el esquema y los OpenAPI existentes, escribió los borradores, generó los PNG con PlantUML local (`-charset UTF-8`), creó la rama, hizo el commit y, tras la aprobación del estudiante, hizo el push y abrió el PR con `gh`. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Basar el modelo en el repositorio real (`hospital_memberships.role_code` en la base CENTRAL, los `openapi.yaml` y las salidas de lint de otros módulos) y redactar la narrativa, las reglas, los casos de uso, la matriz, los tres diagramas, esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2473, tomado textualmente de Odoo, y la orden del estudiante de trabajar en ciclo autónomo con pausa de aprobación, tomando la decisión más razonable ante lo ambiguo y anotándola como supuesto.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-01/semana-01-uml-documentacion-rol.md`, `semana-01/diagramas/` (3 `.puml` y 3 `.png`), `DECLARACION_IA.md`, `GUIA_DEFENSA_ORAL.md`, el README del módulo y la fila del módulo en `docs/README.md`. Supuestos propuestos por la IA: los actores «Autor técnico», «Responsable de integración» y «Lector por rol»; que la audiencia se defina con `role_code` (hoy texto libre sin catálogo); que el almacenamiento de documentos se decida en la Semana 4 porque el esquema no tiene tablas para ello; las reglas R-01 a R-03, R-05, R-07 y R-08 como decisiones de diseño; endpoints y códigos HTTP preliminares (el contrato es de la Semana 5).

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Narrativa, diagramas y reglas (Claude Code) | Aceptado | El estudiante aprobó el paquete de revisión del lote con «ok»; no declaró las validaciones específicas de §6. |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [ ] Leí completo el documento y entiendo cada decisión del modelo.
- [ ] Verifiqué que R-06 corresponda a `hospital_memberships` en la migración del esquema CENTRAL.
- [ ] Comprobé que los identificadores CU, R, D, E, F y S sean coherentes entre los tres diagramas y la matriz.

**Fecha de validación:** ____________
