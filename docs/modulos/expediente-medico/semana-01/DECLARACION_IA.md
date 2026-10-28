# Declaración de uso de inteligencia artificial — Semana 1

**Estudiante:** Josué David Morales Ramírez (carné 1890-23-10545) · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 1 (tarea Odoo 2503) — UML del proceso «apertura y consulta autorizada del expediente longitudinal»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Revisó los esquemas HOSPITAL y CENTRAL, escribió los borradores, generó los PNG con PlantUML local (`-charset UTF-8`), creó la rama, hizo el commit y, tras la aprobación del estudiante, hizo el push y abrió el PR con `gh`. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Basar el modelo en el esquema real (`medical_records`, `care_assignments`, `local_patients.mpi_link_status`, `patient_consents`, `cross_hospital_access_grants`, `cross_hospital_access_audits`, `break_glass_reviews`) y redactar la narrativa, las reglas, los casos de uso, la matriz, los tres diagramas, esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2503, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo, tomando la opción más segura ante lo ambiguo y anotándola como supuesto.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-01/semana-01-uml-expediente.md`, `semana-01/diagramas/` (3 `.puml` y 3 `.png`), `DECLARACION_IA.md`, `GUIA_DEFENSA_ORAL.md`, y el README del módulo. Supuestos propuestos por la IA: la apertura la hace el personal de admisión en el primer episodio; la base CENTRAL como actor secundario por ser un sistema federado distinto; R-03 (solo pacientes activos) como decisión de diseño; endpoints preliminares (el contrato es de la Semana 5). Este PR no agrega la fila de `docs/README.md` porque todas las posiciones de la tabla chocarían con los PR #80 a #84 abiertos; se agregará cuando estos se integren.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Narrativa, diagramas y reglas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 25/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
