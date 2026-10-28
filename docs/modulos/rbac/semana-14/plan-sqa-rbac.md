# Plan de aseguramiento de la calidad (SQA) — RBAC

**Módulo:** RBAC: roles, permisos y protección de rutas · **Responsable del módulo:** Boris Alexander Quiroa Orellana

**Proceso:** «asignación de rol, autorización de una operación y denegación trazable»

Estructura inspirada en IEEE 730 (plan SQA): propósito, organización, métricas, controles, riesgos, no conformidades y calendario.

## 1. Propósito y alcance

Asegurar que el módulo RBAC cumpla, **de forma medible y continua**, cuatro atributos de calidad:

| Atributo | Qué significa para RBAC | Métricas |
|---|---|---|
| **Calidad del flujo** (corrección) | Asignar, autorizar y denegar hacen lo que dicen R-01..R-08 y RF-01..RF-15 | M1, M2 |
| **Confiabilidad** | Ante fallas (base de datos, bitácora, una regla que lanza) el sistema **nunca** permite por error ni deja cambios a medias, y responde a tiempo | M5, M7 |
| **Seguridad** | Las rutas protegidas, el tenant y la identidad se respetan; el contrato no promete ni oculta comportamiento | M3, M6 |
| **Trazabilidad** | Todo intento denegado deja evidencia completa; las consultas pasivas no la contaminan | M4 |

**Alcance.** El Micro-HIS RBAC (`micro-his-rbac/`) y su contrato OpenAPI.

**Fuera de alcance:**
- el prototipo de interfaz, que tiene sus propios controles (recorrido y axe, S11–S12) y aquí solo aparece como control CD-06;
- el resto del HIS.

## 2. Organización y responsables

| Rol | Persona | Responsabilidades SQA |
|---|---|---|
| **Responsable del módulo** (RM) | Boris Alexander Quiroa Orellana | Mantiene el código, las pruebas y el contrato. Corre la medición semanal. Ejecuta las acciones correctivas. Dueño de M1–M5 y M7. |
| **Responsable SQA del grupo** (RSQA) | Compañero/a del grupo *(por confirmar)* | **Independiente** del autor. Revisa el tablero semanal, abre y cierra no conformidades, y decide si un rojo bloquea la liberación. Dueño de M6. |
| **Revisor de seguridad** (RS) | Compañero/a del grupo *(por confirmar)*, R1 de la revisión S13 | Revisa los cambios en `Rutas`, `Kernel`, autenticación y tenant. Ejecuta CD-04 (bitácora). |
| **Moderador de revisiones** (MR) | Compañero/a del grupo *(por confirmar)* | Conduce las revisiones formales (CP-03) y verifica el retrabajo. |
| **Integrador del HIS** (INT) | Docente / responsable de `develop` | Aprueba el merge a `develop` cuando no hay métricas rojas bloqueantes. |

**Regla de independencia.** Quien corrige un hallazgo (RM) no puede ser quien lo da por verificado (RSQA o MR).

## 3. Métricas (7)

La medición es automática con [herramientas/medir-sqa.php](herramientas/medir-sqa.php). Lee las fuentes reales: suite, código, contrato, bitácora en memoria y registro de hallazgos de la S13.

**Estados:**

| Estado | Significa |
|---|---|
| **VERDE** | Cumple el umbral |
| **ÁMBAR** | Cumple, pero con una condición pendiente; no bloquea |
| **ROJO** | Incumple: se abre una no conformidad (§6) |

