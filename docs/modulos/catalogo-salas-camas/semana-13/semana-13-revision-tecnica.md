# Semana 13 — Plan de revisión técnica formal del Catálogo de salas, wards y camas

| | |
|---|---|
| **Tarea Odoo** | 2455 — Semana 13: Calidad del software y revisiones técnicas formales |
| **Módulo** | Catálogo de salas, wards y camas |
| **Flujo revisado** | «Creación de sala y cama con control de estado operativo» |
| **Base** | Reglas R-01 a R-09 y casos de uso CU-01 a CU-05 de la semana 1, RNF de la semana 2 y Micro-HIS de la semana 3 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Plan de revisión

Es una **inspección** (IEEE 1028): una revisión formal con roles fijos, un checklist, criterios de entrada y salida, y un acta con métricas. Se eligió la inspección y no un recorrido porque el catálogo es la fuente de las camas que usan admisión y ocupación, y un error en sus reglas se propaga a esos módulos.

**Objeto de revisión:** el Micro-HIS [micro-his-catalogo-camas](../micro-his-catalogo-camas/), congelado en el commit `8ffa2e2` de `develop`: 170 líneas de código y esquema (`src/`, `database/schema.sql` y `bootstrap.php`) más 54 de pruebas. Los documentos de las semanas 1 a 3 no se revisan: son la referencia contra la que se compara el código.

**Dependencias estrictamente necesarias:** solo los puntos donde el flujo depende de otro módulo. Son el permiso de gestión del catálogo (RBAC), el hospital de la petición (`X-Tenant-ID`), el usuario que firma el historial (auditoría) y el paso a «ocupada», que hace admisión. El código interno de esos módulos queda fuera.

| Fase | Quién | Duración | Producto |
|---|---|---|---|
| 1. Planificación | Moderador y autor | 30 min | Objeto congelado, criterios de entrada verificados, convocatoria con el checklist |
| 2. Presentación | Autor | 15 min | Recorrido del flujo y de las reglas R-01 a R-09; no se buscan errores todavía |
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
| **Revisor de dominio y datos** | Ítems C-01 a C-10: reglas, estados, integridad y capacidad. | — |
| **Revisor de API, permisos y tenant** | Ítems C-11 a C-15: contrato, autorización, aislamiento por hospital y errores técnicos. | — |

Las personas las designa el docente o el grupo al convocar; este plan solo fija qué hace cada rol. Con tres revisores, los roles de lector y registrador recaen en dos de ellos.

## 3. Criterios de entrada

| ID | Criterio | Cómo se comprueba |
|---|---|---|
| E-01 | El objeto está congelado en un commit de `develop`. | El hash va en la convocatoria; si cambia antes de la reunión, se vuelve a convocar. |
| E-02 | Las pruebas del Micro-HIS están en verde. | `php pruebas/pruebas.php` → «RESULTADO: todo correcto». |
| E-03 | El autor hizo su autorrevisión con el checklist y la dejó reproducible. | [verificar-entrada.php](evidencia/verificar-entrada.php) y su [salida](evidencia/salida-verificacion.txt). |
| E-04 | Los documentos de referencia de las semanas 1 a 3 están integrados en `develop`. | Las rutas `semana-01` a `semana-03` existen en el commit congelado. |
| E-05 | El objeto cabe en una reunión: como máximo 400 líneas. | `wc -l` sobre los archivos del objeto (hoy, 224). |
| E-06 | Cada revisor recibió el objeto y el checklist 24 h antes. | La convocatoria tiene fecha; cada revisor anota su tiempo de preparación. |

## 4. Checklist

Cada ítem se responde **sí**, **no** (abre un hallazgo) o **no aplica**, con su motivo.

