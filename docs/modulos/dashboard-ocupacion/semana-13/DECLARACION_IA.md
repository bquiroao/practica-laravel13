# Declaración de uso de inteligencia artificial — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Entrega:** Semana 13 (tarea Odoo 2500) — Plan de revisión técnica formal de «consolidación y consulta de ocupación por sala y estado de cama»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Leyó el Micro-HIS y los documentos de las semanas 1 a 3, escribió el plan, el checklist y el script de autorrevisión, ejecutó el script y una prueba de mutación en una copia temporal, creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Preparar la revisión técnica formal del módulo: plan por fases, roles, criterios de entrada y salida, un checklist de 15 ítems trazado a las reglas del módulo y un formato de hallazgos probado con hallazgos reales y reproducibles.

## 3. Prompts relevantes

El enunciado de la tarea 2500, tomado textualmente de Odoo, y la orden del estudiante de adelantar las semanas que no dependen de código sin integrar, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code: `semana-13-revision-tecnica.md`, `evidencia/verificar-entrada.php` y su salida, esta declaración y la guía. La estructura del plan (fases, roles, criterios y formato) es la misma de la semana 13 de Catálogo (PR #288, aún sin integrar); el checklist, los hallazgos y el script son propios de este módulo. Supuestos: el objeto de revisión es el Micro-HIS del commit `8ffa2e2` de `develop`. La ruta HTTP del PR #201 (semana 4), aún sin integrar, no se revisa: C-13 queda «no aplica» hasta que se integre y se congele el nuevo commit. C-08 (procedencia) lo pide el enunciado de la semana 13 y no sale de una regla R-xx. Los datos del script son ficticios: una cama con dos estancias abiertas y otra ocupada en una sala inactiva son casos que la base del hospital no debería permitir, pero que el tablero debe hacer visibles (R-04). Las personas de cada rol no se nombran, porque las designa el docente o el grupo. Los cinco hallazgos son de la autorrevisión del autor, no de una reunión de inspección realizada. Su severidad es una propuesta que la reunión puede cambiar, y H-01 se pospone a la semana 15 porque RNF-01 ya fija ahí su prueba. El ritmo de 150 líneas por hora y el límite de 2 horas son valores habituales de la literatura de inspecciones, no medidos en este grupo. La fila de la semana 13 en el `README.md` del módulo se agrega cuando se integre el PR #201, que también edita esa tabla. Validación: se corrió el script sobre el código real, que reproduce los cinco hallazgos. En una copia temporal se cambió `EXISTS` por «exactamente una estancia» y se usaron los códigos E2 y E9. Con eso, H-02 y H-03 pasaron a «no reproducido», y la entrada pasó a «FALLAN», porque dos pruebas del Micro-HIS esperan los códigos viejos: corregir H-02 exige actualizar también esas pruebas. Al revisar el documento, Claude Code corrigió una afirmación propia: H-05 decía que «el tablero queda en blanco», algo que el Micro-HIS no muestra; ahora dice que no devuelve ningún corte.

**Actualización del 29/10/2026:** al integrarse la semana 4 (PR #201), Claude Code adaptó `evidencia/verificar-entrada.php` a la nueva fábrica y a `VistaJson`. Lo volvió a ejecutar y comprobó que la salida es idéntica. Esta actualización entra en un PR de mantenimiento propio, y el estudiante la aprueba en su lote.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Plan, checklist, hallazgos y script (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 28/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
