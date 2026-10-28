# Autorrevisión del autor (criterio de entrada E-6)

**Autor:** Boris Alexander Quiroa Orellana · **Paquete:** `micro-his-pacientes` en el commit indicado por [criterios-entrada-2-despues.txt](evidencia/criterios-entrada-2-despues.txt) · **Checklist:** [RT-01..RT-15](checklist-revision.md)

## Qué es y qué no es

Antes de convocar la reunión, el autor aplica el mismo checklist a su propio trabajo y registra lo que encuentra con el mismo formato. Hay dos razones:

1. No se gasta el tiempo de los revisores en lo que el autor podía ver solo.
2. Sirve como **prueba del formato y del checklist** antes de usarlos con el equipo.

**No reemplaza la revisión:** el autor tiene puntos ciegos sobre su propio diseño. Todos estos hallazgos llegan a la reunión **abiertos**, para que el equipo confirme o ajuste la severidad. Los revisores buscan, además, lo que aquí no aparece.

## Resultado por ítem

| Ítem | Resultado | Hallazgos |
|---|:-:|---|
| RT-01 Datos e identidad válidos | **No** | AR-07 |
| RT-02 Un solo dueño del orden de las reglas | Sí | — |
| RT-03 Duplicados | Sí, con riesgo | AR-07 (un DPI mal digitado esquiva R-02) |
| RT-04 Edición segura | Sí | — |
| RT-05 Integridad y trazabilidad | Sí | — (INT-00 sin ejecutar: falta PostgreSQL) |
| RT-06 Contrato = código | Sí | — |
| RT-07 Errores sin filtrar información | **No** | AR-02 |
| RT-08 Validación en la frontera | Sí | — |
| RT-09 Permiso por operación, fail-closed | Sí | — |
| RT-10 Tenant | **No** | AR-02, AR-03 |
| RT-11 Identidad del actor | **No** | AR-01 |
| RT-12 Confidencialidad de protegidos | Sí, con riesgo | AR-04 |
| RT-13 Documento protegido | Sí | — |
| RT-14 Dependencias entre capas | Sí | — |
| RT-15 Solo dependencias necesarias | **No** | AR-05, AR-06 |

**Total:** 7 hallazgos en 3106 líneas efectivas.

| Severidad | Cantidad | IDs |
|---|---:|---|
| Crítico | 1 | AR-02 |
| Mayor | 3 | AR-01, AR-03, AR-04 |
| Menor | 3 | AR-05, AR-06, AR-07 |
| Observación | 0 | — |

