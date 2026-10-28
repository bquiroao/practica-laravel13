# Semana 12 — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Proceso** | «Creación de prescripción con verificación previa de alergias» |
| **Estudiante** | Boris Alexander Quiroa Orellana |

## Entregables

| Pedido | Dónde |
|---|---|
| Presentación breve | [`presentacion/semana-12-defensa-prescripciones.pptx`](presentacion/semana-12-defensa-prescripciones.pptx) (7 diapositivas, con notas del orador) · [PDF](presentacion/semana-12-defensa-prescripciones.pdf) |
| Recorrido navegable | [`recorrido-navegable.md`](recorrido-navegable.md), sobre el [prototipo](prototipo/index.html) actualizado |
| Matriz decisión → RF/RNF → evidencia | [`matriz-decision-rf-rnf-evidencia.md`](matriz-decision-rf-rnf-evidencia.md): D-24..D-41 |
| Respuesta documentada a un cambio solicitado | [`registro-cambio-solicitado.md`](registro-cambio-solicitado.md): «Dígame antes si puedo justificar la advertencia» |

## 1. Qué se defiende, en una frase por tema

| Tema | Decisión | Prueba de que funciona |
|---|---|---|
| **Componentes** | Cuatro capas en el backend y en el frontend; la decisión clínica en **una** política del Dominio | Decisiones clínicas fuera del Dominio: 10 → 0, medidas; caracterización de 32 escenarios |
| **UX** | Las alergias se ven antes de elegir; un bloqueo no tiene «forzar»; cada código tiene su mensaje | 15 hallazgos de la evaluación: 14 corregidos y comprobados, 1 parcial (B-13) |
| **Accesibilidad** | WCAG 2.2 AA, comprobado en el prototipo y no solo declarado | axe en cada paso, con teclado y foco: 86 comprobaciones, 0 fallas |
| **Movilidad** | La franja de alergias es fija; la red inestable nunca duplica ni miente | 46 vistas verificadas; reintentos medidos contra el kernel real |
| **Roles** | Farmacia no prescribe ni anula; sin override, nadie escribe una justificación en vano | Paridad, pasos 9, 10 y 12: 403/409 aunque se fuerce; ahora la previa lo dice (paso 11) |
| **Estados y errores** | Cada respuesta de la API tiene su estado y su mensaje; ante la duda, la pantalla dice que no sabe | Paridad de 42 pasos; recorrido, pasos 16 (alergia nueva) y 18–23 (incierto) |
| **Riesgos** | Se declaran y tienen dónde cerrarse | §4 |

## 2. El cambio solicitado, en resumen

**«Dígame antes si puedo justificar la advertencia.»** La Dra. Ruiz escribió una justificación y el servidor la rechazó: no tiene el permiso override. Es la brecha M-07, abierta desde la semana 8.

- **Backend:** `puede_justificar` en la previa con ADVERTENCIA. Lo calcula `PoliticaEmision`, la misma regla que decide al emitir. La emisión no cambió.
- **Pruebas primero:** fallan 3 → código → fallan la guarda del contrato y la caracterización → contrato 1.2.0 y re-aprobación controlada → 0 fallan.
- **Resultado:** 111/111 con PostgreSQL y 35/35 mutaciones.
- **Interfaz:** la hoja de la advertencia la decide la respuesta de la API, no la copia de los permisos. Se prueba retirando el permiso a mitad de la sesión.

Detalle completo en el [registro](registro-cambio-solicitado.md).

## 3. Cifras verificables

| Verificación | Resultado | Evidencia |
|---|---|---|
| Pruebas del Micro-HIS con PostgreSQL 18 | 111/111 | [cambio-5-postgres.txt](evidencia/cambio-5-postgres.txt) |
| Mutaciones | 35/35 | [mutaciones.txt](evidencia/mutaciones.txt) |
| Contrato 1.2.0 | Válido | [lint-openapi.txt](evidencia/lint-openapi.txt) |
| Paridad prototipo ↔ kernel | 6 conjuntos de datos, 42 pasos y el estado final: 0 diferencias. Control con la API de la semana 11: 2 | [paridad.txt](evidencia/paridad.txt) · [control](evidencia/paridad-control.txt) |
| Recorrido (escritorio y móvil, axe, teclado) | 86 comprobaciones, 0 fallas | [recorrido.txt](evidencia/recorrido.txt) |

```bash
cd docs/modulos/prescripciones-alergias
php micro-his-prescripciones/tests/ejecutar.php && php micro-his-prescripciones/tests/mutaciones.php
node semana-12/herramientas/paridad.cjs && node semana-12/herramientas/recorrido.cjs
```

## 4. Limitaciones que se declaran en la defensa

- **Anular no es idempotente (A-2).** La interfaz reconcilia el 409 con una consulta, pero la API no acepta una clave de idempotencia para anular. Se lleva a la revisión técnica (semana 13).
- **El hallazgo trae el código del alérgeno sin su nombre (A-3).** La interfaz lo busca en el Expediente.
- **No hubo prueba con lectores de pantalla reales**, ni con usuarios.
- **El frontend no está integrado** en `resources/js` del HIS: el prototipo es la especificación ejecutable.
- **B-13 queda parcial:** Esc no cierra la ayuda «?».
