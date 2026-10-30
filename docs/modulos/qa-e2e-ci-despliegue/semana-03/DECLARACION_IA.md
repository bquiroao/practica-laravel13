# Declaración de uso de inteligencia artificial — Semana 3

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 3 — Micro-HIS por capas y vista arquitectónica |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el Micro-HIS, el esquema, las pruebas, las mutaciones y la demo, y los ejecutó contra PostgreSQL. Generó los diagramas C4 y UML con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Construir el módulo por capas con PDO y sus pruebas.
- Documentar la vista arquitectónica y las dependencias.
- Redactar la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Inicie «Micro-HIS QA, pruebas E2E, CI y guía de despliegue final» en PHP 8.2+ vanilla para ejecutar «planificación de pruebas, ejecución E2E, conservación de evidencia y aplicación de quality gates». Separe Presentation, Application, Domain y Persistence; use PDO con sentencias preparadas, sin framework, y pruebe al menos camino feliz, regla de dominio y error de persistencia mediante dobles o una base de prueba. Entregable/Evidencia: Vista arquitectónica C4/UML del módulo y dependencias.
2. «ahora estas tareas» (con `actividades2.pdf`).

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-e2e/*` | Código, esquema, pruebas, mutaciones y demo. Sigue las convenciones del Micro-HIS QA del módulo anterior (arnés, raíz de composición) |
| `semana-03/evidencia/*` | Salidas reales |
| `semana-03/diagramas/*` | C4 y paquetes |
| `semana-03-arquitectura-micro-his-e2e.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **El error H-1 lo escribió la IA** y lo encontró la prueba de integración. Los dobles en memoria no lo mostraban.
- **Las dos mutaciones que sobrevivieron** (H-2 y H-3) eran huecos en las pruebas que también escribió la IA.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Capas y casos de uso | | |
| Esquema y triggers | | |
| Pruebas y mutaciones | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la suite con y sin PostgreSQL, y las mutaciones.
- [ ] Ejecuté la demo HTTP.

**Fecha de validación:** ____________
