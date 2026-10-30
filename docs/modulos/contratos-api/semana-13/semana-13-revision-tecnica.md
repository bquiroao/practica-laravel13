# Semana 13 — Plan de revisión técnica formal del Centro de documentación y manuales por rol

| | |
|---|---|
| **Tarea Odoo** | 2485 — Semana 13: Calidad del software y revisiones técnicas formales |
| **Módulo** | Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) |
| **Flujo revisado** | «Publicación y consulta de documentación y manuales diferenciados por rol» |
| **Base** | Reglas R-01 a R-10 y casos de uso CU-01 a CU-06 de la semana 1, RNF-01 a RNF-05 de la semana 2 y Micro-HIS de la semana 3 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Plan de revisión

Es una **inspección** (IEEE 1028): una revisión formal con roles fijos, un checklist, criterios de entrada y salida, y un acta con métricas. Se eligió la inspección y no un recorrido porque este módulo publica los contratos que usan los demás módulos. Un OpenAPI mal validado o una versión mal marcada como vigente se propaga a cada integración.

**Objeto de revisión:** el Micro-HIS [micro-his-contratos-api](../micro-his-contratos-api/), congelado en el commit `8ffa2e2` de `develop`: 196 líneas de código y esquema (`src/`, `database/schema.sql` y `bootstrap.php`) más 53 de pruebas. Los documentos de las semanas 1 a 3 no se revisan: son la referencia contra la que se compara el código.

**Dependencias estrictamente necesarias:** solo los puntos donde el flujo depende de otro módulo o herramienta. Son la sesión (quién envía o aprueba), los `role_code` activos y vigentes del lector en su hospital (RBAC y `hospital_memberships`), el validador de OpenAPI (lint) y la auditoría. El código interno de esos módulos queda fuera.

| Fase | Quién | Duración | Producto |
|---|---|---|---|
| 1. Planificación | Moderador y autor | 30 min | Objeto congelado, criterios de entrada verificados, convocatoria con el checklist |
| 2. Presentación | Autor | 15 min | Recorrido del flujo, de las reglas R-01 a R-10 y de los estados de una versión; no se buscan errores todavía |
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
| **Revisor de contenido y versionado** | Ítems C-01 a C-07: OpenAPI, Postman, ejemplos, errores documentados y versiones. | — |
| **Revisor de permisos, auditoría y API** | Ítems C-08 a C-15: aprobación, audiencia, 404, obsoletas, auditoría, códigos de error y rutas. | — |

Las personas las designa el docente o el grupo al convocar; este plan solo fija qué hace cada rol. Con tres revisores, los roles de lector y registrador recaen en dos de ellos.

## 3. Criterios de entrada

| ID | Criterio | Cómo se comprueba |
|---|---|---|
| E-01 | El objeto está congelado en un commit de `develop`. | El hash va en la convocatoria; si cambia antes de la reunión, se vuelve a convocar. |
| E-02 | Las pruebas del Micro-HIS están en verde. | `php pruebas/pruebas.php` → «RESULTADO: todo correcto». |
| E-03 | El autor hizo su autorrevisión con el checklist y la dejó reproducible. | [verificar-entrada.php](evidencia/verificar-entrada.php) y su [salida](evidencia/salida-verificacion.txt). |
| E-04 | Los documentos de referencia de las semanas 1 a 3 están integrados en `develop`. | Las rutas `semana-01` a `semana-03` existen en el commit congelado. |
| E-05 | El objeto cabe en una reunión: como máximo 400 líneas. | `wc -l` sobre los archivos del objeto (hoy, 249). |
| E-06 | Cada revisor recibió el objeto y el checklist 24 h antes. | La convocatoria tiene fecha; cada revisor anota su tiempo de preparación. |

## 4. Checklist

Cada ítem se responde **sí**, **no** (abre un hallazgo) o **no aplica**, con su motivo.

