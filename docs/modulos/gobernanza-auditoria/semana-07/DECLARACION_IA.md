# Declaración de uso de inteligencia artificial — Semana 7

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 7 — Diseño de componentes backend/frontend y refactor del punto de mayor acoplamiento |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Adaptar el instrumento de acoplamiento (`bin/acoplamiento.php`) a las 6 preocupaciones del módulo y elegir el punto midiendo.
- Escribir las pruebas de caracterización antes del refactor, hacer el refactor y las pruebas unitarias de las piezas extraídas.
- Hacer el diagrama de componentes, los diagramas antes/después y los contratos de entrada/salida.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe los componentes backend y frontend propios de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema. Refactorice conceptualmente el punto con mayor acoplamiento entre audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas, separando UI, aplicación, dominio y persistencia sin ampliar el módulo. Entregable/Evidencia: Diagrama de componentes, contratos de entrada/salida y comparación antes/después de un refactor concreto con justificación.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-auditoria/src` (6 clases nuevas, 2 casos de uso reescritos), `bin/acoplamiento.php`, pruebas REF-01..06, mutaciones M22..M24 | Borrador completo |
| `semana-07/*` (documentos y diagramas) | Borradores |
| `semana-07/evidencia/*.txt` | Salida real de los comandos |

**Hechos a tener en cuenta:**
- **La tarea dice «refactorice conceptualmente».** Se hizo en el código real para poder **medir** y **probar** que no cambió el comportamiento. Si el docente esperaba solo el diseño, el documento se sostiene igual con los diagramas antes/después.
- **REF-02 falló la primera vez por una expectativa equivocada mía**, no del código. La consulta de red ve también la meta-auditoría de la consulta anterior. Se corrigió la prueba, con un comentario.
- **DEF-07-01 es real y ya existía antes del refactor.** Se registró y no se corrigió, para no ampliar el comportamiento.
- **El Ce sube de 10 a 13 en `ConsultarMovimientos`.** Se reporta, no se oculta.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del punto (consulta) | | |
| Las 5 piezas extraídas | | |
| Diseño del frontend | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php bin/acoplamiento.php` y `php tests/ejecutar.php`.
- [ ] Apliqué a mano la mutación M22 y vi fallar las pruebas.

**Fecha de validación:** ____________
