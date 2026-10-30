# Semana 14 — Plan de aseguramiento de calidad (SQA) del Dashboard de ocupación hospitalaria

| | |
|---|---|
| **Tarea Odoo** | 2501 — Semana 14: Confiabilidad y aseguramiento de calidad |
| **Módulo** | Dashboard de ocupación hospitalaria |
| **Flujo** | «Consolidación y consulta de ocupación por sala y estado de cama» |
| **Base** | Reglas R-01 a R-09 y CU-01 a CU-05 de la semana 1, RNF-01 a RNF-05 de la semana 2, Micro-HIS de las semanas 3 y 4 e inspección de la semana 13 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y objetivos de calidad

El plan cubre el Micro-HIS de ocupación y cada PR que lo cambie, desde la semana 14 hasta la entrega final. Sigue la estructura de IEEE 730 (qué se mide, quién, cuándo y qué se hace si falla). Los objetivos usan las características de ISO/IEC 25010 que más pesan en este flujo:

| Característica | Qué significa en el tablero | Métricas |
|---|---|---|
| Adecuación funcional | La ocupación sigue la fórmula de R-01, y cada regla y caso de uso tienen una prueba que los nombra. | M-01, M-02, M-03 |
| Fiabilidad | Si falla la lectura, se conserva el corte anterior y el tablero no queda sin datos (R-09, RNF-04). | M-01, M-06 |
| Seguridad | Solo consulta quien tiene permiso en su hospital (R-08), y el corte no guarda datos de pacientes (R-07). | M-04, M-06 |
| Mantenibilidad | Cada cliente usa solo sus puertos (RNF-03), los métodos son cortos y los PR caben en una revisión. | M-05, M-07 |
| Integridad de los datos | La consolidación no cambia ninguna cama (RNF-02), y toda diferencia entre cama y estancia queda marcada (R-04). | M-01, M-06 |

## 2. Métricas

Las métricas M-01 a M-05 y M-07 las mide [medir-metricas.php](evidencia/medir-metricas.php) sobre el código y el historial de `develop`. Su [salida](evidencia/salida-metricas.txt) es la **línea base** del 28/10/2026 (commit `21c9c52`, ya con la semana 4 integrada).

| ID | Métrica | Fuente | Umbral | Responsable | Acción si no cumple | Línea base |
|---|---|---|---|---|---|---|
| M-01 | Pruebas del Micro-HIS en verde | `pruebas.php` | 100 % | Autor | El PR no se integra hasta corregirlo. | 29 de 29 ✔ |
| M-02 | Reglas R-xx citadas por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Agregar la prueba que falta, con el ID de la regla en su nombre. | 8 de 9 (88 %) ✔: falta R-08 |
| M-03 | Casos de uso CU-xx citados por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Nombrar el caso de uso en cada prueba de camino feliz. | 2 de 5 (40 %) ✘: faltan CU-02, CU-04 y CU-05 |
| M-04 | Consultas SQL que concatenan variables | `src/Persistencia` | 0 | Revisor del PR | Rechazar el PR; reescribir con `prepare()` y parámetros. | 0 ✔ |
| M-05 | Líneas del método más largo | `src/` | ≤ 15 | Revisor del PR | Extraer el paso a una función con nombre propio. | 12 ✔ |
| M-06 | Hallazgos mayores o críticos abiertos de la inspección | Acta de la semana 13 | 0 | Moderador | No se cierra la inspección; con más de 3, se repite (S-04). | 2 ✘ (H-01 y H-03) |
| M-07 | Líneas del PR más grande integrado | `git show --numstat` | ≤ 400 | Autor | Dividir la tarea en dos PR. | 395 ✔ (9 PR) |

