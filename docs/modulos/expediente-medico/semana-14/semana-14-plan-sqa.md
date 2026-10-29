# Semana 14 — Plan de aseguramiento de calidad (SQA) del Expediente médico electrónico base

| | |
|---|---|
| **Tarea Odoo** | 2516 — Semana 14: Confiabilidad y aseguramiento de calidad |
| **Módulo** | Expediente médico electrónico base |
| **Flujo** | «Apertura y consulta autorizada del expediente longitudinal» |
| **Base** | Reglas R-01 a R-10 y CU-01 a CU-06 de la semana 1, RNF-01 a RNF-04 de la semana 2, Micro-HIS de la semana 3 e inspección de la semana 13 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y objetivos de calidad

El plan cubre el Micro-HIS del expediente y cada PR que lo cambie, desde la semana 14 hasta la entrega final. Sigue la estructura de IEEE 730 (qué se mide, quién, cuándo y qué se hace si falla). Los objetivos usan las características de ISO/IEC 25010 que más pesan en este flujo:

| Característica | Qué significa en el expediente | Métricas |
|---|---|---|
| Adecuación funcional | Cada regla R-xx y cada caso de uso tienen una prueba que los nombra. | M-01, M-02, M-03 |
| Fiabilidad | Si la base CENTRAL cae, la consulta local sigue, y ningún expediente queda a medias. | M-01 |
| Seguridad | Solo consulta quien tiene una atención activa (R-05); otro hospital exige consentimiento (R-06) y solo se devuelve el alcance autorizado (R-09). | M-04, M-06 |
| Trazabilidad | Todo acceso, aceptado, rechazado o fallido, queda en una auditoría que no se puede modificar (R-08, RNF-04). | M-06 |
| Mantenibilidad | Cambiar la base no toca la política (RNF-01), los métodos son cortos y los PR caben en una revisión. | M-05, M-07 |

## 2. Métricas

