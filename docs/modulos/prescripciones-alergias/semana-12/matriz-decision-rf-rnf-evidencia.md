# Matriz decisión → RF/RNF → evidencia (semanas 7–12)

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «creación de prescripción con verificación previa de alergias»

Continúa la [matriz de la semana 6](../semana-06/matriz-decision-evidencia.md) (D-01..D-23). Cada decisión se enlaza con el requisito que la motiva y con evidencia **ejecutable**: una prueba, una mutación, un paso de paridad o una comprobación del recorrido.

**Estado verificable hoy:**

| Verificación | Resultado |
|---|---|
| `php tests/ejecutar.php` con PostgreSQL 18 | 111/111 |
| `php tests/mutaciones.php` | 35/35 |
| `node semana-12/herramientas/paridad.cjs` | 6 conjuntos de datos + 42 pasos + estado final: 0 diferencias |
| `node semana-12/herramientas/recorrido.cjs` | 86 comprobaciones, 0 fallas |

## 1. Requisitos que se formalizan en esta evaluación

Las semanas 8 a 11 tomaron decisiones de UX. Aquí se convierten en requisitos para poder verificarlas.

| ID | Requisito | Criterio verificable | Origen |
|---|---|---|---|
| RNF-09 | Las alergias activas se ven siempre al prescribir y al verificar, antes de elegir el medicamento | La franja está pegada a la barra y dentro del 40 % superior en todos los anchos (R-P1); aparece en el paso 02 | Semana 8 (P-01), semana 10 |
| RNF-10 | Cada código que la API puede devolver tiene un mensaje del catálogo | 23 códigos → M-01..M-22; cada texto lleva su id | Semana 8 |
| RNF-11 | WCAG 2.2 AA en escritorio y en 320–430 px: objetivos táctiles ≥ 44 px, campos ≥ 16 px, ningún control tapado, foco no oculto | axe sin violaciones; las reglas del recorrido dan 0 | Semanas 9, 10 y 11 |
| RNF-12 | Ningún dato clínico (paciente, medicamento, dosis, justificación, motivo) en la URL ni en el almacenamiento del navegador | R-D1 en cada paso; 0 claves en `localStorage`/`sessionStorage` | Semanas 8 y 10 |
| RNF-13 | El prototipo responde igual que el backend (paridad) | `paridad.cjs`: 0 diferencias; con un defecto inyectado, > 0 | Semana 11 |
| RF-19, RF-20, RNF-17 | `puede_justificar` en la previa | Ver el [registro del cambio](registro-cambio-solicitado.md) | Semana 12 |

## 2. Matriz

| ID | Decisión | RF / RNF / regla | Evidencia | Mutación | Sem. |
|---|---|---|---|---|---|
| D-24 | El permiso override entra al Dominio como una `Closure`: no se pregunta a RBAC si no hay advertencia | RF-06 · R-05 | DOM-09 | M29 | 7 |
| D-25 | `Prescripcion::emitir(decisión)`: es imposible guardar una prescripción que el Dominio no permitió | RF-13 · R-12 | DOM-09, APP-06 | M30 | 7 |
| D-26 | Caracterización grabada **antes** del refactor: 32 escenarios con su respuesta exacta | RNF-16 | CAR-01 | — | 7 |
| D-27 | La franja de alergias está fija bajo la barra y nunca se pliega | RNF-09 · P-01 | Semana 10, R-P1 en 46 vistas; recorrido, pasos 02, 14 y 17 | — | 8, 10 |
| D-28 | Un bloqueo no tiene «forzar»: solo «Cambiar medicamento» | RF-05 · R-04 | Recorrido, paso 16 (sin otra acción); APP-05 | M3 | 8 |
| D-29 | Catálogo único de mensajes, indexado por código de la API | RNF-10 | API-03 (24 escenarios, códigos documentados); textos M-xx en el recorrido | — | 8 |
| D-30 | Ningún `disabled`: `aria-disabled` con la razón visible, o la acción no aparece | RNF-11 · B-01 | Semana 10, R-B1; recorrido, pasos 02–05 | — | 9 |
| D-31 | Paleta corregida por contraste medido: secundario `#5F6670`, verde `#1F6B43`, bordes `#7D838A` | RNF-11 · B-04..B-07 | `semana-09/herramientas/contraste.py` (código 0); axe sin `color-contrast` en 52 capturas | — | 9 |
| D-32 | Un solo glosario de severidad en todas las pantallas | RNF-10 · B-09 | `datos.js` (`severidades`); 0 apariciones de «severa» | — | 9 |
| D-33 | Con un resultado INCIERTO, el borrador se bloquea y el reintento usa la **misma** `Idempotency-Key` | RF-12 · R-11 · B-10 | Semana 10, `reintento.php` (clave nueva = 2 prescripciones); recorrido, pasos 18–20 | M8 | 10 |
| D-34 | Sin reintentos automáticos de emitir ni de anular (la previa, sí, porque no tiene efectos) | RF-12 | Semana 10, `reintento.php`, casos 1 y 6 | — | 10 |
| D-35 | El 409 de una anulación repetida se reconcilia con una consulta: «Su anulación quedó registrada» | RF-15..17 | Paridad, pasos 19–21; recorrido, pasos 22–23 | M32 | 10, 11 |
| D-36 | Nada clínico en la URL ni en el almacenamiento del navegador | RNF-12 | Recorrido: R-D1 en cada paso y «0 claves» | — | 8, 10, 11 |
| D-37 | API simulada con paridad **probada** contra el kernel real | RNF-13 | `paridad.cjs`: 0 diferencias; control R-07: 4; control con la API de la semana 11: 2 | — | 11, 12 |
| D-38 | Error crítico del prototipo: alergia registrada entre la verificación y la emisión | RF-04, RF-14 · R-03 | APP-10 (la emisión vuelve a verificar); paridad, paso 14 (el kernel bloquea); recorrido, pasos 14–17 | M10 (el bloqueo no deja traza) | 11 |
| D-39 | El espacio inferior se mide (barra fija + aviso) en lugar de fijarlo; nueva regla «tapados» | RNF-11 (2.4.11) | Semana 11: primera corrida, 6 fallas → 0 | — | 11 |
| D-40 | `puede_justificar` en la previa con ADVERTENCIA, calculado por la **misma** política que decide al emitir | RF-19, RF-20 | JUS-01..04; paridad, pasos 2 y 11; recorrido, pasos 24 y 26 | M34, M35 | 12 |
| D-41 | Re-aprobación **controlada** de la caracterización: solo se regraba si la única diferencia es la explicada | RNF-17 | `reaprobar-caracterizacion.php`: 31 iguales, 1 con la clave nueva, 0 otras | — | 12 |

## 3. Lo que la matriz no cubre (y se declara)

| Tema | Estado |
|---|---|
| Lectores de pantalla reales | No probados: axe y el foco no reemplazan escuchar NVDA o VoiceOver |
| Usuarios reales | La evaluación de la semana 9 fue heurística, sin participantes |
| Anular idempotente (A-2) | Abierto: la interfaz reconcilia el 409 (D-35), pero la API no tiene clave de idempotencia para anular |
| Nombre del alérgeno en el hallazgo (A-3) | Abierto: la interfaz lo busca en el Expediente |
| Frontend integrado en `resources/js` del HIS | No: el prototipo es la especificación ejecutable |
