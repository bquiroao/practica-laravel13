# Declaración de uso de inteligencia artificial — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 2 (tarea Odoo 2504) — RF/RNF, criterios de aceptación y DIP en «apertura y consulta autorizada del expediente longitudinal»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, el ejemplo PHP antes/después y su verificación, ejecutó `php -l` y `verificar.php`, hizo una prueba de mutación en una carpeta temporal, generó los PNG con PlantUML local, creó la rama e hizo el commit; el push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Derivar los RF/RNF y los criterios de aceptación del modelo de la Semana 1, diseñar el antes/después con DIP, escribir un ejemplo PHP ejecutable con dos adaptadores de la base HOSPITAL (memoria y PDO/SQLite) y redactar esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2504, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo, siguiendo el enunciado al pie de la letra y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo la salida real del script: `semana-02-dip-requerimientos-diseno.md`, `ejemplo-dip/` (`antes.php`, `despues.php` y `verificar.php`), `evidencia/salida-verificar.txt`, `diagramas/` (2 `.puml` y 2 `.png`), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: el índice central y la auditoría son en memoria (la base CENTRAL real es otra); el alcance de datos (R-09) se modela en la Semana 4; las horas son segundos enteros. Al revisar el código, Claude Code agregó la auditoría que faltaba en el camino «solo historia local». El README del módulo no se modifica porque todavía no existe en `develop` (llega con el PR #85).

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
