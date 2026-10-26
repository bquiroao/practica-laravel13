# Declaración de uso de inteligencia artificial — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (adaptación: centro de documentación y manuales por rol) · **Entrega:** Semana 2 (tarea Odoo 2474) — RF/RNF, criterios de aceptación e ISP en «publicación y consulta de documentación y manuales diferenciados por rol»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el documento, el ejemplo PHP antes/después y su verificación, ejecutó `php -l` y `verificar.php`, hizo una prueba de mutación en una carpeta temporal, generó los PNG con PlantUML local, creó la rama e hizo el commit; el push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Derivar los RF/RNF y los criterios de aceptación del modelo de la Semana 1, diseñar el antes/después con ISP, escribir un ejemplo PHP ejecutable con una caché de solo lectura que lo demuestre y redactar esta declaración y la guía de defensa.

## 3. Prompts relevantes

El enunciado de la tarea 2474, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo, siguiendo el enunciado al pie de la letra y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo la salida real del script: `semana-02-isp-requerimientos-diseno.md`, `ejemplo-isp/` (`antes.php`, `despues.php` y `verificar.php`), `evidencia/salida-verificar.txt`, `diagramas/` (2 `.puml` y 2 `.png`), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: el almacén es en memoria (el almacenamiento real se decide en la Semana 4); la caché de lectura es el cliente que justifica separar la interfaz; RNF-01 se verificará en la Semana 15. La primera ejecución de `verificar.php` falló por un error del propio script (le pasaba a la clase «antes» un repositorio del diseño nuevo); se corrigió el script y el diseño no cambió. El README del módulo no se modifica en este PR porque todavía no existe en `develop` (llega con el PR #82).

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
