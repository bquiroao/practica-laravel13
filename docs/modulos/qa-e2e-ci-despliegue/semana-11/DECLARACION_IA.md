# Declaración de uso de inteligencia artificial — Semana 11

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones) |
| **Entrega** | Semana 11 — Prototipo navegable de escritorio y móvil |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el prototipo (HTML, CSS, JavaScript), el router `servidor.php`, el recorrido automático con puppeteer-core y axe-core, y el mapa de navegación. Levantó PostgreSQL y el servidor, ejecutó los recorridos y revisó las capturas. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Construir el prototipo sobre la API real.
- Recorrer el camino feliz y el error crítico en dos anchos.
- Documentar los hallazgos.

## 3. Prompts relevantes

1. Instrucción de la tarea (semana 11 de `actividades2.pdf`): prototipo navegable de escritorio y móvil, camino feliz y un error crítico, mapa de navegación y capturas.
2. «ahora estas tareas» (con `actividades2.pdf`), «continua» y «puedes continuar?».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-11/prototipo/*`, `semana-11/servidor.php` | El prototipo y el router |
| `semana-11/herramientas/recorrido.cjs` | El recorrido automático |
| `semana-11/capturas/*`, `evidencia/*`, `diagramas/*` | Salidas y el mapa de navegación |
| `semana-11/*.md` | Documento, guía y esta declaración |

**Hechos a tener en cuenta:**
- **H-3 (contexto contradictorio) se encontró revisando capturas**, con el recorrido en verde.
- **Una comprobación del recorrido (B5) estaba mal escrita y se corrigió.** Era un error del instrumento, no de la interfaz.
- **Los datos son de demostración**, creados por el recorrido contra una base de prueba que se recrea.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Prototipo navegable | | |
| Error crítico elegido | | |
| Mapa de navegación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Recorrí el prototipo a mano en escritorio y en el teléfono.
- [ ] Ejecuté el recorrido automático.

**Fecha de validación:** ____________
