# Semana 2 — Proceso y modelo de diseño; principios SOLID

Módulo: **Admisión hospitalaria y asignación de cama** · Flujo: **admisión de paciente y asignación de una cama disponible**

La semana se entrega en dos partes:
- **Parte A:** este archivo con los requerimientos funcionales (RF), no funcionales (RNF) y criterios de aceptación (CA) en formato Dado/Cuando/Entonces, más el diseño **antes** de aplicar OCP (`diagramas/semana-02-ocp-antes` y `ocp/ejemplo/antes/`).
- **Parte B (`ocp/README.md`):** el diseño **después** de aplicar OCP a la compatibilidad de camas, la justificación de responsabilidades y dependencias y la evidencia ejecutable.

Los IDs de casos de uso (CU), reglas (RN), actividad (A/E) y secuencia (S) vienen de la semana 1.

## 1. Requerimientos funcionales

| ID | Requerimiento | Origen |
|---|---|---|
| RF-01 | El sistema registra la admisión de un paciente identificado por código `PAC-NNNNN`, con servicio, tipo de cama, aislamiento y origen (`EMERGENCIA`, `CONSULTA_EXTERNA` o `REFERENCIA`). | CU-01, A-01 |
| RF-02 | El sistema valida el paciente: formato del código, que exista y esté activo en el Módulo Pacientes y que no tenga otra admisión activa en el hospital. | CU-02, RN-02 |
| RF-03 | El sistema lista las camas disponibles **y compatibles** del hospital del usuario: operativas en el catálogo, sin asignación activa y que cumplan el requerimiento. | CU-03, RN-03, RN-04 |
| RF-04 | El sistema asigna la cama elegida en la **misma transacción** que crea la admisión: o se guardan las dos, o ninguna. | CU-01, A-11 |
| RF-05 | Si otra admisión tomó la cama entre la consulta y la confirmación, el sistema rechaza la asignación con un conflicto recuperable y permite elegir otra cama sin volver a capturar los datos. | CU-05, RN-01 |
| RF-06 | El sistema registra los eventos `ADMISION_CREADA` y `CAMA_ASIGNADA` con usuario, rol, hospital, fecha e id de solicitud. | CU-04, RN-05 |
| RF-07 | El sistema permite consultar una admisión: código de paciente, cama, estado, fecha y responsable. | CU-06 |
| RF-08 | El sistema permite al rol `SUPERVISOR_CAMAS` consultar la trazabilidad (eventos) de una admisión. | CU-06, RN-05 |

## 2. Requerimientos no funcionales

| ID | Atributo | Requerimiento | Cómo se verifica |
|---|---|---|---|
| RNF-01 | Integridad concurrente | Cero camas con dos asignaciones activas. La garantía está en la base de datos (índice único parcial) y en la transacción, no solo en una validación previa. | Prueba con dos conexiones a la base (semana 3) |
| RNF-02 | Seguridad | Cada operación valida rol (RN-06) y hospital (RN-04). El hospital se toma de la sesión o del token, nunca de un dato que envía el cliente. | Pruebas de permisos (semanas 5 y 15) |
| RNF-03 | Privacidad | Pantallas, respuestas, eventos y registros identifican al paciente solo por código; no contienen nombres ni diagnóstico. | Revisión de contrato y de vistas (semanas 5, 8 y 13) |
| RNF-04 | Rendimiento | La consulta de camas disponibles responde en p95 ≤ 500 ms con 500 camas por hospital. **Propuesta:** el umbral se medirá en el plan SQA. | Métrica del plan SQA (semana 14) |
| RNF-05 | Observabilidad | Toda solicitud lleva un id (`X-Request-Id`) que aparece en los eventos y en las respuestas de error. | Prueba de la API (semana 5) |
| RNF-06 | Mantenibilidad | Agregar un criterio de compatibilidad de cama no obliga a modificar el evaluador existente (OCP). | Evidencia ejecutable de la parte B |
| RNF-07 | Accesibilidad | Las pantallas del flujo cumplen WCAG 2.1 nivel AA: teclado, foco visible, contraste ≥ 4.5:1 y errores asociados al campo. | Evaluación de la semana 9 |
| RNF-08 | Resiliencia | Un error de base de datos no deja datos a medias (se revierte la transacción) y devuelve un error recuperable. La espera por un bloqueo tiene un límite (`lock_timeout`). | Prueba de error de persistencia (semana 3) |

