# Matriz decisión → RF/RNF → evidencia (semanas 7–12)

**Estudiante:** Joshua Eduardo García Reyes · **Proceso:** «registro de nota SOAP y asociación de diagnóstico clínico»

Continúa la matriz de la primera evaluación ([semana 6](../semana-06/matriz-decision-evidencia.md), D-01 a D-19, arquitectura). Aquí van las decisiones de **componentes, UX, accesibilidad y movilidad** (semanas 7 a 11) y el **cambio solicitado** (D-31). Cada evidencia es un comando o una prueba que fallaría si la decisión se rompiera.

## 1. Requisitos que se formalizan

Las semanas 8 a 11 aplicaron criterios que nunca se escribieron como requisitos. Se formalizan aquí con el criterio con que ya se verificaban; se declara así para no presentarlos como si existieran desde el principio.

| ID | Requisito | Criterio de aceptación | Desde |
|---|---|---|:-:|
| RNF-08 | Seguridad del paciente en pantalla: el paciente se ve al escribir y la firma nombra la nota | Recorrido CF-3 y CF-7 (S11); hallazgos H-01 y H-02 (S9) cerrados | S9 |
| RNF-09 | Accesibilidad WCAG 2.2 AA y uso con teclado | axe = 0 en 28 pantallas; contraste: 0 propuestas fallan; foco en «Cancelar» y `Esc` en la firma | S9 |
| RNF-10 | Movilidad de 320 px a escritorio | 33 vistas: sin desborde, objetivos ≥ 44 px, campos ≥ 16 px, hospital y paciente visibles | S10 |
| RNF-11 | Conexión limitada: ninguna escritura se repite sola ni se duplica, y nada clínico queda en el teléfono | `reintento.php` (S10); recorrido EC-1..EC-4 (S11) | S10 |
| **RF-16** | La corrección acepta `Idempotency-Key` (CA-18) | CID-01..05, API-05 | **S12** |

## 2. Matriz

Pruebas: `php tests/ejecutar.php` en `micro-his-notas-soap/` da **124 pruebas: 123 pasan, 0 fallan, 1 omitida** (INT-06, PostgreSQL).

| ID | Decisión | RF / RNF / regla | Dónde vive | Evidencia verificable | Sem. |
|---|---|---|---|---|:-:|
| D-20 | **La interfaz muestra, el servidor decide**: el guard oculta, pero cada operación se vuelve a autorizar | RF-14, R-02 | Componentes Vue (diseño S7); guard de `app.js` (S11) | HTTP-13 (403 por ruta); EC-5 del recorrido | 7·11 |
| D-21 | Refactor: reglas en `ReglasDeEscritura`, guardado con evento en `RegistradorClinico` | RNF-04, R-06 | `src/Dominio/Servicios`, `src/Aplicacion/Servicios` | REF-01..04; P de `CorregirNota` 6 → 4 ([S7](../semana-07/semana-07-componentes-refactor.md)) | 7 |
| D-22 | Catálogo de mensajes M-01..M-30, cada uno ligado a un código real | R-04, R-05, contrato API | [S8 §6](../semana-08/semana-08-ux-notas-soap.md) | API-04: cada código devuelto está en el contrato | 8 |
| D-23 | 403 genérico con referencia, sin rol ni motivo (protección D-1 de S8) | RNF-03 | `vDenegado()`, M-11 | EC-5: sin datos clínicos en pantalla | 8·11 |
| D-24 | Nada clínico en el dispositivo; el borrador vive en memoria (protección D-5 de S8) | RNF-03, RNF-11 | `est.borrador` en `app.js` | Sin `localStorage` en el prototipo; R-M3 (S10) | 8·10·11 |
| D-25 | Evaluar con Nielsen + WCAG + reglas del módulo y priorizar por severidad × impacto clínico | RNF-09 | [Checklist S9](../semana-09/checklist-evaluacion.md) (82 ítems) | 16 hallazgos con recorte; BL-01..16 con criterio verificable | 9 |
| D-26 | Paleta corregida (`#5A6472`, `#8A6100`, `#767F8A`) y foco teal de 3 px | RNF-09 (BL-03, BL-04, BL-09, BL-13) | `semana-10/prototipo/estilos.css` | `contraste.py`: 0 propuestas fallan; axe `color-contrast` = 0 | 9·10 |
| D-27 | Paciente siempre visible y firma que nombra versión, paciente y diagnóstico | **RNF-08** (BL-01, BL-02) | `.paciente`, `abrirFirma()` | `verificar-responsive.cjs` (paciente visible); CF-3, CF-7 | 9·10·11 |
| D-28 | Mobile-first 360 / 431 / 768 / 1024; tabla → tarjetas; hoja inferior | **RNF-10** | `estilos.css` | 33 vistas, 0 fallas (S10); 14 pantallas móviles, 0 fallas (S11) | 10·11 |
| D-29 | Escrituras sin reintento automático; registro siempre con `Idempotency-Key`; firma solo «Consultar estado» | **RNF-11**, I-01, I-02, I-06 | `pedir()`, `vNueva()`, `abrirFirma()` | `reintento.php`: sin clave **duplica**; EC-1..EC-4 | 10·11 |
| D-30 | Prototipo de una sola app con API simulada que sigue el contrato, verificado por un recorrido automático | RNF-09, RNF-10 | `semana-11/prototipo/`, `recorrido.cjs` | 28 comprobaciones, 0 fallas; fallo de vista vieja encontrado y corregido | 11 |
| **D-31** | **Cambio solicitado (B-3): corrección idempotente** con el mismo puerto del registro y huella por tipo de petición | **RF-16**, RF-12, R-06 | `CorregirNota`, `CorregirNotaComando::huella()`, contrato 1.2.0 | CID-01..05; API-05; [registro del cambio](registro-cambio-solicitado.md) | 12 |
