# Declaración de uso de inteligencia artificial — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Entrega:** Semana 14 (tarea Odoo 2501) — Plan SQA de «consolidación y consulta de ocupación por sala y estado de cama»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el plan SQA y el script de métricas, ejecutó el script para la línea base y tres pruebas de mutación en una copia temporal, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Definir el plan SQA del módulo: 7 métricas con su fuente, umbral, responsable y acción, una línea base medida, la matriz de riesgos, los controles preventivos y detectivos, los responsables y el calendario de seguimiento.

## 3. Prompts relevantes

El enunciado de la tarea 2501, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-14-plan-sqa.md`, `evidencia/medir-metricas.php` y su salida, esta declaración y la guía. El script es genérico: busca el Micro-HIS del módulo y lee las reglas y los casos de uso de la semana 1, así que el mismo archivo sirve a los seis módulos. Supuestos: la línea base se midió sobre el commit `21c9c52` de `develop`, que ya integra la semana 4 (PR #201) y la inspección de la semana 13 (PR #293). M-06 no la mide el script: su valor (2 mayores) sale de la autorrevisión de esa inspección, hecha sobre el código de la semana 3; al convocar la reunión, los hallazgos se vuelven a reproducir sobre el código actual. M-02 y M-03 miden trazabilidad, es decir, si una prueba nombra la regla o el caso de uso. Una regla probada sin nombrarse cuenta como «sin prueba», y eso es deliberado: lo que no se nombra no se puede seguir. Los umbrales (80 %, 15 líneas, 400 líneas) son decisiones del plan; el de 400 sale de la regla del curso. La probabilidad y el impacto de cada riesgo son estimaciones del autor, no datos medidos. El calendario se expresa en semanas del curso, porque solo esta entrega tiene fecha fija. La fila de la semana 14 en el `README.md` del módulo queda para un PR aparte que agregue juntas todas las filas pendientes. Validación: el script es una copia idéntica del de la semana 14 de Catálogo (PR #295, aún sin integrar, mismo md5). Se corrió sobre el código real, con 1 métrica que no cumple (M-03), y sale con código 1. En una copia temporal fuera del repositorio se añadió una consulta que concatena una variable y un método de 21 líneas. M-04, M-05 y M-07 pasaron a «NO CUMPLE», pero M-01 siguió en verde: la consulta cayó en `RepositorioEnMemoria.php`, y la prueba «PDO» del Micro-HIS solo revisa `RepositorioPdo.php`. M-04, en cambio, revisa toda la carpeta `src/Persistencia`, y por eso RG-08 usa M-04 como control detectivo. La columna de valor de la salida queda pegada a «cumple» en M-05, porque el nombre del método es largo; el script es el mismo de los otros módulos y no se cambió para no perder la copia idéntica.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Plan SQA y script de métricas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 28/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
