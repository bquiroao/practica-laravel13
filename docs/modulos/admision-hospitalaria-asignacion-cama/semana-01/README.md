# Semana 1 — Conceptos generales, orientación a objetos y UML

Módulo: **Admisión hospitalaria y asignación de cama** · Estudiante: Erick Rolando Ramazzini Muralles

Proceso modelado: **admisión de paciente y asignación de una cama disponible**.

## 1. Narrativa de alcance

Un paciente que llega por emergencia, consulta externa o referencia necesita quedar **admitido** en el hospital con **una cama asignada** que sea compatible con lo que requiere (servicio, tipo de cama y aislamiento). El módulo registra la admisión, asigna la cama y deja evidencia de quién lo hizo y cuándo.

El problema central es de **integridad**: en un turno con poca disponibilidad dos admisionistas pueden ver la misma cama libre al mismo tiempo. El módulo garantiza que **solo una** de las dos admisiones se queda con ella (RN-01) y que la otra recibe un conflicto recuperable para elegir otra cama (CU-05).

### Incluido en el módulo
- Registrar la admisión hospitalaria de un paciente identificado **solo por código** (`PAC-NNNNN`).
- Consultar las camas **disponibles y compatibles** para esa admisión.
- Asignar una cama disponible dentro de una transacción.
- Registrar la trazabilidad (eventos inmutables con usuario, rol, fecha y hospital).
- Consultar una admisión y sus eventos.

### Fuera de alcance (pertenece a otros módulos)
| Módulo vecino | Qué le pertenece | Cómo se relaciona Admisión con él |
|---|---|---|
| `catalogo-salas-camas` (Josué Morales) | Salas, wards, camas y su estado operativo | Admisión **solo lee** si la cama existe y está operativa. No escribe en sus tablas. El aviso de «cama asignada» se coordina por contrato (evento o API): **pendiente de confirmar con Josué**. |
| `pacientes` | Datos personales y clínicos del paciente | Admisión solo consulta si el código existe y está activo. No guarda ni muestra nombres. |
| `dashboard-ocupacion` | Indicadores de ocupación | Lee las asignaciones activas que publica Admisión. Admisión no le escribe. |
| Traslados y altas | Mover al paciente o liberar la cama | Cierran la asignación mediante un contrato que expone Admisión: **pendiente de definir con esos módulos**. |

### Supuestos (propuesta, por confirmar)
- El hospital es el **tenant**: cada usuario pertenece a un solo hospital y solo ve y admite dentro de él.
- «Cama disponible» = cama **operativa** en el catálogo **y** sin asignación activa en Admisión.
- Todos los datos de este módulo son **ficticios**.

## 2. Actores

| Actor | Tipo | Objetivo |
|---|---|---|
| Admisionista | Humano, rol `ADMISIONISTA` | Admitir al paciente y dejarlo con una cama asignada. |
| Supervisor(a) de camas | Humano, rol `SUPERVISOR_CAMAS` | Lo mismo que el admisionista, más consultar la trazabilidad completa. |
| Médico tratante | Humano, rol `MEDICO` | Consultar la admisión y la cama de su paciente. No admite. |
| Módulo Pacientes | Sistema externo | Confirmar que el código de paciente existe y está activo. |
| Catálogo de salas y camas | Sistema externo | Informar qué camas existen, sus características y si están operativas. |

## 3. Casos de uso

| ID | Caso de uso | Actor principal | Relación |
|---|---|---|---|
| CU-01 | Admitir paciente y asignar cama disponible | Admisionista | Caso principal |
| CU-02 | Validar paciente (existe, activo, sin admisión activa) | — | `<<include>>` de CU-01 |
| CU-03 | Consultar camas disponibles y compatibles | Admisionista | `<<include>>` de CU-01 |
| CU-04 | Registrar evento de trazabilidad | — | `<<include>>` de CU-01 |
| CU-05 | Elegir otra cama ante conflicto | Admisionista | `<<extend>>` de CU-01 cuando otra admisión tomó la cama |
| CU-06 | Consultar admisión y su trazabilidad | Médico, Supervisor | Independiente |

