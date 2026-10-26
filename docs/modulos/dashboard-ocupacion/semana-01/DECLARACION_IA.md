# Declaración de uso de inteligencia artificial — Semana 1

**Estudiante:** Josué David Morales Ramírez (carné 1890-23-10545) · **Módulo:** Dashboard de ocupación hospitalaria · **Entrega:** Semana 1 (tarea Odoo 2488) — UML del proceso «consolidación y consulta de ocupación por sala y estado de cama»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Revisó el esquema, escribió los borradores, generó los PNG con PlantUML local (`-charset UTF-8`), creó la rama, hizo el commit y, tras la aprobación del estudiante, hizo el push y abrió el PR con `gh`. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Basar el modelo en el esquema real (`wards`, `beds`, `bed_stays` y su exclusión de estancias abiertas) y redactar la narrativa, las reglas, los casos de uso, la matriz, los tres diagramas, esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2488, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo, tomando la opción más segura ante lo ambiguo y anotándola como supuesto.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-01/semana-01-uml-ocupacion.md`, `semana-01/diagramas/` (3 `.puml` y 3 `.png`), `DECLARACION_IA.md`, `GUIA_DEFENSA_ORAL.md`, el README del módulo y la fila del módulo en `docs/README.md`. Supuestos propuestos por la IA: la fórmula de ocupación de R-01 (mantenimiento e inactivas no cuentan); la consolidación cada 5 minutos con un actor «Temporizador»; el aviso de datos viejos a los 15 minutos; que todo usuario con permiso vea todas las salas (el esquema no asigna salas a supervisores); que el almacenamiento de cortes se defina en la Semana 4; y endpoints preliminares (el contrato es de la Semana 5).

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