| ID | Métrica | Definición / fórmula | Fuente | Umbral | Responsable | Frecuencia | Acción si incumple | Línea base |
|---|---|---|---|---|---|---|---|---|
| **M1** | Pruebas en verde | pasan ÷ (total − omitidas) | `php tests/ejecutar.php` | **100 %**. Omitidas ≤ 2 (más de 2 → ÁMBAR). | RM | Cada PR + semanal | Bloquear el merge. Corregir antes de cualquier otro trabajo. | **VERDE** · 100 % (78/78), 2 omitidas |
| **M2** | Cobertura de códigos de decisión | códigos que el dominio o la aplicación devuelven y que alguna prueba afirma ÷ total de códigos | Código (`src/Dominio`, `src/Aplicacion`) cruzado con `tests/**` | **100 %** | RM | Semanal + cada PR que toque reglas | Agregar la prueba que afirme el código, antes de cerrar la semana | **ROJO** · 88.9 % (16/18): faltan `USUARIO_INACTIVO` y `USUARIO_NO_ENCONTRADO` |
| **M3** | Conformidad contrato ↔ código | operaciones del código con la misma ruta, método y `x-permiso` en OpenAPI ÷ operaciones. Además, 0 operaciones solo en el contrato. | `Rutas` + `api/openapi.yaml` (+ API-01..03 y lint) | **100 %** y lint válido | RM | Cada PR que toque `Rutas` o `api/` | Bloquear el merge. Actualizar el contrato y su versión en el mismo PR. | **VERDE** · 100 % (7/7) |
| **M4** | Trazabilidad de denegaciones | intentos denegados con `ACCESO_DENEGADO` completo (8 campos) ÷ intentos (6 escenarios); además, eventos generados por consultas pasivas | Kernel real con bitácora en memoria; en producción, CD-04 | **100 %** y **0** eventos por consultas pasivas | RM; en producción, RS | Semanal | Hallazgo **Crítico** (R-06): corregir y agregar el escenario a la suite | **VERDE** · 100 % (6/6), ruido 0 |
| **M5** | Confiabilidad fail-closed | escenarios con falla inyectada que terminan sin permitir y sin cambios a medias ÷ escenarios (7: lectura caída ×2, bitácora caída, sin reglas, regla que lanza, escritura caída, bitácora caída al asignar) | Dobles de falla del Micro-HIS | **100 %** | RM | Semanal + cada PR que toque `Aplicacion` o `Dominio` | Hallazgo **Crítico** (R-05, R-07): bloquear y corregir | **VERDE** · 100 % (7/7) |
| **M6** | Hallazgos de revisión Crítico/Mayor sin verificar | conteo en el registro de hallazgos con estado ≠ Verificado y ≠ Rechazado | `semana-13/plantillas/registro-hallazgos.csv` | **0 Crítico** siempre; **0 Mayor** para liberar | RSQA | Semanal | Crítico: detener la liberación. Mayor: plan con fecha, y no se libera hasta verificar. | **ROJO** · 3 Mayor (AR-01, AR-02, AR-04), 0 Crítico |
| **M7** | Tiempo de decisión p95 (RNF-09) | percentil 95 de `AutorizarOperacion` sobre 3000 decisiones | En memoria hoy; con PDO + PostgreSQL desde la S15 | **< 50 ms p95 con PostgreSQL** (en memoria → ÁMBAR) | RM | Semanal; por iteración con PostgreSQL | Mayor que 50 ms: perfilar las consultas e índices de `membresias` y revisar la caché del cliente | **ÁMBAR** · 0.006 ms en memoria |

**Línea base (S14): 4 VERDE · 1 ÁMBAR · 2 ROJO.** Salida completa en [evidencia/linea-base-sqa.txt](evidencia/linea-base-sqa.txt).

### 3.1 Las métricas detectan defectos: comprobado

En una copia aparte del Micro-HIS (`git archive`, sin tocar el repositorio) se inyectaron dos defectos y se midió esa copia con `SQA_MICROHIS=<copia>` ([evidencia](evidencia/deteccion-mutantes.txt)):

| Mutante | Qué rompe | Métricas que se ponen en ROJO |
|---|---|---|
| 1 · Fail-open en `PoliticaAutorizacion` (una regla que lanza deja pasar) | R-05 | **M1** (77/78) y **M5** («regla que lanza excepción») |
| 2 · `AutorizarOperacion` deja de registrar `ACCESO_DENEGADO` | R-06 | **M4** (0/6 intentos con evento), **M1** (64/78) y **M5** (sin evento en el respaldo) |

Ambos defectos quedan detectados por **al menos dos** métricas independientes. Esto se vuelve un control (CD-07): si un mutante «sobrevive», falta una prueba.

## 4. Controles preventivos

Evitan que el defecto llegue al código.

