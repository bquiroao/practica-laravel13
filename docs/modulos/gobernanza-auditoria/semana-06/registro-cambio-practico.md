# Registro del cambio práctico defendido — «Corrección trazable de un movimiento»

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Cambio** | Permitir que un módulo emisor **corrija** un movimiento que registró mal, **sin modificar el original** |
| **Método** | Pruebas primero (rojo → verde), sobre el MISMO Micro-HIS de las semanas 3 a 5 |
| **Resultado** | 88 pruebas: **88 pasan con PostgreSQL**, 0 fallan; 21/21 mutaciones detectadas; contrato 1.1.0 compatible |

## 1. Solicitud

> «El integrador de Pacientes tuvo un error: durante una mañana atribuyó las consultas de fichas al usuario de la sesión anterior. La bitácora dice que el doctor X abrió la ficha de una paciente, pero fue la doctora Y. No podemos editar la bitácora, porque es inmutable y así debe seguir. Pero tampoco podemos dejar al doctor X acusado de un acceso que no hizo. Necesitamos corregirlo dejando constancia de quién corrigió, cuándo y por qué.»

Cubre los seis aspectos que pide la tarea:

| Aspecto | Cómo lo cubre el cambio |
|---|---|
| **Audit logs inmutables** | El original **no se toca**, ni su sello: CORR-01 compara el original completo antes y después. La corrección es otro movimiento, sellado en la misma cadena, y su referencia y su motivo también quedan sellados. |
| **Usuario** | Es lo que típicamente se corrige. La consulta por el usuario correcto encuentra la corrección; el original sigue bajo el usuario equivocado, pero marcado `corregido_por` (CORR-06). |
| **Módulo** | Solo el **mismo emisor** puede corregir: el original se busca entre los `event_id` de esa cuenta de servicio, y `ReglaModuloDelEmisor` sigue aplicando (CORR-03). |
| **Acción** | La corrección pasa por las mismas reglas: catálogo, formato y datos prohibidos. No es una vía para colar acciones inválidas. |
| **Tenant** | No se puede corregir un movimiento de otro hospital, y la respuesta es **idéntica** a la de un evento inexistente: no revela que existe (CORR-03, mutación M20). |
| **Filtros por rango de fechas** | El original queda en **su** fecha y la corrección en **la suya**. Consultar el día del original muestra el aviso `corregido_por`; consultar el día de la corrección muestra `corrige_a` (CORR-06). |

## 2. Requisitos nuevos

Regla nueva **R-11 — Corrección trazable**. Continúa la numeración de las semanas 1 y 2.

| ID | Requisito | Criterio de aceptación | Prueba |
|---|---|---|---|
| RF-14 | Un emisor corrige un movimiento suyo enviando otro con `corrige_evento` (el `event_id` del original) y `motivo_correccion` (10 a 500 caracteres). | 201; el original idéntico; la corrección con `corrige_a` sellado; la cadena INTEGRA. Sin motivo, con motivo corto o sin `corrige_evento` → 422. | CORR-01, CORR-02 |
| RF-15 | Solo se corrige un movimiento propio y del mismo hospital; si no, la misma respuesta que «no existe». | Otro hospital, otro emisor e inexistente → la MISMA respuesta 422. | CORR-03 |
| RF-16 | A lo sumo una corrección por movimiento; para volver a corregir se corrige la corrección. El reintento de la misma corrección es idempotente. | Segunda corrección → 409 `YA_CORREGIDO`; corregir la corrección → 201; reintento → 200 `YA_REGISTRADO`. | CORR-04, CORR-05 |
| RF-17 | La consulta y el detalle avisan las correcciones. | Resumen y detalle con `corregido_por` y `corrige_a`. | CORR-06, CORR-07 |
| RNF-10 | La base impide por sí sola una corrección incompleta o doble, aunque se salte la aplicación. | `CHECK` (23514) y único parcial sobre `corrige_a` (23505). | INT-08 |

## 3. Análisis de impacto (antes de programar)

| Capa | ¿Cambia? | Qué y por qué |
|---|---|---|
| Presentación | **No** | El formulario ya pasa el cuerpo completo; el controlador, las rutas y las vistas no saben nada de correcciones |
| Aplicación | Sí, dos casos de uso | `RegistrarMovimiento` resuelve la referencia **dentro de la transacción**, después del candado de la cadena. `ConsultarMovimientos` y `VerDetalle` agregan `corregido_por`. |
| Dominio | Sí | Una regla **nueva** (`ReglaCorreccion`), sin tocar las 8 existentes (OCP de la semana 2). `Movimiento` suma dos campos sellados. `Bitacora` suma dos consultas. |
| Persistencia | Sí | 2 columnas, FK al original, `CHECK` de coherencia, único parcial; los dos adaptadores pasan la nueva cláusula del contrato (CON-*-09) |
| Cliente | **No** | El contrato 1.1.0 es compatible: solo campos opcionales y un código de error nuevo |

**Decisión clave tomada en el análisis:** ¿dónde se valida que el original exista y no esté corregido?

