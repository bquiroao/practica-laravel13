# Semana 14 — Plan de aseguramiento de calidad (SQA) del Centro de documentación y manuales por rol

| | |
|---|---|
| **Tarea Odoo** | 2486 — Semana 14: Confiabilidad y aseguramiento de calidad |
| **Módulo** | Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) |
| **Flujo** | «Publicación y consulta de documentación y manuales diferenciados por rol» |
| **Base** | Reglas R-01 a R-10 y CU-01 a CU-06 de la semana 1, RNF-01 a RNF-05 de la semana 2, Micro-HIS de la semana 3 e inspección de la semana 13 (PR #290) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y objetivos de calidad

El plan cubre el Micro-HIS del centro de documentación, los documentos que publica (OpenAPI, colecciones Postman y manuales) y cada PR que los cambie, desde la semana 14 hasta la entrega final. Sigue la estructura de IEEE 730 (qué se mide, quién, cuándo y qué se hace si falla). Los objetivos usan las características de ISO/IEC 25010 que más pesan en este flujo:

| Característica | Qué significa en el centro de documentación | Métricas |
|---|---|---|
| Adecuación funcional | Cada regla R-xx y cada caso de uso tienen una prueba que los nombra; un OpenAPI publicado pasa el lint (R-04). | M-01, M-02, M-03 |
| Fiabilidad | La versión vigente es siempre la mayor publicada, y una base caída responde 503 sin versiones a medias. | M-01 |
| Seguridad | Un lector solo ve lo de su audiencia, lo ajeno responde 404 (R-06, R-07) y ninguna consulta es inyectable. | M-04, M-06 |
| Mantenibilidad | Cada cliente usa solo su puerto (RNF-03), los métodos son cortos y los PR caben en una revisión. | M-05, M-07 |
| Trazabilidad | Enviar, aprobar, rechazar y marcar obsoleta dejan quién y cuándo (R-10), y las versiones nunca se borran (RNF-05). | M-06 |

## 2. Métricas

Las métricas M-01 a M-05 y M-07 las mide [medir-metricas.php](evidencia/medir-metricas.php) sobre el código y el historial de `develop`. Su [salida](evidencia/salida-metricas.txt) es la **línea base** del 28/10/2026 (commit `8ffa2e2`).

| ID | Métrica | Fuente | Umbral | Responsable | Acción si no cumple | Línea base |
|---|---|---|---|---|---|---|
| M-01 | Pruebas del Micro-HIS en verde | `pruebas.php` | 100 % | Autor | El PR no se integra hasta corregirlo. | 14 de 14 ✔ |
| M-02 | Reglas R-xx citadas por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Agregar la prueba que falta, con el ID de la regla en su nombre. | 7 de 10 (70 %) ✘: faltan R-04, R-09 y R-10 |
| M-03 | Casos de uso CU-xx citados por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Nombrar el caso de uso en cada prueba de camino feliz. | 0 de 6 ✘ |
| M-04 | Consultas SQL que concatenan variables | `src/Persistencia` | 0 | Revisor del PR | Rechazar el PR; reescribir con `prepare()` y parámetros. | 0 ✔ |
| M-05 | Líneas del método más largo | `src/` | ≤ 15 | Revisor del PR | Extraer el paso a una función con nombre propio. | 15 ✔: justo en el límite |
| M-06 | Hallazgos mayores o críticos abiertos de la inspección | Acta de la semana 13 | 0 | Moderador | No se cierra la inspección; con más de 3, se repite (S-04). | 3 ✘ (H-01 a H-03, PR #290) |
| M-07 | Líneas del PR más grande integrado | `git show --numstat` | ≤ 400 | Autor | Dividir la tarea en dos PR. | 398 ✔ (3 PR) |

M-06 no la mide el script: sale del acta de la inspección, que hoy solo tiene la autorrevisión del autor. Las tres reglas sin prueba de M-02 son justo las de contenido y auditoría: lint (R-04), datos ficticios (R-09) y auditoría (R-10). M-05 y M-07 cumplen al límite y comparten el riesgo RG-07.

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; el nivel es P × I: 1–2 bajo, 3–4 medio y 6–9 alto. Los riesgos altos se revisan cada semana.

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo | Responsable | Métrica |
|---|---|---|---|---|---|---|---|---|
| RG-01 | Un lector ve documentos fuera de su audiencia porque el cliente envía sus propios roles (R-06, H-01). | 3 | 3 | **9 alto** | CP-04: usuario y `role_code` desde la sesión y RBAC, no desde la petición | CD-01: prueba de 404 con roles ajenos (semana 15, RNF-01) | Autor | M-06 |
| RG-02 | Se publica un OpenAPI inválido o con datos reales (R-04, R-09, H-02). | 3 | 3 | **9 alto** | CP-05: lint y búsqueda de datos reales antes de pasar a revisión (E4) | CD-03: ítems C-04 a C-07 de la inspección | Autor | M-02, M-06 |
| RG-03 | Se pierde quién aprobó una versión (R-10, H-03). | 3 | 2 | **6 alto** | CP-02: historial de revisiones en lugar de sobrescribir `reviewed_by` | CD-01: prueba que aprueba y marca obsoleta | Autor | M-06 |
| RG-04 | La colección Postman no coincide con el OpenAPI de su versión. | 2 | 2 | 4 medio | CP-05: la colección se valida contra el OpenAPI al enviarla | CD-04: el revisor compara endpoints y métodos | Revisor del PR | M-02 |
| RG-05 | Una versión obsoleta sin reemplazo deja a un rol sin documentación (R-08, H-05). | 2 | 2 | 4 medio | CP-02: `marcarObsoleta` exige la versión que la reemplaza | CD-01: prueba con un rol fuera de la audiencia nueva | Autor | M-01 |
| RG-06 | Dos envíos simultáneos de la misma versión reciben 503 en vez de 409 (H-04). | 1 | 2 | 2 bajo | CP-02: traducir la violación del índice único a E3 | CD-01: prueba con un repositorio que intercala el segundo envío | Autor | M-01 |
| RG-07 | Un método o un PR al límite crece y se revisa por encima. | 2 | 2 | 4 medio | CP-01: un PR por tarea, máximo 400 líneas | CD-02: `medir-metricas.php` en cada PR | Autor | M-05, M-07 |
| RG-08 | Una consulta inyectable expone documentos de otra audiencia. | 1 | 3 | 3 medio | CP-03: solo `prepare()` con parámetros | CD-01: la prueba «PDO» del Micro-HIS | Revisor del PR | M-04 |

## 4. Controles

| ID | Control | Tipo | Cuándo | Responsable | Evidencia |
|---|---|---|---|---|---|
| CP-01 | Plantilla de PR, un commit por tarea y máximo 400 líneas, con una prueba por cada regla nueva | Preventivo | Antes de abrir cada PR | Autor | Cuerpo del PR con `git diff --shortstat` |
| CP-02 | Reglas en el dominio y en la base (índice único, estados, historial sin sobrescribir) | Preventivo | Al diseñar cada cambio | Autor | Código y `schema.sql` |
| CP-03 | Solo sentencias preparadas | Preventivo | Al escribir la persistencia | Autor | `RepositorioPdo` |
| CP-04 | Usuario y roles desde la sesión (CU-06) | Preventivo | Semana 15 | Autor | Controlador y prueba de 404 |
| CP-05 | Validación del contenido antes de la revisión: lint de OpenAPI, Postman contra OpenAPI y datos ficticios (CU-05) | Preventivo | Al enviar cada versión | Autor | Reporte de E4 |
| CD-01 | `pruebas.php` en verde | Detectivo | En cada PR | Autor y revisor | Salida en el cuerpo del PR |
| CD-02 | `medir-metricas.php` | Detectivo | En cada PR y cada viernes | Autor | `salida-metricas.txt` |
| CD-03 | Inspección formal con el checklist de la semana 13 | Detectivo | Al integrar el código congelado | Moderador | Acta con M-06 |
| CD-04 | Revisión del PR por el revisor asignado | Detectivo | En cada PR | Revisor del PR | Aprobación en GitHub |

## 5. Responsables

| Rol | Responsabilidad en el plan |
|---|---|
| **Autor:** Josué David Morales Ramírez | Mide, corrige, mantiene al día la matriz de riesgos y deja la evidencia en cada PR. |
| **Revisor del PR** (designado por el docente) | Verifica M-04 y M-05, compara Postman con OpenAPI y rechaza el PR que no cumpla. |
| **Moderador de la inspección** | Lleva M-06 y decide si la inspección se cierra o se repite. |
| **Docente** | Acepta o rechaza un riesgo alto que se posponga, como RG-01 hasta la semana 15. |

## 6. Calendario de seguimiento

| Momento | Actividad | Métricas y riesgos | Responsable |
|---|---|---|---|
| 28/10/2026 (esta entrega) | Línea base con `medir-metricas.php` | M-01 a M-07 | Autor |
| En cada PR | Pruebas, métricas y revisión antes de integrar | M-01, M-04, M-05, M-07 | Autor y revisor |
| Cada viernes | Medición completa y actualización de la matriz | Todas; riesgos altos | Autor |
| Al integrar los PR #196 y #290 | Congelar el código y convocar la inspección | M-06; RG-02, RG-03, RG-05 | Moderador |
| Semana 15 (pruebas unitarias, caja blanca y caja negra) | Llevar M-02 y M-03 al umbral; pruebas de lint (E4), de auditoría y de roles ajenos | M-02, M-03; RG-01, RG-02, RG-03 | Autor |
| Semana 16 (integración) | Volver a medir sobre `develop` integrado; ningún riesgo alto abierto | Todas | Autor y docente |
| Semana 17 (seguridad) | Pruebas de audiencia, 404 y datos sensibles en ejemplos | M-06; RG-01, RG-02, RG-08 | Autor |

**Bibliografía:** IEEE (2014). *IEEE Std 730-2014: Standard for Software Quality Assurance Processes* · ISO/IEC (2011). *ISO/IEC 25010:2011: Systems and software Quality Requirements and Evaluation (SQuaRE) — System and software quality models* · OpenAPI Initiative (2021). *OpenAPI Specification 3.1.0* · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