- **Densidad:** 7 / 3.106 KLOC = **2.25 por KLOC** (Crítico + Mayor + Menor). Solo Crítico + Mayor: **1.29 por KLOC**.
- **Lo que significa para la reunión:** con un hallazgo crítico abierto, la regla del [plan §7](plan-revision-tecnica.md#7-criterios-de-salida-y-decisión) llevaría a **re-inspección** si el equipo lo confirma.

Registro en CSV: [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv). Los 7 hallazgos se **reproducen** contra el kernel real con [herramientas/verificar-autorrevision.php](herramientas/verificar-autorrevision.php) ([salida](evidencia/autorrevision-verificaciones.txt)).

## Hallazgos

### AR-01 · La autenticación de demostración no se puede desactivar · Mayor · Riesgo

| | |
|---|---|
| Ítem | RT-11 |
| Ubicación | `bootstrap/contenedor.php:94-97` (`crearKernel`) · `config/config.php` (`tokens_demo`) |
| Descripción | El punto de entrada real siempre autentica con `AutenticadorTokenDemo` y 4 tokens estáticos. Ninguna configuración lo impide fuera de desarrollo: si el servicio se despliega así, cualquiera que conozca un token de ejemplo actúa como supervisión. |
| Evidencia | `crearKernel() + «Bearer token-supervisor-oriente» → HTTP 200 · tokens demo configurados: 4 · variable de entorno que lo impida: ninguna` |
| Regla afectada | R-05, OWASP API2 |
| Propuesta | Elegir el autenticador por configuración. Sin `MICROHIS_ENTORNO=desarrollo`, arrancar **sin** tokens de demostración (fail-closed). En el HIS, el autenticador real lo provee el módulo de autenticación. |
| Responsable · Estado | Autor · Abierto |

### AR-02 · El 409 por documento revela un paciente de otro hospital · Crítico · Defecto

| | |
|---|---|
| Ítem | RT-07, RT-10 |
| Ubicación | `src/Dominio/Servicios/PoliticaDuplicados.php:29-37` · `src/Persistencia/EnMemoria/IndiceDuplicadosEnMemoria.php:17-26` · `src/Persistencia/Pdo/PdoIndiceDuplicados.php:17` |
| Descripción | Que el índice de duplicados busque en **toda la red** es intencional: R-02 bloquea el mismo documento en cualquier hospital. Pero el 409 `PACIENTE_EXISTENTE` devuelve el **código local** y el documento enmascarado de un paciente de **otro** hospital. Ese código no sirve en el hospital activo (da 404) y revela que el paciente existe, y los últimos dígitos de su documento, a quien no tiene acceso a ese hospital. Si el paciente es protegido, `visibles()` decide con el permiso del hospital **activo**, no con el del hospital del paciente. |
| Evidencia | `admisión de hosp-NORTE registra el DPI de una paciente de hosp-ORIENTE → HTTP 409 {"error":"PACIENTE_EXISTENTE","codigo":"P-000001","identificador":"*********0101"}` · `la misma admisión abre la ficha P-000001 → HTTP 404` |
| Regla afectada | R-01, D-20 (mismo 404 para «no existe» y «es de otro hospital»: el 409 lo contradice) |
| Propuesta | Si el titular es de otro hospital: 409 con un código neutro (por ejemplo `EN_OTRO_HOSPITAL`), sin documento, y la conciliación queda en el MPI central. |
| Responsable · Estado | Autor · Abierto |

### AR-03 · Los candidatos de una coincidencia incluyen otros hospitales · Mayor · Defecto

| | |
|---|---|
| Ítem | RT-10 |
| Ubicación | `src/Dominio/Servicios/PoliticaDuplicados.php:40-45` · `IndiceDuplicados*::coincidenciasProbables` |
| Descripción | El 409 `POSIBLE_DUPLICADO` lista códigos de pacientes de otros hospitales. Admisión no puede abrirlos ni compararlos, que es justo lo que pide la pantalla W3 (semana 8). Además revela que existe un homónimo en otro hospital. |
| Evidencia | `admisión de hosp-NORTE registra a una homónima sin documento → HTTP 409 {"error":"POSIBLE_DUPLICADO","candidatos":["P-000001"]}`, y P-000001 es de hosp-ORIENTE |
| Regla afectada | R-01, R-03, D-20 |
| Propuesta | Limitar los candidatos al hospital activo, o devolver aparte solo el **número** de coincidencias en otros hospitales. |
| Responsable · Estado | Autor · Abierto |

### AR-04 · Con el DPI se confirma que una paciente protegida está en el hospital · Mayor · Riesgo

| | |
|---|---|
| Ítem | RT-12 |
| Ubicación | `src/Dominio/Modelo/CriterioBusqueda.php` (búsqueda por documento) · `Paciente::resumen()` |
| Descripción | RF-13 permite, a propósito, encontrar a una paciente protegida por código o documento, con la vista reducida, para poder atenderla. El efecto secundario no se analizó: quien tenga su DPI (por ejemplo, un agresor con acceso a una cuenta clínica) confirma que está registrada en **este** hospital. 1 resultado frente a 0 basta. |
| Evidencia | `clínico busca el DPI de la protegida → total=1 {"nombre":"Identidad protegida",…}` · `clínico busca un DPI que no existe → total=0` |
| Regla afectada | R-11, RF-13, RNF-10 |
| Propuesta | Decisión de negocio, no técnica: exigir `pacientes.leer_protegido` también para la búsqueda **por documento**, o registrar esa búsqueda como «acceso a un protegido» para poder alertar. |
| Responsable · Estado | Autor (propone) y el responsable del negocio (decide) · Abierto |

### AR-05 · Extensiones PHP usadas y no declaradas · Menor · Defecto

| | |
|---|---|
| Ítem | RT-15 |
| Ubicación | `README.md` · `src/Dominio/Modelo/{Paciente,Texto}.php` (`mb_*`) · `src/Persistencia/Seguridad/ProteccionDocumentos.php` (`sodium_*`) |
| Descripción | El código usa **mbstring** y **sodium**, que no vienen activas en todas las instalaciones de PHP, y el README no las declara como requisitos. |
| Evidencia | `mbstring usada en src/ · declarada en README: NO` · `sodium usada en src/ · declarada en README: NO` |
| Regla afectada | D-13, RT-15 |
| Propuesta | Sección «Requisitos»: PHP 8.2+, json, mbstring, sodium y pdo_pgsql (solo en modo PDO). |
| Responsable · Estado | Autor · Abierto |

### AR-06 · Las claves de idempotencia no vencen · Menor · Riesgo

| | |
|---|---|
| Ítem | RT-15 |
| Ubicación | `src/Aplicacion/Puertos/AlmacenIdempotencia.php` · `src/Persistencia/{EnMemoria,Pdo}/*AlmacenIdempotencia.php` |
| Descripción | Cada registro con `Idempotency-Key` deja una fila que nunca se borra. El puerto no define vencimiento ni limpieza, y la tabla crece sin límite. |
| Evidencia | `50 registros con clave → el almacén conserva todas; propiedades del almacén: registros (ninguna de vencimiento)` |
| Regla afectada | RNF-06 |
| Propuesta | Vencimiento de 24 h (fecha en la clave) más limpieza periódica, documentados en el contrato. |
| Responsable · Estado | Autor · Abierto |

### AR-07 · El DPI no valida el dígito verificador ni el municipio · Menor · Defecto

| | |
|---|---|
| Ítem | RT-01 (y RT-03) |
| Ubicación | `src/Dominio/Modelo/Dpi.php:15-27` |
| Descripción | El DPI solo exige 13 dígitos y un departamento entre 01 y 22. Acepta números imposibles. Un DPI mal digitado es «válido», así que **esquiva el bloqueo por documento** (R-02). Solo lo frena la advertencia por nombre y fecha (R-03). |
| Evidencia | `DPI 0000000000101 → 201 aceptado` · `DPI 1234567890199 (departamento 01, municipio 99) → 201 aceptado` |
| Regla afectada | R-01, R-02 |
| Propuesta | Validar el dígito verificador y el municipio de cada departamento (tabla del RENAP). |
| Responsable · Estado | Autor · Abierto |

## Lo que la autorrevisión no puede cubrir

- **RT-05 con PostgreSQL:** la atomicidad real de la transacción solo la prueba INT-00 contra una base real, que sigue omitida. Se revisa por lectura de `PdoTransaccion`.
- **Puntos ciegos del autor:** decisiones que el autor tomó a conciencia, como la búsqueda en toda la red de AR-02 o la visibilidad por documento de AR-04, son justo las que un revisor externo debe cuestionar.
