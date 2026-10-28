# Declaración de uso de inteligencia artificial — Semana 9

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 9 — Evaluación del diseño, usabilidad y accesibilidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Aplicar la checklist (heurísticas de Nielsen, WCAG 2.2 AA y 12 comprobaciones propias de Pacientes) al diseño de la semana 8.
- Adaptar el script de contraste de RBAC a los colores de los wireframes de Pacientes y ejecutarlo.
- Comparar cada regla de la UI con el código del Micro-HIS para encontrar inconsistencias.
- Generar los recortes marcados de cada hallazgo con un script de Python (Pillow).
- Redactar los hallazgos, el backlog priorizado, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Evalúe el flujo UX de Pacientes: registro, edición, búsqueda y detalle con heurísticas de usabilidad y WCAG: teclado, foco, contraste, etiquetas, mensajes y prevención de errores. Priorice correcciones según impacto en identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad. Entregable/Evidencia: Checklist completado, evidencia de al menos 6 hallazgos y backlog priorizado con corrección propuesta y criterio verificable.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana es de evaluación: no se modificó código ni los wireframes de la semana 8.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-09-evaluacion-ux-pacientes.md` | Borrador completo |
| `checklist-evaluacion.md` | Borrador completo; los conteos del resumen se verificaron con un script contra las tablas |
| `herramientas/contraste.py` | Adaptado del script de la semana 9 de RBAC, con los pares de color de los wireframes de Pacientes |
| `evidencia/contraste-wcag.txt` | Salida real del script |
| `evidencia/hallazgos/*.png` | Recortes de los wireframes de la semana 8 con marcas, generados por un script |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Dos hallazgos P1 son errores del diseño de la semana 8, que también hizo la IA.** W3 muestra «Reservado» al supervisor (H-01) y W5 pide motivo por una tilde (H-02). Se encontraron al comparar el diseño con el código del backend. H-02 se confirmó ejecutando `Texto::normalizar()`.
- **La paleta de los wireframes de la semana 8 se copió del generador de RBAC**, incluido el gris secundario que la evaluación de RBAC ya había marcado como insuficiente. Por eso H-05 y H-06 repiten esos hallazgos.
- **Las severidades son juicio del evaluador**, sin pruebas con usuarios.
- **El teclado y el lector de pantalla se evaluaron sobre la especificación**, no sobre una página implementada.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Checklist (93 ítems) | | |
| Hallazgos H-01..H-14 y sus severidades | | |
| Backlog BL-01..BL-14 | | |
| Script de contraste | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `python herramientas/contraste.py` y obtuve la misma tabla.
- [ ] Revisé cada recorte y estoy de acuerdo con la severidad asignada.
- [ ] Puedo explicar H-01 y H-02 mostrando el código del backend.

**Fecha de validación:** ____________
