# Micro-HIS RBAC: roles, permisos y protección de rutas

Micro-proyecto en **PHP 8.2+ vanilla** (sin framework ni Composer) que ejecuta el flujo «asignación de rol, autorización de una operación y denegación trazable».

| Semana | Evolución |
|---|---|
| 3 | Cuatro capas, PDO con sentencias preparadas, pruebas por capa y de arquitectura |
| 4 | Entrada con **MVC** (controladores, formularios, vistas); patrón **Repository** con adaptadores **PDO e InMemory**; suite de contrato |
| 5 | **Cliente-servidor**: contrato **OpenAPI 3.1**, endpoint de decisión (PDP), librería **cliente** con resiliencia, observabilidad (`X-Request-ID`) y medición de la frontera de microservicio |
| 6 | Cambio práctico defendido: **revocar rol** (permiso `roles.revocar`, `PoliticaRevocacion`, `revocar()` en PDO e InMemory, contrato v1.1.0), hecho con pruebas primero |

## Estructura

```
micro-his-rbac/
├── public/index.php              ← front controller (único punto de entrada HTTP)
├── api/openapi.yaml              ← contrato REST (OpenAPI 3.1), validado contra el código (API-01..03)
├── bin/demo.php                  ← flujo completo en un proceso, sin base de datos
├── bin/medir.php                 ← latencia de una decisión: en proceso vs. HTTP vs. red
├── bootstrap/
│   ├── autoload.php              ← autoload PSR-4 mínimo
│   └── contenedor.php            ← raíz de composición: elige PDO o InMemory y conecta las capas
├── config/config.php             ← configuración desde variables de entorno
├── database/
│   ├── schema.sql                ← tablas, índice único parcial y trigger append-only
│   ├── datos-demo.sql            ← datos ficticios para PostgreSQL
│   └── datos-demo.php            ← los mismos datos para el adaptador InMemory
├── src/
│   ├── Presentacion/Http/        ← Kernel, Rutas, Controladores, Formularios, Vistas (MVC)
│   ├── Aplicacion/               ← casos de uso, comandos, resultados, puerto Transaccion
│   ├── Dominio/                  ← modelo, reglas, políticas, puertos de repositorio, excepciones
│   ├── Persistencia/             ← Pdo/ (PostgreSQL) · EnMemoria/ · Archivo/ (respaldo)
│   └── Cliente/                  ← librería para otros módulos: timeout, reintento, circuit breaker, fail-closed
└── tests/                        ← dominio, aplicación, persistencia, contrato, MVC/HTTP, arquitectura, integración
```

Regla de dependencias: **Presentación → Aplicación → Dominio ← Persistencia**; `Cliente` no conoce ninguna clase del servidor, solo el contrato HTTP. Se verifica con las pruebas `ARQ-01` a `ARQ-08`.

## Ejecutar las pruebas

```bash
cd docs/modulos/rbac/micro-his-rbac
php tests/ejecutar.php
```

No necesitan base de datos. Para correr también la suite de contrato contra PDO y las pruebas de integración, que crean y eliminan un esquema temporal:

```powershell
$env:MICROHIS_DB_DSN = "pgsql:host=127.0.0.1;port=5432;dbname=postgres"
$env:MICROHIS_DB_USUARIO = "postgres"
$env:MICROHIS_DB_CLAVE = "<tu contraseña>"
php tests/ejecutar.php
```

## Demostración sin base de datos

```bash
php bin/demo.php
```

## Contrato y medición

```bash
npx @redocly/cli@1.34.3 lint api/openapi.yaml   # valida el contrato
php bin/medir.php 5000 300                      # costo de una decisión en 3 despliegues
```

## Ejecutar la API

| `MICROHIS_PERSISTENCIA` | Adaptadores | Requisitos |
|---|---|---|
| `pdo` (por defecto) | PostgreSQL | Base creada con `schema.sql` + `datos-demo.sql`, más las variables `MICROHIS_DB_*` |
| `memoria` | InMemory con `datos-demo.php` | Ninguno. El estado se reinicia en cada petición HTTP. |

```bash
# Con PostgreSQL
createdb -U postgres micro_his_rbac
psql -U postgres -d micro_his_rbac -f database/schema.sql
psql -U postgres -d micro_his_rbac -f database/datos-demo.sql
php -S 127.0.0.1:8080 -t public

# Sin base de datos (PowerShell)
$env:MICROHIS_PERSISTENCIA = "memoria"; php -S 127.0.0.1:8080 -t public
```

Tokens de demostración: `token-admin-oriente`, `token-ana`, `token-marta` y `token-admin-norte`.

```bash
curl -i -X POST http://127.0.0.1:8080/api/v1/prescripciones \
     -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-ana"
```

## Rutas

Contrato completo en [`api/openapi.yaml`](api/openapi.yaml). Toda respuesta incluye `X-Request-ID`.


| Método | Ruta | Permiso | Controlador |
|---|---|---|---|
| POST | `/api/v1/roles/asignaciones` | `roles.asignar` | `AsignacionRolControlador::crear` |
| POST | `/api/v1/roles/revocaciones` | `roles.revocar` | `RevocacionRolControlador::crear` |
| GET | `/api/v1/auditoria/eventos/{correlationId}` | `auditoria.consultar` | `AuditoriaControlador::mostrar` |
| POST | `/api/v1/autorizaciones/decisiones` | — (solo autenticación) | `AutorizacionControlador::decidir` (PDP) |
| POST | `/api/v1/prescripciones` | `prescripciones.crear` | `OperacionClinicaControlador::prescribir` (demo) |
| POST | `/api/v1/signos-vitales` | `signos_vitales.registrar` | `OperacionClinicaControlador::registrarSignosVitales` (demo) |
