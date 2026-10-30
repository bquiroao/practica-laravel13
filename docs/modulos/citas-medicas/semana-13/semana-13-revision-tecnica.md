# Semana 13 — Plan de revisión técnica formal de Citas médicas y agenda

| | |
|---|---|
| **Tarea Odoo** | 2470 — Semana 13: Calidad del software y revisiones técnicas formales |
| **Módulo** | Citas médicas y agenda |
| **Flujo revisado** | «Solicitud, validación de disponibilidad y confirmación o cancelación de cita» |
| **Base** | Reglas R-01 a R-10 y casos de uso CU-01 a CU-05 de la semana 1, RNF-01 a RNF-05 de la semana 2 y Micro-HIS de la semana 3 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Plan de revisión

Es una **inspección** (IEEE 1028): una revisión formal con roles fijos, un checklist, criterios de entrada y salida, y un acta con métricas. Se eligió la inspección y no un recorrido porque la agenda tiene concurrencia real, con dos recepcionistas que piden el mismo horario, y esos errores no aparecen al leer el camino feliz.

**Objeto de revisión:** el Micro-HIS [micro-his-citas](../micro-his-citas/), congelado en el commit `8ffa2e2` de `develop`: 185 líneas de código y esquema (`src/`, `database/schema.sql` y `bootstrap.php`) más 56 de pruebas. Los documentos de las semanas 1 a 3 no se revisan: son la referencia contra la que se compara el código.

**Dependencias estrictamente necesarias:** solo los puntos donde el flujo depende de otro módulo. Son el permiso y el hospital de la petición (RBAC y `X-Tenant-ID`), el usuario que firma la cancelación (auditoría) y la especialidad y el perfil vigentes del médico (catálogo de personal). El código interno de esos módulos queda fuera.

| Fase | Quién | Duración | Producto |
|---|---|---|---|
| 1. Planificación | Moderador y autor | 30 min | Objeto congelado, criterios de entrada verificados, convocatoria con el checklist |
| 2. Presentación | Autor | 15 min | Recorrido del flujo, de las reglas R-01 a R-10 y de los estados de una cita; no se buscan errores todavía |
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
| **Revisor de dominio y concurrencia** | Ítems C-01 a C-11: horarios, cruces, vigencia, estados, cancelación y carreras. | — |
| **Revisor de API, permisos y tenant** | Ítems C-12 a C-15: contrato, autorización, aislamiento por hospital y errores técnicos. | — |

Las personas las designa el docente o el grupo al convocar; este plan solo fija qué hace cada rol. Con tres revisores, los roles de lector y registrador recaen en dos de ellos.

## 3. Criterios de entrada

| ID | Criterio | Cómo se comprueba |
|---|---|---|
| E-01 | El objeto está congelado en un commit de `develop`. | El hash va en la convocatoria; si cambia antes de la reunión, se vuelve a convocar. |
| E-02 | Las pruebas del Micro-HIS están en verde. | `php pruebas/pruebas.php` → «RESULTADO: todo correcto». |
| E-03 | El autor hizo su autorrevisión con el checklist y la dejó reproducible. | [verificar-entrada.php](evidencia/verificar-entrada.php) y su [salida](evidencia/salida-verificacion.txt). |
| E-04 | Los documentos de referencia de las semanas 1 a 3 están integrados en `develop`. | Las rutas `semana-01` a `semana-03` existen en el commit congelado. |
| E-05 | El objeto cabe en una reunión: como máximo 400 líneas. | `wc -l` sobre los archivos del objeto (hoy, 241). |
| E-06 | Cada revisor recibió el objeto y el checklist 24 h antes. | La convocatoria tiene fecha; cada revisor anota su tiempo de preparación. |

## 4. Checklist

Cada ítem se responde **sí**, **no** (abre un hallazgo) o **no aplica**, con su motivo.

