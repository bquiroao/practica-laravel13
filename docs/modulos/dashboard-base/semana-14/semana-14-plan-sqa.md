# Semana 14 — Plan de aseguramiento de calidad (SQA) del Dashboard base

| | |
|---|---|
| **Tarea Odoo** | 2441 — Semana 14: Confiabilidad y aseguramiento de calidad |
| **Módulo** | Dashboard base UI/UX e implementación transversal |
| **Flujo** | «Navegación del dashboard con estados de carga, vacío, error y resumen inicial» |
| **Base** | Reglas R-01 a R-09 y CU-01 a CU-05 de la semana 1, RNF-01 a RNF-05 de la semana 2, Micro-HIS de las semanas 3 y 4 e inspección de la semana 13 |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y objetivos de calidad

El plan cubre el Micro-HIS del dashboard y cada PR que lo cambie, desde la semana 14 hasta la entrega final. Sigue la estructura de IEEE 730 (qué se mide, quién, cuándo y qué se hace si falla). Los objetivos usan las características de ISO/IEC 25010 que más pesan en este flujo:

| Característica | Qué significa en el dashboard | Métricas |
|---|---|---|
| Adecuación funcional | Cada regla R-xx y cada caso de uso tienen una prueba que los nombra; cada conteo es el correcto. | M-01, M-02, M-03 |
| Fiabilidad | Cada tarjeta carga y falla por su cuenta (R-03), y «vacío» nunca se confunde con «error» (R-05). | M-01 |
| Seguridad | Ninguna consulta es inyectable, el menú solo muestra lo que el rol permite (R-02) y las tarjetas solo dan conteos (R-06). | M-04, M-06 |
| Mantenibilidad | Cambiar una regla o un texto toca una sola función (RNF-03), los métodos son cortos y los PR caben en una revisión. | M-05, M-07 |
| Usabilidad | Ningún texto muestra códigos ni errores técnicos (R-09, RNF-01). | M-01, M-06 |

## 2. Métricas

Las métricas M-01 a M-05 y M-07 las mide [medir-metricas.php](evidencia/medir-metricas.php) sobre el código y el historial de `develop`. Su [salida](evidencia/salida-metricas.txt) es la **línea base** del 28/10/2026 (commit `21c9c52`, ya con la semana 4 integrada).

| ID | Métrica | Fuente | Umbral | Responsable | Acción si no cumple | Línea base |
|---|---|---|---|---|---|---|
| M-01 | Pruebas del Micro-HIS en verde | `pruebas.php` | 100 % | Autor | El PR no se integra hasta corregirlo. | 27 de 27 ✔ |
| M-02 | Reglas R-xx citadas por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Agregar la prueba que falta, con el ID de la regla en su nombre. | 9 de 9 (100 %) ✔ |
| M-03 | Casos de uso CU-xx citados por alguna prueba | `pruebas.php` contra la semana 1 | ≥ 80 % | Autor | Nombrar el caso de uso en cada prueba de camino feliz. | 3 de 5 (60 %) ✘: faltan CU-03 y CU-05 |
| M-04 | Consultas SQL que concatenan variables | `src/Persistencia` | 0 | Revisor del PR | Rechazar el PR; reescribir con `prepare()` y parámetros. | 0 ✔ |
| M-05 | Líneas del método más largo | `src/` | ≤ 15 | Revisor del PR | Extraer el paso a una función con nombre propio. | 11 ✔ |
| M-06 | Hallazgos mayores o críticos abiertos de la inspección | Acta de la semana 13 | 0 | Moderador | No se cierra la inspección; con más de 3, se repite (S-04). | 1 ✘ (H-01) |
| M-07 | Líneas del PR más grande integrado | `git show --numstat` | ≤ 400 | Autor | Dividir la tarea en dos PR. | 386 ✔ (9 PR) |

