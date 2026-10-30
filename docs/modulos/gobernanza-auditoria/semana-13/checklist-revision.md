# Checklist de la revisión técnica formal — Gobernanza y auditoría

**Objeto:** Micro-HIS Auditoría en el commit congelado por [`criterios-entrada.php`](herramientas/criterios-entrada.php) · **Plan:** [plan-revision-tecnica.md](plan-revision-tecnica.md)

## Cómo se usa

- **Cada ítem es una afirmación** que el revisor intenta **refutar**: si encuentra un caso que la contradice, es un hallazgo.
- **«Cómo se verifica»** dice qué prueba o comando ya la respalda. El revisor no se queda con que la prueba pase: busca el caso que la prueba **no** cubre.
- **Perspectiva** asignada a cada revisor (lectura basada en perspectivas):
  - **D** · dominio;
  - **C** · contrato y frontera;
  - **S** · permisos y tenant;
  - **I** · integridad de la bitácora;
  - **A** · arquitectura y dependencias.
- **«Sev. si falla»:** la severidad por defecto, si se refuta. El moderador la puede ajustar con justificación.

## Checklist (15 ítems)

| ID | Persp. | Afirmación a verificar | Dónde mirar | Cómo se verifica | Base | Sev. si falla |
|---|:-:|---|---|---|---|:-:|
| RT-01 | D | **Movimiento completo y bien formado (R-01).** Las 9 reglas de registro rechazan los campos faltantes, el formato inválido, el módulo ajeno al emisor, una acción fuera del catálogo, una fecha futura y los datos prohibidos (R-07). Ningún movimiento existe sin pasar todas. | `Dominio/Reglas/*`, `contenedor.php::reglasDeRegistro` | DOM-*, APP-02, CA-01..09; probar un `recurso` de 129 caracteres y un `detalle` de 5 KB | R-01, R-07, RF-01, RF-03 | Mayor |
| RT-02 | D | **Rango de fechas y filtros (R-06).** El rango es obligatorio, en hora de Guatemala y con «hasta» inclusivo. Los filtros se aplican igual en memoria (`coincide`) que en SQL (`sql`). Ningún parámetro desconocido se ignora en silencio. | `Dominio/Filtros/*`, `CatalogoFiltros` | CA-13, INT-03, REF-05, REC-01..03, M4, M24 | R-06, RF-08, RF-18 | Mayor |
| RT-03 | D | **Corrección trazable (semana 6).** Corregir crea **otro** movimiento; el original no cambia; hay una sola corrección por original, del mismo emisor y hospital; un evento de otro hospital da la misma respuesta que uno inexistente. | `ReglaCorreccion`, `RegistrarMovimiento`, `schema.sql` (índice parcial) | CORR-01..07, INT-08, M19, M20 | R-11, RF-14..17 | **Crítico** |
| RT-04 | C | **Contrato = código.** Las mismas 5 operaciones, el mismo `x-permiso`, todos los códigos que devuelve el servidor documentados, los parámetros de consulta iguales a los filtros, versión 1.2.0 y lint válido. | `api/openapi.yaml`, `Rutas.php`, `contenedor.php::filtrosDeConsulta` | API-01..03, REC-04; `npx @redocly/cli@1.34.3 lint api/openapi.yaml` | RF-09, RNF-11 | Mayor |
| RT-05 | C | **Los errores no filtran información.** El 404 del detalle es idéntico si el movimiento es de otro hospital. Un 409 no revela hechos de otro tenant. El 422 lista campos sin repetir lo enviado. Sin trazas ni SQL (500/503 genéricos). | `ErrorVista`, `VerDetalleMovimiento`, `RegistrarMovimiento`, `Kernel` | HTTP-05, HTTP-06, REF-02; registrar en Norte un `event_id` usado en Oriente | R-05, RF-13 | **Crítico** |
| RT-06 | C | **Formato en la frontera.** Cuerpo JSON objeto, parámetros de texto (un parámetro repetido da 400) y ancla bien formada. El dominio nunca recibe tipos inválidos. | `FormularioMovimientos`, `Solicitud` | MVC-01, HTTP-03, HTTP-04, paridad 19 (S11) | RF-09 | Mayor |
| RT-07 | S | **Permiso por operación, en el caso de uso.** Registrar, consultar, consultar la red, ver el detalle, verificar y pedir el ancla exigen su permiso **antes** de leer datos, aunque se llame sin HTTP. | `AutorizacionLectura`, `CasosDeUso/*` | APP-07, REF-06, M23; llamar a un caso de uso sin la ruta | R-05, CU-05 | **Crítico** |
| RT-08 | S | **Tenant.** Sin `X-Tenant-ID` → 400. El token debe actuar en ese hospital (`*` solo para la red). Toda lectura y todo dato devuelto pertenecen al alcance (`Alcance`), y el emisor solo registra en su hospital. | `Kernel`, `Alcance`, `ReglaTenantDelEmisor`, `ConsultaMovimientos`, `PdoBitacora::buscar` | HTTP-02, REF-04, REC-02, M2, M3, M12, M22 | R-05, RF-02, RF-13 | **Crítico** |
| RT-09 | S | **Identidad del actor.** El actor y el hospital salen del token, nunca del cuerpo. La autenticación de demostración **no puede** quedar activa fuera de desarrollo. | `Autenticador*`, `crearKernel`, `config.php` | Leer `crearKernel`; [AR-01](autorrevision-autor.md) | R-05, OWASP API2 | **Crítico** |
| RT-10 | I | **Inmutabilidad (R-02).** No hay operación, método ni SQL que modifique o borre un movimiento; el trigger rechaza `UPDATE`, `DELETE` y `TRUNCATE`. | `Bitacora` (puerto), `schema.sql`, `integracion-central.sql` | ARQ-05, HTTP-02 (405), INT-02, M11 | R-02, RF-06 | **Crítico** |
| RT-11 | I | **Sello encadenado (R-03).** El sello cubre todo el contenido canónico, incluida la referencia de una corrección. La cadena se bloquea antes de leer el último sello. La verificación detecta una alteración, un hueco y, con ancla, un truncamiento. | `Sello`, `CadenaSellada`, `VerificarIntegridad`, `PdoBitacora::bloquearCadena` | DOM-03, APP-09..11, INT-01, INT-05, INT-07, M1, M8, M17 | R-03, RF-07, RF-11 | **Crítico** |
| RT-12 | I | **Meta-auditoría transaccional (R-08).** Cada consulta, detalle y verificación deja su movimiento **en la misma transacción**: si no se puede registrar, no se entregan resultados. | `CadenaSellada::auditarLectura`, `ConsultarMovimientos`, `VerDetalleMovimiento` | APP-05, REF-02, M6 | R-08, RF-12 | **Crítico** |
| RT-13 | I | **Idempotencia (R-04).** El mismo `(emisor, event_id)` con el mismo contenido da 200 sin duplicar; con otro contenido, 409. El alcance de la unicidad está documentado para los emisores. | `RegistrarMovimiento`, `schema.sql` (`audit_logs_evento_unico`), contrato | APP-03, CLI-02, CON-*-03, M5; [AR-06](autorrevision-autor.md) | R-04, RF-05 | Mayor |
| RT-14 | A | **Dependencias entre capas.** Presentación → Aplicación → Dominio ← Persistencia. El Dominio no usa PDO, HTTP ni E/S. El Cliente no conoce clases del servidor. | `src/**` | ARQ-01..08; `php bin/acoplamiento.php` | D-11, D-22 | Mayor |
| RT-15 | A | **Solo dependencias estrictamente necesarias y recursos acotados.** Sin Composer ni librerías de terceros. Las extensiones PHP que se usan están declaradas. Ninguna operación carga en memoria algo que crece sin límite. | README, `src/**`, `PdoBitacora::cadena` | [AR-02](autorrevision-autor.md); `grep` de las funciones de extensiones en `src/` | RNF-06, D-17 | Mayor |

**Cobertura del pedido de la tarea:**

| Tema pedido | Ítems |
|---|---|
| Reglas de dominio | RT-01..03 |
| Contrato API | RT-04..06 |
| Permisos / tenant | RT-07..09 |
| Integridad de audit logs | RT-10..13 |
| Dependencias estrictamente necesarias | RT-14, RT-15 |

## Registro por revisor (durante la preparación)

Cada revisor anota en [`plantillas/registro-preparacion.csv`](plantillas/registro-preparacion.csv):
- los minutos de preparación;
- las líneas revisadas;
- los hallazgos preliminares.

Y registra los hallazgos en [`plantillas/registro-hallazgos.csv`](plantillas/registro-hallazgos.csv) con el [formato](formato-hallazgos.md).
