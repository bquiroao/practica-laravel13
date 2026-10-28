# Semana 13 — Plan de revisión técnica formal del Dashboard base

| | |
|---|---|
| **Tarea Odoo** | 2440 — Semana 13: Calidad del software y revisiones técnicas formales |
| **Módulo** | Dashboard base UI/UX e implementación transversal |
| **Flujo revisado** | «Navegación del dashboard con estados de carga, vacío, error y resumen inicial» |
| **Base** | Reglas R-01 a R-09 y casos de uso CU-01 a CU-05 de la semana 1, RNF-01 a RNF-05 de la semana 2 y Micro-HIS de la semana 3 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Plan de revisión

Es una **inspección** (IEEE 1028): una revisión formal con roles fijos, un checklist, criterios de entrada y salida, y un acta con métricas. Se eligió la inspección y no un recorrido porque el dashboard es la entrada de todos los roles. Un menú o una tarjeta que se salta un permiso, o un estado mal clasificado, lo ve cada usuario del hospital.

**Objeto de revisión:** el Micro-HIS [micro-his-dashboard-base](../micro-his-dashboard-base/), congelado en el commit `8ffa2e2` de `develop`: 169 líneas de código y esquema (`src/`, `database/schema.sql` y `bootstrap.php`) más 59 de pruebas. Los documentos de las semanas 1 a 3 no se revisan: son la referencia contra la que se compara el código.

**Dependencias estrictamente necesarias:** solo los puntos donde el flujo depende de otro módulo. Son la sesión y el hospital seleccionado (`X-Tenant-ID`), los permisos del rol (RBAC) y las tres fuentes que se cuentan: pacientes, citas y camas. El cliente web (guards de Vue, Axios y el límite de 10 s de la semana 2) es otra pieza y queda fuera.

| Fase | Quién | Duración | Producto |
|---|---|---|---|
| 1. Planificación | Moderador y autor | 30 min | Objeto congelado, criterios de entrada verificados, convocatoria con el checklist |
| 2. Presentación | Autor | 15 min | Recorrido del flujo, de las reglas R-01 a R-09 y de los cuatro estados de una tarjeta; no se buscan errores todavía |
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
| **Revisor de sesión, permisos y navegación** | Ítems C-01 a C-05: hospital, permisos, menú, tarjetas y rutas. | — |
| **Revisor de tarjetas, estados y API** | Ítems C-06 a C-15: carga, vacío, error, reintento, conteos, textos y códigos. | — |

Las personas las designa el docente o el grupo al convocar; este plan solo fija qué hace cada rol. Con tres revisores, los roles de lector y registrador recaen en dos de ellos.

## 3. Criterios de entrada

| ID | Criterio | Cómo se comprueba |
|---|---|---|
| E-01 | El objeto está congelado en un commit de `develop`. | El hash va en la convocatoria; si cambia antes de la reunión, se vuelve a convocar. |
| E-02 | Las pruebas del Micro-HIS están en verde. | `php pruebas/pruebas.php` → «RESULTADO: todo correcto». |
| E-03 | El autor hizo su autorrevisión con el checklist y la dejó reproducible. | [verificar-entrada.php](evidencia/verificar-entrada.php) y su [salida](evidencia/salida-verificacion.txt). |
| E-04 | Los documentos de referencia de las semanas 1 a 3 están integrados en `develop`. | Las rutas `semana-01` a `semana-03` existen en el commit congelado. |
| E-05 | El objeto cabe en una reunión: como máximo 400 líneas. | `wc -l` sobre los archivos del objeto (hoy, 228). |
| E-06 | Cada revisor recibió el objeto y el checklist 24 h antes. | La convocatoria tiene fecha; cada revisor anota su tiempo de preparación. |

## 4. Checklist

Cada ítem se responde **sí**, **no** (abre un hallazgo) o **no aplica**, con su motivo.

