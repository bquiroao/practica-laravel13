# Semana 12 — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Proceso** | «Registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» |
| **Estudiante** | Boris Alexander Quiroa Orellana |

## Entregables

| Pedido | Dónde |
|---|---|
| Presentación breve | [`presentacion/semana-12-defensa-auditoria.pptx`](presentacion/semana-12-defensa-auditoria.pptx) (7 diapositivas, con notas del orador) · [PDF](presentacion/semana-12-defensa-auditoria.pdf) |
| Recorrido navegable | [`recorrido-navegable.md`](recorrido-navegable.md), sobre el [prototipo](prototipo/index.html) actualizado |
| Matriz decisión → RF/RNF → evidencia | [`matriz-decision-rf-rnf-evidencia.md`](matriz-decision-rf-rnf-evidencia.md): D-22..D-39 |
| Respuesta documentada a un cambio solicitado | [`registro-cambio-solicitado.md`](registro-cambio-solicitado.md): «¿Quién vio la ficha de este paciente?» |

## 1. Qué se defiende, en una frase por tema

| Tema | Decisión | Prueba de que funciona |
|---|---|---|
| **Componentes** | Las mismas cuatro capas en el frontend y el backend; el refactor de la consulta deja una decisión por clase | Decisiones de tenant 7 + 7 → 0, medidas; REF-01..03 antes y después |
| **UX** | La inmutabilidad se ve, leer deja rastro y el alcance siempre está a la vista | 15 hallazgos de la evaluación, corregidos y comprobados |
| **Accesibilidad** | WCAG 2.2 AA, comprobado en el prototipo y no solo declarado | axe en cada paso, con teclado y foco: 58 comprobaciones, 0 fallas |
| **Movilidad** | En el teléfono se responde una pregunta concreta, con mala señal | 39 vistas verificadas; reintentos medidos contra el backend |
| **Roles** | El auditor no ve la red ni la integridad, y la API tampoco lo deja | Paridad 12–13: 403 aunque se fuerce |
| **Estados y errores** | Cada respuesta de la API tiene su estado y su mensaje; el error crítico guía sin empeorar | Paridad de 26 pasos; recorrido 16 (ALTERADA) |
| **Riesgos** | Se declaran y tienen dónde cerrarse | §4 |

## 2. El cambio solicitado, en resumen

**«¿Quién vio la ficha P-000001?»** Es la pregunta más común en auditoría clínica, y el módulo no podía responderla.

- **Backend:** una extensión OCP (`FiltroRecurso`), sin tocar el caso de uso.
- **Pruebas primero:** 3 fallan → código → la guarda «contrato = código» falla → contrato 1.2.0 → 0 fallan.
- **Resultado:** 98/98 con PostgreSQL y 26/26 mutaciones.
- **Interfaz:** la pregunta nace en el detalle de un movimiento. El botón lleva a los filtros con el recurso puesto y el foco en él, **sin consultar todavía**.

Detalle completo en el [registro](registro-cambio-solicitado.md).

## 3. Cifras verificables

| Verificación | Resultado | Evidencia |
|---|---|---|
| Pruebas del Micro-HIS con PostgreSQL 18 | 98/98 | [cambio-4-postgres.txt](evidencia/cambio-4-postgres.txt) |
| Mutaciones | 26/26 | [mutaciones.txt](evidencia/mutaciones.txt) |
| Contrato 1.2.0 | Válido | [lint-openapi.txt](evidencia/lint-openapi.txt) |
| Paridad prototipo ↔ kernel | 26 pasos y la semilla, 0 diferencias | [paridad.txt](evidencia/paridad.txt) |
| Recorrido (escritorio y móvil, axe, teclado) | 58, 0 fallas | [recorrido.txt](evidencia/recorrido.txt) |

```bash
cd docs/modulos/gobernanza-auditoria
php micro-his-auditoria/tests/ejecutar.php && php micro-his-auditoria/tests/mutaciones.php
node semana-12/herramientas/paridad.cjs && node semana-12/herramientas/recorrido.cjs
```

## 4. Limitaciones que se declaran en la defensa

- **DEF-07-01**, el cursor que acepta fechas imposibles, sigue abierto: se corrige en la semana 15.
- **Los sellos del prototipo son una imitación.** El prototipo detecta filas faltantes, no contenido alterado; el backend detecta las dos cosas.
- **No hubo prueba con lectores de pantalla reales**, ni con usuarios.
- **El frontend no está integrado** en `resources/js` del HIS: el prototipo es la especificación ejecutable.
- **El recurso es texto libre del emisor:** se propone un catálogo de prefijos en la revisión técnica (semana 13).
