# Semana 13 — Plan de revisión técnica formal del Expediente médico electrónico base

| | |
|---|---|
| **Tarea Odoo** | 2515 — Semana 13: Calidad del software y revisiones técnicas formales |
| **Módulo** | Expediente médico electrónico base |
| **Flujo revisado** | «Apertura y consulta autorizada del expediente longitudinal» |
| **Base** | Reglas R-01 a R-10 y casos de uso CU-01 a CU-06 de la semana 1, RNF-01 a RNF-04 de la semana 2 y Micro-HIS de la semana 3 (PR #191) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Plan de revisión

Es una **inspección** (IEEE 1028): una revisión formal con roles fijos, un checklist, criterios de entrada y salida, y un acta con métricas. Se eligió la inspección y no un recorrido porque el expediente guarda los datos más sensibles del sistema. Un acceso sin atención activa, sin consentimiento o sin auditoría expone a un paciente aunque el camino feliz funcione.

**Objeto de revisión:** el Micro-HIS `micro-his-expediente` de la semana 3, congelado en el commit `f09b83c` del PR #191: 186 líneas de código y esquemas (`src/`, `database/schema.sql`, `database/schema-central.sql` y `bootstrap.php`) más 59 de pruebas. **El PR #191 aún no está integrado**, así que el objeto no cumple E-01: la reunión no se convoca hasta que se integre. La autorrevisión de la §6 se corrió sobre una copia de ese commit. Los documentos de las semanas 1 y 2 no se revisan: son la referencia contra la que se compara el código.

**Dependencias estrictamente necesarias:** solo los puntos donde el flujo depende de otro módulo. Son la sesión y el rol (RBAC), las asignaciones de atención, el índice maestro de pacientes y los consentimientos de la base CENTRAL, y la auditoría. El código interno de esos módulos queda fuera.

| Fase | Quién | Duración | Producto |
|---|---|---|---|
| 1. Planificación | Moderador y autor | 30 min | Objeto congelado, criterios de entrada verificados, convocatoria con el checklist |
| 2. Presentación | Autor | 15 min | Recorrido de la apertura, la consulta local y la longitudinal, y de las reglas R-01 a R-10; no se buscan errores todavía |
| 3. Preparación individual | Cada revisor | Hasta 2 h (unas 150 líneas por hora) | Hallazgos propios en el formato de la §6 y el tiempo usado |
| 4. Reunión de inspección | Todos | Hasta 2 h | El lector parafrasea el código; se registran hallazgos, no se discuten soluciones |
| 5. Corrección | Autor | Según severidad | Un commit por hallazgo o grupo, con su ID en el mensaje |
| 6. Seguimiento | Moderador | 30 min | Verificación de cada corrección y decisión de salida (§5) |

## 2. Roles

| Rol | Responsabilidad | Restricción |
|---|---|---|
| **Autor:** Josué David Morales Ramírez | Congela el objeto, hace la autorrevisión, presenta y corrige. Responde preguntas, pero no defiende el código en la reunión. | Nunca modera ni registra. |
| **Moderador** | Verifica la entrada, conduce la reunión a ritmo, corta las discusiones de solución y decide la salida. | No puede ser el autor. |
| **Lector** | Parafrasea el código línea a línea durante la reunión, para que nadie lea «lo que cree que dice». | Lo asume uno de los revisores. |
| **Registrador** | Escribe cada hallazgo en el formato de la §6 y lee el acta al final. | Lo asume otro revisor. |
| **Revisor de integridad del expediente** | Ítems C-01 a C-04 y C-14: expediente único, número, pacientes activos, retención y capas. | — |
| **Revisor de acceso y datos sensibles** | Ítems C-05 a C-13 y C-15: atención activa, rol, consentimiento, MPI, alcance, emergencia, auditoría, códigos y rutas. | — |

Las personas las designa el docente o el grupo al convocar; este plan solo fija qué hace cada rol. Con tres revisores, los roles de lector y registrador recaen en dos de ellos.

## 3. Criterios de entrada

| ID | Criterio | Cómo se comprueba |
|---|---|---|
| E-01 | El objeto está congelado en un commit de `develop`. | Hoy **no se cumple**: `f09b83c` está en el PR #191, sin integrar. |
| E-02 | Las pruebas del Micro-HIS están en verde. | `php pruebas/pruebas.php` → «RESULTADO: todo correcto». |
| E-03 | El autor hizo su autorrevisión con el checklist y la dejó reproducible. | [verificar-entrada.php](evidencia/verificar-entrada.php) y su [salida](evidencia/salida-verificacion.txt). |
| E-04 | Los documentos de referencia de las semanas 1 a 3 están integrados en `develop`. | Las semanas 1 y 2 sí; la 3 llega con el PR #191. |
| E-05 | El objeto cabe en una reunión: como máximo 400 líneas. | `wc -l` sobre los archivos del objeto (hoy, 245). |
| E-06 | Cada revisor recibió el objeto y el checklist 24 h antes. | La convocatoria tiene fecha; cada revisor anota su tiempo de preparación. |

## 4. Checklist

Cada ítem se responde **sí**, **no** (abre un hallazgo) o **no aplica**, con su motivo.

| # | Pregunta | Fuente | Dónde mirar |
|---|---|---|---|
| C-01 | ¿Cada paciente tiene un solo expediente en el hospital (E3, 409)? | R-01 | `Paciente::numeroParaAbrir`, `UNIQUE (patient_code)` |
| C-02 | ¿El número de expediente es único? | R-02 | `UNIQUE (record_number)` |
| C-03 | ¿Solo se abre para pacientes activos (E2)? | R-03 | `numeroParaAbrir` |
| C-04 | ¿Ningún camino borra un expediente? | R-04 | Puertos y repositorio |
| C-05 | ¿Consultar exige una asignación de atención activa del profesional (E1, 403)? | R-05, CU-05 | `asignacionActiva` |
| C-06 | ¿Solo admisión abre expedientes, y el profesional sale de la sesión y no de la petición? | CU-01, CU-05 | Entrada del controlador, `abrir` |
| C-07 | ¿La historia de otro hospital exige consentimiento activo, vigente y para el hospital solicitante (E5)? | R-06 | `Consentimiento::vigente`, `consents` |
| C-08 | ¿Un paciente sin vínculo con el MPI solo tiene historia local, con aviso (E4)? | R-10 | `consultar` |
| C-09 | ¿Solo se devuelve el alcance autorizado por la asignación y el consentimiento? | R-09 | `care_assignments`, `consents`, `expedientesExternos` |
| C-10 | ¿El acceso de emergencia exige justificación, dura como máximo 1 hora y deja una revisión pendiente? | R-07, CU-04 | — |
| C-11 | ¿Todo acceso, aceptado, rechazado o fallido, queda auditado con quién, qué paciente y qué resultado? | R-08, CU-06, RNF-04 | `ServicioExpediente::auditado` |
| C-12 | ¿La auditoría no se puede modificar ni borrar? | R-08 | `audit_events` |
| C-13 | ¿Los códigos coinciden con los de la semana 1 (E1 sin asignación, E2, E3, E4 solo local, E5 sin consentimiento), y el 503 no filtra detalles? | Semana 1, §7 (D1 a D8) | `ControladorExpediente::HTTP` |
| C-14 | ¿Solo hay sentencias preparadas, cada capa apunta hacia adentro y el reloj se inyecta? | RNF-02, RNF-03, semana 3 | `AdaptadorPdo`, pruebas |
| C-15 | ¿Las rutas coinciden con las de la semana 1 (`GET /patients/{id}/record` y `…/record/external`)? | Semana 1, §8 | Capa de presentación |

## 5. Criterios de salida

| ID | Criterio |
|---|---|
| S-01 | Los 15 ítems tienen respuesta, y cada «no aplica» tiene su motivo. |
| S-02 | Cada hallazgo tiene ubicación, severidad, tipo, responsable y estado. |
| S-03 | No queda ningún hallazgo crítico ni mayor abierto. Si alguno se pospone, el moderador anota en el acta el motivo y la semana en que se corrige. |
| S-04 | Con un hallazgo crítico o más de tres mayores, se repite la inspección completa; si no, el moderador verifica solo las correcciones. |
| S-05 | Las correcciones dejan las pruebas en verde, y `verificar-entrada.php` marca su hallazgo como «no reproducido». |
| S-06 | El acta registra las métricas: tiempo de preparación por revisor, duración de la reunión, hallazgos por severidad y hallazgos por cada 100 líneas. |

## 6. Formato de hallazgos

| Severidad | Significado |
|---|---|
| **Crítica** | Muestra datos clínicos a quien no está autorizado o deja un acceso sin rastro; bloquea la integración. |
| **Mayor** | Incumple una regla R-xx, un caso de uso o un RNF. |
| **Menor** | Inconsistencia entre documento y código que no rompe una regla. |
| **Observación** | Mejora de claridad o mantenimiento; no exige corrección. |

El **tipo** es omisión (falta algo pedido), error (hace algo distinto de lo pedido) o inconsistencia (dos fuentes se contradicen). Cada fila lleva: ID, ítem del checklist, ubicación (archivo y commit), descripción con evidencia, severidad, tipo, responsable y estado (abierto, corregido, verificado o pospuesto).

**Prueba del formato con la autorrevisión del autor (E-03)** sobre una copia de `f09b83c`. Cada fila se reproduce con [verificar-entrada.php](evidencia/verificar-entrada.php); son hallazgos del autor y la reunión puede reclasificarlos. En esta rama, sin el PR #191, el script avisa que falta el Micro-HIS y sale con código 1.

| ID | Ítem | Ubicación | Descripción y evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | C-13 | `ControladorExpediente::HTTP` | Los códigos no son los de la semana 1: sin asignación activa responde 403 E4 (allí es E1), y E1 no existe. | Menor | Inconsistencia | Autor | Abierto |
| H-02 | C-06 | `ControladorExpediente::atender`, `abrir` | Abrir no comprueba rol ni permiso, y el profesional llega en la petición: m2, un médico sin relación con p5, abre su expediente con 201. | Mayor | Omisión | Autor | Abierto |
| H-03 | C-09 | `schema.sql`, `schema-central.sql` | No hay alcance: ni la asignación ni el consentimiento tienen `scope`, y la consulta externa devuelve todos los expedientes de otros hospitales. | Mayor | Omisión | Autor | Abierto |
| H-04 | C-12 | `audit_events` | La auditoría es una tabla común: un `UPDATE` cambió un rechazo a «ok» (1 fila). | Mayor | Omisión | Autor | Abierto |
| H-05 | C-11 | `ServicioExpediente::auditado` | Solo se audita una `ReglaDeDominio`: con CENTRAL caída, la consulta longitudinal da 503 E8 y no deja ningún evento. | Mayor | Error | Autor | Abierto |

Respuestas de la autorrevisión: C-01 a C-05, C-07, C-08 y C-14 «sí», confirmadas por las pruebas en verde. C-04 se confirma porque ningún puerto ni consulta hace `DELETE`. C-10 «no aplica»: la semana 3 dejó el acceso de emergencia para después, según sus limitaciones. C-15 «no aplica»: las rutas HTTP llegan en la semana 4, aún pendiente. Hay cuatro mayores, así que, según S-04, la inspección se repite completa después de las correcciones.

**Bibliografía:** IEEE (2008). *IEEE Std 1028-2008: Standard for Software Reviews and Audits* · Fagan, M. E. (1976). Design and code inspections to reduce errors in program development. *IBM Systems Journal, 15*(3) · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