| # | Pregunta | Fuente | Dónde mirar |
|---|---|---|---|
| C-01 | ¿Sin sesión ni hospital seleccionado se responde 401 E1 y no se entrega nada? | R-01 | `exigirSesion` |
| C-02 | ¿El hospital y los permisos salen de la sesión y de RBAC, y no de lo que envía el cliente? | R-02, CU-05 | Entrada del controlador |
| C-03 | ¿El menú muestra solo las opciones que el rol permite? | R-02 | `Catalogo::menu` |
| C-04 | ¿Una tarjeta sin permiso responde 403 E4, para que el cliente la oculte? | R-02, R-07 | `Catalogo::tarjetas`, `tarjeta` |
| C-05 | ¿Una ruta escrita a mano que el rol no permite se rechaza con E6? | CU-03, CU-05 | Capa de presentación |
| C-06 | ¿El inicio entrega el layout con todas las tarjetas del rol en «cargando»? | R-04, CU-01 | `ServicioDashboard::inicio` |
| C-07 | ¿Cada tarjeta carga y falla por su cuenta, sin bloquear a las demás? | R-03 | `tarjeta`, pruebas §3 |
| C-08 | ¿Un conteo de 0 es «vacío» con una acción sugerida, y no un error? | R-05 | `EstadoTarjeta::deConteo` |
| C-09 | ¿Un fallo de la base da «error» con un texto amable, sin códigos ni detalles técnicos? | R-05, R-09, RNF-01 | `Tarjeta::texto`, 503 E5 |
| C-10 | ¿Reintentar es volver a pedir solo esa tarjeta, sin ningún bucle automático en el servidor? | R-08, CU-04 | `tarjeta` |
| C-11 | ¿Una tarjeta que tarda más de 10 s se trata como error (E2)? | R-04, RNF-02 | Cliente de la semana 2 |
| C-12 | ¿Las tarjetas devuelven solo conteos, sin ningún dato que identifique a un paciente? | R-06, RNF-05 | `RepositorioPdo::conteo` (solo `COUNT`) |
| C-13 | ¿Cada conteo es correcto: pacientes activos, camas disponibles y citas activas del día **del hospital**? | CU-02 | `conteo`, `Reloj` |
| C-14 | ¿Cada código tiene su HTTP (E1 y E3 401, E4 403, E5 503, inexistente 404), y la ruta coincide con `GET /dashboard/cards/{tarjeta}` de la semana 1? | Semana 1, §7 y §8 | `ControladorDashboard::HTTP` |
| C-15 | ¿Los textos son correctos para cualquier número, solo hay sentencias preparadas y cada capa apunta hacia adentro? | RNF-01, semana 3 | `Tarjeta::texto`, pruebas §4 |

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
| **Crítica** | Muestra datos de otro hospital o datos que identifican a un paciente; bloquea la integración. |
| **Mayor** | Incumple una regla R-xx, un caso de uso o un RNF. |
| **Menor** | Inconsistencia entre documento y código, o una respuesta equivocada que no rompe una regla. |
| **Observación** | Mejora de claridad o mantenimiento; no exige corrección. |

El **tipo** es omisión (falta algo pedido), error (hace algo distinto de lo pedido) o inconsistencia (dos fuentes se contradicen). Cada fila lleva: ID, ítem del checklist, ubicación (archivo y commit), descripción con evidencia, severidad, tipo, responsable y estado (abierto, corregido, verificado o pospuesto).

**Prueba del formato con la autorrevisión del autor (E-03)** sobre `8ffa2e2`. Cada fila se reproduce con [verificar-entrada.php](evidencia/verificar-entrada.php); son hallazgos del autor y la reunión puede reclasificarlos.

| ID | Ítem | Ubicación | Descripción y evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | C-02, C-14 | `ControladorDashboard::atender` | `hospital` y `permisos` llegan en el cuerpo de la petición, como avisa su propio comentario: con hospital «hospital-ajeno» y permisos escritos por el cliente, la tarjeta responde 200. Tampoco existe E3 (sesión expirada). | Mayor | Omisión | Autor | Abierto |
| H-02 | C-05 | `ControladorDashboard` | No hay operación que valide una ruta escrita a mano: «navegar a /usuarios» da el 404 genérico y no existe E6, aunque CU-05 lo incluye. | Menor | Inconsistencia | Autor | Abierto |
| H-03 | C-14 | `ServicioDashboard::tarjeta` | Una tarjeta que no existe responde 403 E4, y por R-07 el cliente la oculta como si fuera falta de permiso; debería ser 404. | Menor | Error | Autor | Abierto |
| H-04 | C-13 | `RepositorioPdo::conteo` | «Citas de hoy» aplica `date()` al texto guardado. Una cita de las 20:00 del 26 en Guatemala, guardada en UTC como `2026-10-27T02:00:00Z`, no se cuenta el 26. | Menor | Omisión | Autor | Abierto |
| H-05 | C-15 | `Tarjeta::texto` | Con un solo registro, el texto queda en plural: «1 pacientes activos». | Observación | Error | Autor | Abierto |

Respuestas de la autorrevisión: C-01, C-03, C-04 y C-06 a C-10 «sí», confirmadas por las pruebas en verde. C-12 «sí»: el repositorio solo hace `COUNT`, y una prueba comprueba que la tarjeta devuelve solo id, estado y texto. C-11 «no aplica»: el límite de 10 s está en el cliente de la semana 2 (`crearClienteTarjetas`), no en el Micro-HIS. La ruta de C-14 tampoco se puede revisar en `8ffa2e2`: llega con el PR #200, aún sin integrar. Si se integra antes de la reunión, se congela el nuevo commit (E-01) y la ruta se revisa contra él. Hay un solo mayor, así que, según S-04, el moderador verifica solo las correcciones.

**Bibliografía:** IEEE (2008). *IEEE Std 1028-2008: Standard for Software Reviews and Audits* · Fagan, M. E. (1976). Design and code inspections to reduce errors in program development. *IBM Systems Journal, 15*(3) · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
