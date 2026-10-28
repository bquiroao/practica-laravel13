# Declaración de uso de inteligencia artificial — Semana 12

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 12 — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Elegir el cambio solicitado, escribir sus pruebas primero e implementarlo en el backend y en el prototipo.
- Generar la presentación (pptxgenjs, exportada con PowerPoint) con notas del orador.
- Redactar la matriz, el recorrido, el registro del cambio, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defienda las decisiones de componentes, UX, accesibilidad y movilidad de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema. Demuestre con el prototipo cómo el módulo trata roles, estados, errores y riesgos de audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas. Entregable/Evidencia: Presentación breve, recorrido navegable, matriz decisión→RF/RNF→evidencia y respuesta documentada a un cambio solicitado.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-auditoria/` (`FiltroRecurso`, `maximo()`, contrato 1.2.0, índice, REC-01..04, M25, M26) | Completo |
| `semana-12/prototipo/*`, `herramientas/*` | Extensión del prototipo de la semana 11 |
| `semana-12/presentacion/*`, `*.md` | Borradores; la presentación se revisó en imagen y se corrigieron dos defectos de maquetación |
| `semana-12/evidencia/*`, `capturas/*` | Salida real de los comandos |

**Hechos a tener en cuenta:**
- **El cambio solicitado lo eligió la IA** («¿quién vio la ficha?»). Hay que validar con el docente que sea aceptable.
- **Se modificó una prueba de la semana 6 (CORR-07)**, que exigía el texto literal de la versión. El motivo está en el registro del cambio, §4.
- **Dos expectativas del recorrido estaban mal** (no la interfaz): el paso 10 repite la búsqueda vigente, que después del cambio es la del recurso.
- **Al revisar las capturas** se agregaron dos mejoras: el foco en el campo «Recurso» y un texto más corto para el botón.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Cambio solicitado elegido | | |
| Presentación y notas | | |
| Matriz D-22..D-39 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ensayé la presentación y el recorrido navegable (≈ 15 min).
- [ ] Ejecuté las pruebas, la paridad y el recorrido.

**Fecha de validación:** ____________