| ID | Control | Responsable | Umbral | Fuente | Acción si se incumple | Frecuencia | Riesgos |
|---|---|---|---|---|---|---|---|
| CP-01 | **Pruebas antes del código** en todo cambio de reglas, rutas o casos de uso, con evidencia rojo → verde en el PR | RM | 100 % de los PR que tocan `src/Dominio`, `src/Aplicacion` o `Rutas` | PR + `evidencia/*rojo*.txt` (como en S6 y S12) | El RSQA devuelve el PR | Cada PR | RS-01, RS-05, RS-10 |
| CP-02 | **Guardas automáticas** de capas y contrato dentro de la suite (ARQ-01..08, API-01..03) | RM | 11/11 en verde | `php tests/ejecutar.php` | Bloquear el merge | Cada PR | RS-01, RS-02, RS-06, RS-07 |
| CP-03 | **Revisión técnica formal** (S13) antes de liberar cambios de dominio, contrato o seguridad | MR | E-1..E-8 cumplidos; acta con S-1..S-5 | `semana-13/herramientas/criterios-entrada.php`, acta | Sin acta firmada no se libera | Por liberación | RS-05, RS-11 |
| CP-04 | **Fail-closed por diseño**: todo `catch` en el camino de autorización deniega; todo cambio de membresía va con su evento en una transacción | RM | M5 = 100 % | `medir-sqa.php` (M5), revisión RT-02 y RT-04 | Hallazgo Crítico | Cada PR que toque esas clases | RS-01, RS-02, RS-04 |
| CP-05 | **Configuración por entorno sin secretos**: credenciales solo en variables de entorno; tokens de demostración **solo** con `MICROHIS_ENTORNO=desarrollo` | RM | 0 credenciales en el repositorio; 0 tokens de demostración fuera de desarrollo | Revisión RT-15 y RT-10; `verificar-autorrevision.php` (AR-01) | Bloquear el despliegue | Por liberación | RS-03 · **pendiente de implementar (S15)** |
| CP-06 | **Plantilla de PR** con validación ejecutada y requisitos documentados (README) | RM | 100 % de los PR con la sección «Validación ejecutada» llena con salidas reales | `.github/pull_request_template.md` | El RSQA devuelve el PR | Cada PR | RS-12 |
| CP-07 | **Contrato primero**: todo cambio de API actualiza `openapi.yaml` y su versión en el mismo PR | RM | Lint válido; M3 = 100 % | `npx @redocly/cli@1.34.3 lint`, M3 | Bloquear el merge | Cada PR que toque `api/` o `Rutas` | RS-06 |

## 5. Controles detectivos

Encuentran el defecto que llegó.

| ID | Control | Responsable | Umbral | Fuente | Acción si se incumple | Frecuencia | Riesgos |
|---|---|---|---|---|---|---|---|
| CD-01 | **Suite completa** | RM | M1: 100 %, omitidas ≤ 2 | `php tests/ejecutar.php` | Bloquear el merge; issue si es intermitente | Cada PR + antes de liberar | RS-01, RS-02, RS-06, RS-07, RS-09 |
| CD-02 | **Medición SQA** y tablero | RM corre · RSQA revisa | 0 ROJO para liberar | `php herramientas/medir-sqa.php` (+ [plantillas/tablero-seguimiento.csv](plantillas/tablero-seguimiento.csv)) | No conformidad por cada ROJO (§6) | Semanal (lunes) | Todos |
| CD-03 | **Lint del contrato + paridad** API simulada ↔ kernel | RM | Lint válido; paridad 38/38 | Redocly; `semana-12/herramientas/paridad.cjs` | Bloquear el merge | Cada PR de contrato o prototipo | RS-06 |
| CD-04 | **Revisión de la bitácora**: denegaciones por motivo, `TENANT_NO_COINCIDE` repetidos, intentos tras revocar, eventos incompletos, conciliación de cambios de rol | RS | Consulta 4 = 0 filas; consulta 2: ≥ 3 por actor y día → incidente; consulta 3: > 5 en 24 h → avisar | [herramientas/revision-bitacora.sql](herramientas/revision-bitacora.sql) (PostgreSQL; **sin ejecutar aún**) | Incidente de seguridad al RM y al administrador del hospital | Semanal (cuando haya PostgreSQL, S15) | RS-02, RS-04, RS-05, RS-11 |
| CD-05 | **Alerta de falla doble de la bitácora** | RM | 0 eventos perdidos | `error_log` del servidor | Incidente Crítico | Continuo | RS-04 · **pendiente de implementar (S15)** |
| CD-06 | **Recorrido automático** de la interfaz (escritorio + móvil, axe, bitácora) | RM | 0 fallas (42/42) | `semana-12/herramientas/recorrido.cjs` | Bloquear el merge del prototipo | Cada PR del prototipo | RS-07 |
| CD-07 | **Inyección de defectos** (mutantes 1 y 2) | RSQA | Cada mutante pone ≥ 1 métrica en ROJO | `SQA_MICROHIS=<copia mutada> php herramientas/medir-sqa.php` | Si un mutante sobrevive: agregar la prueba que falta | Por iteración | RS-01, RS-10 |

## 6. No conformidades y escalamiento

| Situación | Quién la abre | Plazo | Escalamiento |
|---|---|---|---|
| Métrica en **ROJO** | RSQA, al revisar el tablero | Acción con fecha en ≤ 2 días hábiles | Si sigue roja en la medición siguiente: al Integrador; se congela la liberación |
| Hallazgo **Crítico** (de revisión, M4 o M5) | Quien lo detecte | Corrección inmediata; nada se libera | Al Integrador el mismo día |
| Hallazgo **Mayor** | MR o RSQA | Plan con fecha; verificación antes de liberar | Si vence el plazo: pasa a bloqueante |
| Incidente de bitácora (CD-04) | RS | Análisis en ≤ 1 día | Administrador del hospital + RM |

