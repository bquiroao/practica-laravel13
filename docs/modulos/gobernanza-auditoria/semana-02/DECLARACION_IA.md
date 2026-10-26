# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 2 — RF/RNF, criterios de aceptación y aplicación de OCP |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Redactar los RF, RNF y criterios de aceptación a partir de las reglas de la semana 1.
- Diseñar y programar el ejemplo antes/después, con la misma estructura que la semana 2 de RBAC, y su verificación.
- Escribir el script de mutaciones y los diagramas de clases.
- Redactar el documento, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Aplique OCP al diseño del flujo «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas». Defina RF/RNF y criterios de aceptación; muestre el diseño antes/después, justifique responsabilidades y dependencias y aporte evidencia verificable. Entregable/Evidencia: RF/RNF, criterios de aceptación y ejemplo SOLID.
2. Instrucción permanente: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL`.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-02-ocp-requerimientos-diseno.md` | Borrador completo |
| `ejemplo-ocp/**` (antes, después, `verificar.php`, `mutaciones.php`) | Borrador completo |
| `diagramas/*` | Borrador completo; imágenes generadas con PlantUML |
| `evidencia/*.txt` | Salida real de los scripts |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Una prueba falló por un error de la IA.** CA-13 esperaba un orden equivocado: el movimiento de las 23:30 es el más tardío del día, así que va primero. El código estaba bien; se corrigió la expectativa.
- **Una mutación sobrevivió y resultó equivalente** (secuencias consecutivas). Se reemplazó por otra y se documentó el análisis.
- **Se reforzó CA-11** con un atacante que recalcula el sello de la fila alterada. Sin ese caso, la mutación «sello sin cadena» no se habría detectado.
- **El límite de la última fila** (truncamiento) se documenta como pendiente; no está resuelto.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| RF-01..RF-13, RNF-01..RNF-09 | | |
| Puntos de extensión elegidos | | |
| Invariantes fijos en los servicios | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `verificar.php` (21/21) y `mutaciones.php` (8/8).
- [ ] Hice la demostración de comentar la extensión.

**Fecha de validación:** ____________
