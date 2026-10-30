# Declaración de uso de inteligencia artificial — Semana 12

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 12 — Segunda evaluación parcial |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el flujo C del recorrido y el cambio en el prototipo, y los ejecutó con Chrome sin interfaz y axe-core. Generó la presentación con pptxgenjs y el mapa con PlantUML, y revisó visualmente las capturas y las diapositivas. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Implementar el cambio solicitado con la prueba primero.
- Armar la matriz decisión → RF/RNF → evidencia a partir de las semanas 1 a 11.
- Preparar la presentación breve, el guion del recorrido, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defienda las decisiones de componentes, UX, accesibilidad y movilidad de QA funcional, pruebas manuales y matriz de regresión. Demuestre con el prototipo cómo «diseño de casos manuales, campaña de regresión, evidencia, defectos y trazabilidad» trata roles, estados, errores y riesgos asociados con campañas manuales, matriz de regresión, defectos, severidad, evidencia y trazabilidad. Entregable/Evidencia: Presentación breve, recorrido navegable, matriz decisión→RF/RNF→evidencia y respuesta documentada a un cambio solicitado.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*` | El cambio sobre el prototipo de la semana 11 (rol, «Ver por», vista por requisito) |
| `herramientas/recorrido.cjs` | Flujo C agregado al recorrido de la semana 11 |
| `capturas/*`, `evidencia/*` | Capturas y salidas reales de las corridas (rojo, la falla en móvil, verde) |
| `presentacion/*` | Presentación de 6 diapositivas con notas, y su PDF |
| `diagramas/*` | Mapa de navegación (y la corrección del mapa de la semana 11) |
| `matriz-decision-requisito-evidencia.md`, `registro-cambio-solicitado.md`, `semana-12-evaluacion-parcial-qa.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **El pedido de cambio es un escenario planteado para el ejercicio**, no un pedido de un usuario real del hospital.
- **H-3 (el mapa de la semana 11 mal dibujado) se coló en una entrega anterior** generada con la misma herramienta: la revisión de entonces no leyó el texto del diagrama.
- La IA propuso RNF-09 y RNF-10; **no están aprobados** como requisitos del módulo.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Cambio solicitado elegido | | |
| Matriz U-01..U-17 | | |
| RNF-09 y RNF-10 propuestos | | |
| Presentación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Recorrí el prototipo a mano con los tres roles, en escritorio y en móvil.
- [ ] Ejecuté `recorrido.cjs`.
- [ ] Ensayé la presentación con el tiempo de la guía.

**Fecha de validación:** ____________