## 4. Reglas de negocio (se usan con los mismos IDs en todo el módulo)

| ID | Regla |
|---|---|
| RN-01 | Una cama solo puede tener **una** asignación activa. Dos admisiones simultáneas no pueden quedarse con la misma cama. |
| RN-02 | Un paciente solo puede tener **una** admisión activa por hospital. |
| RN-03 | La cama debe ser compatible con el requerimiento: mismo servicio, mismo tipo de cama y aislamiento que coincida (una cama de aislamiento se reserva para quien lo requiere). |
| RN-04 | Solo se asignan camas **operativas** del catálogo y del **mismo hospital** del usuario. |
| RN-05 | Cada admisión y cada asignación generan eventos de trazabilidad **inmutables** (usuario, rol, fecha, hospital, id de solicitud). |
| RN-06 | Solo `ADMISIONISTA` y `SUPERVISOR_CAMAS` pueden admitir; `MEDICO` solo consulta. |

## 5. Trazabilidad entre los tres diagramas

Las acciones del diagrama de actividad llevan un ID `A-NN` y las excepciones un ID `E-NN`. El diagrama de secuencia numera sus mensajes `S-NN` y escribe entre corchetes la acción o excepción que realiza.

| Caso de uso | Actividad | Secuencia | Regla |
|---|---|---|---|
| CU-01 | A-01 captura de datos | S-01, S-02 | — |
| CU-01 | A-02 rol y tenant / E-01 | S-03 / S-22 | RN-06, RN-04 |
| CU-02 | A-03 formato del código / E-02 | S-04 / S-23 | — |
| CU-02 | A-04, A-05 paciente existe y activo / E-03 | S-05, S-06 / S-24 | — |
| CU-02 | A-06 sin admisión activa / E-04 | S-07, S-08 / S-25 | RN-02 |
| CU-03 | A-07, A-08, A-09 camas compatibles / E-05 | S-09 a S-12 / S-26 | RN-03, RN-04 |
| CU-01 | A-10 elegir cama | (nota previa a S-01) | — |
| CU-01 | A-11, A-12 transacción e índice único | S-13 a S-16 | RN-01 |
| CU-05 | E-06, A-13 conflicto y otra cama | S-27 a S-30 | RN-01 |
| CU-04 | A-14 eventos de trazabilidad | S-17 | RN-05 |
| CU-01 | A-15, A-16 confirmar y mostrar resultado | S-18 a S-21 | — |

E-05 cubre dos momentos: la lista queda vacía (actividad) o la cama elegida dejó de ser compatible u operativa al confirmar (secuencia, S-26).

## 6. Diagramas

| Diagrama | Archivo |
|---|---|
| Casos de uso | `diagramas/semana-01-casos-de-uso.puml` / `.png` |
| Actividad de CU-01 (decisiones, excepciones y resultado) | `diagramas/semana-01-actividad.puml` / `.png` |
| Secuencia de CU-01 (participantes, mensajes, validaciones y respuesta) | `diagramas/semana-01-secuencia.puml` / `.png` |

Los `.png` se generaron desde sus `.puml` con PlantUML.

## 7. Datos ficticios de referencia

Se usan los mismos en todas las semanas.

| Dato | Valores |
|---|---|
| Hospitales (tenant) | `HOSP-01`, `HOSP-02` |
| Pacientes | `PAC-00451`, `PAC-00452`, `PAC-00453` (HOSP-01); `PAC-00460` inactivo; `PAC-00900` (HOSP-02) |
| Camas HOSP-01 | `B-201`, `B-202` (Medicina interna, general); `B-203` (en mantenimiento); `B-210` (aislamiento); `C-101` (Cirugía); `U-01` (UCI) |
| Usuarios | `usr-adm-01` admisionista, `usr-sup-01` supervisor(a), `usr-med-01` médico (HOSP-01); `usr-adm-02` admisionista (HOSP-02) |
