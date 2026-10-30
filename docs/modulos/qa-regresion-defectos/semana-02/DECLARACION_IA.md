# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 2 — Proceso y modelo de diseño; principios SOLID (LSP) |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó la verificación y las mutaciones, y generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Redactar los RF/RNF y los criterios de aceptación a partir de las reglas de la semana 1.
- Diseñar y programar el ejemplo LSP (antes y después), su verificación y sus mutaciones.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Aplique LSP al diseño del flujo «diseño de casos manuales, campaña de regresión, evidencia, defectos y trazabilidad». Defina RF/RNF y criterios de aceptación; muestre el diseño antes/después, justifique responsabilidades y dependencias y aporte evidencia verificable. Entregable/Evidencia: RF/RNF, criterios de aceptación y ejemplo SOLID.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `ejemplo-lsp/**` | Código completo: antes, después, verificación y mutaciones |
| `diagramas/*` | Fuentes PlantUML y sus PNG |
| `evidencia/*` | Salidas reales de `verificar.php` y `mutaciones.php` |
| `semana-02-lsp-requerimientos-diseno.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **El antes es un diseño construido para mostrar la violación**, no código previo del proyecto. Las dos violaciones son plausibles: «el hash de la URL porque descargar es lento» y «lanzar para proteger los datos».
- **El resultado JUnit de las pruebas es un ejemplo con el formato de PHPUnit.** No es la salida de una corrida real del HIS.
- **RF-11 (trazabilidad)** no tiene criterio ejecutable esta semana.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| RF/RNF y criterios | | |
| Diseño antes/después (LSP) | | |
| Umbrales (5 MB, 95 %) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `verificar.php` y `mutaciones.php`.
- [ ] Revisé que el antes y el después muestren la violación y su corrección.

**Fecha de validación:** ____________
