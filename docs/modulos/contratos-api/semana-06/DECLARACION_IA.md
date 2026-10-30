# Declaración de uso de inteligencia artificial — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica · **Entrega:** Semana 6 (tarea Odoo 2478) — Primera evaluación parcial: defensa de la arquitectura de «publicación y consulta de documentación y manuales diferenciados por rol» y cambio práctico

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió la presentación, la matriz, el registro del cambio, el diagrama y el cambio de auditoría en el Micro-HIS con su prueba. Corrió las pruebas y el guion de auditoría antes y después, PlantUML local y Marp (`npx @marp-team/marp-cli@4.0.0`) para comprobar las 8 diapositivas; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender la arquitectura del módulo conectando actores, UML, RF/RNF, ISP, capas, Repository y contrato API, y responder un cambio práctico de versionado documental (auditoría de quién aprueba y quién marca obsoleta) con evidencia ejecutable de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2478, tomado textualmente de Odoo, y la orden del estudiante de hacer la S6 en lotes de 3, declarando la dependencia de la S5 (#350 a #357), con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-06-defensa-arquitectura.md`, `semana-06-diapositivas.md`, `diagramas/` (1 `.puml` y 1 `.png`), `evidencia/` (`probar-auditoria.php` y las salidas de antes, de después y de las pruebas), esta declaración, la guía y, en `micro-his-contratos-api/`, el comentario del puerto en `ServiciosDocumentacion.php`, los dos adaptadores, `schema.sql` y `pruebas.php`.

Supuestos: el cambio práctico lo eligió Claude Code entre los temas del enunciado (versionado documental y permisos), porque ya estaba pedido en R-10 de la S1 y detectado en la S13 (H-03); no lo pidió un evaluador. Enlazar la obsoleta con su reemplazo (R-08, H-05) queda fuera. El esquema real del HIS no tiene tablas de documentos (supuesto de la S1), así que `obsoleted_by` es una propuesta. La diapositiva del contrato depende del PR #354 (S5, aún sin integrar); el cambio no toca rutas ni respuestas, y su prueba de contrato sigue en 18 de 18. La fila de la semana 6 del `README.md` del módulo va en un PR de mantenimiento, porque #354 edita la misma tabla.

Validación: las pruebas pasan de 26 a 28, en verde. El guion de auditoría pasa de `reviewed_by = marta` a `reviewed_by = luis` y `obsoleted_by = marta`, en PDO e InMemory, y Marp generó las 8 diapositivas. Con este código, el script de la S13 (#364) da H-03 «no reproducido», y la métrica M-02 de la S14 (#297) sube a 8 de 10. Claude Code corrigió dos detalles propios: la primera versión interpolaba el nombre de la columna en el SQL, y se cambió por dos sentencias fijas; e InMemory devolvía las claves en otro orden que PDO, y se igualó.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Presentación, matriz, registro, diagrama y cambio en el Micro-HIS (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 29/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
