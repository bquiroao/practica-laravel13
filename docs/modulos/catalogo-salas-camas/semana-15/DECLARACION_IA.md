# Declaración de uso de inteligencia artificial — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Entrega:** Semana 15 (tarea Odoo 2457) — Pruebas unitarias, de caja blanca y de caja negra trazadas a RF/RNF, con defectos registrados

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió `pruebas-s15.php` y el documento. Ejecutó las pruebas con PHP 8.4.23 contra `develop` y contra una copia del código de la semana 6, y corrió la regresión y dos mutaciones en copias temporales. Generó las 2 capturas de la salida con Edge sin interfaz (el `capturar.mjs` de la semana 11) y las revisó a la vista. Creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Diseñar y ejecutar pruebas del catálogo trazadas a RF/RNF y registrar con evidencia los defectos encontrados.

## 3. Prompts relevantes

El enunciado de la tarea 2457, tomado textualmente de Odoo, y la orden del estudiante de seguir con la S15 en lotes de 3, con una rama desde `origin/develop`, un commit por tarea y las dependencias declaradas.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-15-pruebas.md`, `pruebas/pruebas-s15.php`, `evidencia/salida-pruebas.txt`, `capturas/` (2 PNG de la salida), esta declaración y la guía.

Supuestos:
- Se prueba el Micro-HIS de `develop` sin modificarlo. Los 3 defectos se registran y no se corrigen, porque corregirlos es un PR propio.
- U-05 (capacidad) depende de la semana 6 (PR #433, sin integrar). En `develop` se marca «PEND.», y contra una copia del código de la S6 pasa. El doble espía se adapta a las dos versiones del puerto.
- Lo esperado en CN-02 (« A» = «A») y en CN-06 (503 E8) sale de R-01, de H-03 de la S13 y de E8 de la semana 3. Lo esperado en CN-04 (403) sale de RNF-01 y de CP-04 de la S14.
- DEF-03 muestra el error de PHP porque `display_errors` está activo en este PHP. Con `display_errors` apagado no se verían los detalles, pero tampoco respondería 503 E8.

Validación: `php -l` sin errores; la salida real está en `salida-pruebas.txt`. Contra `develop` pasan 9 de 12 (exit 1), con el código de la S6 pasan 10 de 13, la regresión de `pruebas.php` está en verde y cada mutación la detecta una prueba. **Hallazgo durante la ejecución:** la primera corrida de CN-06 dio 200. Claude Code revisó la respuesta cruda antes de registrarla y encontró el «Fatal error» con SQLSTATE; agregó esa comprobación a CN-06. El texto crudo no se guarda porque incluye rutas locales.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Pruebas, documento, evidencia y capturas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 30/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
