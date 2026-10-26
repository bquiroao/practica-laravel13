# Micro-HIS Gobernanza y auditoría

Aplicación PHP 8.2+ vanilla, sin framework ni Composer, que ejecuta el flujo **«registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas»** sobre PostgreSQL con PDO.

```
micro-his-auditoria/
├── api/openapi.yaml            ← contrato OpenAPI 3.1 (semana 5), validado con Redocly
├── public/index.php            ← front controller (php -S)
├── bootstrap/                  ← autoload PSR-4 y raíz de composición (contenedor.php)
├── config/config.php           ← variables de entorno, catálogo, tokens demo y permisos provisionales
├── database/schema.sql         ← audit_logs: solo inserción (trigger), sello, únicos e índices
├── src/
│   ├── Presentacion/Http/      ← Kernel, MVC (Controladores, Formularios, Vistas), Rutas, autenticación demo
│   ├── Aplicacion/             ← casos de uso, CadenaSellada, comandos, Resultado, puertos
│   ├── Dominio/                ← Movimiento, Sello, reglas y filtros (OCP), puertos Bitacora y Reloj
│   ├── Persistencia/           ← Pdo/ y EnMemoria/ (dos adaptadores por puerto), FabricaAdaptadores, configuración
│   └── Cliente/                ← ClienteAuditoria para los emisores: reintento idempotente, circuit breaker, BandejaLocal
├── database/integracion-central.sql ← audit_logs en la base central del HIS (semana 4)
├── bin/demo.php                ← demo de punta a punta SIN base de datos (modo memoria)
├── bin/medir.php               ← costo de la frontera: en proceso frente a HTTP (semana 5)
├── bin/demo-http.sh            ← recorrido de punta a punta con curl
├── bin/validar-integracion-central.php ← aplica integracion-central.sql sobre las migraciones reales
└── tests/                      ← dominio, aplicación, PDO con dobles, contrato del Repository, MVC y HTTP, arquitectura, integración, mutaciones
```

**Regla de dependencias:** Presentación → Aplicación → Dominio ← Persistencia. La verifican las pruebas ARQ-01..ARQ-05.

## API

| Método | Ruta | Permiso | Respuestas |
|---|---|---|---|
| POST | `/api/v1/auditoria/movimientos` | `auditoria.registrar` (cuenta de servicio) | 201 · 200 `YA_REGISTRADO` · 403 · 409 · 422 · 503 |
| GET | `/api/v1/auditoria/movimientos?desde=&hasta=[&usuario=&modulo=&accion=&resultado=&cursor=]` | `auditoria.consultar` (`consultar_red` con `X-Tenant-ID: *`) | 200 · 403 · 422 · 503 |
| GET | `/api/v1/auditoria/movimientos/{id}` | `auditoria.consultar` | 200 · 404 (idéntico fuera del alcance) |
| POST | `/api/v1/auditoria/verificaciones` `{"hospital": …, "ancla": {…}}` | `auditoria.verificar` | 200 `INTEGRA` / `ALTERADA` / `TRUNCADA` |
| GET | `/api/v1/auditoria/anclas?hospital=` | `auditoria.verificar` | 200 cabeza de la cadena (secuencia y sello) |

Todas exigen `X-Tenant-ID` (400) y `Authorization: Bearer` (401). Si el token no corresponde a ese hospital, la respuesta es 403. Toda respuesta lleva `X-Request-ID`. Contrato completo: [`api/openapi.yaml`](api/openapi.yaml).

## Ejecutar las pruebas

Requisitos: PHP 8.2+ con `pdo_pgsql` y `mbstring`.

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
php tests/ejecutar.php        # 116 pruebas; las 17 de PostgreSQL se omiten sin base de datos
php tests/mutaciones.php      # 27 defectos inyectados; todos deben detectarse
php bin/demo.php              # la API completa en memoria, sin base de datos
php bin/demo-correccion.php   # el cambio de la semana 6 (corrección trazable) por la API
php bin/acoplamiento.php      # acoplamiento por clase (semana 7)
php tests/ejecutar.php --solo=UNI,CB,CN   # solo las pruebas de la semana 15
php tests/ejecutar.php --defectos --solo=DEF   # defectos ABIERTOS: fallan hasta corregirse
```

**Con PostgreSQL real.** Cada ejecución crea un esquema temporal y lo elimina al terminar. Para usarlo, defina las variables y vuelva a correr los dos scripts:

```bash
export AUDITORIA_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=micro_his_auditoria"
export AUDITORIA_DB_USUARIO=postgres AUDITORIA_DB_CLAVE=…
php tests/ejecutar.php        # 116/116
php tests/mutaciones.php      # 27/27 (M1 y M11 solo las detecta la base real)
```

## Levantar la API

```bash
psql -d micro_his_auditoria -f database/schema.sql
AUDITORIA_DB_DSN=… php -S 127.0.0.1:8088 -t public
bash bin/demo-http.sh         # en otra terminal
```

## Evolución por semana

| Semana | Qué agregó |
|---|---|
| 3 | Cuatro capas, PDO con sentencias preparadas, `audit_logs` de solo inserción, sello encadenado con candado por cadena, idempotencia, consulta paginada con cursor y meta-auditoría. Pruebas con dobles, de integración con PostgreSQL y de mutación. |
| 4 | Entrada **MVC** (`AuditoriaControlador`, `FormularioMovimientos`, `MovimientosVista`/`ErrorVista`), adaptadores **InMemory de producción** junto a los PDO, `AUDITORIA_PERSISTENCIA` = `pdo` o `memoria`, suite de **contrato** común (CON-MEM/CON-PDO), demo sin base e integración con la base central del HIS (`integracion-central.sql`, validada sobre las migraciones reales). |
| 5 | Contrato **OpenAPI 3.1** (5 operaciones) con conformidad probada (API-01..03), capa **Cliente** con reintento idempotente, circuit breaker y **BandejaLocal** (outbox del emisor), `X-Request-ID` y registro de acceso, **ancla externa** (`GET /anclas`, verificación `TRUNCADA`) y medición de la frontera (`bin/medir.php`). |
| 6 | **Corrección trazable** (`corrige_evento` + `motivo_correccion`): un movimiento nuevo, sellado, que referencia al original sin modificarlo; una sola corrección por original (`YA_CORREGIDO`), mismo emisor y hospital. `ReglaCorreccion`, `corregido_por` en la consulta y el detalle, contrato **1.1.0**, `bin/demo-correccion.php`. 88 pruebas y 21 mutaciones. |
| 7 | Refactor de la consulta sin cambiar el comportamiento: `Alcance` y `CatalogoFiltros` (Dominio), `AutorizacionLectura`, `CursorPaginacion` y `ProyeccionMovimientos` (Aplicación); `VerDetalleMovimiento` ya no depende de `ConsultarMovimientos`. Instrumento `bin/acoplamiento.php`. 94 pruebas y 24 mutaciones. |
| 12 | Cambio solicitado **filtro por recurso** («¿quién vio la ficha de este paciente?»): `FiltroRecurso` (extensión OCP), `FiltroPorCampo::maximo()`, índice `audit_logs_recurso_fecha`, contrato **1.2.0** y guarda «contrato = código» (REC-04). 98 pruebas y 26 mutaciones. |
| 15 | Pruebas **UNI** (8), **CB** (3) y **CN** (7) trazadas a RF/RNF; grupo **Defectos** con los defectos abiertos (`--defectos`) y filtro por prefijo (`--solo=UNI,CB,CN`); corrección de DEF-07-01 en `CursorPaginacion` (M27). 116 pruebas y 27 mutaciones. |
