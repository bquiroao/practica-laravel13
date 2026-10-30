# Contratos de entrada y salida — Semana 7

**Módulo:** QA, pruebas E2E, CI y guía de despliegue final (gestión de ejecuciones automatizadas y calidad de versiones)
**Diagrama:** [semana-07-componentes.png](diagramas/semana-07-componentes.png)

En este documento, un contrato dice qué recibe cada frontera, qué devuelve y con qué códigos falla. Hay cuatro fronteras, una por capa. En cada una, lo que entra se valida una sola vez, en el lugar que le corresponde:

| Frontera | Valida | No valida |
|---|---|---|
| 1. Cliente → HTTP | Autenticación, forma del JSON, cabeceras y tipos | Reglas de negocio |
| 2. HTTP → Aplicación | Permisos (RBAC) y tenant | La forma, que ya llegó validada |
| 3. Aplicación → Dominio | Las reglas R-01…R-12 | Quién llama ni cómo se guarda |
| 4. Dominio → Persistencia | Integridad: claves, triggers y RLS, como segunda barrera | — |

## 1. Frontera externa: HTTP (`api/openapi.yaml` 1.1.0)

**Entrada común** a todas las operaciones:
- `Authorization: Bearer <token>`; sin él, `401 NO_AUTENTICADO`.
- `X-Tenant-Id`, el hospital; sin él, `400 TENANT_REQUERIDO`.
- `X-Request-ID` es opcional. Si no llega, el servidor genera uno y lo devuelve.

**Salida común:**
- Una operación que sale bien devuelve `{"codigo": "...", ...datos}`.
- Un error devuelve `{"error": "<uno de 38 códigos>", "detalle"?: [...], "campos"?: [...]}`.
- Los códigos de error son estables. El cliente decide por el código, no por el texto.

| Operación | Permiso | Entrada (cuerpo) | Éxito | Errores de negocio |
|---|---|---|---|---|
| `POST /escenarios` (CU-01) | `e2e.disenar` | `EscenarioNuevo`: `id ^E2E-\d{3,5}$`, título ≤ 200, módulo, crítico, `requisitos[] ^RF-\d{2,3}$`, `datos ^SINT-…` | `201 ESCENARIO_REGISTRADO` | 409 `ESCENARIO_EXISTENTE` · 422 `DATOS_NO_SINTETICOS`, `DATOS_INVALIDOS` |
| `POST /versiones` (CU-02) | `e2e.versionar` | `VersionNueva`: número, `commit ^[0-9a-f]{7,40}$`, `digest ^sha256:[0-9a-f]{64}$`, `modulos[]` | `201 VERSION_REGISTRADA` + `Location` | 409 `VERSION_EXISTENTE`, `DIGEST_EN_USO` |
| `POST /versiones/{v}/plan` (CU-03) | `e2e.planificar` | `Plan`: `escenarios[]` | `200 PLAN_DEFINIDO` | 409 `PLAN_CONGELADO` · 422 `PLAN_INCOMPLETO`, `ESCENARIO_INEXISTENTE` |
| `POST /versiones/{v}/ejecuciones` (CU-04) | `e2e.ejecutar` | `InicioEjecucion`: digest, entorno | `201 EJECUCION_INICIADA` | 409 `DIGEST_DISTINTO`, `VERSION_SIN_PLAN` |
| `POST /ejecuciones/{id}/resultados` (CU-05) | `e2e.reportar` | `Idempotency-Key` (8–80 caracteres `[A-Za-z0-9._-]`) + `ResultadoNuevo`: escenario, `resultado ∈ {PASA, FALLA, OMITIDO}`, intentos 0–5, `evidencias[{tipo, contenido_base64}]` | `201 RESULTADO_REPORTADO`; `200 RESULTADO_YA_REPORTADO` si es un reintento idéntico | 400 `IDEMPOTENCY_KEY_REQUERIDA`, `BASE64_INVALIDO` (formulario) · 409 `CLAVE_REUTILIZADA`, `ESCENARIO_FUERA_DEL_PLAN`, `ESCENARIO_YA_REPORTADO`, `EJECUCION_NO_ADMITE_RESULTADOS` · 413 `EVIDENCIA_DEMASIADO_GRANDE` · 422 `DATOS_INVALIDOS`, `TIPO_NO_PERMITIDO`, `EVIDENCIA_VACIA` · 503 `EVIDENCIA_NO_DISPONIBLE` |
| `POST /ejecuciones/{id}/cierre` (CU-07) | `e2e.ejecutar` | `Cierre`: `abortar?` (el motivo) | `200 EJECUCION_COMPLETADA` / `EJECUCION_ABORTADA` | 409 `ESTADO_NO_PERMITE_CERRAR` · 422 `ESCENARIOS_SIN_RESULTADO`, `MOTIVO_REQUERIDO` |
| `POST /versiones/{v}/gate` (CU-09) | `e2e.evaluar` | `PedidoGate`: ejecución | `200 GATE_EVALUADO` + `Decision`: `{decision: APROBADO o RECHAZADO, reglas, causas, digest, politica}` | 409 `EJECUCION_NO_COMPLETADA`, `EJECUCION_DE_OTRA_VERSION` |
| `POST /versiones/{v}/despliegues` (CU-11) | `e2e.desplegar` | `DespliegueNuevo`: digest, entorno, `guia ^guia-\d{4}\.\d+$`, `smoke ∈ {PASA, FALLA}` | `201 DESPLIEGUE_REGISTRADO`; con `smoke = FALLA`, queda REVERTIDO | 409 `GATE_NO_APROBADO`, `APROBACION_VENCIDA`, `DIGEST_DISTINTO` |
| `GET /versiones/{v}/trazabilidad` (CU-12) | `e2e.consultar` | — | `200 TRAZABILIDAD`: versión, plan, ejecuciones, resultados, evidencias (solo las huellas) y evaluaciones | 404 `NO_ENCONTRADO` |

