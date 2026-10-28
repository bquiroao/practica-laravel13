# Declaración de uso de inteligencia artificial — Semana 7

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias |
| **Entrega** | Semana 7 — Diseño de componentes y refactorización |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó la medición, la caracterización, las pruebas y las mutaciones. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir el medidor de acoplamiento y elegir el punto a refactorizar con sus números.
- Grabar la caracterización antes del refactor y escribir la prueba que la compara.
- Hacer el refactor (`PoliticaEmision`, `DecisionEmision`, `Prescripcion::emitir`) y sus pruebas y mutaciones.
- Diseñar los componentes frontend y redactar los contratos de entrada/salida, los diagramas, el documento y la guía.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe los componentes backend y frontend propios de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias. Refactorice conceptualmente el punto con mayor acoplamiento entre prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro, separando UI, aplicación, dominio y persistencia sin ampliar el módulo. Entregable/Evidencia: Diagrama de componentes, contratos de entrada/salida y comparación antes/después de un refactor concreto con justificación.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `micro-his-prescripciones/` (`PoliticaEmision`, `DecisionEmision`, `Prescripcion::emitir`, casos de uso, `bin/acoplamiento.php`, `bin/caracterizar.php`, `tests/Caracterizacion/*`, DOM-09, mutaciones) | Código completo |
| `semana-07-componentes-refactor.md`, `contratos-entrada-salida.md` | Borradores completos |
| `diagramas/*.puml` y `*.png` | Borrador completo; imágenes generadas con PlantUML |
| `refactor/antes/*` | Copia del código anterior, sin cambios |
| `evidencia/*.txt` | Salidas reales de la ejecución |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- La caracterización se grabó **antes** del refactor. Se volvió a grabar una sola vez, también antes del refactor, para corregir la etiqueta de un escenario («consultar 3» consultaba la 2). El contenido observable no cambió.
- La métrica Dc la definió la IA para esta tarea; cuenta menciones, no semántica (§10 del documento).
- Los componentes frontend son **diseño**: no hay código Vue en el repositorio.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del punto de mayor acoplamiento | | |
| `PoliticaEmision` y el override como `Closure` | | |
| Componentes frontend propuestos | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté el medidor, la caracterización y la suite, y obtuve los mismos resultados.
- [ ] Revisé que el refactor no cambió ninguna respuesta de la API.

**Fecha de validación:** ____________
