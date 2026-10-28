# Declaración de uso de inteligencia artificial — Semana 9

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Entrega:** Semana 9 (tarea Odoo 2451) — Evaluación de usabilidad y accesibilidad del flujo UX con heurísticas y WCAG 2.2

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Revisó los wireframes de la semana 8 y los colores reales del frontend, escribió el checklist, los hallazgos, el backlog y el script `contraste.php`, generó la evidencia con `git show`, `grep` y PHP, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Evaluar el flujo UX de la semana 8 con las heurísticas de Nielsen y WCAG 2.2 AA, documentar los hallazgos con evidencia verificable y priorizar las correcciones según su impacto en el catálogo.

## 3. Prompts relevantes

El enunciado de la tarea 2451, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-09/semana-09-evaluacion-usabilidad.md`, `semana-09/evidencia/` (`contraste.php`, su salida y las líneas citadas de los wireframes), `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: el objeto evaluado es la semana 8 del PR #202, aún no integrado; los enlaces apuntan a su commit `54f336a` y, si el PR cambia, se revisan los hallazgos. Las pantallas del módulo reutilizarán el layout y el estilo de formulario reales (`AppLayout.vue`, `LoginPage.vue`). La paleta de estados y el borde propuestos son decisiones de diseño, medidas con el script. Hubo un solo evaluador. La fila de la semana 9 en el `README.md` del módulo se agrega cuando se integre el PR #194, que también edita esa tabla. Al generar la evidencia, Claude Code corrigió dos errores propios: unos patrones de `grep` que no coincidían con nada o con todas las líneas, y una autoprueba que dependía de `zend.assertions`.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Evaluación, backlog y script (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 27/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
