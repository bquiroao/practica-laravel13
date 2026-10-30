# Declaración de uso de inteligencia artificial — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Entrega:** Semana 15 (tarea Odoo 2442) — Pruebas unitarias, de caja blanca y de caja negra trazadas a RF/RNF, con defectos registrados

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal, con `git` y `gh`. Escribió `pruebas-s15.php` y el documento. Ejecutó las pruebas con PHP 8.4.23 contra `develop` y contra una copia del código de la semana 6, y corrió la regresión y dos mutaciones en copias temporales. Generó las 2 capturas de la salida con Edge sin interfaz (el `capturar.mjs` de la semana 11) y las revisó a la vista. Creó la rama, hizo el commit, el push y el PR con `gh`, con la autorización anticipada del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Diseñar y ejecutar pruebas del dashboard trazadas a RF/RNF y registrar con evidencia los defectos encontrados.

## 3. Prompts relevantes

El enunciado de la tarea 2442, tomado textualmente de Odoo, y la orden del estudiante de trabajar en modo autónomo: una rama desde `origin/develop`, un commit por tarea, push y PR hacia `develop` sin esperar su «ok», y la §6 sin marcar hasta que él revise.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-15-pruebas.md`, `pruebas/pruebas-s15.php`, `evidencia/salida-pruebas.txt`, `capturas/` (2 PNG de la salida), esta declaración y la guía.

Supuestos:
- Se prueba el Micro-HIS de `develop` sin modificarlo. Los 3 defectos se registran y no se corrigen, porque corregirlos es un PR propio.
- Las dependencias de las semanas 4 y 5 (#349 a #357) ya están integradas en `develop` (verificado con `gh pr list` el 30/10/2026). U-05 (navegación) depende de la semana 6 (PR #446, sin integrar): en `develop` se marca «PEND.» y no cuenta en el total; contra una copia del código de la S6 pasa.
- Lo esperado en CN-04 (403) sale de CU-05, de CP-04 de la S14 y de H-01 de la S13; en CN-05 (404 para una tarjeta inexistente), de R-07 y de H-03; en CN-07 (503 E5), de R-03 y R-09.
- CN-06 fuerza la falla de una sola tarjeta reemplazando la tabla `beds` del SQLite temporal por una vista que falla al evaluarse; el Micro-HIS no se toca.
- DEF-03 muestra el error de PHP porque `display_errors` está activo en este PHP. Con `display_errors` apagado no se verían los detalles, pero tampoco respondería 503 E5.

Validación: `php -l` sin errores; la salida real está en `salida-pruebas.txt`. Contra `develop` pasan 11 de 14 (exit 1), con el código de la S6 pasan 12 de 15, la regresión de `pruebas.php` está en verde y cada mutación la detecta al menos una prueba.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Pruebas, documento, evidencia y capturas (Claude Code) | En revisión | El estudiante aún no revisa el contenido; ver §6. |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [ ] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [ ] Acepto los supuestos anotados en §4.

**Fecha de validación:** 

Pendiente de validación humana por el estudiante; las casillas se marcarán tras su revisión.
