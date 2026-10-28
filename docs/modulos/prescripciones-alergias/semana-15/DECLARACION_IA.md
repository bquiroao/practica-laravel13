# Declaración de uso de inteligencia artificial — Semana 15

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 15 — Pruebas unitarias, de caja blanca y de caja negra |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó las pruebas (con y sin PostgreSQL), las mutaciones, la cobertura con phpdbg y la medición SQA, y generó las capturas. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar y escribir las pruebas unitarias, de caja blanca y de caja negra, y las pruebas de los defectos abiertos.
- Corregir DEF-02 con la prueba primero y agregar su mutación (M36).
- Medir la cobertura de la caja blanca, dibujar el grafo de flujo, generar las capturas y redactar la matriz, el registro de defectos, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe y ejecute pruebas de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias trazadas a RF/RNF: unitarias para reglas de prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro; caja blanca con ramas/condiciones del flujo; caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos. Entregable/Evidencia: Matriz RF/RNF→prueba, mínimo 3 unitarias, 2 de caja blanca y 4 de caja negra, comandos/resultados, capturas y defectos registrados.
2. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-prescripciones/tests/{Dominio/UnitariasPrueba,Aplicacion/CajaBlancaPrueba,Presentacion/CajaNegraPrueba,Defectos/DefectosPrueba}.php` | Código de las pruebas |
| `micro-his-prescripciones/src/Aplicacion/CasosDeUso/EmitirPrescripcion.php` | Corrección de DEF-02 (una condición) |
| `micro-his-prescripciones/tests/{ejecutar,mutaciones}.php`, `README.md` | Opción `--defectos`, M36 y cifras |
| `semana-13/plantillas/registro-hallazgos.csv` | AR-02 pasa a `Cerrado`, con su verificación |
| `semana-15/herramientas/*`, `semana-15/diagramas/*` | Herramienta de cobertura (adaptada de Auditoría) y grafo de flujo |
| `semana-15/evidencia/*`, `semana-15/capturas/*` | Salidas reales; las capturas se generan a partir de esos textos |
| Documentos `.md` de la semana 15 | Borradores |

**Hechos a tener en cuenta:**
- **DEF-08 (dosis por ítem) apareció al diseñar la caja negra** y se confirmó contra el kernel antes de escribir la prueba.
- **CB-01 se escribió antes de corregir DEF-02** y falló solo en el camino esperado.
- **Las capturas son imágenes de las salidas de texto reales** de `evidencia/`, no capturas de pantalla de una terminal.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pruebas UNI, CB y CN | | |
| Corrección de DEF-02 | | |
| Severidad de DEF-08 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `tests/ejecutar.php` (con y sin `--defectos`) y la cobertura con phpdbg.
- [ ] Revisé DEF-03 y DEF-08 con un criterio clínico.

**Fecha de validación:** ____________