Las métricas M-01 a M-05 y M-07 las mide [medir-metricas.php](evidencia/medir-metricas.php) sobre el código y el historial de `develop`. Su [salida](evidencia/salida-metricas.txt) es la **línea base** del 28/10/2026 (commit `21c9c52`, con el Micro-HIS de la semana 3 integrado por el PR #191).

| ID | Métrica | Fuente | Umbral | Responsable | Acción si no cumple | Línea base |
|---|---|---|---|---|---|---|
| M-01 | Pruebas del Micro-HIS en verde | `pruebas.php` | 100 % | Autor | El PR no se integra hasta corregirlo. | 14 de 14 ✔ |
| M-02 | Reglas R-xx citadas por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Agregar la prueba que falta, con el ID de la regla en su nombre. | 6 de 10 (60 %) ✘: faltan R-02, R-04, R-07 y R-09 |
| M-03 | Casos de uso CU-xx citados por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Nombrar el caso de uso en cada prueba de camino feliz. | 3 de 6 (50 %) ✘: faltan CU-04, CU-05 y CU-06 |
| M-04 | Consultas SQL que concatenan variables | `src/Persistencia` | 0 | Revisor del PR | Rechazar el PR; reescribir con `prepare()` y parámetros. | 0 ✔ |
| M-05 | Líneas del método más largo | `src/` | ≤ 15 | Revisor del PR | Extraer el paso a una función con nombre propio. | 13 ✔ |
| M-06 | Hallazgos mayores o críticos abiertos de la inspección | Acta de la semana 13 | 0 | Moderador | No se cierra la inspección; con más de 3, se repite (S-04). | 4 ✘ (H-02 a H-05) |
| M-07 | Líneas del PR más grande integrado | `git show --numstat` | ≤ 400 | Autor | Dividir la tarea en dos PR. | 397 ✔ (8 PR) |

M-06 no la mide el script: sale del acta de la inspección de la semana 13, que hoy solo tiene la autorrevisión del autor. Las reglas sin prueba de M-02 coinciden con los hallazgos: alcance (R-09, H-03) y emergencia (R-07, que la semana 3 dejó para después). M-07 está a 3 líneas del límite.

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; el nivel es P × I: 1–2 bajo, 3–4 medio y 6–9 alto. Los riesgos altos se revisan cada semana.

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo | Responsable | Métrica |
|---|---|---|---|---|---|---|---|---|
| RG-01 | Un acceso fallido no queda en la auditoría, o un evento se modifica después (R-08, RNF-04, H-04, H-05). | 3 | 3 | **9 alto** | CP-02: auditar también los fallos técnicos y bloquear `UPDATE` y `DELETE` en la auditoría | CD-01: prueba con CENTRAL caída y prueba de `UPDATE` rechazado | Autor | M-06 |
| RG-02 | Se devuelve más historia de la que autoriza el consentimiento (R-09, H-03). | 3 | 3 | **9 alto** | CP-02: alcance en la asignación y en el consentimiento, filtrado antes de responder | CD-01: prueba con un consentimiento de alcance parcial | Autor | M-02, M-06 |
| RG-03 | Alguien que no es de admisión abre un expediente, o el profesional llega desde la petición (CU-01, H-02). | 3 | 2 | **6 alto** | CP-04: rol y profesional desde la sesión | CD-01: prueba de 403 con un médico que intenta abrir | Autor | M-06 |
| RG-04 | Un profesional sin atención activa ve el expediente (R-05). | 1 | 3 | 3 medio | CP-02: asignación activa antes de leer nada | CD-01: prueba de 403 con un profesional sin asignación | Autor | M-01 |
| RG-05 | Se abre un segundo expediente para el mismo paciente (R-01, R-02). | 1 | 3 | 3 medio | CP-02: `UNIQUE` en paciente y en número | CD-01: prueba de E3; falta una que nombre R-02 | Autor | M-02 |
| RG-06 | Un acceso de emergencia sin justificación o sin límite de 1 hora (R-07). | 2 | 3 | **6 alto** | CP-02: CU-04 con justificación obligatoria y vencimiento | CD-03: ítem C-10 de la inspección, hoy «no aplica» | Autor | M-02 |
| RG-07 | Un PR demasiado grande se revisa por encima. | 2 | 2 | 4 medio | CP-01: un PR por tarea, máximo 400 líneas | CD-02: `medir-metricas.php` en cada PR | Autor | M-07 |
| RG-08 | Una consulta inyectable expone datos clínicos. | 1 | 3 | 3 medio | CP-03: solo `prepare()` con parámetros | CD-01: la prueba «PDO» del Micro-HIS | Revisor del PR | M-04 |

## 4. Controles

| ID | Control | Tipo | Cuándo | Responsable | Evidencia |
|---|---|---|---|---|---|
| CP-01 | Plantilla de PR, un commit por tarea y máximo 400 líneas, con una prueba por cada regla nueva | Preventivo | Antes de abrir cada PR | Autor | Cuerpo del PR con `git diff --shortstat` |
| CP-02 | Reglas en el dominio y en la base (`UNIQUE`, asignación activa, consentimiento vigente, alcance, auditoría inmutable) | Preventivo | Al diseñar cada cambio | Autor | Código y esquemas |
| CP-03 | Solo sentencias preparadas | Preventivo | Al escribir la persistencia | Autor | `RepositoriosPdo` |
| CP-04 | Sesión, rol y relación de atención antes de cada caso de uso (CU-05) | Preventivo | Semana 15 | Autor | Controlador y pruebas de 403 |
| CD-01 | `pruebas.php` en verde | Detectivo | En cada PR | Autor y revisor | Salida en el cuerpo del PR |
| CD-02 | `medir-metricas.php` | Detectivo | En cada PR y cada viernes | Autor | `salida-metricas.txt` |
| CD-03 | Inspección formal con el checklist de la semana 13 | Detectivo | Al convocar la reunión | Moderador | Acta con M-06 |
| CD-04 | Revisión del PR por el revisor asignado | Detectivo | En cada PR | Revisor del PR | Aprobación en GitHub |

## 5. Responsables

| Rol | Responsabilidad en el plan |
|---|---|
| **Autor:** Josué David Morales Ramírez | Mide, corrige, mantiene al día la matriz de riesgos y deja la evidencia en cada PR. |
| **Revisor del PR** (designado por el docente) | Verifica M-04 y M-05 y rechaza el PR que no cumpla. |
| **Moderador de la inspección** | Lleva M-06 y decide si la inspección se cierra o se repite. |
| **Docente** | Acepta o rechaza un riesgo alto que se posponga, como RG-06 hasta que se diseñe el acceso de emergencia. |

## 6. Calendario de seguimiento

| Momento | Actividad | Métricas y riesgos | Responsable |
|---|---|---|---|
| 28/10/2026 (esta entrega) | Línea base con `medir-metricas.php` | M-01 a M-07 | Autor |
| En cada PR | Pruebas, métricas y revisión antes de integrar | M-01, M-04, M-05, M-07 | Autor y revisor |
| Cada viernes | Medición completa y actualización de la matriz | Todas; riesgos altos | Autor |
| Semana 4 pendiente (tarea 2506) | Volver a medir con el nuevo código y convocar la inspección; con 4 mayores, se repite completa | M-05, M-06, M-07; RG-01 a RG-03 | Autor y moderador |
| Semana 15 (pruebas unitarias, caja blanca y caja negra) | Llevar M-02 y M-03 al umbral; pruebas de auditoría, alcance y rol | M-02, M-03; RG-01 a RG-03, RG-05 | Autor |
| Semana 16 (integración) | Volver a medir sobre `develop` integrado; ningún riesgo alto abierto | Todas | Autor y docente |
| Semana 17 (seguridad) | Pruebas de datos sensibles, consentimiento y emergencia | M-06; RG-02, RG-06, RG-08 | Autor |

**Bibliografía:** IEEE (2014). *IEEE Std 730-2014: Standard for Software Quality Assurance Processes* · ISO/IEC (2011). *ISO/IEC 25010:2011: Systems and software Quality Requirements and Evaluation (SQuaRE) — System and software quality models* · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
