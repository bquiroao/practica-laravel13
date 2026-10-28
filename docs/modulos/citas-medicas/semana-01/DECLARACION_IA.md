# Declaración de uso de inteligencia artificial — Semana 1

**Estudiante:** Josué David Morales Ramírez (carné 1890-23-10545) · **Módulo:** Citas médicas y agenda · **Entrega:** Semana 1 (tarea Odoo 2458) — UML del proceso «solicitud, validación de disponibilidad y confirmación o cancelación de cita»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Revisó el esquema, escribió los borradores, generó los PNG con PlantUML local (`-charset UTF-8`), creó la rama, hizo el commit y, tras la aprobación del estudiante, hizo el push y abrió el PR con `gh`. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Basar el modelo en el esquema real (`appointments` con sus exclusiones `gist` y triggers, `doctor_profiles`, `doctor_specialties`) y redactar la narrativa, las reglas, los casos de uso, la matriz, los tres diagramas, esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2458, tomado textualmente de Odoo; la instrucción del estudiante de tratar «disponibilidad» como supuesto explícito y dejar el horario de atención como limitación; y la orden de trabajar en ciclo autónomo con pausa de aprobación.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-01/semana-01-uml-citas.md`, `semana-01/diagramas/` (3 `.puml` y 3 `.png`), `DECLARACION_IA.md`, `GUIA_DEFENSA_ORAL.md`, el README del módulo y la fila del módulo en `docs/README.md`. Supuestos propuestos por la IA: el actor «Recepcionista» (ningún otro módulo define ese rol); el paciente no es actor (no hay portal); R-01 (no agendar en el pasado), R-05 (perfil vigente) y R-10 (motivo de cancelación en auditoría) como decisiones de diseño; endpoints y códigos HTTP preliminares (el contrato es de la Semana 5).

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Supuesto de disponibilidad y limitación del horario | Aceptado | Definidos por el estudiante. |
| Narrativa, diagramas y reglas (Claude Code) | Aceptado | El estudiante los revisó en la pausa de aprobación y los aprobó sin cambios (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Leí completo el documento y entiendo cada decisión del modelo.
- [x] Verifiqué que las reglas R-02, R-03, R-04, R-07 y R-08 correspondan a la migración del esquema HOSPITAL.
- [x] Comprobé que los identificadores CU, R, D, E, F y S sean coherentes entre los tres diagramas y la matriz.

**Fecha de validación:** 25/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
