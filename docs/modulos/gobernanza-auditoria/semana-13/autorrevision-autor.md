# Autorrevisión del autor (criterio de entrada E-6)

**Autor:** Boris Alexander Quiroa Orellana · **Fecha:** 26/10/2026 · **Commit:** el de [`criterios-entrada-2-despues.txt`](evidencia/criterios-entrada-2-despues.txt)

## Qué es y qué no es

**Qué es:**
- Una pasada del autor por los 15 ítems del [checklist](checklist-revision.md) **antes** de la reunión.
- Su propósito: que la reunión no gaste tiempo en lo que el propio autor podía encontrar.
- Cada hallazgo se **reproduce** contra el kernel real con [`herramientas/verificar-autorrevision.php`](herramientas/verificar-autorrevision.php) ([salida](evidencia/autorrevision-verificaciones.txt)).

**Qué no es:** **no reemplaza** a los revisores. El autor tiene puntos ciegos sobre sus propias decisiones (§«Lo que la autorrevisión no puede cubrir»).

## Resultado por ítem

| Ítem | Resultado | Nota |
|---|---|---|
| RT-01 Movimiento bien formado | ⚠️ AR-04 | Las 9 reglas funcionan; el **formato** del recurso no se valida |
| RT-02 Rango y filtros | ⚠️ AR-03 | Filtros correctos en memoria y en SQL (REC, CA-13, INT-03); el cursor acepta fechas imposibles |
| RT-03 Corrección trazable | ✅ | CORR-01..07, INT-08, M19, M20 |
| RT-04 Contrato = código | ✅ | API-01..03 y REC-04 en verde; lint válido (1.2.0) |
| RT-05 Errores sin fuga | ✅ | El 404 es idéntico; el 409 de AR-06 es del **mismo** emisor (no revela otro tenant: el emisor ya conoce sus hechos) |
| RT-06 Formato en la frontera | ✅ | MVC-01, HTTP-03/04 |
| RT-07 Permiso en el caso de uso | ✅ | Cada caso de uso pregunta a `PoliticaAcceso` antes de leer; REF-06, M23 |
| RT-08 Tenant | ✅ | REF-04, REC-02, M2, M3, M12, M22 |
| RT-09 Identidad del actor | ❌ AR-01 | El actor sale del token (✅), pero los tokens demo están siempre activos |
| RT-10 Inmutabilidad | ✅ | Sin métodos de cambio, 405, trigger (INT-02, M11) |
| RT-11 Sello encadenado | ✅ (riesgo en RT-15) | Detecta la alteración, el hueco y el truncamiento |
| RT-12 Meta-auditoría | ✅ · observación AR-05 | Transaccional (APP-05, M6) |
| RT-13 Idempotencia | ⚠️ AR-06 | Funciona; el alcance de la unicidad no está documentado |
| RT-14 Capas | ✅ | ARQ-01..08 |
| RT-15 Dependencias y recursos acotados | ⚠️ AR-02 | Sin Composer; extensiones declaradas (`pdo_pgsql`, `mbstring`; `json`, `hash` y `random` son del núcleo de PHP 8). La verificación no está acotada |

## Hallazgos

### AR-01 · La autenticación de demostración no se puede desactivar · Crítico · Riesgo

- **Dónde:** `bootstrap/contenedor.php:109-115` · `config/config.php:25-31`
- **Qué pasa:** `crearKernel()`, el punto de entrada real, construye **siempre** `AutenticadorTokenDemo` con 5 tokens fijos que están en el repositorio. Uno de ellos (`token-superadmin`) tiene alcance de **red**.
- **Evidencia:** «Bearer token-superadmin» con `X-Tenant-ID: *` → **HTTP 200**: lectura de toda la red. No existe ninguna variable de entorno que lo impida.
- **Por qué es crítico:** cualquiera que lea el repositorio puede leer la bitácora de toda la red si el servicio se despliega tal cual.
- **Propuesta (para después de la reunión):**
  - el autenticador se elige por configuración;
  - sin `AUDITORIA_ENTORNO=desarrollo`, el servicio arranca con el autenticador del HIS (JWT) y **sin** tokens demo (fail-closed).

### AR-02 · La verificación carga la cadena completa en memoria · Mayor · Riesgo

