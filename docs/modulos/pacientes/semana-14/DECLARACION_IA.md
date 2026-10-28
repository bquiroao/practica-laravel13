# Declaración de uso de inteligencia artificial — Semana 14

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 14 — Confiabilidad y aseguramiento de la calidad (plan SQA) |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar las 7 métricas de Pacientes y escribir `medir-sqa.php`, que las mide contra el kernel real.
- Inyectar tres defectos en copias aparte para comprobar que las métricas los detectan.
- Redactar el plan SQA, la matriz de riesgos, las consultas de revisión de la bitácora y el tablero, siguiendo la estructura de la semana 14 de RBAC.
- Redactar el documento de la semana, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defina el plan SQA de Pacientes: registro, edición, búsqueda y detalle: calidad de «registro, búsqueda y actualización segura de un paciente», confiabilidad, seguridad, trazabilidad y riesgos de identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad. Asigne responsable, umbral, fuente y acción para cada métrica y control. Entregable/Evidencia: Plan SQA con 5–7 métricas, matriz de riesgos, controles preventivos/detectivos, responsables y calendario de seguimiento.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana no se modificó el Micro-HIS: el plan mide su estado actual, y las correcciones son el retrabajo de la S15.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `plan-sqa-pacientes.md`, `matriz-riesgos.md`, `semana-14-sqa-pacientes.md` | Borrador completo (estructura de RBAC, contenido de Pacientes) |
| `herramientas/medir-sqa.php` | Borrador completo; M1, M2 y M7 se adaptaron del script de RBAC, y M3 a M6 son propias |
| `herramientas/revision-bitacora.sql` | Borrador completo; **no se ejecutó** (falta PostgreSQL) |
| `plantillas/tablero-seguimiento.csv`, `evidencia/*.txt` | Salida real de los scripts |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **M4 dio un ROJO falso en la primera corrida.** El escenario «editar con el DPI de otro paciente» combinaba los datos con `+`, así que conservaba el documento propio y la edición pasaba. Se corrigió el script; no era un defecto del módulo.
- **El SQL tenía un nombre de columna equivocado** (`id` en vez de `evento_id`). Se corrigió revisando `schema.sql`, pero el archivo sigue sin ejecutarse contra una base real.
- **Los dos ROJOS de la línea base son defectos reales** de la revisión de la semana 13 (AR-02, AR-03), en código que también escribió la IA.
- **Los mutantes se aplicaron en copias temporales**, nunca en el repositorio.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Métricas M1–M7 y umbrales | | |
| Matriz de riesgos (P, I) | | |
| Controles CP y CD | | |
| Responsables y calendario | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php herramientas/medir-sqa.php` y obtuve 5 VERDE · 2 ROJO.
- [ ] Revisé la estimación de P e I de los tres riesgos Altos.
- [ ] Acordé con el grupo quiénes son RSQA, RS y MR.

**Fecha de validación:** ____________
