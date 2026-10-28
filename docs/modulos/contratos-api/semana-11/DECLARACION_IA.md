# Declaración de uso de inteligencia artificial — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) · **Entrega:** Semana 11 (tarea Odoo 2483) — Prototipo navegable desktop y móvil de la consulta de documentación por rol

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el prototipo, el mapa de navegación y el documento; ejecutó `capturar.mjs` con Edge sin interfaz, revisó las capturas, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Construir un prototipo navegable de escritorio y móvil del flujo principal, con su camino feliz y un error crítico, y documentarlo con un mapa de navegación y capturas reales.

## 3. Prompts relevantes

El enunciado de la tarea 2483, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: el documento, `prototipo/` (`app.js` propio; `estilos.css` e `index.html` adaptados de la semana 11 de Catálogo), `evidencia/` (`capturar.mjs`, copia del script de Catálogo de la rama `semana-11-catalogo-salas-camas`, aún sin integrar, y su salida), 8 capturas, el mapa de navegación, esta declaración y la guía. Supuestos: el prototipo usa datos ficticios en memoria y no se conecta al Micro-HIS. El diseño sale de las semanas 8 a 10 (PR #204, #210 y #227), y las rutas de citas-api, del PR #195, todos aún no integrados. Las versiones del prototipo tienen el mismo contenido. La fila de la semana 11 en el `README.md` del módulo se agrega cuando se integre el PR #196. Al construirlo, Claude Code corrigió dos errores propios:

- el aviso de versión obsoleta aparecía ya lleno dentro de su región viva, y los lectores de pantalla no lo anunciarían;
- el script de captura usaba un puerto fijo, y un Edge huérfano de una ejecución cortada lo ocupaba; ahora Edge elige un puerto libre, y los procesos huérfanos se cerraron.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Prototipo, capturas y documento (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 28/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
