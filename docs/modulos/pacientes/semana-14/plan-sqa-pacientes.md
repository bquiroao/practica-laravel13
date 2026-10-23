# Plan de aseguramiento de la calidad (SQA) — Pacientes

**Módulo:** ASII-03 · Pacientes: registro, edición, búsqueda y detalle · **Responsable del módulo:** Boris Alexander Quiroa Orellana

**Proceso:** «registro, búsqueda y actualización segura de un paciente»

Estructura inspirada en IEEE 730 (plan SQA): propósito, organización, métricas, controles, riesgos, no conformidades y calendario.

## 1. Propósito y alcance

Asegurar que el módulo cumpla, **de forma medible y continua**, cinco atributos de calidad:

| Atributo | Qué significa para Pacientes | Métricas |
|---|---|---|
| **Calidad del flujo** (corrección) | Registrar, buscar, consultar y actualizar hacen lo que dicen R-01..R-11 y RF-01..RF-19 | M1, M2 |
| **Confiabilidad** | Ante fallas de la base o de la bitácora, no quedan fichas, eventos ni claves a medias, y el usuario recibe un 503 claro | M6 |
| **Seguridad** | Ningún actor ve datos que no le corresponden: de una protegida, de otro hospital o el documento en claro | M5, M7 |
| **Trazabilidad** | Toda escritura deja exactamente un evento completo, y las consultas se auditan sin datos personales | M3 |
| **Integridad de la identidad** | Una persona, una ficha: ningún intento de duplicado crea una segunda | M4 |

**Alcance:** el Micro-HIS Pacientes (`micro-his-pacientes/`) y su contrato OpenAPI 1.2.0.

**Fuera de alcance:** el prototipo de interfaz, que tiene sus propios controles (paridad y recorrido con axe, semanas 11 y 12) y aquí solo aparece como control CD-06; y los demás módulos del HIS.

## 2. Organización y responsables

| Rol | Persona | Responsabilidades SQA |
|---|---|---|
| **Responsable del módulo** (RM) | Boris Alexander Quiroa Orellana | Mantiene el código, las pruebas y el contrato. Corre la medición semanal. Ejecuta las acciones correctivas. Dueño de M1–M6. |
| **Responsable SQA del grupo** (RSQA) | Compañero/a del grupo *(por confirmar)* | **Independiente** del autor. Revisa el tablero semanal, abre y cierra no conformidades, y decide si un ROJO bloquea la liberación. Dueño de M7. |
| **Revisor de seguridad** (RS) | Compañero/a del grupo *(por confirmar)*: el R1 de la revisión de la semana 13 | Revisa los cambios en permisos, tenant, confidencialidad y documento. Ejecuta CD-04 (bitácora). |
| **Moderador de revisiones** (MR) | Compañero/a del grupo *(por confirmar)* | Conduce las revisiones formales (CP-03) y verifica el retrabajo. |
| **Supervisión de admisión** (negocio) | Personal del hospital *(en el proyecto: el docente)* | Decide lo que no es técnico (AR-04) y revisa las coincidencias confirmadas (CD-04, consulta 4). |
| **Integrador del HIS** (INT) | Docente / responsable de `develop` | Aprueba el merge a `develop` cuando no hay métricas en ROJO bloqueantes. |

**Regla de independencia.** Quien corrige un hallazgo (RM) no puede ser quien lo da por verificado (RSQA o MR).

## 3. Métricas (7)

La medición es automática con [herramientas/medir-sqa.php](herramientas/medir-sqa.php). Ejecuta escenarios contra el **kernel real** del Micro-HIS y lee la suite, el código y el registro de hallazgos de la semana 13.

**Estados:** **VERDE** cumple el umbral · **ÁMBAR** cumple con una condición pendiente y no bloquea · **ROJO** incumple y abre una no conformidad (§6).