**Errores técnicos**, comunes a todas las operaciones:

| Estado | Códigos | Origen |
|---|---|---|
| 400 | `JSON_INVALIDO`, `CUERPO_INVALIDO` | Los produce el Kernel o el Formulario |
| 403 | `PERMISO_DENEGADO`, `TENANT_AJENO` | — |
| 404 | `RUTA_DESCONOCIDA` | — |
| 405 | `METODO_NO_PERMITIDO` | — |
| 500 | `ERROR_INTERNO` | Lleva el `X-Request-ID` para buscarlo en el log |
| 503 | `ERROR_PERSISTENCIA` | — |

**Verificación:**
- API-01…API-04 comprueban que cada código que el código produce está en el contrato, y al revés.
- HTTP-* recorren el ciclo completo.

## 2. Frontera Presentación → Aplicación

Los controladores pasan al caso de uso tipos primitivos ya validados por el Formulario, nunca el request:

```php
RegistrarEscenario::ejecutar(string $usuario, string $tenant, array $datos): Salida
RegistrarVersion::ejecutar(string $usuario, string $tenant, array $datos): Salida
PlanificarPruebas::ejecutar(string $usuario, string $tenant, string $numero, array $propuesta): Salida
IniciarEjecucion::ejecutar(string $usuario, string $tenant, string $numero, string $digest, string $entorno): Salida
ReportarResultado::ejecutar(string $usuario, string $tenant, int $ejecucionId, string $clave, array $datos): Salida
CerrarEjecucion::ejecutar(string $usuario, string $tenant, int $id, ?string $motivoAborto = null): Salida
EvaluarGate::ejecutar(string $usuario, string $tenant, string $numero, int $ejecucionId): Salida
RegistrarDespliegue::ejecutar(string $usuario, string $tenant, string $numero, array $datos): Salida
ConsultarTrazabilidad::ejecutar(string $usuario, string $tenant, string $numero): Salida
```

**Salida** es la única forma de respuesta: `Salida(tipo, codigo, datos)`.

| Contrato | Regla |
|---|---|
| Mapeo de `tipo` a HTTP | Lo hace solo `SalidaVista`: CREADO 201, OK 200, INVALIDO 422, CONFLICTO 409, NO_ENCONTRADO 404, DENEGADO 403, DEMASIADO_GRANDE 413, NO_DISPONIBLE 503 |
| El caso de uso no conoce HTTP | No hay status ni cabeceras en `src/Aplicacion`. ARQ-02 lo verifica |
| El caso de uso no lanza excepciones | Toda falla esperada sale como `Salida`. Una excepción que se escapa es un 500 con `X-Request-ID` |

## 3. Frontera Aplicación → Dominio

