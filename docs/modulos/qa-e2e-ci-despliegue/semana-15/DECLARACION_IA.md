# Declaración de uso de inteligencia artificial — Semana 15

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 15 — Matriz de pruebas, pruebas por técnica y defectos |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió las 9 pruebas antes de la corrección, corrigió DEF-01 (dominio, caso de uso, contrato 1.3.0 y M35), ejecutó la suite, las mutaciones y el script de reproducción, y generó las capturas de terminal. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Armar la matriz.
- Diseñar las pruebas por técnica.
- Corregir el defecto de la revisión técnica.

## 3. Prompts relevantes

1. Instrucción de la tarea (semana 15 de `actividades2.pdf`): matriz RF/RNF → pruebas; al menos 3 unitarias, 2 de caja blanca y 4 de caja negra; comandos y resultados; capturas; defectos registrados.
2. «Ok, continua, haz las actividades no muy extensas…».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-e2e/` (`Despliegue::esReintento`, `RegistrarDespliegue`, `openapi.yaml` 1.3.0, `tests/Calidad/`, M35) | La corrección y sus pruebas |
| `semana-15/*` | Documento, evidencia, capturas, guía y esta declaración |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Corrección de DEF-01 (ventana de 15 min) | | |
| Pruebas por técnica | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la suite y el script de RT-01.

**Fecha de validación:** ____________
