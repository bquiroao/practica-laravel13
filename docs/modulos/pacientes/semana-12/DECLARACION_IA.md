# Declaración de uso de inteligencia artificial — Semana 12

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 12 — Segunda evaluación parcial |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Proponer el cambio solicitado a partir de la brecha B-4 de la semana 11, escribir sus pruebas primero (B4-01..04) e implementarlo en el Micro-HIS (contrato 1.2.0).
- Actualizar el prototipo, la paridad y el recorrido al contrato 1.2.0.
- Generar la presentación de 8 diapositivas con `pptxgenjs` y exportarla con PowerPoint.
- Redactar la matriz decisión → RF/RNF → evidencia, el recorrido navegable, el registro del cambio, el documento de la semana, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defienda las decisiones de componentes, UX, accesibilidad y movilidad de Pacientes: registro, edición, búsqueda y detalle. Demuestre con el prototipo cómo «registro, búsqueda y actualización segura de un paciente» trata roles, estados, errores y riesgos asociados con identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad. Entregable/Evidencia: Presentación breve, recorrido navegable, matriz decisión→RF/RNF→evidencia y respuesta documentada a un cambio solicitado.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. El cambio solicitado se implementó en el Micro-HIS (`docs/modulos/pacientes/micro-his-pacientes/`); la aplicación Laravel no se modificó.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `../micro-his-pacientes/`: `Paciente`, `DatosPaciente`, `RevisionDeFicha`, ambos `FormularioPaciente`, `openapi.yaml` 1.2.0, `CambioSolicitadoPrueba.php`, mutaciones M25/M26 | Borrador completo |
| `prototipo/*`, `herramientas/*` | Adaptados de la semana 11 al contrato 1.2.0 |
| `presentacion/*` | Generada con un script de `pptxgenjs` (tema de la semana 6); exportada con PowerPoint |
| `capturas/**`, `evidencia/*.txt` | Salida real de los scripts |
| Documentos `.md` de la semana | Borradores |

**Hechos a tener en cuenta:**
- **El cambio solicitado lo eligió la IA**, a partir de una brecha que encontró ella misma al construir el prototipo de la semana 11. No viene de un usuario real.
- **B4-03 falló una vez por un error de la prueba, no del código.** `$x['nombres'] ?? 'falta'` convierte un `null` legítimo en «falta». Se corrigió y la evidencia en rojo se regeneró con las pruebas finales sobre el código anterior.
- **Dos comprobaciones del recorrido fallaron al principio por un error del propio recorrido:** buscaba un apellido en el texto visible, pero los valores de los campos no forman parte de ese texto. Se cambió para leer el valor del campo.
- **La presentación tuvo superposiciones** en las diapositivas 1, 5, 7 y 8, detectadas al revisarla como imágenes. Se corrigieron.
- **PostgreSQL no se ejecutó** (2 pruebas omitidas). El cambio no toca la persistencia.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del cambio solicitado (B-4) | | |
| Implementación y contrato 1.2.0 | | |
| Matriz D-22..D-37 y RNF-12..15 | | |
| Recorrido navegable | | |
| Presentación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté las pruebas (85 pasan, 0 fallan) y las mutaciones (26/26).
- [ ] Recorrí el guion de `recorrido-navegable.md` en el prototipo y lo cronometré.
- [ ] Revisé las 8 diapositivas y sus notas.
- [ ] Puedo explicar por qué omitir `documento` ya no lo borra y por qué `null` sí.

**Fecha de validación:** ____________
