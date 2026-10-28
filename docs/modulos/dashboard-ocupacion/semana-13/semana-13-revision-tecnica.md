# Semana 13 — Plan de revisión técnica formal del Dashboard de ocupación hospitalaria

| | |
|---|---|
| **Tarea Odoo** | 2500 — Semana 13: Calidad del software y revisiones técnicas formales |
| **Módulo** | Dashboard de ocupación hospitalaria |
| **Flujo revisado** | «Consolidación y consulta de ocupación por sala y estado de cama» |
| **Base** | Reglas R-01 a R-09 y casos de uso CU-01 a CU-05 de la semana 1, RNF-01 a RNF-05 de la semana 2 y Micro-HIS de la semana 3 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Plan de revisión

Es una **inspección** (IEEE 1028): una revisión formal con roles fijos, un checklist, criterios de entrada y salida, y un acta con métricas. Se eligió la inspección y no un recorrido porque jefatura decide con estas cifras dónde ingresar a un paciente. Una cama mal contada o un corte viejo sin aviso lleva a una decisión clínica equivocada.

**Objeto de revisión:** el Micro-HIS [micro-his-dashboard-ocupacion](../micro-his-dashboard-ocupacion/), congelado en el commit `8ffa2e2` de `develop`: 173 líneas de código y esquema (`src/`, `database/schema.sql` y `bootstrap.php`) más 57 de pruebas. Los documentos de las semanas 1 a 3 no se revisan: son la referencia contra la que se compara el código.

**Dependencias estrictamente necesarias:** solo los puntos donde el flujo depende de otro módulo. Son el permiso de ocupación y el hospital (RBAC y `X-Tenant-ID`), las salas y camas del catálogo, las estancias abiertas de admisión y el temporizador que dispara la consolidación. El código interno de esos módulos queda fuera.

| Fase | Quién | Duración | Producto |
|---|---|---|---|
| 1. Planificación | Moderador y autor | 30 min | Objeto congelado, criterios de entrada verificados, convocatoria con el checklist |
| 2. Presentación | Autor | 15 min | Recorrido de la consolidación, la fórmula de R-01 y el aviso de corte viejo; no se buscan errores todavía |
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
| **Revisor de indicadores y procedencia** | Ítems C-01 a C-09: fórmula, salas, estados de cama, consistencia, corte, hora y fuentes. | — |
| **Revisor de permisos, API y disponibilidad** | Ítems C-10 a C-15: permiso y tenant, filtro, códigos, rutas, solo lectura y fallos. | — |

Las personas las designa el docente o el grupo al convocar; este plan solo fija qué hace cada rol. Con tres revisores, los roles de lector y registrador recaen en dos de ellos.

## 3. Criterios de entrada

| ID | Criterio | Cómo se comprueba |
|---|---|---|
| E-01 | El objeto está congelado en un commit de `develop`. | El hash va en la convocatoria; si cambia antes de la reunión, se vuelve a convocar. |
| E-02 | Las pruebas del Micro-HIS están en verde. | `php pruebas/pruebas.php` → «RESULTADO: todo correcto». |
| E-03 | El autor hizo su autorrevisión con el checklist y la dejó reproducible. | [verificar-entrada.php](evidencia/verificar-entrada.php) y su [salida](evidencia/salida-verificacion.txt). |
| E-04 | Los documentos de referencia de las semanas 1 a 3 están integrados en `develop`. | Las rutas `semana-01` a `semana-03` existen en el commit congelado. |
| E-05 | El objeto cabe en una reunión: como máximo 400 líneas. | `wc -l` sobre los archivos del objeto (hoy, 230). |
| E-06 | Cada revisor recibió el objeto y el checklist 24 h antes. | La convocatoria tiene fecha; cada revisor anota su tiempo de preparación. |

## 4. Checklist

Cada ítem se responde **sí**, **no** (abre un hallazgo) o **no aplica**, con su motivo.