M-06 no la mide el script: sale del acta de la inspección de la semana 13, que hoy solo tiene la autorrevisión del autor. Los dos casos de uso sin prueba de M-03 son justo los de navegación y autorización (CU-03 y CU-05), los mismos que señaló la inspección (H-01 y H-02).

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; el nivel es P × I: 1–2 bajo, 3–4 medio y 6–9 alto. Los riesgos altos se revisan cada semana.

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo | Responsable | Métrica |
|---|---|---|---|---|---|---|---|---|
| RG-01 | El cliente envía su hospital y sus permisos, y ve tarjetas o menús que no le tocan (R-02, H-01). | 3 | 3 | **9 alto** | CP-04: hospital y permisos desde la sesión y RBAC, antes de cada caso de uso | CD-01: prueba con un hospital ajeno (semana 15) | Autor | M-06 |
| RG-02 | Una ruta escrita a mano abre un módulo que el rol no permite (CU-03, H-02). | 2 | 2 | 4 medio | CP-04: el backend valida la ruta con E6 | CD-01: prueba de navegación con E6 | Autor | M-03 |
| RG-03 | «Citas de hoy» cuenta mal por la zona horaria (H-04). | 2 | 2 | 4 medio | CP-02: el día se calcula en la zona del hospital | CD-01: prueba con una cita de las 20:00 guardada en UTC | Autor | M-01 |
| RG-04 | Una tarjeta muestra un dato que identifica a un paciente (R-06). | 1 | 3 | 3 medio | CP-02: el repositorio solo hace `COUNT` | CD-01: prueba de que la tarjeta solo trae id, estado y texto | Revisor del PR | M-01 |
| RG-05 | Una tarjeta caída bloquea a las demás (R-03). | 1 | 2 | 2 bajo | CP-02: cada tarjeta se pide y falla por separado | CD-01: prueba con una base rota para una sola tarjeta | Autor | M-01 |
| RG-06 | Una tarjeta inexistente se oculta como si faltara permiso (H-03). | 2 | 1 | 2 bajo | CP-02: un id desconocido responde 404 | CD-03: ítem C-14 de la inspección | Autor | M-06 |
| RG-07 | Un PR demasiado grande se revisa por encima. | 2 | 2 | 4 medio | CP-01: un PR por tarea, máximo 400 líneas | CD-02: `medir-metricas.php` en cada PR | Autor | M-07 |
| RG-08 | Una consulta inyectable expone conteos de otro hospital. | 1 | 3 | 3 medio | CP-03: solo `prepare()` con parámetros | CD-01: la prueba «PDO» del Micro-HIS | Revisor del PR | M-04 |

## 4. Controles

| ID | Control | Tipo | Cuándo | Responsable | Evidencia |
|---|---|---|---|---|---|
| CP-01 | Plantilla de PR, un commit por tarea y máximo 400 líneas, con una prueba por cada regla nueva | Preventivo | Antes de abrir cada PR | Autor | Cuerpo del PR con `git diff --shortstat` |
| CP-02 | Reglas en el dominio (estados, textos y conteos con una sola razón de cambio) | Preventivo | Al diseñar cada cambio | Autor | Código del dominio y del repositorio |
| CP-03 | Solo sentencias preparadas | Preventivo | Al escribir la persistencia | Autor | `RepositorioPdo` |
| CP-04 | Sesión, hospital y permisos antes de cada caso de uso (CU-05) | Preventivo | Semana 15 | Autor | Controlador y pruebas de 401, 403 y E6 |
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
| **Docente** | Acepta o rechaza un riesgo alto que se posponga, como RG-01 hasta la semana 15. |

## 6. Calendario de seguimiento

| Momento | Actividad | Métricas y riesgos | Responsable |
|---|---|---|---|
| 28/10/2026 (esta entrega) | Línea base con `medir-metricas.php` | M-01 a M-07 | Autor |
| En cada PR | Pruebas, métricas y revisión antes de integrar | M-01, M-04, M-05, M-07 | Autor y revisor |
| Cada viernes | Medición completa y actualización de la matriz | Todas; riesgos altos | Autor |
| Reunión de inspección (código de la semana 4 ya integrado) | Volver a reproducir los hallazgos sobre el código actual y cerrar el acta | M-06; RG-01, RG-02, RG-06 | Moderador |
| Semana 15 (pruebas unitarias, caja blanca y caja negra) | Llevar M-03 al umbral; pruebas de hospital ajeno, E6 y zona horaria | M-03; RG-01 a RG-03 | Autor |
| Semana 16 (integración) | Volver a medir sobre `develop` integrado; ningún riesgo alto abierto | Todas | Autor y docente |
| Semana 17 (seguridad) | Pruebas de permisos, tenant y datos de pacientes en tarjetas | M-06; RG-01, RG-04, RG-08 | Autor |

**Bibliografía:** IEEE (2014). *IEEE Std 730-2014: Standard for Software Quality Assurance Processes* · ISO/IEC (2011). *ISO/IEC 25010:2011: Systems and software Quality Requirements and Evaluation (SQuaRE) — System and software quality models* · Pressman, R. S. y Maxim, B. R. (2020). *Software Engineering: A Practitioner's Approach* (9.ª ed.). McGraw-Hill.
