# Declaración de uso de inteligencia artificial — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 13 (tarea Odoo 2515) — Plan de revisión técnica formal de «apertura y consulta autorizada del expediente longitudinal»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Leyó el Micro-HIS del PR #191 y los documentos de las semanas 1 a 3, escribió el plan, el checklist y el script de autorrevisión, ejecutó el script sobre una copia temporal de ese commit y una prueba de mutación, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Preparar la revisión técnica formal del módulo: plan por fases, roles, criterios de entrada y salida, un checklist de 15 ítems trazado a las reglas del módulo y un formato de hallazgos probado con hallazgos reales y reproducibles.

## 3. Prompts relevantes

El enunciado de la tarea 2515, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-13-revision-tecnica.md`, `evidencia/verificar-entrada.php` y su salida, esta declaración y la guía. La estructura del plan (fases, roles, criterios y formato) es la misma de la semana 13 de Catálogo (PR #288, aún sin integrar); el checklist, los hallazgos y el script son propios de este módulo. Supuestos: el objeto de revisión es el Micro-HIS del commit `f09b83c` del **PR #191 (semana 3), aún sin integrar**. Este PR no copia su código: el script lo busca en `../../micro-his-expediente`, y en esta rama avisa que falta y sale con código 1. La salida guardada se obtuvo copiando ese commit con `git archive` a una carpeta temporal, fuera del repositorio. Por lo mismo, el editor marca como desconocidas las clases de ese Micro-HIS. Mientras el PR #191 no se integre, E-01 y E-04 no se cumplen y la reunión no se convoca. C-10 es «no aplica» porque la semana 3 dejó el acceso de emergencia para después, y C-15 lo es porque las rutas llegan en la semana 4 (tarea 2506), aún pendiente. Las personas de cada rol no se nombran, porque las designa el docente o el grupo. Los cinco hallazgos son de la autorrevisión del autor, no de una reunión de inspección realizada, y su severidad es una propuesta que la reunión puede cambiar. El ritmo de 150 líneas por hora y el límite de 2 horas son valores habituales de la literatura de inspecciones, no medidos en este grupo. La fila de la semana 13 en el `README.md` del módulo se agrega cuando se integre el PR #191, que también edita esa tabla. Validación: se corrió el script sobre la copia, que reproduce los cinco hallazgos. En otra copia se hicieron dos cambios: un trigger que impide el `UPDATE` de la auditoría y auditar también los fallos técnicos. Con eso, H-04 y H-05 pasaron a «no reproducido», y los demás siguieron reproducidos. Al escribir el script, Claude Code corrigió un error propio: sin el Micro-HIS, terminaba con código 0, como si hubiera pasado.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Plan, checklist, hallazgos y script (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 28/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
