# Matriz decisión → evidencia (semanas 1–6)

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas»

**Cómo leer la matriz.** Cada decisión de arquitectura se enlaza con:
- el requisito que la motiva;
- el lugar donde vive;
- la evidencia **ejecutable** que la comprueba.

Las rutas son relativas a [`micro-his-auditoria/`](../micro-his-auditoria/README.md).

**Estado verificable hoy:**

| Verificación | Resultado |
|---|---|
| `php tests/ejecutar.php` con PostgreSQL 18 | 88 pruebas, 88 pasan |
| `php tests/mutaciones.php` | 21/21 mutaciones detectadas |
| Lint del contrato 1.1.0 (Redocly) | Válido |

| ID | Decisión | RF / RNF / regla | Dónde vive | Evidencia | Sem. |
|---|---|---|---|---|:-:|
| D-01 | **No existe** ningún caso de uso, endpoint ni método para editar o borrar un movimiento | R-02, RF-06 | CU-01..07 (sin «editar»); puerto `Bitacora` | CA-10 (S2), ARQ-05, HTTP-02 (`PUT`/`DELETE` → 405) | 1 |
| D-02 | Trigger de solo inserción (`UPDATE`, `DELETE`, `TRUNCATE`) | R-02 | `database/schema.sql` | INT-02, mutación M11 | 3 |
| D-03 | Sello encadenado por hospital (`sha256(anterior ‖ secuencia ‖ contenido)`) | R-03, RF-07 | `Dominio/Modelo/Sello`, `CadenaSellada` | DOM-03, APP-09, INT-05, M3 | 1–3 |
| D-04 | Idempotencia por (emisor, `event_id`) | R-04, RF-05 | `RegistrarMovimiento` + `UNIQUE (emisor, event_id)` | APP-03, CLI-02, CON-*-03, M5 | 1–3 |
| D-05 | Tenant del token antes que el contenido; 404 idéntico entre hospitales | R-05, RF-02, RF-13 | `Kernel`, `ReglaTenantDelEmisor`, `VerDetalle` | HTTP-02, HTTP-05, APP-07, M12 | 1–3 |
| D-06 | **OCP:** reglas de registro y filtros de consulta como puntos de extensión | RNF-01 | `Dominio/Reglas`, `Dominio/Filtros` | OCP-01..03 y PAR-01 (S2); la regla nueva del S6 entró sin tocar el caso de uso | 2 |
| D-07 | Los invariantes (alcance, sello, idempotencia, meta-auditoría) **no** son extensibles | R-05, R-08 | Casos de uso | OCP-02 (S2), M2, M4, M6 | 2 |
| D-08 | Rango de fechas obligatorio, en hora local, con «hasta» inclusivo | R-06, RF-08 | `FiltroRangoFechas` | DOM-04, CA-13 (S2), INT-03, M1/M4 | 1–3 |
| D-09 | Cada filtro trae su `sql()` con parámetros: el `WHERE` se compone sin concatenar valores | RNF-07 | `FiltroMovimiento::sql()`, `PdoBitacora::buscar()` | PER-02, ARQ-03, M10 | 2–3 |
| D-10 | Meta-auditoría transaccional: sin evidencia de la lectura, no hay resultados | R-08, RF-12 | `CadenaSellada::auditarLectura()` | APP-05, M6 | 1–3 |
| D-11 | Cuatro capas con puertos; Persistencia es la única con PDO | Tarea S3 | `src/*` | ARQ-01..05, M9 | 3 |
| D-12 | Canonizar el detalle antes de sellar (`jsonb` reordena las claves) | R-03 | `Movimiento::contenido()` | PER-04, INT-01, CON-*-07, M1 | 3 |
| D-13 | Candado transaccional por cadena (`pg_advisory_xact_lock`) | R-03 | `PdoBitacora::bloquearCadena()` | APP-10, M8 | 3 |
| D-14 | MVC: el controlador no tiene SQL ni reglas; solo las vistas tienen códigos HTTP | Tarea S4 | `Presentacion/Http/*` | ARQ-07, ARQ-08, MVC-01..04, M13 | 4 |
| D-15 | Repository en el Dominio con adaptadores PDO e InMemory **de producción** y una suite de contrato | Tarea S4 | `Persistencia/{Pdo,EnMemoria}` | CON-MEM/PDO-01..09, ARQ-06, M14, M15 | 4 |
| D-16 | Integración por la bandeja central; hospital del **nodo** de origen; `usuario_id` sin FK | R-05 | `integracion-central.sql` | `bin/validar-integracion-central.php` (6/6) | 4 |
| D-17 | No extraer el microservicio todavía: escritura asíncrona y 5 criterios medibles | Tarea S5 | `bin/medir.php` | 1 ms frente a 69 ms ([medición](../semana-05/evidencia/1-medicion-frontera.txt)) | 5 |
| D-18 | Cliente con reintento idempotente, circuit breaker y bandeja local | Resiliencia | `src/Cliente/*` | CLI-01..08, M16 | 5 |
| D-19 | Ancla externa: detectar el truncamiento de la última fila | R-03 | `ObtenerAncla`, `VerificarIntegridad` | APP-11, INT-07, M17 | 5 |
| D-20 | Contrato OpenAPI verificado contra el código | Tarea S5 | `api/openapi.yaml` | API-01..03, lint | 5 |
| **D-21** | **Corrección trazable:** otro movimiento que referencia al original; el original no cambia | **R-11, RF-14..17, RNF-10** | `ReglaCorreccion`, `RegistrarMovimiento`, esquema | CORR-01..07, CON-*-09, INT-08, M19..M21 ([registro](registro-cambio-practico.md)) | **6** |

## Cómo usar la matriz en la defensa

1. Elegir una decisión.
2. Abrir el archivo de «Dónde vive».
3. Ejecutar la evidencia.
4. Para demostrar que la prueba no pasa por casualidad, aplicar su mutación: debe hacerla fallar.

**Ejemplo en vivo, D-05:** en `Kernel.php`, cambiar `if (!$identidad->puedeActuarEn($hospital))` por `if (false)` (mutación M12). Falla HTTP-02: el auditor de oriente ve la red.