| # | Pregunta | Fuente | Dónde mirar |
|---|---|---|---|
| C-01 | ¿Toda versión tiene tipo (OpenAPI, Postman o manual), versión `x.y.z` y al menos un rol de audiencia? | R-01 | `VersionNueva::validar`, `CHECK (doc_type …)` |
| C-02 | ¿La pareja documento-versión es única y la nueva es mayor, también con dos envíos simultáneos (409 E3)? | R-02 | `versiones`, índice `doc_versions_slug_version` |
| C-03 | ¿Una versión publicada no se edita, y un cambio genera otra versión? | R-03 | `EstadoVersion::validarCambioA` |
| C-04 | ¿Un OpenAPI pasa la validación (lint) antes de ir a revisión, y si no, se rechaza con E4 y su reporte? | R-04, CU-05 | `ServicioPublicacion::enviar` |
| C-05 | ¿La colección Postman de una versión cubre los mismos endpoints y métodos que su OpenAPI? | Enunciado de la semana 13 | Contenido de la versión |
| C-06 | ¿Los ejemplos usan solo datos ficticios, sin pacientes reales ni credenciales? | R-09, CU-05 | Contenido de la versión |
| C-07 | ¿Cada endpoint documentado declara sus errores y el permiso que exige? | Enunciado de la semana 13 | Contenido de la versión |
| C-08 | ¿Nadie aprueba su propia publicación (E5, 403), comparando con el usuario de la sesión y no con un dato del cliente? | R-05 | `ServicioRevision::cambiar`, entrada del controlador |
| C-09 | ¿La audiencia se cruza con los `role_code` activos y vigentes del lector en su hospital, y no con roles que envía el cliente? | R-06, CU-06, RNF-01 | `consultar`, entrada del controlador |
| C-10 | ¿Un documento fuera de la audiencia responde 404, igual que uno inexistente? | R-07, RNF-04 | `ServicioConsulta::consultar` |
| C-11 | ¿Una obsoleta no se borra, enlaza a la versión que la reemplaza y sigue consultable con aviso? | R-08, CU-04, RNF-05 | `marcarObsoleta`, `doc_versions` |
| C-12 | ¿Enviar, aprobar, rechazar y marcar obsoleta dejan quién y cuándo, sin sobrescribir lo anterior? | R-10 | `cambiarEstado`, `reviewed_by` |
| C-13 | ¿Cada error tiene un código y un HTTP fijos (E1 a E7 y NF), y el 503 no filtra detalles técnicos? | Semana 1, §7 (D1 a D9) | `ControladorDocumentacion::HTTP` |
| C-14 | ¿Las rutas coinciden con las de la semana 1 (`POST /docs/{slug}/versions`, `GET /docs/{slug}`)? | Semana 1, §8 | Capa de presentación |
| C-15 | ¿Solo hay sentencias preparadas, `PDOException` se traduce a E8, cada capa apunta hacia adentro y cada cliente usa solo su puerto? | Semana 3, RNF-03 | `RepositorioPdo`, pruebas §3 y §4 |

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
| **Crítica** | Expone documentos a quien no pertenece a su audiencia, o publica un contrato inválido como vigente; bloquea la integración. |
| **Mayor** | Incumple una regla R-xx, un caso de uso o un RNF. |
| **Menor** | Inconsistencia entre documento y código, o una respuesta equivocada que no rompe una regla. |
| **Observación** | Mejora de claridad o mantenimiento; no exige corrección. |

El **tipo** es omisión (falta algo pedido), error (hace algo distinto de lo pedido) o inconsistencia (dos fuentes se contradicen). Cada fila lleva: ID, ítem del checklist, ubicación (archivo y commit), descripción con evidencia, severidad, tipo, responsable y estado (abierto, corregido, verificado o pospuesto).

**Prueba del formato con la autorrevisión del autor (E-03)** sobre `8ffa2e2`. Cada fila se reproduce con [verificar-entrada.php](evidencia/verificar-entrada.php); son hallazgos del autor y la reunión puede reclasificarlos.

| ID | Ítem | Ubicación | Descripción y evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | C-08, C-09, C-13 | `ControladorDocumentacion::atender` | `usuario` y `roles` llegan en el cuerpo de la petición, como avisa su propio comentario: quien escribe `roles = [medico]` obtiene 200. No existe E1. | Mayor | Omisión | Autor | Pospuesto a la semana 15 (RNF-01) |
| H-02 | C-04 a C-07, C-13 | `schema.sql`, `ServicioPublicacion::enviar` | La versión no guarda contenido: un `openapi` con «esto no es OpenAPI» entra a revisión con 201. No hay lint ni E4, y no se pueden revisar Postman, ejemplos ni errores documentados. | Mayor | Omisión | Autor | Abierto |
| H-03 | C-12 | `RepositorioPdo::cambiarEstado` | `reviewed_by` se sobrescribe: la 1.0.0 aprobada por luis y marcada obsoleta por marta queda con `reviewed_by = marta`, y se pierde quién aprobó. | Mayor | Error | Autor | Abierto |
| H-04 | C-02 | `ServicioPublicacion::enviar` | Con dos envíos simultáneos de la 2.0.0, el índice único evita el duplicado, pero la solicitud recibe 503 E8 en vez de 409 E3. | Menor | Error | Autor | Abierto |
| H-05 | C-11 | `doc_versions`, `marcarObsoleta` | No se guarda qué versión reemplaza a la obsoleta. Enfermería, que no está en la audiencia de la 1.1.0, consulta sin versión y recibe 404 E6, sin aviso de que su 1.0.0 quedó obsoleta. | Menor | Omisión | Autor | Abierto |

Respuestas de la autorrevisión: C-01, C-03, C-10 y C-15 «sí», confirmadas por las pruebas en verde. C-02 es «sí» con envíos sucesivos (prueba de 409 E3) y «no» con envíos simultáneos (H-04). C-04 a C-07 son «no»: sin contenido guardado no hay nada que validar (H-02). C-13 es «no» porque faltan E1 y E4. C-14 «no aplica» en `8ffa2e2`: las rutas HTTP llegan con el PR #196, aún sin integrar. Si se integra antes de la reunión, se congela el nuevo commit (E-01) y C-14 se revisa contra sus rutas. Hay tres mayores, así que, según S-04, el moderador verifica solo las correcciones.

**Reverificación tras integrar la semana 4** (29/10/2026, `develop` en `1595e00`). La semana 4 cambió la fábrica `crearControlador` y movió los códigos HTTP a `VistaJson`, así que `verificar-entrada.php` se adaptó a esa API. Su salida es idéntica: los cinco hallazgos siguen reproducidos con el código actual, y C-14 ya se puede revisar contra `Rutas.php` en la reunión.

**Bibliografía:** IEEE (2008). *IEEE Std 1028-2008: Standard for Software Reviews and Audits* · Fagan, M. E. (1976). Design and code inspections to reduce errors in program development. *IBM Systems Journal, 15*(3) · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill · OpenAPI Initiative (2021). *OpenAPI Specification 3.1.0*.
