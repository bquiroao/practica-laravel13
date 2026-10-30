# Declaración de uso de inteligencia artificial — Semana 6

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 6 — Primera evaluación parcial: defensa de la arquitectura y cambio práctico «identidad protegida» |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Proponer un cambio práctico que cubriera los seis aspectos pedidos (identidad, datos demográficos, búsqueda, edición, duplicados y confidencialidad), y redactar sus requisitos (R-11, RF-12..RF-16, RNF-10).
- Escribir primero las pruebas del cambio (PROT-01..06, DOM-09, CON-*-08) y después implementarlo en el Micro-HIS.
- Agregar las mutaciones M19..M22 y el script `bin/demo-proteccion.php`.
- Redactar el registro del cambio, la matriz decisión → evidencia, los dos diagramas, el documento de la semana, la guía de defensa y esta declaración.
- Generar la presentación de 8 diapositivas con `pptxgenjs` y exportarla a PDF.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Semana 6: Primera evaluación parcial. Tarea individual: Defienda la arquitectura propuesta para «registro, búsqueda y actualización segura de un paciente» en Pacientes: registro, edición, búsqueda y detalle: conecte actores, UML, RF/RNF, principio SOLID, capas, Repository y contrato API. Responda un cambio práctico considerando identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad. Entregable/Evidencia: Presentación de 6–8 diapositivas, diagrama trazable, matriz decisión→evidencia y registro del cambio práctico defendido.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana el cambio se hizo en el Micro-HIS (`docs/modulos/pacientes/micro-his-pacientes/`), que es el prototipo que evoluciona cada semana. La aplicación Laravel no se modificó.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `../micro-his-pacientes/`: `PoliticaConfidencialidad`, modelo, casos de uso, persistencia, `schema.sql`, `openapi.yaml` 1.1.0, pruebas, mutaciones y `bin/demo-proteccion.php` | Borrador completo |
| `registro-cambio-practico.md` | Borrador completo |
| `matriz-decision-evidencia.md` | Borrador completo |
| `semana-06-defensa-arquitectura.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; imágenes generadas con PlantUML |
| `presentacion/semana-06-defensa-pacientes.pptx` y `.pdf` | Borrador completo, con notas del orador; el PDF se exportó con PowerPoint |
| `evidencia/*.txt` | Salida real de PHP 8.4.26 y Redocly 1.34.3 |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

**Hechos a tener en cuenta:**
- **PROT-06 encontró una fuga real.** Con la primera implementación, el 409 por documento duplicado ocultaba el código de la protegida, pero seguía mostrando su documento enmascarado. Se corrigió: cuando el código es `RESERVADO`, el detalle no incluye el identificador.
- **El diseño de PROT-04 detectó un riesgo de omisión.** Un `PUT` que no envía `identidad_protegida` habría quitado la protección sin querer. Se decidió que el campo ausente significa «sin cambio», y la prueba lo verifica.
- **Hubo un error de interpolación en las pruebas.** En PHP, `"«$var»"` no interpolaba como se esperaba y los mensajes de error salían mal. Se corrigió con llaves (`«{$var}»`) en `ProteccionPrueba` y en `ObservabilidadPrueba`.
- **La mutación M3 se actualizó.** El texto que modificaba cambió con la implementación y el script reportó «NO SE PUDO APLICAR». Se ajustó su objetivo y volvió a detectarse.
- **Tres pruebas existentes se actualizaron** porque el cambio modificó firmas o respuestas a propósito: DOM-06 (nuevo parámetro), PER-02 (nuevo parámetro SQL) y HTTP-04 (nuevo campo en el resumen).
- **Las pruebas contra PostgreSQL real no se ejecutaron** por falta de credenciales. Las 2 pruebas omitidas son esas, y la consulta `SQL_PROTEGIDOS` de `PdoIndiceDuplicados` no se probó contra una base real.
- **Las diapositivas se revisaron como imágenes.** Se exportaron con PowerPoint porque LibreOffice no está instalado. En esa revisión se corrigieron superposiciones en las diapositivas 4, 5 y 8.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del cambio práctico (identidad protegida) | | |
| Requisitos R-11, RF-12..RF-16, RNF-10 | | |
| `PoliticaConfidencialidad` y la vista reducida por defecto | | |
| Código `RESERVADO` en los duplicados | | |
| Matriz decisión → evidencia | | |
| Diagramas | | |
| Presentación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` y obtuve 78 pasan, 0 fallan, 2 omitidas.
- [ ] Ejecuté `php tests/mutaciones.php` y obtuve 22/22 detectadas.
- [ ] Ejecuté `php bin/demo-proteccion.php` y entiendo cada uno de los 8 pasos.
- [ ] Revisé las 8 diapositivas y las notas del orador, y ensayé la exposición.
- [ ] Puedo explicar por qué el campo ausente en el `PUT` no quita la protección.

**Fecha de validación:** ____________
