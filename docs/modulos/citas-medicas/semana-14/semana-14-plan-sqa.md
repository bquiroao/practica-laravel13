# Semana 14 — Plan de aseguramiento de calidad (SQA) de Citas médicas y agenda

| | |
|---|---|
| **Tarea Odoo** | 2471 — Semana 14: Confiabilidad y aseguramiento de calidad |
| **Módulo** | Citas médicas y agenda |
| **Flujo** | «Solicitud, validación de disponibilidad y confirmación o cancelación de cita» |
| **Base** | Reglas R-01 a R-10 y CU-01 a CU-05 de la semana 1, RNF-01 a RNF-05 de la semana 2, Micro-HIS de la semana 3 e inspección de la semana 13 (PR #289) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y objetivos de calidad

El plan cubre el Micro-HIS de citas y cada PR que lo cambie, desde la semana 14 hasta la entrega final. Sigue la estructura de IEEE 730 (qué se mide, quién, cuándo y qué se hace si falla). Los objetivos usan las características de ISO/IEC 25010 que más pesan en este flujo:

| Característica | Qué significa en la agenda | Métricas |
|---|---|---|
| Adecuación funcional | Cada regla R-xx y cada caso de uso tienen una prueba que los nombra. | M-01, M-02, M-03 |
| Fiabilidad | Dos solicitudes simultáneas no dejan dos citas en el mismo horario, y una base caída responde 503 sin citas a medias. | M-01, M-06 |
| Seguridad | Ninguna consulta es inyectable, y el médico solo cancela sus propias citas (R-09). | M-04, M-06 |
| Mantenibilidad | Los métodos son cortos y los PR caben en una revisión (RNF-02, RNF-03). | M-05, M-07 |
| Trazabilidad | Cada cancelación guarda usuario, fecha y motivo (R-10, RNF-05), y cada hallazgo de inspección tiene dueño. | M-06 |

## 2. Métricas

Las métricas M-01 a M-05 y M-07 las mide [medir-metricas.php](evidencia/medir-metricas.php) sobre el código y el historial de `develop`. Su [salida](evidencia/salida-metricas.txt) es la **línea base** del 28/10/2026 (commit `8ffa2e2`).

| ID | Métrica | Fuente | Umbral | Responsable | Acción si no cumple | Línea base |
|---|---|---|---|---|---|---|
| M-01 | Pruebas del Micro-HIS en verde | `pruebas.php` | 100 % | Autor | El PR no se integra hasta corregirlo. | 17 de 17 ✔ |
| M-02 | Reglas R-xx citadas por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Agregar la prueba que falta, con el ID de la regla en su nombre. | 8 de 10 (80 %) ✔: justo en el umbral; faltan R-06 y R-09 |
| M-03 | Casos de uso CU-xx citados por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Nombrar el caso de uso en cada prueba de camino feliz. | 0 de 5 ✘ |
| M-04 | Consultas SQL que concatenan variables | `src/Persistencia` | 0 | Revisor del PR | Rechazar el PR; reescribir con `prepare()` y parámetros. | 0 ✔ |
| M-05 | Líneas del método más largo | `src/` | ≤ 15 | Revisor del PR | Extraer el paso a una función con nombre propio. | 14 ✔ |
| M-06 | Hallazgos mayores o críticos abiertos de la inspección | Acta de la semana 13 | 0 | Moderador | No se cierra la inspección; con más de 3, se repite (S-04). | 5 ✘ (H-01 a H-05, PR #289) |
| M-07 | Líneas del PR más grande integrado | `git show --numstat` | ≤ 400 | Autor | Dividir la tarea en dos PR. | 397 ✔ (3 PR) |

M-06 no la mide el script: sale del acta de la inspección, que hoy solo tiene la autorrevisión del autor. M-02 y M-07 cumplen al límite (80 % y 3 líneas de margen), y por eso tienen riesgos propios (RG-06 y RG-07).

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; el nivel es P × I: 1–2 bajo, 3–4 medio y 6–9 alto. Los riesgos altos se revisan cada semana.

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo | Responsable | Métrica |
|---|---|---|---|---|---|---|---|---|
| RG-01 | Alguien agenda o cancela sin permiso, o un médico cancela una cita ajena (R-09, H-01). | 3 | 3 | **9 alto** | CP-04: sesión, permiso y `X-Tenant-ID` antes de cada caso de uso | CD-01: prueba de 403 en `pruebas.php` (semana 15, RNF-01) | Autor | M-06 |
| RG-02 | Dos recepcionistas reservan el mismo horario a la vez (CU-01, H-02). | 2 | 3 | **6 alto** | CP-02: exclusión de cruces en la base, traducida a 409 E6 | CD-01: prueba con un repositorio que intercala la segunda solicitud | Autor | M-01, M-06 |
| RG-03 | Una cancelación no dice quién la hizo (R-10, H-04). | 3 | 2 | **6 alto** | CP-02: columna de usuario `NOT NULL` en las cancelaciones | CD-03: ítem C-10 de la inspección | Autor | M-06 |
| RG-04 | Se agenda con una especialidad vencida en la fecha de la cita (R-04, H-05). | 2 | 3 | **6 alto** | CP-02: vigencia por fechas en `doctor_specialties` | CD-01: prueba con una especialidad que vence antes de la cita | Autor | M-02 |
| RG-05 | Se confirma una cita que ya empezó (CU-02, H-03). | 2 | 2 | 4 medio | CP-02: `confirmar` compara con el reloj inyectado | CD-01: prueba con el reloj después del inicio | Autor | M-01 |
| RG-06 | Una regla o un caso de uso sin prueba se rompe sin que nadie lo note. | 2 | 2 | 4 medio | CP-01: cada regla nueva entra con una prueba que la nombra | CD-02: `medir-metricas.php` en cada PR | Autor | M-02, M-03 |
| RG-07 | Un PR demasiado grande se revisa por encima. | 2 | 2 | 4 medio | CP-01: un PR por tarea, máximo 400 líneas | CD-02: `git diff --shortstat` en el cuerpo del PR | Autor | M-07 |
| RG-08 | Una consulta inyectable expone la agenda. | 1 | 3 | 3 medio | CP-03: solo `prepare()` con parámetros | CD-01: la prueba «PDO» del Micro-HIS | Revisor del PR | M-04 |

## 4. Controles

| ID | Control | Tipo | Cuándo | Responsable | Evidencia |
|---|---|---|---|---|---|
| CP-01 | Plantilla de PR, un commit por tarea y máximo 400 líneas, con una prueba por cada regla nueva | Preventivo | Antes de abrir cada PR | Autor | Cuerpo del PR con `git diff --shortstat` |
| CP-02 | Reglas en el dominio y en la base (`CHECK`, exclusión de cruces, `UPDATE` condicionado, reloj inyectado) | Preventivo | Al diseñar cada cambio | Autor | Código y `schema.sql` |
| CP-03 | Solo sentencias preparadas | Preventivo | Al escribir la persistencia | Autor | `RepositorioPdo` |
| CP-04 | Autorización antes de cada caso de uso (CU-05) | Preventivo | Semana 15 | Autor | Controlador y prueba 403 |
| CD-01 | `pruebas.php` en verde | Detectivo | En cada PR | Autor y revisor | Salida en el cuerpo del PR |
| CD-02 | `medir-metricas.php` | Detectivo | En cada PR y cada viernes | Autor | `salida-metricas.txt` |
| CD-03 | Inspección formal con el checklist de la semana 13 | Detectivo | Al integrar el código congelado | Moderador | Acta con M-06 |
| CD-04 | Revisión del PR por el revisor asignado | Detectivo | En cada PR | Revisor del PR | Aprobación en GitHub |

## 5. Responsables

| Rol | Responsabilidad en el plan |
|---|---|
| **Autor:** Josué David Morales Ramírez | Mide, corrige, mantiene al día la matriz de riesgos y deja la evidencia en cada PR. |
| **Revisor del PR** (designado por el docente) | Verifica M-04 y M-05 y rechaza el PR que no cumpla. |
| **Moderador de la inspección** | Lleva M-06 y decide si la inspección se cierra o se repite. |
| **Docente** | Acepta o rechaza un riesgo alto que se posponga, como RG-01 hasta la semana 15. |

## 6. Calendario de seguimiento

| Momento | Actividad | Métricas y riesgos | Responsable |
|---|---|---|---|
| 28/10/2026 (esta entrega) | Línea base con `medir-metricas.php` | M-01 a M-07 | Autor |
| En cada PR | Pruebas, métricas y revisión antes de integrar | M-01, M-04, M-05, M-07 | Autor y revisor |
| Cada viernes | Medición completa y actualización de la matriz | Todas; riesgos altos | Autor |
| Al integrar los PR #195 y #289 | Congelar el código y convocar la inspección; con 5 mayores, se repite completa | M-06; RG-02 a RG-05 | Moderador |
| Semana 15 (pruebas unitarias, caja blanca y caja negra) | Llevar M-03 al umbral; pruebas de carrera, de reloj y de 403 | M-02, M-03; RG-01, RG-02, RG-05 | Autor |
| Semana 16 (integración) | Volver a medir sobre `develop` integrado; ningún riesgo alto abierto | Todas | Autor y docente |
| Semana 17 (seguridad) | Pruebas de permisos, tenant y citas ajenas | M-06; RG-01, RG-08 | Autor |

**Bibliografía:** IEEE (2014). *IEEE Std 730-2014: Standard for Software Quality Assurance Processes* · ISO/IEC (2011). *ISO/IEC 25010:2011: Systems and software Quality Requirements and Evaluation (SQuaRE) — System and software quality models* · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
