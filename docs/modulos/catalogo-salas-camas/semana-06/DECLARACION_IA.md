# Declaración de uso de inteligencia artificial — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Entrega:** Semana 6 (tarea Odoo 2448) — Primera evaluación parcial: defensa de la arquitectura de «creación de sala y cama con control de estado operativo» y cambio práctico

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió la presentación, la matriz, el registro del cambio, el diagrama y el cambio de capacidad en el Micro-HIS con sus pruebas. Corrió las pruebas antes y después, PlantUML local y Marp (`npx @marp-team/marp-cli@4.0.0`) para comprobar las 8 diapositivas; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender la arquitectura del módulo conectando actores, UML, RF/RNF, SRP, capas, Repository y contrato API, y responder un cambio práctico (capacidad de la sala) con evidencia ejecutable de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2448, tomado textualmente de Odoo, y la orden del estudiante de hacer la S6 en lotes de 3, declarando la dependencia de la S5 (#350 a #357), con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-06-defensa-arquitectura.md`, `semana-06-diapositivas.md`, `diagramas/` (1 `.puml` y 1 `.png`), `evidencia/` (`probar-capacidad.php` y las salidas de antes, de después y de las pruebas), esta declaración, la guía y, en `micro-his-catalogo-camas/`, `Dominio/CapacidadSala.php` (nuevo), el puerto y `ServicioCatalogo`, los dos adaptadores, el controlador, `VistaJson`, `schema.sql`, `bootstrap.php` y `pruebas.php`.

Supuestos: el cambio práctico lo eligió Claude Code entre los temas del enunciado (capacidad y consistencia); no lo pidió un evaluador. Las camas inactivas no cuentan para la capacidad, y `NULL` es sin tope, para no romper las salas existentes. La migración real (PostgreSQL) no tiene `capacity`: hace falta una migración del esquema compartido, que no es archivo de este módulo. La diapositiva del contrato depende del PR #350 (S5, aún sin integrar). Su OpenAPI todavía no documenta `capacidad` ni E9; se agregan cuando se integre, y su prueba de contrato sigue en 11 de 11 con este cambio. La fila de la semana 6 del `README.md` del módulo va en un PR de mantenimiento, porque #350 edita la misma tabla.

Validación: las pruebas pasan de 28 a 36, en verde. El guion de capacidad cambia de 201 a 409 E9 con PDO e InMemory, y Marp generó las 8 diapositivas. El script de la S13 (#358) sigue reproduciendo H-01 a H-05; H-01 ahora lista E9 entre los códigos. Claude Code corrigió dos errores propios: cambiar la firma de `guardarSala` rompía una llamada directa en las pruebas, y se resolvió con un parámetro opcional; y la primera versión pasaba de 400 líneas, así que se compactaron la salida del guion y esta declaración.

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
