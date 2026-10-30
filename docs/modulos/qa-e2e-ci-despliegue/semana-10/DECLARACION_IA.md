# Declaración de uso de inteligencia artificial — Semana 10

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 10 — Diseño para movilidad (320–430 px) |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el prototipo HTML/CSS (sobre la base compartida de los prototipos del HIS), el verificador con puppeteer-core y el armado de láminas. Ejecutó las verificaciones y la contraprueba, y revisó las capturas. Redactó los documentos. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Llevar las pantallas al teléfono.
- Definir y medir las reglas de breakpoint.
- Armar los dos escenarios.

## 3. Prompts relevantes

1. Instrucción de la tarea (semana 10 de `actividades2.pdf`): móvil de 320 a 430 px, 3–5 pantallas responsive, reglas de breakpoint y 2 escenarios móviles.
2. «ahora estas tareas» (con `actividades2.pdf`), «continua» y «puedes continuar?».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-10/prototipo/*` | 4 pantallas, estilos (base compartida + propios) y el script de estados |
| `semana-10/herramientas/*` | Verificador responsive y armado de láminas |
| `semana-10/capturas/*`, `laminas/*`, `evidencia/*` | Salidas de las herramientas |
| `semana-10/*.md` | Documento, guía y esta declaración |

**Hechos a tener en cuenta:**
- **Las reglas R-F1 y R-X1 nacieron de una revisión visual** de las capturas, no del primer diseño del verificador.
- **Los datos de las pantallas son de ejemplo.**
- **Las medidas son en Chrome emulado**, no en teléfonos reales.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pantallas responsive | | |
| Reglas de breakpoint | | |
| Escenarios | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí el prototipo en un teléfono real.
- [ ] Ejecuté el verificador.

**Fecha de validación:** ____________