| Elemento | Entrada | Salida | Falla con |
|---|---|---|---|
| `ReporteResultado::desde(int $ejecucion, string $clave, array $datos)` **(nuevo)** | Lo que reportó el pipeline | Un valor inmutable: clave, huella SHA-256 del contenido, escenario, resultado, intentos y artefactos normalizados | `ReglaRechazada('DATOS_INVALIDOS', ['Idempotency-Key'])` |
| `ReporteResultado::repite(int $ejecucionPrevia, ResultadoEscenario $previo)` **(nuevo)** | Lo guardado con la misma clave | `true` solo si es la misma ejecución y la misma huella | — |
| `Ejecucion::preparar(ReporteResultado $r, DateTimeImmutable $ahora)` **(nuevo)** | El reporte | Un `ResultadoEscenario` **sin evidencias**. No escribe nada | Revisa en este orden, el mismo que grabó la caracterización: plan (`ESCENARIO_FUERA_DEL_PLAN`) → datos (`DATOS_INVALIDOS`) → estado y duplicado (`EJECUCION_NO_ADMITE_RESULTADOS`, `ESCENARIO_YA_REPORTADO`) → evidencia (`TIPO_NO_PERMITIDO`, `EVIDENCIA_VACIA`, `EVIDENCIA_DEMASIADO_GRANDE`) |
| `ResultadoEscenario::conEvidencias(list<Evidencia>)` **(nuevo)** | Las evidencias que confirmó el almacén | Una copia con las evidencias | — |
| `Ejecucion::agregar(ResultadoEscenario)` | El resultado | — | Las mismas reglas de estado y duplicado (`exigirAdmite`) |
| `Despliegue::registrar(Version, list<Decision> $evaluaciones, …)` **(cambió la firma)** | Todas las evaluaciones de la versión, en orden | El `Despliegue` | `GATE_NO_APROBADO`, `DIGEST_DISTINTO`, `APROBACION_VENCIDA` |
| `QualityGate::evaluar(PoliticaGate, Ejecucion, Version, DateTimeImmutable $ahora, …)` | La política vigente y una ejecución COMPLETADA | `Decision`, una función pura que no consulta ni el reloj ni la base | — |

**Puertos de aplicación**, con la interfaz en Aplicación y la implementación en Persistencia:

| Puerto | Contrato |
|---|---|
| `PoliticaAcceso::permite(usuario, permiso, tenant): bool` | — |
| `AlmacenEvidencias::guardar(tipo, bytes): Evidencia` | Fail-closed: si no puede confirmar, lanza `AlmacenNoDisponible` |
| `AlmacenEvidencias::confirmada(sha256): bool` | — |
| `RegistroAuditoria::registrar(accion, tenant, usuario, recurso)` | — |
| `Transaccion::ejecutar(callable)` | Todo o nada |

## 4. Frontera Dominio → Persistencia (Repository)

Los puertos los define el dominio. `Pdo*` y `EnMemoria*` cumplen la misma suite de contrato (CON-01…CON-08):

```php
RepositorioEscenarios:  guardar(EscenarioE2E) · porId(tenant, id): ?EscenarioE2E · delTenant(tenant): list
RepositorioVersiones:   crear(Version) · actualizar(Version) · porNumero(tenant, n): ?Version · porDigest(tenant, d): ?Version
RepositorioEjecuciones: crear(Ejecucion): Ejecucion · actualizarEstado(Ejecucion) · agregarResultado(tenant, id, ResultadoEscenario): ResultadoEscenario
                        porId(tenant, id): ?Ejecucion · resultadoPorClave(tenant, clave): ?array{int, ResultadoEscenario} · deVersion(tenant, v): list
RepositorioEvaluaciones: guardar(Decision) · deVersion(tenant, v): list<Decision> (en orden de evaluación)
RepositorioDespliegues: registrar(Despliegue): Despliegue · deVersion(tenant, v): list · vigente(tenant, entorno): ?Despliegue
```

**Errores:**
- Un fallo de la base sale como `ErrorPersistencia` (`ConsultasPdo::traducir`), nunca como `PDOException`.
- Lo que el dominio ya validó, la base lo vuelve a exigir con CHECK, triggers y RLS. Por ejemplo, si alguien se salta la aplicación, INT-10 y M30 siguen en pie.

## 5. Qué cambió en los contratos por el refactor

| Contrato | Antes | Después | ¿Rompe algo? |
|---|---|---|---|
| HTTP (`openapi.yaml`) | 1.1.0 | 1.1.0, sin cambios | No. CAR-01 muestra que las 35 respuestas grabadas son idénticas |
| `ReportarResultado::ejecutar` | La misma firma | La misma firma | No |
| `Despliegue::registrar` | `?Decision $ultima` | `list<Decision> $evaluaciones` | Solo hacia adentro. Su único llamador, `RegistrarDespliegue`, se actualizó en el mismo cambio |
| Dominio | — | `ReporteResultado`, `Ejecucion::preparar`, `ResultadoEscenario::conEvidencias` | Es aditivo |
