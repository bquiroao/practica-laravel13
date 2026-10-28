# Registro de defectos — pruebas de la semana 15

Formato: el de la [revisión técnica de la S13](../semana-13/formato-hallazgos.md), con la misma escala de severidad.

**Cómo se reproducen.** Cada defecto tiene una prueba que afirma el comportamiento **correcto** y que hoy **falla**. Está en `micro-his-rbac/tests/Defectos/DefectosPrueba.php` y se corre con:

```bash
php tests/ejecutar.php --defectos --solo=DEF
```

Resultado actual: 5 fallan ([salida](evidencia/4-defectos-abiertos.txt), [captura](capturas/4-defectos-abiertos.png)). Cuando un defecto se corrija, su prueba pasará y se moverá a la suite normal como prueba de regresión.

## Resumen

| ID | Severidad | Tipo | Encontrado por | Relación | Estado |
|---|---|---|---|---|---|
| DEF-01 | **Mayor** | Defecto | Caja negra (límites de fecha), **nuevo** en esta semana | — | Abierto |
| DEF-02 | **Mayor** | Defecto | Caja negra (equivalencia: duplicado) | AR-02 (S13) | Abierto |
| DEF-03 | **Mayor** | Riesgo | Prueba del punto de entrada real | AR-01 (S13) | Abierto |
| DEF-04 | **Mayor** | Riesgo | Caja blanca (camino C5) | AR-04 (S13) | Abierto |
| DEF-05 | Menor | Riesgo | Caja negra (tenant) | AR-03 (S13) | Abierto |
| OBS-01 | Observación | Pregunta | Prueba exploratoria | — | Para decidir |

## DEF-01 · Una fecha imposible se «corrige» en silencio y la vigencia queda distinta de la pedida · Mayor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-12 (validación en la frontera) · RF-03, RF-07 |
| Ubicación | `src/Presentacion/Http/Formularios/FormularioAsignacion.php:61-73` (`fecha()`) |
| Descripción | La validación revisa el **formato** `AAAA-MM-DD`, pero no que la fecha **exista**. PHP normaliza las fechas imposibles en lugar de rechazarlas, y la membresía se guarda con otra vigencia. |
| Evidencia | `2026-02-30 → 201`, guardada como `2026-03-02` · `2026-04-31 → 201`, guardada como `2026-05-01` · `2026-00-10 → 201`, guardada como **`2025-12-10`** |
| Por qué es Mayor | La vigencia decide PERMITIDO o DENEGADO (RF-07). Con `2026-00-10`, el rol queda vigente **un mes antes** de lo solicitado: la persona tendría acceso clínico antes de la fecha autorizada. No es Crítico porque requiere un error al escribir la fecha, pero ese error es plausible. |
| Propuesta | Después de construir la fecha, comparar el día, el mes y el año con los del texto, o usar `DateTimeImmutable::createFromFormat('!Y-m-d', …)` y revisar `getLastErrors()`. Responder 422 `DATOS_INVALIDOS ["valido_desde"]`. |
| Prueba | DEF-01 (falla) |

## DEF-02 · La misma asignación con fecha de fin se puede duplicar · Mayor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-03, RT-11 · R-03, RF-03 |
| Ubicación | `src/Dominio/Reglas/Asignacion/ReglaSinMembresiaDuplicada.php:21`, `database/schema.sql:45-47` |
| Descripción | La regla de duplicados y el índice único solo consideran membresías **abiertas** (sin fecha de fin). |
| Evidencia | Primer envío: 201; segundo envío idéntico: **201**, y quedan 2 membresías activas iguales. |
| Propuesta | Detectar el **solapamiento** de periodos en la regla y en el índice (restricción `EXCLUDE` con `tstzrange`), o aceptar `Idempotency-Key` (B-5). |
| Prueba | DEF-02 (falla) |

## DEF-03 · El arranque real acepta tokens de demostración fuera de desarrollo · Mayor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-10 · R-04, CP-05 (S14) |
| Ubicación | `bootstrap/contenedor.php:178`, `config/config.php:14-20` |
| Descripción | `crearKernel()`, que usa `public/index.php`, siempre autentica con `AutenticadorTokenDemo` y 4 tokens fijos. |
| Evidencia | Sin `MICROHIS_ENTORNO`, `Bearer token-admin-oriente` asigna un rol: **201**. |
| Propuesta | Elegir el autenticador por entorno. Sin `MICROHIS_ENTORNO=desarrollo`, ningún token de demostración es válido (401). |
| Prueba | DEF-03 (falla) |

## DEF-04 · Si fallan la bitácora y el respaldo, no queda ningún rastro · Mayor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-05 · R-06, RNF-02 |
| Ubicación | `src/Aplicacion/CasosDeUso/AutorizarOperacion.php:72-80` |
| Descripción | Camino C5 de la caja blanca: la denegación se mantiene (bien, fail-closed), pero el segundo `catch` está vacío y no queda ni siquiera una línea en `error_log`. |
| Evidencia | CB-01 (C5): 0 eventos en las dos bitácoras. DEF-04: `error_log` no menciona `corr-def04`. |
| Propuesta | `error_log()` con la referencia y el motivo cuando fallan las dos; comprobar al arrancar que el respaldo se puede escribir. |
| Prueba | DEF-04 (falla) |

## DEF-05 · Un sondeo entre hospitales por permisos efectivos no deja rastro · Menor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-05, RT-09 · RNF-14 (frontera entre pasivo y activo) |
| Ubicación | `src/Aplicacion/CasosDeUso/ConsultarPermisosEfectivos.php:36-48` |
| Descripción | Un token de Norte usado contra Oriente en `/permisos-efectivos` responde todo `false` (bien: no da acceso), pero no registra `TENANT_NO_COINCIDE`, como sí hace `/decisiones`. |
| Evidencia | 0 eventos tras el sondeo |
| Propuesta | Auditar solo el caso tenant ≠ token en ese caso de uso. |
| Prueba | DEF-05 (falla) |

## OBS-01 · Se puede asignar una membresía que ya venció · Observación / Pregunta

| Campo | Valor |
|---|---|
| Ubicación | `src/Dominio/Reglas/Asignacion/ReglaVigenciaValida.php` |
| Descripción | `valido_desde = 2026-01-01`, `valido_hasta = 2026-02-01` (antes de «ahora») → **201**. La membresía nace vencida y no sirve para nada. |
| Por qué no es un defecto todavía | Ningún RF dice que la vigencia deba incluir el presente. Podría usarse para registrar un rol histórico. Se lleva a la reunión de revisión para decidirlo; si se decide rechazarlo, se agrega una prueba. |

## Errores en el diseño de las pruebas (no son defectos del producto)

Se registran porque muestran que las pruebas también se verificaron:

| Prueba | Error | Corrección |
|---|---|---|
| CN-03 (primera versión) | Esperaba el texto del 404 de la API **simulada** del prototipo («No existe un evento…»), no el del backend real («Evento no encontrado.»). El contrato no fija ese texto. | El límite se rediseñó con lo que el contrato **sí** fija: el patrón `{1,64}` y que la ruta está protegida (sin permiso: 64 → 403, 65 → 404). |
| CN-03 (segunda versión) | Los nombres de permisos generados (`modulo.accion_1`) tenían dígitos; el contrato solo admite `[a-z_]`. | Nombres válidos (`modulo.x`, `modulo.xx`, …). |
