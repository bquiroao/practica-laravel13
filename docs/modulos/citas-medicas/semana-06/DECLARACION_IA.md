# Declaración de uso de inteligencia artificial — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Entrega:** Semana 6 (tarea Odoo 2463) — Primera evaluación parcial: defensa de la arquitectura de «solicitud, validación de disponibilidad y confirmación o cancelación de cita» y cambio práctico

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió la presentación, la matriz, el registro del cambio, el diagrama y la barrera de concurrencia en el Micro-HIS con su prueba. Corrió las pruebas y el guion de concurrencia antes y después, PlantUML local y Marp (`npx @marp-team/marp-cli@4.0.0`) para comprobar las 8 diapositivas; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Defender la arquitectura del módulo conectando actores, UML, RF/RNF, DIP, capas, Repository y contrato API, y responder un cambio práctico de concurrencia (dos solicitudes del mismo horario) con evidencia ejecutable de antes y después.

## 3. Prompts relevantes

El enunciado de la tarea 2463, tomado textualmente de Odoo, y la orden del estudiante de hacer la S6 en lotes de 3, declarando la dependencia de la S5 (#350 a #357), con una rama desde `origin/develop` y un commit por tarea.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-06-defensa-arquitectura.md`, `semana-06-diapositivas.md`, `diagramas/` (1 `.puml` y 1 `.png`), `evidencia/` (`probar-concurrencia.php` y las salidas de antes, de después y de las pruebas), esta declaración, la guía y, en `micro-his-citas/`, `ServicioCitas.php` (puerto, `HorarioTomado` y E6), los dos adaptadores, `VistaJson` y `pruebas.php`.

Supuestos: el cambio práctico lo eligió Claude Code entre los temas del enunciado (concurrencia y disponibilidad), porque ya estaba diseñado en la S1 (D7) y detectado en la S13 (H-02); no lo pidió un evaluador. La concurrencia se simula con un repositorio que intercala la segunda solicitud, la misma técnica que el script de la S13; no hay dos procesos reales. En PostgreSQL la barrera final es la exclusión `gist` de la migración real, y traducir su violación a `HorarioTomado` queda para el adaptador de producción. La diapositiva del contrato depende del PR #351 (S5, aún sin integrar); su OpenAPI marca E6 como pendiente y se actualiza cuando se integre. Su prueba de contrato sigue en 12 de 12 con este cambio. La fila de la semana 6 del `README.md` del módulo va en un PR de mantenimiento, porque #351 edita la misma tabla.

Validación: las pruebas pasan de 28 a 30, en verde. El guion de concurrencia pasa de 2 citas activas cruzadas a 1, con 409 E6, en PDO e InMemory, y Marp generó las 8 diapositivas. Con este código, el script de la S13 (#359) da H-02 «no reproducido» y H-01 lista E6 entre los códigos. Claude Code corrigió un error propio: un reemplazo con perl sobre `pruebas.php` no se aplicó, y lo detectó porque el conteo seguía en 28; la prueba se agregó con una edición directa.

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