| ID | Métrica | Definición / fórmula | Fuente | Umbral | Responsable | Frecuencia | Acción si incumple | Línea base |
|---|---|---|---|---|---|---|---|---|
| **M1** | Pruebas en verde | pasan ÷ (total − omitidas) | `php tests/ejecutar.php` | **100 %**, omitidas ≤ 2 (más → ÁMBAR) | RM | Cada PR + semanal | Bloquear el merge; corregir antes de cualquier otro trabajo | **VERDE** · 100 % (85/85), 2 omitidas |
| **M2** | Cobertura de códigos de error | códigos que el dominio o la aplicación lanzan (`ErrorDominio`) y que alguna prueba afirma ÷ total | `src/Dominio` + `src/Aplicacion` cruzado con `tests/**` | **100 %** | RM | Semanal + cada PR que toque reglas | Agregar la prueba que afirme el código antes de cerrar la semana | **VERDE** · 100 % (13/13) |
| **M3** | Trazabilidad | escenarios en los que la bitácora queda bien ÷ escenarios (5): registro y edición con 1 evento completo; escrituras rechazadas sin evento; búsqueda sin el criterio en claro; consulta con `acceso_limitado` | Kernel real con bitácora en memoria; en producción, CD-04 | **100 %** | RM; en producción, RS | Semanal | Hallazgo **Crítico** (R-09): corregir y agregar el escenario a la suite | **VERDE** · 100 % (5/5) |
| **M4** | Integridad de la identidad | intentos de duplicar a una persona que terminan con **una** sola ficha ÷ intentos (8): mismo DPI, DPI con espacios, otro hospital, reintento con clave, homónima sin documento, nombre en mayúsculas sin tildes, DPI mal digitado, editar con un DPI ajeno | Kernel real | **100 %** | RM | Semanal + cada PR que toque `PoliticaDuplicados` o `Dpi` | Hallazgo **Mayor** (R-02, R-03): corregir con prueba | **VERDE** · 100 % (8/8) |
| **M5** | Confidencialidad y tenant | respuestas sin datos que el actor no debe ver ÷ respuestas sondeadas (8): 5 sobre una protegida a admisión y clínico, 3 de otro hospital | Kernel real | **100 %** | RM; RS revisa | Semanal + cada PR que toque búsqueda, errores o duplicados | Hallazgo **Crítico**: bloquear la liberación | **ROJO** · 75 % (6/8): el 409 a otro hospital revela P-000001 y `0101` (AR-02, AR-03) |
| **M6** | Confiabilidad ante fallas | escenarios con falla inyectada que terminan en 503 sin ficha, evento ni clave a medias ÷ escenarios (6) | Dobles de falla del Micro-HIS | **100 %** | RM | Semanal + cada PR que toque casos de uso o persistencia | Hallazgo **Crítico** (R-09): bloquear y corregir | **VERDE** · 100 % (6/6) |
| **M7** | Hallazgos de revisión abiertos | hallazgos Crítico/Mayor del registro de la semana 13 con estado distinto de Verificado o Rechazado | `semana-13/plantillas/registro-hallazgos.csv` | **0 Crítico** siempre; **0 Mayor** para liberar | RSQA | Semanal | Crítico: detener la liberación. Mayor: plan con fecha; no se libera hasta verificar. | **ROJO** · 4 (Crítico 1: AR-02 · Mayor 3: AR-01, AR-03, AR-04) |

**Línea base (S14): 5 VERDE · 0 ÁMBAR · 2 ROJO.** Salida completa en [evidencia/linea-base-sqa.txt](evidencia/linea-base-sqa.txt).

### 3.1 Las métricas detectan defectos: comprobado

En copias aparte del Micro-HIS (`git archive`, sin tocar el repositorio) se inyectaron tres defectos y se midió cada copia con `SQA_MICROHIS=<copia>` ([evidencia](evidencia/deteccion-mutantes.txt)):

| Mutante | Qué rompe | Métricas que se ponen en ROJO (además de la línea base) |
|---|---|---|
| 1 · `PoliticaDuplicados::visibles()` deja de reservar el código de un protegido | R-11, RF-16 | **M1** (83/85) y **M5**: aparece una fuga nueva, «admisión la registra otra vez (409) → P-000002» |
| 2 · `RegistrarPaciente` guarda la ficha **fuera** de la transacción | R-09 | **M1** (83/85) y **M6** (4/6): la bitácora caída deja la ficha; la clave de idempotencia se pierde |
| 3 · El evento `PACIENTE_ACTUALIZADO` pierde el «antes» | R-09 | **M1** (80/85) y **M3** (4/5) |

Cada defecto lo detectan **al menos dos** métricas independientes. Esto se vuelve un control (CD-07): si un mutante «sobrevive», falta una prueba.

## 4. Controles preventivos

Evitan que el defecto llegue al código.

