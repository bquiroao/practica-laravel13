# Declaración de uso de inteligencia artificial — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Entrega:** Semana 15 (tarea Odoo 2502) — Pruebas unitarias, de caja blanca y de caja negra trazadas a RF/RNF, con defectos registrados

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal, con `git` y `gh`. Escribió `pruebas-s15.php` y el documento. Ejecutó las pruebas con PHP 8.4.23 contra `develop`, y corrió la regresión y dos mutaciones en copias temporales. Generó las 2 capturas de la salida con Edge sin interfaz (el `capturar.mjs` de la semana 11) y las revisó a la vista. Creó la rama, hizo el commit, el push y el PR con `gh`, con la autorización anticipada del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Diseñar y ejecutar pruebas del tablero de ocupación trazadas a RF/RNF y registrar con evidencia los defectos encontrados.

## 3. Prompts relevantes

El enunciado de la tarea 2502, tomado textualmente de Odoo, y la orden del estudiante de trabajar en modo autónomo: una rama desde `origin/develop`, un commit por tarea, push y PR hacia `develop` sin esperar su «ok», y la §6 sin marcar hasta que él revise.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-15-pruebas.md`, `pruebas/pruebas-s15.php`, `evidencia/salida-pruebas.txt`, `capturas/` (2 PNG de la salida), esta declaración y la guía.

Supuestos:
- Se prueba el Micro-HIS de `develop` sin modificarlo; ya incluye la semana 6 (#447) y las semanas 4 y 5 (verificado con `gh pr list` el 30/10/2026), así que no hay dependencias pendientes. Los 3 defectos se registran y no se corrigen, porque corregirlos es un PR propio.
- Lo esperado en CN-04 (403) sale de RNF-01, R-08, CP-04 de la S14 y H-01 de la S13; en CN-07, de R-04 y H-04 (abierto según la semana 6); en CN-08 (503 E8), de R-09 y RNF-04.
- CN-06 fuerza la falla de lectura reemplazando la tabla `wards` del SQLite temporal por una vista que falla al evaluarse, y la restaura después; el Micro-HIS no se toca.
- DEF-03 muestra el error de PHP porque `display_errors` está activo en este PHP. Con `display_errors` apagado no se verían los detalles, pero tampoco respondería 503 E8.

Validación: `php -l` sin errores; la salida real está en `salida-pruebas.txt`. Contra `develop` pasan 13 de 16 (exit 1), la regresión de `pruebas.php` está en verde y cada mutación la detecta al menos una prueba. **Errores del propio ejecutor, corregidos antes del commit:** en la primera corrida CB-01 falló porque el corte de ejemplo ya tenía más de 15 minutos (error de la prueba, no del Micro-HIS), y CN-02 usaba el límite exacto de 900 s por HTTP, que depende de que el reloj del servidor no avance un segundo; el límite exacto quedó en U-04. La primera mutación M1 no se aplicó por un error de comillas, el script lo avisó y se volvió a generar toda la evidencia.

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