| # | Pregunta | Fuente | Dónde mirar |
|---|---|---|---|
| C-01 | ¿El horario es futuro y su fin es posterior al inicio, en el servicio y en la base? | R-01 | `Horario::validar`, `CHECK (ends_at > starts_at)` |
| C-02 | ¿Un médico no puede tener dos citas activas que se crucen, con intervalo `[inicio, fin)`? | R-02 | `RepositorioPdo::cruce` |
| C-03 | ¿Un paciente no puede tener dos citas activas que se crucen? | R-03 | `cruce` |
| C-04 | ¿La especialidad del médico está activa **y vigente en la fecha de la cita**? | R-04 | `medicoVigente`, `doctor_specialties` |
| C-05 | ¿Se comprueba que el perfil del médico esté activo y vigente? | R-05 | `medicoVigente` |
| C-06 | ¿El motivo es obligatorio, sin aceptar solo espacios? | R-06 | `solicitar`, `CHECK (trim(reason) <> '')` |
| C-07 | ¿Las transiciones son exactamente las de R-07, y una cita completada o cancelada ya no cambia? | R-07 | `EstadoCita::validarCambioA` |
| C-08 | ¿Confirmar rechaza una cita que ya empezó (E7)? | CU-02 | `ServicioCitas::confirmar` |
| C-09 | ¿Cancelar exige motivo y libera el horario, y ninguna cita se borra? | R-08 | `cancelar`, `cruce` (solo activas) |
| C-10 | ¿Cada cancelación guarda usuario, fecha y motivo en la misma transacción que el cambio de estado? | R-10, RNF-05 | `cambiarEstado`, `appointment_cancellations` |
| C-11 | ¿Dos solicitudes simultáneas del mismo horario dejan una sola cita, y la otra recibe 409 E6? | CU-01 (D7), RNF-04 | `solicitar` entre `cruce` y `guardar` |
| C-12 | ¿Cada error tiene un código y un HTTP fijos (E1 a E7 y NF), y el 503 no filtra detalles técnicos? | Semana 1, §7 (D1 a D8) | `ControladorCitas::HTTP` |
| C-13 | ¿Las rutas y los payloads coinciden con los de la semana 1 (`POST /appointments`, `PATCH /appointments/{id}/cancel`)? | Semana 1, §8 | Capa de presentación |
| C-14 | ¿Toda operación verifica sesión, permiso y hospital (`X-Tenant-ID`), y el médico solo cancela sus propias citas (E1, 403)? | R-09, CU-05, RNF-01 | Entrada del controlador |
| C-15 | ¿Solo hay sentencias preparadas, `PDOException` se traduce a E8, cada capa apunta hacia adentro y «ahora» se inyecta? | Semana 3, RNF-03 | `RepositorioPdo`, `Reloj`, pruebas §3 y §4 |

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
| **Crítica** | Expone datos de otro hospital o deja la agenda inconsistente sin ninguna barrera; bloquea la integración. |
| **Mayor** | Incumple una regla R-xx, un caso de uso o un RNF. |
| **Menor** | Inconsistencia entre documento y código que no rompe una regla. |
| **Observación** | Mejora de claridad o mantenimiento; no exige corrección. |

El **tipo** es omisión (falta algo pedido), error (hace algo distinto de lo pedido) o inconsistencia (dos fuentes se contradicen). Cada fila lleva: ID, ítem del checklist, ubicación (archivo y commit), descripción con evidencia, severidad, tipo, responsable y estado (abierto, corregido, verificado o pospuesto).

**Prueba del formato con la autorrevisión del autor (E-03)** sobre `8ffa2e2`. Cada fila se reproduce con [verificar-entrada.php](evidencia/verificar-entrada.php); son hallazgos del autor y la reunión puede reclasificarlos.

| ID | Ítem | Ubicación | Descripción y evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | C-14 | `ControladorCitas.php` | La entrada `atender(accion, d)` no recibe usuario, permiso ni hospital, y no existe E1. Nada impide que un médico cancele una cita ajena. | Mayor | Omisión | Autor | Pospuesto a la semana 15 (RNF-01) |
| H-02 | C-11, C-12 | `ServicioCitas::solicitar`, `ControladorCitas::HTTP` | Se consulta el cruce y después se inserta, sin barrera en medio. Intercalando una segunda solicitud del mismo horario, las dos dan 201 y quedan 2 citas activas de m1. E6 no existe: con una barrera en la base, la solicitud recibiría 503 E8 y no 409 E6. | Mayor | Omisión | Autor | Abierto |
| H-03 | C-08 | `ServicioCitas::confirmar` | Confirmar no consulta el reloj: una cita de [7000, 8000) se confirma a las 7500 con 200. | Mayor | Error | Autor | Abierto |
| H-04 | C-10 | `schema.sql` | `appointment_cancellations` guarda motivo y fecha, pero no quién canceló. | Mayor | Omisión | Autor | Abierto |
| H-05 | C-04, C-05 | `schema.sql`, `medicoVigente` | `doctor_specialties` solo tiene un estado, sin fechas de vigencia, y no hay perfil de médico: no hay forma de rechazar una especialidad que vence antes de la cita. | Mayor | Omisión | Autor | Abierto |

Respuestas de la autorrevisión: C-01 a C-03, C-07, C-09 y C-15 «sí», confirmadas por las pruebas en verde. C-06 «sí» por lectura (`trim` y el `CHECK`), aunque ninguna prueba envía un motivo de solo espacios. C-13 «no aplica» en `8ffa2e2`: las rutas HTTP llegan con el PR #195, aún sin integrar. Si se integra antes de la reunión, se congela el nuevo commit (E-01) y C-13 se revisa contra sus rutas. Hay cinco mayores, así que, según S-04, la inspección se repite completa después de las correcciones.

**Reverificación tras integrar la semana 4** (29/10/2026, `develop` en `1595e00`). La semana 4 cambió la fábrica `crearControlador` y movió los códigos HTTP a `VistaJson`, así que `verificar-entrada.php` se adaptó a esa API. Su salida es idéntica: los cinco hallazgos siguen reproducidos con el código actual, y C-13 ya se puede revisar contra `Rutas.php` en la reunión.

**Bibliografía:** IEEE (2008). *IEEE Std 1028-2008: Standard for Software Reviews and Audits* · Fagan, M. E. (1976). Design and code inspections to reduce errors in program development. *IBM Systems Journal, 15*(3) · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
