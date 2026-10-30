# Declaración de uso de inteligencia artificial — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación y manuales por rol) · **Entrega:** Semana 15 (tarea Odoo 2487) — Pruebas unitarias, de caja blanca y de caja negra trazadas a RF/RNF, con defectos registrados

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal, con `git` y `gh`. Escribió `pruebas-s15.php` y el documento. Ejecutó las pruebas con PHP 8.4.23 contra `develop` y contra una copia del código de la semana 6, la regresión de `pruebas.php`, la prueba de contrato de la semana 5 y dos mutaciones en copias temporales. Generó las 2 capturas de la salida con Edge sin interfaz (el `capturar.mjs` de la semana 11) y las revisó a la vista. Creó la rama, hizo el commit, el push y el PR con `gh`, con la autorización anticipada del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Diseñar y ejecutar pruebas del centro de documentación trazadas a RF/RNF y registrar con evidencia los defectos encontrados.

## 3. Prompts relevantes

El enunciado de la tarea 2487, tomado textualmente de Odoo, y la orden del estudiante de trabajar en modo autónomo: una rama desde `origin/develop`, un commit por tarea, push y PR hacia `develop` sin esperar su «ok», y la §6 sin marcar hasta que él revise.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: `semana-15-pruebas.md`, `pruebas/pruebas-s15.php`, `evidencia/salida-pruebas.txt`, `capturas/` (2 PNG de la salida), esta declaración y la guía.

Supuestos:
- Se prueba el Micro-HIS de `develop` sin modificarlo. Los 3 defectos se registran y no se corrigen, porque corregirlos es un PR propio.
- Las dependencias de la semana 5 (#350 a #357) y de la semana 4 (#349) ya están integradas en `develop` (verificado con `gh pr list` el 30/10/2026). La semana 6 (#436) sigue sin integrar: solo se usa para comparar, y da el mismo resultado.
- Lo esperado en U-05 (E3) sale de R-02 y de H-04 de la S13; en CN-04 (403), de RNF-01, CU-06 (E1) y CP-04 de la S14; en CN-06 (503 E8), de E8 de la semana 3.
- R-04 y R-09 (lint del contenido y ejemplos ficticios) quedan sin prueba: el Micro-HIS guarda metadatos, no el contenido de cada documento.
- DEF-03 muestra el error de PHP porque `display_errors` está activo en este PHP. Con `display_errors` apagado no se verían los detalles, pero tampoco respondería 503 E8.

Validación: `php -l` sin errores; la salida real está en `salida-pruebas.txt`. Contra `develop` pasan 11 de 14 (exit 1), con el código de la S6 también 11 de 14, la regresión de `pruebas.php` está en verde, la prueba de contrato da 18 de 18 y cada mutación la detecta al menos una prueba. **Incidente durante la ejecución:** el disco se llenó al regenerar la captura 02; tras liberar espacio se volvió a generar desde el mismo texto de la evidencia y se revisó a la vista.

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