| # | Pregunta | Fuente | Dónde mirar |
|---|---|---|---|
| C-01 | ¿El código de sala es único y se normaliza (espacios, mayúsculas) antes de compararlo y guardarlo? | R-01 | `ServicioCatalogo::registrarSala`, `wards.code UNIQUE` |
| C-02 | ¿El código de cama es único dentro de su sala, también en la base? | R-02 | `registrarCama`, `UNIQUE (ward_id, code)` |
| C-03 | ¿Solo se registran camas en salas activas (E4)? | R-03 | `registrarCama`, `salaActiva` |
| C-04 | ¿La cama nueva nace «disponible» en el servicio y en el `DEFAULT` de la base? | R-04 | `guardarCama`, `beds.status` |
| C-05 | ¿Las transiciones manuales son exactamente las de R-05, y cualquier otra da E6? | R-05 | `EstadoCama::validarCambioA` |
| C-06 | ¿Una cama ocupada no cambia a mano (E5), y está definido quién la marca ocupada? | R-06, CU-03 | `EstadoCama`, nota de CU-03 |
| C-07 | ¿El cambio de estado es atómico y detecta el cambio concurrente (E7) sin dejar historial huérfano? | CU-03 | `RepositorioPdo::cambiarEstado` |
| C-08 | ¿Cada cambio de estado guarda usuario, fecha y motivo? | R-09 | `bed_status_history` |
| C-09 | ¿La capacidad de una sala se calcula de sus camas no inactivas, sin un contador guardado que pueda desviarse? | Consistencia del catálogo | Consultas por sala |
| C-10 | ¿Los datos que piden CU-01 (ubicación) y CU-02 (tipo) existen en el esquema y se validan? | CU-01, CU-02 | `wards`, `beds` |
| C-11 | ¿Cada error de negocio tiene un código y un HTTP fijos (E2 a E7 y NF), y el 503 no filtra detalles técnicos? | Semana 1, §7 (E1 a E7) | `ControladorCatalogo::HTTP` |
| C-12 | ¿Las rutas y los payloads coinciden con los endpoints de la semana 1 (`POST /wards/{id}/beds`, `PATCH /beds/{id}/status`)? | Semana 1, §8 | Capa de presentación |
| C-13 | ¿Toda operación verifica sesión y permiso de gestión del catálogo antes de CU-01 a CU-03, con E1 y 403? | R-07, CU-05, RNF-01 | Entrada del controlador |
| C-14 | ¿Una sala o cama de otro hospital es invisible: toda consulta filtra por el hospital de `X-Tenant-ID`? | R-07, RNF-01 | Esquema y consultas del repositorio |
| C-15 | ¿Solo hay sentencias preparadas, `PDOException` se traduce a E8 y cada capa apunta hacia adentro? | Semana 3 | `RepositorioPdo`, pruebas §3 y §4 |

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
| **Crítica** | Expone datos de otro hospital o deja el catálogo inconsistente; bloquea la integración. |
| **Mayor** | Incumple una regla R-xx, un caso de uso o un RNF. |
| **Menor** | Inconsistencia entre documento y código que no rompe una regla. |
| **Observación** | Mejora de claridad o mantenimiento; no exige corrección. |

El **tipo** es omisión (falta algo pedido), error (hace algo distinto de lo pedido) o inconsistencia (dos fuentes se contradicen). Cada fila lleva: ID, ítem del checklist, ubicación (archivo y commit), descripción con evidencia, severidad, tipo, responsable y estado (abierto, corregido, verificado o pospuesto).

**Prueba del formato con la autorrevisión del autor (E-03)** sobre `8ffa2e2`. Cada fila se reproduce con [verificar-entrada.php](evidencia/verificar-entrada.php); son hallazgos del autor y la reunión puede reclasificarlos.

| ID | Ítem | Ubicación | Descripción y evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | C-13, C-14 | `ControladorCatalogo.php`, `schema.sql` | La entrada `atender(accion, d)` no recibe usuario, permiso ni hospital, no existe E1, y `wards` no tiene columna de hospital. | Mayor | Omisión | Autor | Pospuesto a la semana 15 (RNF-01) |
| H-02 | C-08 | `schema.sql` | `bed_status_history` guarda fecha y motivo, pero no quién hizo el cambio. | Mayor | Omisión | Autor | Abierto |
| H-03 | C-01 | `ServicioCatalogo::registrarSala` | El código se valida con `trim()` y se guarda sin normalizar: «A» y « A» se registran las dos (201 y 201). | Mayor | Error | Autor | Abierto |
| H-04 | C-10 | `schema.sql` | CU-01 pide ubicación y CU-02 tipo de cama; el esquema no tiene `location` ni `type`. | Menor | Inconsistencia | Autor | Abierto |
| H-05 | C-06 | `EstadoCama`, CU-03 | CU-03 dice que admisión marca «ocupada», pero «disponible → ocupada» da 422 E6: falta definir esa entrada. | Menor | Inconsistencia | Autor | Abierto |

Respuestas de la autorrevisión: C-02 a C-05, C-07 y C-15 «sí», confirmadas por las pruebas en verde. C-11 «sí» (`ControladorCatalogo::HTTP` y la prueba del 503). C-09 «no aplica»: el Micro-HIS todavía no consulta la capacidad. C-12 «no aplica» en `8ffa2e2`: las rutas HTTP llegan con el PR #194, aún sin integrar. Si se integra antes de la reunión, se congela el nuevo commit (E-01) y C-12 se revisa contra sus rutas.

**Bibliografía:** IEEE (2008). *IEEE Std 1028-2008: Standard for Software Reviews and Audits* · Fagan, M. E. (1976). Design and code inspections to reduce errors in program development. *IBM Systems Journal, 15*(3) · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
