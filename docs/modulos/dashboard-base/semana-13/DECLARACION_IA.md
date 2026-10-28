# Declaración de uso de inteligencia artificial — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Entrega:** Semana 13 (tarea Odoo 2440) — Plan de revisión técnica formal de «navegación del dashboard con estados de carga, vacío, error y resumen inicial»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Leyó el Micro-HIS y los documentos de las semanas 1 a 3, escribió el plan, el checklist y el script de autorrevisión, ejecutó el script y una prueba de mutación en una copia temporal, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Preparar la revisión técnica formal del módulo: plan por fases, roles, criterios de entrada y salida, un checklist de 15 ítems trazado a las reglas del módulo y un formato de hallazgos probado con hallazgos reales y reproducibles.

## 3. Prompts relevantes

El enunciado de la tarea 2440, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-13-revision-tecnica.md`, `evidencia/verificar-entrada.php` y su salida, esta declaración y la guía. La estructura del plan (fases, roles, criterios y formato) es la misma de la semana 13 de Catálogo (PR #288, aún sin integrar); el checklist, los hallazgos y el script son propios de este módulo. Supuestos: el objeto de revisión es el Micro-HIS del commit `8ffa2e2` de `develop`. La ruta HTTP del PR #200 (semana 4), aún sin integrar, no se revisa hasta que se integre y se congele el nuevo commit. El cliente web de la semana 2, con su límite de 10 s, queda fuera del objeto, y por eso C-11 es «no aplica». H-04 supone que las citas se guardan en UTC, como es habitual con `timestamptz`; el Micro-HIS no fija la zona horaria, y esa ambigüedad es el hallazgo. Las personas de cada rol no se nombran, porque las designa el docente o el grupo. Los cinco hallazgos son de la autorrevisión del autor, no de una reunión de inspección realizada, y su severidad es una propuesta que la reunión puede cambiar. H-05 apareció al leer la salida del script, no al buscarlo. El ritmo de 150 líneas por hora y el límite de 2 horas son valores habituales de la literatura de inspecciones, no medidos en este grupo. La fila de la semana 13 en el `README.md` del módulo se agrega cuando se integre el PR #200, que también edita esa tabla. Validación: se corrió el script sobre el código real, que reproduce los cinco hallazgos. En una copia temporal, añadir E6 al controlador y contar las citas con `date(starts_at, '-6 hours')` cambió H-02 y H-04 a «no reproducido», y los demás siguieron reproducidos.

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