| # | Pregunta | Fuente | Dónde mirar |
|---|---|---|---|
| C-01 | ¿La ocupación es ocupadas ÷ (disponibles + ocupadas), con mantenimiento e inactivas aparte? | R-01 | `OcupacionSala::calcular` |
| C-02 | ¿Una sala sin camas operativas muestra «no aplica», nunca una división entre cero? | R-02 | `calcular` |
| C-03 | ¿Solo se consolidan salas activas, y lo que queda fuera por estar inactiva se hace visible si tiene pacientes? | R-03, R-04 | `camasDeSalasActivas` |
| C-04 | ¿Se marca toda diferencia entre el estado de la cama y sus estancias abiertas, incluida más de una estancia en la misma cama? | R-04, CU-04 | `calcular`, `con_estancia` |
| C-05 | ¿Cada corte guarda su hora y la consulta la muestra? | R-05 | `Corte`, `occupancy_snapshots` |
| C-06 | ¿Un corte de más de 15 minutos se muestra con aviso? | R-06 | `Corte::esViejo` |
| C-07 | ¿El corte guarda solo cifras por sala, sin ningún dato de paciente? | R-07, RNF-05 | `payload` |
| C-08 | ¿La procedencia es trazable: cada cifra sale de `beds` y `bed_stays` en el instante del corte, con sentencias preparadas? | Enunciado de la semana 13 | `RepositorioPdo` |
| C-09 | ¿Sin ningún corte todavía, la consulta muestra el estado vacío (E4) y no un error? | CU-02 | `ConsultaOcupacion::consultar` |
| C-10 | ¿Solo consulta quien tiene el permiso de ocupación en el mismo hospital (E3, 403)? | R-08, CU-05, RNF-01 | Entrada del controlador |
| C-11 | ¿El filtro por estado deja solo las salas con camas en ese estado, y un estado que no existe se rechaza? | CU-03 | `Corte::filtrar` |
| C-12 | ¿Los códigos coinciden con los de la semana 1 (E1 inconsistencia, E2 falla de lectura, E3 sin permiso, E4 sin corte)? | Semana 1, §7 (D1 a D8) | `ControladorOcupacion` |
| C-13 | ¿La ruta coincide con `GET /occupancy?estado=…` de la semana 1? | Semana 1, §8 | Capa de presentación |
| C-14 | ¿La consolidación es de solo lectura: ningún puerto cambia una cama? | R-09, RNF-02 | Puertos de `ServiciosOcupacion` |
| C-15 | ¿Si falla la lectura se conserva el corte anterior, y el tablero nunca queda en blanco? | R-09, RNF-04 | `consolidar`, `consultar` |

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
| **Crítica** | Muestra datos de pacientes o de otro hospital, o una cifra falsa sin ningún aviso; bloquea la integración. |
| **Mayor** | Incumple una regla R-xx, un caso de uso o un RNF. |
| **Menor** | Inconsistencia entre documento y código, o una respuesta equivocada que no rompe una regla. |
| **Observación** | Mejora de claridad o mantenimiento; no exige corrección. |

El **tipo** es omisión (falta algo pedido), error (hace algo distinto de lo pedido) o inconsistencia (dos fuentes se contradicen). Cada fila lleva: ID, ítem del checklist, ubicación (archivo y commit), descripción con evidencia, severidad, tipo, responsable y estado (abierto, corregido, verificado o pospuesto).

**Prueba del formato con la autorrevisión del autor (E-03)** sobre `8ffa2e2`. Cada fila se reproduce con [verificar-entrada.php](evidencia/verificar-entrada.php); son hallazgos del autor y la reunión puede reclasificarlos.

| ID | Ítem | Ubicación | Descripción y evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | C-10 | `ControladorOcupacion::atender` | La entrada `atender(accion, d)` no recibe usuario, permiso ni hospital: consultar sin permisos responde 200. | Mayor | Omisión | Autor | Pospuesto a la semana 15 (RNF-01) |
| H-02 | C-12 | `Corte::filtrar`, `ControladorOcupacion` | Los códigos no son los de la semana 1: un estado inexistente usa E3 (allí es «sin permiso», 403) con 422, y la falla de lectura sale como E8 en vez de E2. | Menor | Inconsistencia | Autor | Abierto |
| H-03 | C-04 | `camasDeSalasActivas` | `con_estancia` usa `EXISTS`: la cama A1 ocupada con dos estancias abiertas (dos pacientes) no se marca como inconsistente. | Mayor | Omisión | Autor | Abierto |
| H-04 | C-03 | `camasDeSalasActivas` | La cama C1, ocupada y con estancia abierta en la sala inactiva C, desaparece del corte sin ninguna marca: ese paciente no se ve en el tablero. | Menor | Omisión | Autor | Abierto |
| H-05 | C-15 | `ConsultaOcupacion::consultar` | Si la consulta no puede leer los cortes, responde 503 con «Se conserva el corte anterior», pero no devuelve ninguno: el cliente no tiene qué mostrar, contra RNF-04. | Menor | Error | Autor | Abierto |

Respuestas de la autorrevisión: C-01, C-02, C-05 a C-07, C-09, C-11 y C-14 «sí», confirmadas por las pruebas en verde. C-08 «sí»: la consolidación lee `beds` y `bed_stays` en el momento del corte, y el corte guarda su hora (R-05). C-13 «no aplica» en `8ffa2e2`: la ruta HTTP llega con el PR #201, aún sin integrar. Si se integra antes de la reunión, se congela el nuevo commit (E-01) y C-13 se revisa contra ella. Hay dos mayores, así que, según S-04, el moderador verifica solo las correcciones.

**Bibliografía:** IEEE (2008). *IEEE Std 1028-2008: Standard for Software Reviews and Audits* · Fagan, M. E. (1976). Design and code inspections to reduce errors in program development. *IBM Systems Journal, 15*(3) · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