Cada no conformidad se registra con el [formato de hallazgos de la S13](../semana-13/formato-hallazgos.md), con prefijo `NC-`, para usar un solo registro y las mismas severidades.

## 7. Plan de acción de la línea base

| Hallazgo de la línea base | Acción | Responsable | Plazo | Cómo se verifica |
|---|---|---|---|---|
| M2 ROJO (88.9 %) | Afirmar `USUARIO_INACTIVO` y `USUARIO_NO_ENCONTRADO` en las pruebas de asignación | RM | S15 | M2 = 100 % |
| M6 ROJO: AR-01 | Autenticador por entorno (CP-05) + prueba de que sin entorno de desarrollo responde 401 | RM | S15 | M6 baja; `verificar-autorrevision.php` → 401 |
| M6 ROJO: AR-02 | Regla de solapamiento de periodos (o `Idempotency-Key`) + prueba | RM | S15 | M6; AR-02 → «201 y 409» |
| M6 ROJO: AR-04 | `error_log` en la falla doble + comprobar el respaldo al arrancar (CD-05) + prueba | RM | S15 | M6; prueba nueva PER-11 |
| M7 ÁMBAR | Medir con PDO + PostgreSQL | RM | S15 | M7 VERDE con < 50 ms |
| RS-09 (omitidas) | Base PostgreSQL de pruebas; ejecutar CON-PDO e INT-00..05 | RM | S15 | M1 con 0 omitidas |
| Roles por confirmar | Nombrar RSQA, RS y MR | Grupo | Antes de la S15 | Nombres en este plan §2 |

Las correcciones de AR-01, AR-02 y AR-04 tocan el paquete congelado de la revisión S13. Por eso se hacen en el **retrabajo** de esa revisión, después de la reunión, y no antes.

## 8. Calendario de seguimiento

### 8.1 Cadencias

| Cadencia | Qué se ejecuta | Quién | Registro |
|---|---|---|---|
| **Cada PR** | CD-01, CD-03 (si toca contrato o prototipo), CD-06 (si toca prototipo); CP-01, CP-02, CP-06, CP-07 | RM; el RSQA revisa | Sección «Validación ejecutada» del PR |
| **Semanal** (lunes) | CD-02 (`medir-sqa.php`) → fila nueva en el tablero; CD-04 cuando haya PostgreSQL | RM corre; RSQA revisa en 15 min; RS revisa la bitácora | [plantillas/tablero-seguimiento.csv](plantillas/tablero-seguimiento.csv) |
| **Por iteración** (cierre de cada semana del curso) | CD-07 (mutantes); revisión de la matriz de riesgos (re-estimar P con la evidencia nueva) | RSQA + RM | `matriz-riesgos.md` (historial en git) |
| **Por liberación** | CP-03 (revisión formal), CP-05; 0 ROJO en el tablero | MR, RSQA, INT | Acta + tablero |

### 8.2 Hitos

| Semana | Hito | Criterio de cumplimiento |
|---|---|---|
| **S14** (esta) | Plan SQA aprobado y **línea base** medida | `linea-base-sqa.txt`: 4 VERDE · 1 ÁMBAR · 2 ROJO; mutantes detectados |
| **S15** | Retrabajo de la revisión S13 + PostgreSQL | M2 = 100 %; M6: 0 Mayor; M7 medido con PostgreSQL; INT/CON-PDO ejecutadas; CP-05 y CD-05 implementados |
| **S16** | Candidata a liberación del módulo | Tablero con **0 ROJO** dos semanas seguidas; riesgos Altos ≤ 1 (RS-08, aceptado); acta de cierre |

## 9. Herramientas y evidencia

| Archivo | Qué hace |
|---|---|
| [herramientas/medir-sqa.php](herramientas/medir-sqa.php) | Mide M1–M7. Con `SQA_MICROHIS` mide otra copia (mutantes). |
| [herramientas/revision-bitacora.sql](herramientas/revision-bitacora.sql) | Consultas de CD-04 |
| [plantillas/tablero-seguimiento.csv](plantillas/tablero-seguimiento.csv) | Una fila por semana y métrica; la primera es la línea base |
| [evidencia/linea-base-sqa.txt](evidencia/linea-base-sqa.txt) | Medición de la S14 |
| [evidencia/deteccion-mutantes.txt](evidencia/deteccion-mutantes.txt) | Prueba de que las métricas detectan defectos |
| [matriz-riesgos.md](matriz-riesgos.md) | 12 riesgos con P, I, nivel, controles, responsable y tratamiento |
