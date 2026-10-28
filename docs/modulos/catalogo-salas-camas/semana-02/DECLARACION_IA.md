# Declaración de uso de inteligencia artificial — Semana 2

**Estudiante:** Josué David Morales Ramírez (carné 1890-23-10545) · **Módulo:** Catálogo de salas, wards y camas · **Entrega:** Semana 2 (tarea Odoo 2444) — RF/RNF, criterios de aceptación y SRP en «creación de sala y cama con control de estado operativo»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, el ejemplo PHP antes/después y su verificación, ejecutó `php -l` y `verificar.php`, hizo una prueba de mutación en una carpeta temporal, generó los PNG con PlantUML local, creó la rama, hizo el commit y, tras la aprobación del estudiante, hizo el push y abrió el PR con `gh`. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Derivar los RF/RNF y los criterios de aceptación del modelo de la Semana 1, diseñar el antes/después con SRP, escribir un ejemplo PHP ejecutable que lo demuestre y redactar esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2444, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo, siguiendo el enunciado al pie de la letra y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo la salida real del script: `semana-02-srp-requerimientos-diseno.md`, `ejemplo-srp/` (`antes.php`, `despues.php` y `verificar.php`), `evidencia/salida-verificar.txt`, `diagramas/` (2 `.puml` y 2 `.png`), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: el ejemplo usa repositorio y auditoría en memoria (sin base de datos), porque esta semana se evalúa el diseño; el estado «ocupada» se simula escribiendo en el repositorio, como lo haría admisión; RNF-01 se verificará en la Semana 15. El README del módulo no se modifica en este PR porque todavía no existe en `develop` (llega con el PR #80); la fila de la Semana 2 se agregará cuando ese PR se integre.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Documento, ejemplo y diagramas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 25/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
