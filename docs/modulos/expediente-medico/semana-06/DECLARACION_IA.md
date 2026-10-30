# Declaración de uso de inteligencia artificial — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 6 (tarea Odoo 2508) — Primera evaluación parcial: defensa de la arquitectura de «apertura y consulta autorizada del expediente longitudinal» y cambio práctico

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió la presentación, la matriz, el registro del cambio, el diagrama y la auditoría de los fallos técnicos en el Micro-HIS con sus pruebas. Corrió las pruebas y el guion de auditoría antes y después, la prueba de contrato de la semana 5, PlantUML local y Marp (`npx @marp-team/marp-cli@4.0.0`) para comprobar las 8 diapositivas; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender la arquitectura del módulo conectando actores, UML, RF/RNF, DIP, capas, Repository y contrato API, y responder un cambio práctico de acceso autorizado a datos sensibles (auditar el intento fallido por CENTRAL caída) con evidencia ejecutable de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2508, tomado textualmente de Odoo, y la orden del estudiante de hacer la S6 en lotes de 3, declarando la dependencia del #349 donde aplique, con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-06-defensa-arquitectura.md`, `semana-06-diapositivas.md`, `diagramas/` (1 `.puml` y 1 `.png`), `evidencia/` (`probar-auditoria.php` y las salidas de antes, de después y de las pruebas), esta declaración, la guía y, en `micro-his-expediente/`, `ServicioExpediente.php` y `pruebas.php`.

Supuestos: el cambio práctico lo eligió Claude Code entre los temas del enunciado (acceso autorizado, integridad y datos sensibles), porque R-08 y RNF-04 ya lo pedían y el hallazgo H-05 de la S13 mostraba que no se cumplía; no lo pidió un evaluador. El evento usa E8 como resultado, el mismo código que ve el cliente. Si la auditoría misma falla, se conserva el error original. La semana 4 (#349) ya está integrada, así que no hay dependencias abiertas. El script de la semana 13 de este módulo no corre sobre `develop` desde que se integró #349; su ajuste va en un PR de mantenimiento aparte, y aquí se verificó con esa versión ajustada. La fila de la semana 6 del `README.md` del módulo va en el PR de mantenimiento de las filas pendientes.

Validación: las pruebas pasan de 25 a 27, en verde. El guion de auditoría pasa de 0 a 1 evento E8 en la consulta longitudinal con CENTRAL caída, y Marp generó las 8 diapositivas. La prueba de contrato de la S5 sigue cumpliéndose, y la S13 ajustada da H-05 «no reproducido». Claude Code corrigió un detalle propio: el guion de evidencia interpolaba un número en el SQL, y se cambió por una sentencia preparada.

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