- **Dónde:** `src/Persistencia/Pdo/PdoBitacora.php:118-122` (`fetchAll`) · `src/Aplicacion/CasosDeUso/VerificarIntegridad.php:54-57`
- **Qué pasa:** se hidrata toda la cadena de un hospital y se recorre. La cadena nunca deja de crecer, y además crece con cada lectura (R-08, ver AR-05).
- **Evidencia medida:** 866 B por movimiento hidratado.

  | Movimientos | Memoria solo en objetos |
  |---|---|
  | 100 000 | ≈ 83 MB |
  | 1 000 000 | ≈ 826 MB |

  El `memory_limit` por defecto de PHP es de 128 MB.
- **Consecuencia:** a partir de un volumen realista, la verificación falla. La herramienta que detecta manipulaciones queda inutilizable justo cuando el sistema tiene historia.
- **Propuesta:** verificar por lotes (`WHERE secuencia > :ultima ORDER BY secuencia LIMIT 5000`) arrastrando el sello anterior, o desde la última ancla ya verificada.

### AR-03 · DEF-07-01: el cursor acepta fechas imposibles · Menor · Defecto

- **Dónde:** `src/Aplicacion/Servicios/CursorPaginacion.php:22-31`
- **Qué pasa:** `leer('2026-13-45T99:00:00Z|1')` devuelve `2027-02-18T03:00Z` en lugar de `null`. Hallado en la semana 7 y diferido para no cambiar el comportamiento durante el refactor.
- **Impacto:** acotado. Con un cursor manipulado, la paginación continúa desde otra posición; no hay fuga, porque el alcance y los filtros se siguen aplicando.
- **Propuesta:** comparar la fecha formateada de vuelta con el texto, como ya hace `FiltroRangoFechas::fecha()`.

### AR-04 · El recurso es texto libre · Mayor · Riesgo

- **Dónde:** `src/Dominio/Reglas/ReglaFormatoCampos.php:16` (solo valida la longitud) · `FiltroRecurso`
- **Qué pasa:** `paciente:P-000001`, `P-000001` y `Paciente:p-000001` se registran (201), pero «¿quién vio la ficha?» (semana 12) encuentra **1 de 3** accesos al mismo paciente.
- **Por qué es mayor:** la pregunta del cambio solicitado da una respuesta incompleta **sin avisar**. En una investigación, un acceso que no aparece es peor que un error.
- **Propuesta:** una regla de formato `tipo:identificador`, con un catálogo de tipos por módulo (como `CatalogoAcciones`). Es una regla nueva (OCP); el filtro no cambia.

### AR-05 · La bitácora se observa a sí misma · Observación · Riesgo

- **Dónde:** `CadenaSellada::auditarLectura` · `ConsultarMovimientos`
- **Qué pasa:** cada lectura agrega un movimiento a la cadena del hospital. Ese movimiento aparece **primero** en la consulta siguiente (sin filtro de módulo) y alarga la verificación (AR-02). En la evidencia, la cadena de Oriente tiene 3 movimientos de emisores y 3 lecturas.
- **Por qué es solo una observación:** es el comportamiento pedido (R-08) y es verdad que esas lecturas ocurrieron. Lo que falta es decidir la **presentación** y **medir** la proporción.
- **Propuesta:**
  - decidir si la consulta por defecto excluye el módulo `auditoria`;
  - medir la proporción lecturas/emisores en el plan SQA (semana 14).

### AR-06 · El alcance de la unicidad de event_id no está documentado · Menor · Contrato

- **Dónde:** `database/schema.sql:24` (`UNIQUE (emisor, event_id)`) · `api/openapi.yaml:283` · `RegistrarMovimiento.php:59`
- **Qué pasa:** el mismo emisor (`svc-pacientes`) registra `evt-0001` en Oriente (201) y **otro** hecho con `evt-0001` en Norte, y recibe 409 `EVENTO_EN_CONFLICTO`. El contrato solo dice «uuid recomendado».
- **Impacto:** un emisor que numere por hospital pierde movimientos en el segundo hospital. La bandeja local los reintentaría para siempre, aunque la semana 5 evita reintentar los 4xx.
- **Propuesta:** documentar en el contrato que `event_id` es único por emisor **en toda la red** (uuid v4 o v7) y el 409 que se obtiene si no.

## Lo que la autorrevisión no puede cubrir

- **Mis propias decisiones de diseño.** Por ejemplo, que la meta-auditoría vaya a la cadena del hospital y no a una cadena aparte: el autor tiende a justificar lo que ya decidió. Para eso está la perspectiva **I** de R3.
- **El comportamiento con PostgreSQL bajo concurrencia real**, más allá de las pruebas INT. Por ejemplo, el candado por cadena con muchas escrituras simultáneas.
- **La integración real con JwtAuth y RBAC del HIS**, que todavía no existe (relacionado con AR-01).
