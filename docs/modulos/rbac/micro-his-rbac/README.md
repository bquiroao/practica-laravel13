# Micro-HIS RBAC: roles, permisos y protección de rutas

Micro-proyecto en **PHP 8.2+ vanilla** (sin framework ni Composer) que ejecuta el flujo «asignación de rol, autorización de una operación y denegación trazable». Se inició en la semana 3 y continúa en las semanas 4 y 5.

## Estructura por capas

```
micro-his-rbac/
├── public/index.php              ← front controller (único punto de entrada HTTP)
├── bootstrap/
│   ├── autoload.php              ← autoload PSR-4 mínimo
│   └── contenedor.php            ← raíz de composición: conecta las capas
├── config/config.php             ← configuración desde variables de entorno
├── database/
│   ├── schema.sql                ← tablas, índice único parcial y trigger append-only
│   └── datos-demo.sql            ← datos ficticios
├── src/
│   ├── Presentacion/Http/        ← Kernel, rutas, middleware, acciones, autenticación
│   ├── Aplicacion/               ← casos de uso, comandos, resultados, puerto Transaccion
│   ├── Dominio/                  ← modelo, reglas, políticas, puertos de repositorio, excepciones
│   └── Persistencia/             ← adaptadores PDO (sentencias preparadas) y respaldo en archivo
└── tests/                        ← pruebas por capa + arquitectura + integración opcional
```

Regla de dependencias: **Presentación → Aplicación → Dominio ← Persistencia**. Se verifica automáticamente con las pruebas `ARQ-01` a `ARQ-04`.

## Ejecutar las pruebas

No necesitan base de datos: usan dobles en memoria y dobles de PDO.

```bash
cd docs/modulos/rbac/micro-his-rbac
php tests/ejecutar.php
```

### Pruebas de integración con PostgreSQL (opcional)

Crean un esquema temporal, aplican `schema.sql` y `datos-demo.sql`, ejecutan las pruebas y eliminan el esquema.

```powershell
$env:MICROHIS_DB_DSN = "pgsql:host=127.0.0.1;port=5432;dbname=postgres"
$env:MICROHIS_DB_USUARIO = "postgres"
$env:MICROHIS_DB_CLAVE = "<tu contraseña>"
php tests/ejecutar.php
```

## Ejecutar la API

1. Crear la base y cargar el esquema:

   ```bash
   createdb -U postgres micro_his_rbac
   psql -U postgres -d micro_his_rbac -f database/schema.sql
   psql -U postgres -d micro_his_rbac -f database/datos-demo.sql
   ```

2. Definir las variables de entorno (`MICROHIS_DB_DSN`, `MICROHIS_DB_USUARIO` y `MICROHIS_DB_CLAVE`) y levantar el servidor:

   ```bash
   php -S 127.0.0.1:8080 -t public
   ```

3. Probar con los tokens de demostración (`token-admin-oriente`, `token-ana`, `token-marta`, `token-admin-norte`):

   ```bash
   # 403: la enfermera no puede prescribir (la respuesta incluye correlation_id)
   curl -i -X POST http://127.0.0.1:8080/api/v1/prescripciones \
        -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-ana"

   # 201: el administrador asigna un rol
   curl -i -X POST http://127.0.0.1:8080/api/v1/roles/asignaciones \
        -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-admin-oriente" \
        -H "Content-Type: application/json" \
        -d '{"usuario_id":"nuevo-01","rol":"laboratorio","valido_desde":"2026-11-15T00:00:00-06:00"}'

   # 200: el administrador consulta la denegación por su correlation_id
   curl -i http://127.0.0.1:8080/api/v1/auditoria/eventos/<correlation_id> \
        -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-admin-oriente"
   ```

Si la base de datos no está disponible, la API responde `503` con un mensaje controlado.

## Rutas

| Método | Ruta | Permiso | Caso de uso |
|---|---|---|---|
| POST | `/api/v1/roles/asignaciones` | `roles.asignar` | CU-01 Asignar rol |
| GET | `/api/v1/auditoria/eventos/{correlationId}` | `auditoria.consultar` | CU-06 Consultar evento |
| POST | `/api/v1/prescripciones` | `prescripciones.crear` | CU-02 (operación demo) |
| POST | `/api/v1/signos-vitales` | `signos_vitales.registrar` | CU-02 (operación demo) |
