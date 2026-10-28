# Semana 12 — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73-svg`) · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Proceso:** «registro de nota SOAP y asociación de diagnóstico clínico»

| Pedido | Dónde |
|---|---|
| Presentación breve | [presentacion/semana-12-defensa-notas-soap.pptx](presentacion/semana-12-defensa-notas-soap.pptx): 8 diapositivas con notas del orador · [PDF](presentacion/semana-12-defensa-notas-soap.pdf) |
| Recorrido navegable | [recorrido-navegable.md](recorrido-navegable.md), con el prototipo de la semana 11 |
| Matriz decisión → RF/RNF → evidencia | [matriz-decision-rf-rnf-evidencia.md](matriz-decision-rf-rnf-evidencia.md): D-20 a D-31, RNF-08..11 formalizados y RF-16 |
| Respuesta documentada a un cambio solicitado | [registro-cambio-solicitado.md](registro-cambio-solicitado.md): B-3, corrección idempotente, con pruebas primero, medición y costo |

## 1. Qué se defiende, en una frase por tema

| Tema | Decisión central | Cómo se demuestra |
|---|---|---|
| Componentes | La interfaz muestra; el servidor decide cada operación. | Recorrido B5 y HTTP-13 |
| Roles y estados | Lo que cada rol ve sale de una sola regla; estados con icono + texto, iguales en tabla y tarjeta | A1 y A6 |
| Errores | Cada mensaje sale de un código real; el 403 no da motivo | API-04; B5 |
| Riesgos clínicos | Paciente siempre visible; la firma nombra la nota; no se duplica una nota por un reintento | A2, A5, B1–B4 |
| Accesibilidad | WCAG 2.2 AA medido, no estimado | axe = 0 en 28 pantallas; `contraste.py` |
| Movilidad | Una app mobile-first; tabla → tarjetas; hoja inferior | 33 vistas y 14 pantallas móviles sin fallas |
| Cambio solicitado | Reintentar una corrección es tan seguro como reintentar un registro | CID-01: antes 201 → 409, ahora 201 → 201 |

## 2. Cifras verificables

| Comando | Resultado |
|---|---|
| `php micro-his-notas-soap/tests/ejecutar.php` | **124 pruebas: 123 pasan, 0 fallan, 1 omitida** (PostgreSQL). Antes del cambio: 118. |
| `npx @redocly/cli@1.34.3 lint micro-his-notas-soap/api/openapi.yaml` | Válido (v1.2.0) |
| `node semana-11/herramientas/recorrido.cjs` | 28 comprobaciones, 0 fallas, axe 0 |
| `node semana-10/herramientas/verificar-responsive.cjs` | 33 vistas, 0 fallas |
| `php semana-10/herramientas/reintento.php` | Registrar sin clave duplica la nota; con clave, no |
| `python semana-09/herramientas/contraste.py` | 20 pares; 0 propuestas que no cumplen |

## 3. Limitaciones que se declaran

- **Servidor simulado.** El prototipo sigue el contrato, pero no se comparó respuesta por respuesta con el kernel PHP.
- **Sin lector de pantalla real.** Accesibilidad verificada con axe y teclado, no con NVDA, VoiceOver ni TalkBack.
- **La corrección no está en el prototipo navegable.** El cambio se demuestra con sus pruebas.
- **RNF formalizados ahora.** RNF-08..11 se escribieron en esta entrega a partir de criterios que ya se aplicaban desde la semana 8.
- **Brechas.** B-3 queda cerrada. Siguen abiertas B-1 (nombre del médico), B-2 (catálogo CIE-10), B-4, B-5 y B-6 (clave opcional en el registro).

## 4. Defensa oral

**Orden (8 diapositivas, unos 8 minutos):** portada con cifras → componentes → UX → accesibilidad → movilidad → riesgos clínicos y red (demostración de la parte B del recorrido) → cambio solicitado (rojo → guarda → verde) → matriz y limitaciones.

**Preguntas probables:**

- *¿Por qué la corrección no era idempotente desde la semana 6?* Porque el `UNIQUE(supersedes_id)` ya impedía duplicados. Era **seguro** para los datos, pero no para la persona: el 409 parecía un error.
- *¿Por qué reutilizar el puerto del registro?* Ya estaba probado con memoria, SQLite y PostgreSQL, y la huella con `correccion` y la nota impide mezclar claves entre operaciones.
- *¿Qué hizo API-05?* Falló cuando el código empezó a leer la clave y el contrato aún no la declaraba. Por eso se subió el contrato a 1.2.0.
