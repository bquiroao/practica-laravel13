# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 1 — UML del proceso y narrativa de alcance |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Revisó el workflow, `phpunit.xml` y `package.json` del HIS, y consultó la API de GitHub Actions para el punto de partida. Escribió los diagramas en PlantUML y el documento. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Encontrar el punto de partida real del proyecto en CI, pruebas y despliegue.
- Modelar el proceso en casos de uso, actividad y secuencia, con su trazabilidad.
- Redactar la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Modele el proceso «planificación de pruebas, ejecución E2E, conservación de evidencia y aplicación de quality gates». Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas. Entregable/Evidencia: UML de casos de uso y narrativa de alcance.
2. «ahora estas tareas» (con `actividades2.pdf`).

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `diagramas/*.puml`, `diagramas/*.png` | Los tres diagramas |
| `semana-01-uml-proceso-e2e-ci.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **«0 ejecuciones»** es lo que devolvió la API de GitHub Actions el 2026-10-29 con las credenciales del estudiante. Si Actions se habilita después, el dato cambia.
- Los umbrales de R-08 (90 días) y R-09e (24 h) son una **propuesta inicial**, no un requisito del hospital.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Actores y casos de uso | | |
| Reglas R-01..R-14 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé los tres diagramas y su trazabilidad.
- [ ] Verifiqué el punto de partida en el repositorio.

**Fecha de validación:** ____________