| ID | Control | Responsable | Umbral | Fuente | Acción si se incumple | Frecuencia | Riesgos |
|---|---|---|---|---|---|---|---|
| CP-01 | **Pruebas antes del código** en todo cambio de reglas o casos de uso, con evidencia rojo → verde en el PR | RM | 100 % de los PR que tocan `src/Dominio` o `src/Aplicacion` | PR + `evidencia/*rojo*.txt` (como en las semanas 6 y 12) | El RSQA devuelve el PR | Cada PR | RS-04, RS-06 |
| CP-02 | **Guardas automáticas** de capas y contrato dentro de la suite (ARQ-01..08, API-01..03) | RM | 11/11 en verde | `php tests/ejecutar.php` | Bloquear el merge | Cada PR | RS-09 |
| CP-03 | **Revisión técnica formal** (semana 13) antes de liberar cambios de dominio, contrato o seguridad | MR | E-1..E-8 cumplidos; acta con S-1..S-5 | `semana-13/herramientas/criterios-entrada.php`, acta | Sin acta firmada no se libera | Por liberación | RS-01, RS-02, RS-06 |
| CP-04 | **Reglas en un solo lugar y fail-closed**: toda regla de la ficha va en `RevisionDeFicha`; las capacidades valen «nada» por defecto; la vista reducida es la opción por defecto | RM | M1 = 100 %; REF-01/02 en verde | Revisión RT-02, RT-09, RT-12; `bin/acoplamiento.php` | Hallazgo Crítico | Cada PR que toque esas clases | RS-01, RS-05..RS-08, RS-12 |
| CP-05 | **Configuración por entorno sin secretos**: credenciales y claves solo en variables de entorno; tokens de demostración **solo** con `MICROHIS_ENTORNO=desarrollo` | RM | 0 credenciales en el repositorio; 0 tokens de demostración fuera de desarrollo | Revisión RT-11; `verificar-autorrevision.php` (AR-01) | Bloquear el despliegue | Por liberación | RS-03 · **pendiente de implementar (S15)** |
| CP-06 | **Plantilla de PR** con la validación ejecutada y los requisitos documentados (README) | RM | 100 % de los PR con «Validación ejecutada» llena con salidas reales | `.github/pull_request_template.md` | El RSQA devuelve el PR | Cada PR | RS-11 |
| CP-07 | **Contrato primero**: todo cambio de API actualiza `openapi.yaml` y su versión en el mismo PR | RM | Lint válido | `npx @redocly/cli@1.34.3 lint` | Bloquear el merge | Cada PR que toque `api/` | RS-09 |

## 5. Controles detectivos

Encuentran el defecto que llegó.

| ID | Control | Responsable | Umbral | Fuente | Acción si se incumple | Frecuencia | Riesgos |
|---|---|---|---|---|---|---|---|
| CD-01 | **Suite completa** | RM | M1: 100 %, omitidas ≤ 2 | `php tests/ejecutar.php` | Bloquear el merge; issue si es intermitente | Cada PR + antes de liberar | RS-05..RS-10 |
| CD-02 | **Medición SQA** y tablero | RM corre · RSQA revisa | 0 ROJO para liberar | `php herramientas/medir-sqa.php` + [plantillas/tablero-seguimiento.csv](plantillas/tablero-seguimiento.csv) | Una no conformidad por cada ROJO (§6) | Semanal (lunes) | Todos |
| CD-03 | **Lint del contrato + paridad** prototipo ↔ kernel | RM | Lint válido; paridad 37/37 | Redocly; `semana-12/herramientas/paridad.cjs` | Bloquear el merge | Cada PR de contrato o prototipo | RS-09 |
| CD-04 | **Revisión de la bitácora**: accesos limitados repetidos, búsquedas por documento, cambios de identidad sin motivo, coincidencias confirmadas, fichas sin evento, cambios de protección | RS (y supervisión, consulta 4) | Consultas 3 y 5 = 0 filas; consultas 1 y 2 según su umbral; consultas 4 y 6, revisión del 100 % | [herramientas/revision-bitacora.sql](herramientas/revision-bitacora.sql) (PostgreSQL; **sin ejecutar aún**) | Incidente al RM y a supervisión | Semanal (cuando haya PostgreSQL, S15) | RS-02, RS-04, RS-05, RS-11, RS-12 |
| CD-05 | **Pruebas de mutación** del Micro-HIS | RM | 26/26 detectadas | `php tests/mutaciones.php` | Agregar la prueba que falta | Cada PR que toque reglas | RS-06, RS-08 |
| CD-06 | **Recorrido automático** del prototipo (escritorio + móvil, axe) | RM | 0 fallas (36/36) | `semana-12/herramientas/recorrido.cjs` | Bloquear el merge del prototipo | Cada PR del prototipo | RS-02 |
| CD-07 | **Inyección de defectos SQA** (mutantes 1 a 3) | RSQA | Cada mutante pone ≥ 1 métrica en ROJO además de M1 | `SQA_MICROHIS=<copia mutada> php herramientas/medir-sqa.php` | Si un mutante sobrevive: agregar el escenario que falta | Por iteración | RS-01, RS-05 |

## 6. No conformidades y escalamiento