| Opción | Problema |
|---|---|
| En una regla de dominio | La regla consultaría la base **fuera** de la transacción: dos correcciones simultáneas podrían pasar las dos |
| **En el caso de uso, dentro de la transacción** (elegida) | La regla de dominio valida solo la **forma**; el caso de uso, la **existencia**. El único parcial en la base es la última defensa. |

## 4. Implementación

| Archivo | Cambio |
|---|---|
| `src/Dominio/Reglas/ReglaCorreccion.php` | **Nuevo.** Forma: `corrige_evento` y `motivo_correccion` van juntos o no van; el motivo tiene de 10 a 500 caracteres; no puede corregirse a sí mismo |
| `src/Dominio/Modelo/Movimiento.php` | `corrigeA` y `motivoCorreccion` en el contenido sellado; `corrigiendo()`; la idempotencia compara el motivo y no la referencia sin resolver |
| `src/Dominio/Puertos/Bitacora.php` | `correccionDe()` y `correccionesDe()` |
| `src/Aplicacion/CasosDeUso/RegistrarMovimiento.php` | Original buscado por (emisor, `event_id`) y mismo hospital → idempotencia **antes** que `YA_CORREGIDO` → 422 / 409 / corrigiendo |
| `ConsultarMovimientos`, `VerDetalleMovimiento` | `corregido_por` (una sola consulta por página, sin N+1) |
| `PdoBitacora`, `BitacoraEnMemoria` | Columnas nuevas; las dos consultas; unicidad por original |
| `database/schema.sql`, `integracion-central.sql` | `corrige_a` (FK), `motivo_correccion`, `CHECK`, único parcial |
| `bootstrap/contenedor.php` | Una línea: `new ReglaCorreccion()` |
| `api/openapi.yaml` | 1.0.0 → **1.1.0** (compatible) |

## 5. Pruebas: rojo → verde

| Momento | Resultado | Evidencia |
|---|---|---|
| Pruebas CORR escritas, **sin implementar** | 84 pruebas: **5 fallan** (CORR-01..04 y 06) | [cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt) |
| Después de implementar (dobles) | 88 · 71 pasan · **0 fallan** · 17 omitidas | [cambio-2-despues-verde.txt](evidencia/cambio-2-despues-verde.txt) |
| Con PostgreSQL 18 | **88/88** | [cambio-3-postgres.txt](evidencia/cambio-3-postgres.txt) |
| Mutaciones (3 nuevas: M19, M20, M21) | **21/21** detectadas | [mutaciones.txt](evidencia/mutaciones.txt) |
| Contrato 1.1.0 | Redocly: válido | [lint-openapi.txt](evidencia/lint-openapi.txt) |

**CORR-05 pasaba desde el principio.** La idempotencia de la semana 3 ya resolvía el reintento. Se mantuvo como **guardia**: obligó a que, en la implementación, la comprobación de idempotencia fuera **antes** que la de `YA_CORREGIDO`. Al revés, el reintento legítimo de una corrección daría 409.

## 6. Demostración de punta a punta

`php bin/demo-correccion.php` ([salida](evidencia/demo-correccion.txt)), por la API real y sin base de datos:

| Paso | Respuesta |
|---|---|
| 1. Registro con el usuario equivocado | 201 |
| 2. Corrección sin motivo | 422 `motivo_correccion` |
| 3. Corrección con motivo | 201 (secuencia 2) |
| 4. Reintento de la misma corrección | 200 `YA_REGISTRADO` |
| 5. Otra corrección del mismo original | 409 `YA_CORREGIDO` |
| 6. Norte intenta corregir el evento de oriente | 422, igual que si no existiera |
| 7. Consulta del día | El original con `corregido_por: 2`; la corrección con `corrige_a: 1` |
| 8. Verificación | `INTEGRA` (3 movimientos: 2 + la consulta auditada) |

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Referencia por **`event_id`** del emisor (`corrige_evento`), no por id de la base | `corrige_a` con el id interno | El emisor conoce su `event_id`; el id interno no lo conoce, sobre todo si envió por la bandeja |
| La referencia y el motivo **dentro del sello** | Guardarlos fuera del contenido | Si no, alguien podría desligar una corrección de su original sin romper la cadena |
| **Una** corrección por original; se corrige la corrección | Varias correcciones del mismo | Historial lineal y sin ambigüedad sobre cuál es la vigente |
| Otro hospital → 422 idéntico a inexistente | 403 o 404 | No revelar que el evento existe en otro tenant |
| `corregido_por` calculado al consultar | Marcar el original | Marcar el original sería **modificarlo** |

## 8. Riesgos y pendientes

- **Abuso de correcciones:** un emisor podría «corregir» para encubrir. Mitigación: el original nunca desaparece, la corrección lleva motivo y queda sellada. En la semana 14 se agregará una métrica de correcciones por emisor.
- **La interfaz** (semana 8) debe mostrar el original y su corrección juntos, sin esconder ninguno.
- **Integración:** en `integracion-central.sql` ya están las columnas; el consumidor de la bandeja (T4) debe pasar `corrige_evento` tal cual.
