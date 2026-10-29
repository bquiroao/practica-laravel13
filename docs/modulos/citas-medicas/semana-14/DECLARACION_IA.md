# Declaración de uso de inteligencia artificial — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Entrega:** Semana 14 (tarea Odoo 2471) — Plan SQA de «solicitud, validación de disponibilidad y confirmación o cancelación de cita»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el plan SQA y el script de métricas, ejecutó el script para la línea base y tres pruebas de mutación en una copia temporal, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Definir el plan SQA del módulo: 7 métricas con su fuente, umbral, responsable y acción, una línea base medida, la matriz de riesgos, los controles preventivos y detectivos, los responsables y el calendario de seguimiento.

## 3. Prompts relevantes

El enunciado de la tarea 2471, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-14-plan-sqa.md`, `evidencia/medir-metricas.php` y su salida, esta declaración y la guía. El script es genérico: busca el Micro-HIS del módulo y lee las reglas y los casos de uso de la semana 1, así que el mismo archivo sirve a los seis módulos. Supuestos: la línea base se midió sobre el commit `8ffa2e2` de `develop`. M-06 no la mide el script: su valor (5 mayores) sale de la autorrevisión de la semana 13, en el PR #289, aún sin integrar. M-02 y M-03 miden trazabilidad, es decir, si una prueba nombra la regla o el caso de uso. Una regla probada sin nombrarse cuenta como «sin prueba», y eso es deliberado: lo que no se nombra no se puede seguir. Los umbrales (80 %, 15 líneas, 400 líneas) son decisiones del plan; el de 400 sale de la regla del curso. La probabilidad y el impacto de cada riesgo son estimaciones del autor, no datos medidos. El calendario se expresa en semanas del curso, porque solo esta entrega tiene fecha fija. La fila de la semana 14 en el `README.md` del módulo se agrega cuando se integre el PR #195, que también edita esa tabla. Validación: el script es una copia idéntica del de la semana 14 de Catálogo (rama `semana-14-catalogo-salas-camas`, del mismo lote, mismo md5). Se corrió sobre el código real, con 1 métrica que no cumple (M-03), y sale con código 1. En una copia temporal fuera del repositorio se añadió una consulta que concatena una variable y un método de 21 líneas. M-04, M-05 y M-07 pasaron a «NO CUMPLE», y M-01 también, porque la prueba «PDO» del Micro-HIS detecta la consulta. M-02 cumple justo en el umbral (8 de 10); el plan lo trata como riesgo (RG-06).

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