| Situación | Quién la abre | Plazo | Escalamiento |
|---|---|---|---|
| Métrica en **ROJO** | RSQA, al revisar el tablero | Acción con fecha en ≤ 2 días hábiles | Si sigue roja en la medición siguiente: al Integrador, y se congela la liberación |
| Hallazgo **Crítico** (de revisión, M3, M5 o M6) | Quien lo detecte | Corrección inmediata; nada se libera | Al Integrador el mismo día |
| Hallazgo **Mayor** | MR o RSQA | Plan con fecha; verificación antes de liberar | Si vence el plazo, pasa a bloqueante |
| Incidente de bitácora (CD-04) | RS | Análisis en ≤ 1 día | Supervisión de admisión + RM |

Cada no conformidad se registra con el [formato de hallazgos de la semana 13](../semana-13/formato-hallazgos.md), con prefijo `NC-`, para tener un solo registro y las mismas severidades.

## 7. Plan de acción de la línea base

| Hallazgo de la línea base | Acción | Responsable | Plazo | Cómo se verifica |
|---|---|---|---|---|
| M5 ROJO + M7: AR-02 (crítico) | El 409 por documento de otro hospital no devuelve su código ni su documento; prueba de caja negra por tenant | RM | S15 | M5 sube; `verificar-autorrevision.php` sin `P-000001` |
| M5 ROJO + M7: AR-03 | Candidatos de `POSIBLE_DUPLICADO` solo del hospital activo; prueba | RM | S15 | M5 = 100 % |
| M7: AR-01 | Autenticador por entorno (CP-05) + prueba de que fuera de desarrollo responde 401 | RM | S15 | `verificar-autorrevision.php` → 401 |
| M7: AR-04 | Decisión de negocio; mientras tanto, CD-04 consulta 2 | RM + supervisión | S15 | Decisión documentada o prueba nueva |
| RS-10 (omitidas) | Base PostgreSQL de pruebas; ejecutar CON-PDO e INT-00 | RM | S15 | M1 con 0 omitidas |
| Roles por confirmar | Nombrar RSQA, RS y MR | Grupo | Antes de la S15 | Nombres en este plan (§2) |

Las correcciones de AR-01..AR-04 tocan el paquete congelado de la revisión de la semana 13. Por eso se hacen en el **retrabajo** de esa revisión, después de la reunión, y no antes.

## 8. Calendario de seguimiento

### 8.1 Cadencias

| Cadencia | Qué se ejecuta | Quién | Registro |
|---|---|---|---|
| **Cada PR** | CP-01, CP-02, CP-06, CP-07; CD-01, CD-05; CD-03 si toca contrato o prototipo; CD-06 si toca prototipo | RM; el RSQA revisa | Sección «Validación ejecutada» del PR |
| **Semanal** (lunes) | CD-02 (`medir-sqa.php`) → fila nueva en el tablero; CD-04 cuando haya PostgreSQL | RM corre; RSQA revisa en 15 min; RS revisa la bitácora | [plantillas/tablero-seguimiento.csv](plantillas/tablero-seguimiento.csv) |
| **Por iteración** (cierre de cada semana del curso) | CD-07 (mutantes SQA); revisión de la matriz de riesgos (reestimar P con la evidencia nueva) | RSQA + RM | `matriz-riesgos.md` (historial en git) |
| **Por liberación** | CP-03 (revisión formal), CP-05; 0 ROJO en el tablero | MR, RSQA, INT | Acta + tablero |

### 8.2 Hitos

| Semana | Hito | Criterio de cumplimiento |
|---|---|---|
| **S14** (esta) | Plan SQA aprobado y **línea base** medida | `linea-base-sqa.txt`: 5 VERDE · 0 ÁMBAR · 2 ROJO; 3 mutantes detectados |
| **S15** | Retrabajo de la revisión de la semana 13 + pruebas de caja negra | M5 = 100 %; M7: 0 Crítico; CP-05 implementado; defectos registrados con prueba |
| **S16** | Candidata a liberación del módulo | Tablero con **0 ROJO** dos semanas seguidas; riesgos Altos = 0 o aceptados por negocio (RS-02); acta de cierre |

## 9. Herramientas y evidencia

| Archivo | Qué hace |
|---|---|
| [herramientas/medir-sqa.php](herramientas/medir-sqa.php) | Mide M1–M7; con `SQA_MICROHIS` mide otra copia (mutantes) |
| [herramientas/revision-bitacora.sql](herramientas/revision-bitacora.sql) | Las 6 consultas de CD-04 |
| [plantillas/tablero-seguimiento.csv](plantillas/tablero-seguimiento.csv) | Una fila por semana y métrica; la primera es la línea base |
| [evidencia/linea-base-sqa.txt](evidencia/linea-base-sqa.txt) | Medición de la semana 14 |
| [evidencia/deteccion-mutantes.txt](evidencia/deteccion-mutantes.txt) | Prueba de que las métricas detectan defectos |
| [matriz-riesgos.md](matriz-riesgos.md) | 12 riesgos con P, I, nivel, controles, responsable y tratamiento |
