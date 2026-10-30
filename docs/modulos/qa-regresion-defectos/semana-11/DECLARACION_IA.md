# Declaración de uso de inteligencia artificial — Semana 11

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 11 — Prototipo navegable de escritorio y móvil |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el prototipo (HTML, CSS y JavaScript) y el recorrido automático, y los ejecutó con Chrome sin interfaz y axe-core. Revisó las capturas y generó el mapa con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Construir el prototipo navegable con la API simulada y cerrar el backlog de la semana 9.
- Automatizar el recorrido del camino feliz y del error crítico, con capturas y accesibilidad.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Construya un prototipo navegable desktop y móvil con camino feliz y un error crítico. Entregable/Evidencia: Mapa de navegación y capturas desktop/móvil.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*` | Prototipo completo. Los estilos parten de la semana 10 |
| `herramientas/recorrido.cjs` | Recorrido automático (adaptado del de Prescripciones) |
| `capturas/*`, `evidencia/*` | Capturas y salidas reales |
| `diagramas/*` | Mapa de navegación |
| `semana-11-prototipo-qa.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **H-3 (la previsión con una causa menos) no lo detectó una prueba**, sino la revisión de las capturas. La comprobación original era débil.
- **La pérdida de red se simula** desde el script (`ApiSimulada.cortarRed()`), no con la red real del navegador.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Prototipo y rutas | | |
| Error crítico elegido | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Recorrí el prototipo a mano en escritorio y en móvil.
- [ ] Ejecuté `recorrido.cjs`.

**Fecha de validación:** ____________