M-06 no la mide el script: sale del acta de la inspección de la semana 13, que hoy solo tiene la autorrevisión del autor. La regla sin prueba de M-02 es justo la del permiso (R-08), y el caso de uso CU-05 es su autorización: es el mismo hueco que la inspección registró como H-01.

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; el nivel es P × I: 1–2 bajo, 3–4 medio y 6–9 alto. Los riesgos altos se revisan cada semana.

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo | Responsable | Métrica |
|---|---|---|---|---|---|---|---|---|
| RG-01 | Alguien sin permiso de ocupación, o de otro hospital, ve el tablero (R-08, H-01). | 3 | 3 | **9 alto** | CP-04: sesión, permiso y `X-Tenant-ID` antes de consultar | CD-01: prueba de 403 en `pruebas.php` (semana 15, RNF-01) | Autor | M-02, M-06 |
| RG-02 | Dos pacientes en una cama no se marcan como inconsistencia (R-04, H-03). | 2 | 3 | **6 alto** | CP-02: contar las estancias abiertas, no solo si existe alguna | CD-01: prueba con dos estancias abiertas en la misma cama | Autor | M-06 |
| RG-03 | Un paciente en una sala inactiva desaparece del tablero (H-04). | 1 | 3 | 3 medio | CP-02: lo excluido por R-03 se marca si tiene estancias abiertas | CD-03: ítem C-03 de la inspección | Autor | M-06 |
| RG-04 | Jefatura decide con un corte viejo sin saberlo (R-06). | 1 | 3 | 3 medio | CP-02: cada corte guarda su hora y la consulta avisa a los 15 minutos | CD-01: prueba con un corte de 20 minutos | Autor | M-01 |
| RG-05 | Si la base cae, la consulta no devuelve ningún corte (RNF-04, H-05). | 2 | 2 | 4 medio | CP-02: la consulta conserva en memoria el último corte leído | CD-01: prueba con la tabla de cortes caída | Autor | M-01 |
| RG-06 | Los códigos de error no son los de la semana 1, y el cliente muestra un mensaje equivocado (H-02). | 2 | 1 | 2 bajo | CP-01: los códigos salen de la tabla de la semana 1 | CD-04: el revisor compara los códigos con la semana 1 | Revisor del PR | M-06 |
| RG-07 | Un PR demasiado grande se revisa por encima. | 2 | 2 | 4 medio | CP-01: un PR por tarea, máximo 400 líneas | CD-02: `medir-metricas.php` en cada PR | Autor | M-07 |
| RG-08 | Una consulta inyectable expone la ocupación de otro hospital. | 1 | 3 | 3 medio | CP-03: solo `prepare()` con parámetros | CD-02: M-04 sobre toda la carpeta de persistencia | Revisor del PR | M-04 |

## 4. Controles

| ID | Control | Tipo | Cuándo | Responsable | Evidencia |
|---|---|---|---|---|---|
| CP-01 | Plantilla de PR, un commit por tarea y máximo 400 líneas, con una prueba por cada regla nueva | Preventivo | Antes de abrir cada PR | Autor | Cuerpo del PR con `git diff --shortstat` |
| CP-02 | Reglas en el dominio (fórmula, «no aplica», inconsistencias, corte viejo) y solo lectura sobre las camas | Preventivo | Al diseñar cada cambio | Autor | Código del dominio y puertos sin `cambiar…` |
| CP-03 | Solo sentencias preparadas | Preventivo | Al escribir la persistencia | Autor | `RepositorioPdo` |
| CP-04 | Autorización antes de consultar (CU-05) | Preventivo | Semana 15 | Autor | Controlador y prueba 403 |
| CD-01 | `pruebas.php` en verde | Detectivo | En cada PR | Autor y revisor | Salida en el cuerpo del PR |
| CD-02 | `medir-metricas.php` | Detectivo | En cada PR y cada viernes | Autor | `salida-metricas.txt` |
| CD-03 | Inspección formal con el checklist de la semana 13 | Detectivo | Al convocar la reunión | Moderador | Acta con M-06 |
| CD-04 | Revisión del PR por el revisor asignado | Detectivo | En cada PR | Revisor del PR | Aprobación en GitHub |

## 5. Responsables

| Rol | Responsabilidad en el plan |
|---|---|
| **Autor:** Josué David Morales Ramírez | Mide, corrige, mantiene al día la matriz de riesgos y deja la evidencia en cada PR. |
| **Revisor del PR** (designado por el docente) | Verifica M-04, M-05 y los códigos de error, y rechaza el PR que no cumpla. |
| **Moderador de la inspección** | Lleva M-06 y decide si la inspección se cierra o se repite. |
| **Docente** | Acepta o rechaza un riesgo alto que se posponga, como RG-01 hasta la semana 15. |

## 6. Calendario de seguimiento

| Momento | Actividad | Métricas y riesgos | Responsable |
|---|---|---|---|
| 28/10/2026 (esta entrega) | Línea base con `medir-metricas.php` | M-01 a M-07 | Autor |
| En cada PR | Pruebas, métricas y revisión antes de integrar | M-01, M-04, M-05, M-07 | Autor y revisor |
| Cada viernes | Medición completa y actualización de la matriz | Todas; riesgos altos | Autor |
| Reunión de inspección (código de la semana 4 ya integrado) | Volver a reproducir los hallazgos sobre el código actual y cerrar el acta | M-06; RG-02, RG-03, RG-06 | Moderador |
| Semana 15 (pruebas unitarias, caja blanca y caja negra) | Llevar M-03 al umbral; pruebas de 403, de dos estancias y de base caída | M-02, M-03; RG-01, RG-02, RG-05 | Autor |
| Semana 16 (integración) | Volver a medir sobre `develop` integrado; ningún riesgo alto abierto | Todas | Autor y docente |
| Semana 17 (seguridad) | Pruebas de permisos, tenant y ausencia de datos de pacientes en los cortes | M-06; RG-01, RG-08 | Autor |

**Bibliografía:** IEEE (2014). *IEEE Std 730-2014: Standard for Software Quality Assurance Processes* · ISO/IEC (2011). *ISO/IEC 25010:2011: Systems and software Quality Requirements and Evaluation (SQuaRE) — System and software quality models* · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
