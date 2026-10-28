# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Email** | mlopez58@miumg.edu.gt |
| **Módulo** | Ingreso de resultados de laboratorio |
| **Entrega** | Semana 2 — Aplicación de LSP |

## 1. Herramienta utilizada

| Herramienta | Proveedor | Modo de uso |
|---|---|---|
| Asistente de IA en VS Code | GitHub Copilot SDK | Apoyo para revisar la consigna y el modelo del repositorio, preparar el diseño antes/después, desarrollar un ejemplo PHP vanilla y escribir pruebas. |

## 2. Propósito del uso

- Descomponer la consigna en requerimientos, criterios verificables y evidencia.
- Analizar un caso de subtipo que rompe el comportamiento prometido para resultados de laboratorio.
- Diseñar una alternativa con tipos y capacidades explícitos, versionado inmutable y un puerto sustituible.
- Preparar un ejemplo ejecutable y pruebas de contrato que ayuden a revisar el comportamiento.

## 3. Contenido elaborado con apoyo de IA

| Archivo | Aporte de IA |
|---|---|
| `semana-02-lsp-ingreso-resultados.md` | Borrador de RF/RNF, criterios de aceptación, comparación antes/después y trazabilidad. |
| `ejemplo-lsp/src/LabResultWorkflow.php` | Modelo de versión, tipos por estado, puerto, adaptadores y servicio del ejemplo. |
| `ejemplo-lsp/tests/run.php` | Runner PHP de aceptación y contrato de sustitución. |
| `ejemplo-lsp/README.md` | Instrucciones para ejecutar y límites del ejemplo. |
| `GUIA_DEFENSA_ORAL.md` | Borrador del recorrido y preguntas para la defensa. |
| `DECLARACION_IA.md` | Borrador de esta declaración. |

La estudiante debe revisar las decisiones, ejecutar los comandos indicados, comprender las pruebas y corregir cualquier aspecto que no represente su razonamiento o la consigna del curso.

## 4. Revisión y validación humana

| Elemento | Decisión (aceptado / modificado / descartado) | Observaciones |
|---|---|---|
| RF/RNF y criterios | | |
| Ejemplo de violación de LSP | | |
| Diseño corregido y dependencias | | |
| Suite de contrato y resultados | | |
| Límites clínicos y de implementación | | |

- [ ] Leí y entiendo el contrato anterior y por qué falla al sustituir un resultado validado.
- [ ] Ejecuté los comandos `php -l` y la suite `php tests/run.php`.
- [ ] Puedo explicar por qué las correcciones crean versiones en lugar de sobrescribir.
- [ ] Confirmé que el ejemplo no inventa reglas clínicas ni afirma ser una API desplegada.

**Fecha de revisión:** ____________________
