# Declaración de uso de inteligencia artificial — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Entrega:** Semana 2 (tarea Odoo 2429) — RF/RNF, criterios de aceptación y SRP en «navegación del dashboard con estados de carga, vacío, error y resumen inicial»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, el ejemplo JavaScript antes/después y su verificación, ejecutó `node --check` y `verificar.mjs`, hizo una prueba de mutación en una carpeta temporal, generó los PNG con PlantUML local, creó la rama e hizo el commit; el push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Derivar los RF/RNF y los criterios de aceptación del modelo de la Semana 1, diseñar el antes/después con SRP, escribir un ejemplo ejecutable en JavaScript (el lenguaje del frontend Vue del repositorio) y redactar esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2429, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo, siguiendo el enunciado al pie de la letra y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo la salida real del script: `semana-02-srp-requerimientos-diseno.md`, `ejemplo-srp/` (`antes.mjs`, `despues.mjs` y `verificar.mjs`), `evidencia/salida-verificar.txt`, `diagramas/` (2 `.puml` y 2 `.png`), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. No se modificó código de `resources/`. Supuestos: el ejemplo usa JavaScript con Node 24 y una API simulada, porque el repositorio no tiene un ejecutor de pruebas de frontend; el límite de 10 s se prueba con 30 ms; CA-08 (tarjetas independientes en pantalla) se verificará en la Semana 15. El README del módulo no se modifica en este PR porque todavía no existe en `develop` (llega con el PR #83).

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Documento, ejemplo y diagramas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 26/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