## 3. Criterios de aceptación

| ID | Cubre | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-01, RF-04, RF-06 | el admisionista `usr-adm-01` de `HOSP-01`, el paciente `PAC-00451` activo sin admisión y la cama `B-201` libre y compatible | confirma la admisión con la cama `B-201` | queda una admisión `ACTIVA`, la cama `B-201` queda asignada a esa admisión y se registran los eventos `ADMISION_CREADA` y `CAMA_ASIGNADA` |
| CA-02 | RF-02 | un código escrito como `PAC-451` | el admisionista intenta admitir | se rechaza por formato inválido, no se crea nada y lo capturado se conserva |
| CA-03 | RF-02 | el código `PAC-00460`, inactivo en el Módulo Pacientes | el admisionista intenta admitir | se rechaza con «paciente no encontrado o inactivo» |
| CA-04 | RF-02, RN-02 | `PAC-00451` con una admisión activa | se intenta una segunda admisión | se rechaza con «el paciente ya tiene una admisión activa» y no se crea una segunda |
| CA-05 | RF-03, RN-03, RN-04 | requerimiento Medicina interna, cama general, sin aislamiento | el admisionista consulta camas | aparecen `B-201` y `B-202`; no aparecen `B-203` (mantenimiento), `B-210` (reservada para aislamiento), `C-101` (otro servicio) ni camas de `HOSP-02` |
| CA-06 | RF-03 | requerimiento Medicina interna con aislamiento | el admisionista consulta camas | solo aparece `B-210` |
| CA-07 | RF-05, RNF-01 | dos admisiones que eligieron `B-202` al mismo tiempo | ambas confirman | exactamente una queda con `B-202`; la otra recibe «cama ya asignada», no se guarda nada de ella y puede elegir otra cama |
| CA-08 | RNF-02, RN-06 | el usuario `usr-med-01` con rol `MEDICO` | intenta admitir | se rechaza por acceso denegado y no se crea nada |
| CA-09 | RNF-02, RN-04 | el admisionista `usr-adm-02` de `HOSP-02` | intenta asignar una cama de `HOSP-01` | se rechaza como cama no encontrada; nunca ve datos de otro hospital |
| CA-10 | RF-07, RNF-03 | una admisión activa | el médico la consulta | ve código de paciente, cama, estado, fecha y responsable, y ningún nombre |
| CA-11 | RF-08 | una admisión con sus eventos | `usr-sup-01` consulta la trazabilidad | ve los eventos en orden, con usuario, rol, fecha e id de solicitud; un `MEDICO` recibe acceso denegado |
| CA-12 | RNF-08 | la base de datos falla al registrar la asignación | se confirma la admisión | se revierte todo, no queda admisión sin cama y se informa un error recuperable |

## 4. Trazabilidad requerimiento → criterio

| Requerimiento | Criterios |
|---|---|
| RF-01 | CA-01 |
| RF-02 | CA-02, CA-03, CA-04 |
| RF-03 | CA-05, CA-06 |
| RF-04 | CA-01, CA-12 |
| RF-05 | CA-07 |
| RF-06 | CA-01 |
| RF-07 | CA-10 |
| RF-08 | CA-11 |
| RNF-01 | CA-07 |
| RNF-02 | CA-08, CA-09 |
| RNF-03 | CA-10 |
| RNF-06 | Evidencia de la parte B (`ocp/`) |
| RNF-08 | CA-12 |

RNF-04, RNF-05 y RNF-07 se verifican con las mediciones y evaluaciones indicadas en la tabla de la sección 2.
