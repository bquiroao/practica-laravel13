# Declaración de uso de inteligencia artificial — Semana 10

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 10 — Diseño para movilidad (320–430 px) |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el prototipo HTML/CSS y el script de verificación, y los ejecutó con Chrome sin interfaz (puppeteer-core). Revisó las capturas e hizo la contraprueba con defectos sembrados. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Construir las 4 pantallas responsive y aplicar el backlog de la semana 9.
- Escribir y ejecutar las reglas de breakpoint verificables y su contraprueba.
- Documentar los dos escenarios móviles; redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Adapte el flujo a móvil 320–430 px considerando jerarquía, navegación, formularios, tablas, confirmaciones y conexión limitada. Entregable/Evidencia: 3–5 pantallas responsive, reglas de breakpoint y dos escenarios móviles.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*` | 4 pantallas HTML, estilos y script de estados. La base CSS común viene de los prototipos de Prescripciones del mismo estudiante |
| `herramientas/verificar-responsive.cjs` | Script de verificación (adaptado de Prescripciones) |
| `capturas/*`, `evidencia/*` | Salidas y capturas reales |
| `semana-10-movil-qa.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La primera corrida dio 32/32.** El hueco de R-P1 lo encontró la contraprueba, no la corrida normal.
- **No se probó con dispositivos ni con lectores de pantalla reales**, solo con la emulación móvil de Chrome.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pantallas m1–m4 | | |
| Reglas de breakpoint | | |
| Escenarios móviles | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí el prototipo en un teléfono o en el modo móvil del navegador.
- [ ] Ejecuté `verificar-responsive.cjs`.

**Fecha de validación:** ____________
