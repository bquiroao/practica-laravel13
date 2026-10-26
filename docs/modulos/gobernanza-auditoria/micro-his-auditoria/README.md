# Micro-HIS Gobernanza y auditoría

Aplicación PHP 8.2+ vanilla, sin framework ni Composer, que ejecuta el flujo **«registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas»** sobre PostgreSQL con PDO.

```
micro-his-auditoria/
├── public/index.php            ← front controller (php -S)
├── bootstrap/                  ← autoload PSR-4 y raíz de composición (contenedor.php)
├── config/config.php           ← variables de entorno, catálogo, tokens demo y permisos provisionales
├── database/schema.sql         ← audit_logs: solo inserción (trigger), sello, únicos e índices
├── src/
│   ├── Presentacion/Http/      ← Kernel, acciones, traductor Resultado → HTTP, autenticación demo
│   ├── Aplicacion/             ← casos de uso, CadenaSellada, comandos, Resultado, puertos
│   ├── Dominio/                ← Movimiento, Sello, reglas y filtros (OCP), puertos Bitacora y Reloj
│   └── Persistencia/           ← PdoBitacora, PdoTransaccion, adaptadores de configuración
├── bin/demo-http.sh            ← recorrido de punta a punta con curl
└── tests/                      ← dominio, aplicación, PDO con dobles, HTTP, arquitectura, integración, mutaciones
```

**Regla de dependencias:** Presentación → Aplicación → Dominio ← Persistencia. La verifican las pruebas ARQ-01..ARQ-05.

## API

| Método | Ruta | Permiso | Respuestas |
|---|---|---|---|
| POST | `/api/v1/auditoria/movimientos` | `auditoria.registrar` (cuenta de servicio) | 201 · 200 `YA_REGISTRADO` · 403 · 409 · 422 · 503 |
| GET | `/api/v1/auditoria/movimientos?desde=&hasta=[&usuario=&modulo=&accion=&resultado=&cursor=]` | `auditoria.consultar` (`consultar_red` con `X-Tenant-ID: *`) | 200 · 403 · 422 · 503 |
| GET | `/api/v1/auditoria/movimientos/{id}` | `auditoria.consultar` | 200 · 404 (idéntico fuera del alcance) |
| POST | `/api/v1/auditoria/verificaciones` `{"hospital": …}` | `auditoria.verificar` | 200 `INTEGRA` / `ALTERADA` |

Todas exigen `X-Tenant-ID` (400) y `Authorization: Bearer` (401). Si el token no corresponde a ese hospital, la respuesta es 403.

## Ejecutar las pruebas

Requisitos: PHP 8.2+ con `pdo_pgsql` y `mbstring`.

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
php tests/ejecutar.php        # 39 pruebas; las 6 de integración se omiten sin base de datos
php tests/mutaciones.php      # 12 defectos inyectados; todos deben detectarse
```

**Con PostgreSQL real.** Cada ejecución crea un esquema temporal y lo elimina al terminar. Para usarlo, defina las variables y vuelva a correr los dos scripts:

```bash
export AUDITORIA_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=micro_his_auditoria"
export AUDITORIA_DB_USUARIO=postgres AUDITORIA_DB_CLAVE=…
php tests/ejecutar.php        # 39/39
php tests/mutaciones.php      # 12/12 (M1 y M11 solo las detecta la base real)
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
