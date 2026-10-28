# Declaración de uso de inteligencia artificial — Semana 9

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 9 — Evaluación heurística y WCAG del flujo UX |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Evaluar los wireframes de la semana 8 con Nielsen y WCAG 2.2 AA y completar el checklist.
- Adaptar los scripts de medición de contraste y de recortes marcados.
- Redactar los hallazgos, el backlog, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Evalúe el flujo UX de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema con heurísticas de usabilidad y WCAG: teclado, foco, contraste, etiquetas, mensajes y prevención de errores. Priorice correcciones vinculadas con audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas. Entregable/Evidencia: Checklist completado, evidencia de al menos 6 hallazgos y backlog priorizado con corrección propuesta y criterio verificable.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-09/*.md` | Borradores |
| `semana-09/herramientas/*.py` | Adaptados de los de Pacientes (semana 9) |
| `semana-09/evidencia/*` | Salida real de los scripts |

**Hechos a tener en cuenta:**
- **La IA evaluó un diseño que ella misma propuso en la semana 8.** Para compensarlo, cada hallazgo cita un criterio externo y el contraste se mide con un script.
- **Las severidades son un juicio**, y conviene revisarlas.
- **El recorrido de teclado es simulado:** no se probó con un lector de pantalla real (limitación declarada en §7).

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Hallazgos H-01..H-15 | | |
| Severidades y prioridades | | |
| Backlog B-01..B-15 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `contraste.py` y revisé los recortes de los hallazgos.
- [ ] Revisé que las severidades reflejen mi criterio.

**Fecha de validación:** ____________
