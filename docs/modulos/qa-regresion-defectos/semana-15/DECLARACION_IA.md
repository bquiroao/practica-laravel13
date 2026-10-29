# Declaración de uso de inteligencia artificial — Semana 15

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 15 — Pruebas unitarias, de caja blanca y de caja negra |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió las 12 pruebas antes de corregir y las correcciones del código del Micro-HIS QA, con las 5 mutaciones nuevas. Ejecutó la suite, las mutaciones, la sonda y el tablero contra PostgreSQL y generó las capturas de la terminal. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar y ejecutar las pruebas trazadas a RF/RNF y corregir los hallazgos de la revisión de la semana 13 con la prueba primero.
- Redactar la matriz RF/RNF → prueba, el registro de defectos, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe y ejecute pruebas de QA funcional, pruebas manuales y matriz de regresión trazadas a RF/RNF: unitarias para reglas de campañas manuales, matriz de regresión, defectos, severidad, evidencia y trazabilidad; caja blanca con ramas/condiciones del flujo «diseño de casos manuales, campaña de regresión, evidencia, defectos y trazabilidad»; caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos. Entregable/Evidencia: Matriz RF/RNF→prueba, mínimo 3 unitarias, 2 de caja blanca y 4 de caja negra, comandos/resultados, capturas y defectos registrados.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-qa/tests/Semana15/PruebasSemana15Prueba.php` | Las 12 pruebas |
| `micro-his-qa/src/…` (Campana, Defecto, puerto y adaptadores de defectos, ConsultarTrazabilidad, contenedor), `api/openapi.yaml` 1.2.0, `README.md` | Las correcciones |
| `micro-his-qa/tests/` (CON-06, descripciones de APP-01, APP-09, CAM-01, mutaciones M32–M33 y M36–M40) | Ajustes de las pruebas existentes |
| `evidencia/*`, `capturas/*` | Salidas reales y sus capturas |
| `matriz-rf-prueba.md`, `registro-defectos.md`, `semana-15-pruebas.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La IA cometió cuatro errores en sus propias pruebas** (DP-01..DP-04 del registro), y uno de ellos era un oráculo equivocado. Los encontró la primera corrida, antes de tocar el código.
- **La corrección de RT-01 invierte una decisión de la semana 4**, también propuesta con IA. La decisión nueva (contar la severidad propuesta) la tiene que aceptar el estudiante.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Contar la severidad propuesta (DEF-01) | | |
| Todo NO_APLICA → NO_GO (DEF-03) | | |
| Las 12 pruebas | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` con PostgreSQL y `php tests/mutaciones.php`.
- [ ] Revisé la decisión de DEF-01 y la acepto.

**Fecha de validación:** ____________
