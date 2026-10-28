# Declaración de uso de inteligencia artificial — Semana 5

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 5 — Cliente-servidor, contrato API, evaluación de la frontera de microservicio y plan de issue, rama, worktree y PR |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. No tuvo acceso a GitHub, no creó el issue ni hizo commits. |

## 2. Propósito del uso

- Escribir el contrato OpenAPI 3.1 y validarlo con Redocly.
- Agregar al micro-HIS la idempotencia del registro (`Idempotency-Key`), el GET condicional (304), `X-Request-ID` y el registro de acceso.
- Escribir la librería cliente (`ClientePacientes`, circuit breaker, transporte HTTP) y sus pruebas.
- Escribir el script de medición y ejecutarlo; contar, con las migraciones reales, las tablas que dependen de `local_patients`.
- Redactar la evaluación de la frontera, los diagramas, el plan de issue/worktree/PR, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Evolucione el MISMO «Micro-HIS Pacientes: registro, edición, búsqueda y detalle» y el flujo «registro, búsqueda y actualización segura de un paciente» hacia un diseño cliente-servidor y evalúe una frontera de microservicio. Especifique contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia; incluya diagramas y una migración razonada. Puede entregar diseño/prototipo: no construya infraestructura de producción ni divida el sistema sin una justificación medible. Entregable/Evidencia: Contrato API y plan de issue, rama, worktree y PR.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana **no** se modificó: la decisión fue no dividir, y las migraciones quedan como tarea T1 del issue.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `../micro-his-pacientes/api/openapi.yaml` | Borrador completo |
| Cambios en `../micro-his-pacientes/` (idempotencia, 304, observabilidad, `src/Cliente/`, `bin/medir.php`, pruebas, mutaciones, README) | Borrador completo |
| `semana-05-cliente-servidor-microservicios.md` | Borrador completo |
| `plan/issue-asii-03-pacientes.md` y `plan/pr-integracion-pacientes.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; imágenes generadas con PlantUML 1.2024.7 |
| `evidencia/*.txt` | Salida real de PHP 8.4.26, Redocly 1.34.3 y `php -S` |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

**Hechos a tener en cuenta:**
- **IDEM-03 se corrigió.** La IA supuso que, sin `Idempotency-Key`, reintentar un registro sin documento crearía un duplicado. La prueba mostró otra cosa: R-03 lo detiene con 409 `POSIBLE_DUPLICADO`. Se corrigió la prueba y el documento explica el problema real: un error engañoso por algo que sí se hizo.
- **CLI-06 se corrigió.** La prueba esperaba `FALLA_SERVICIO`, pero el circuito se abre en esa misma llamada; el resultado correcto es `CIRCUITO_ABIERTO`.
- **La primera evidencia del servidor real se descartó.** Mezclaba dos sesiones y un POST falló por la codificación de la consola de Windows. Se regeneró en una sola sesión, con texto ASCII.
- **Las pruebas contra PostgreSQL real no se ejecutaron** por falta de la contraseña.
- **El issue no se creó en GitHub.** Queda el texto listo y el comando `gh` para que lo cree el estudiante.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Contrato OpenAPI | | |
| Idempotencia del registro | | |
| Librería cliente y políticas de resiliencia | | |
| Decisión de no dividir (§1, §5) | | |
| Propiedad de datos, comunicación, consistencia | | |
| Migración razonada | | |
| Issue, worktree y PR (plan/) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` y obtuve 70 pasan, 0 fallan, 2 omitidas.
- [ ] Ejecuté `php bin/medir.php` y mis números tienen el mismo orden de magnitud.
- [ ] Puedo explicar por qué el POST se reintenta y el PUT no.
- [ ] Revisé el texto del issue antes de crearlo en GitHub.
- [ ] Comprobé que las referencias bibliográficas existan.

**Fecha de validación:** ____________
