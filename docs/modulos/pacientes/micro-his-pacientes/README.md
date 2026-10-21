# Micro-HIS Pacientes: registro, edición, búsqueda y detalle

Micro-proyecto en **PHP 8.2+ vanilla** (sin framework ni Composer) que ejecuta el flujo «registro, búsqueda y actualización segura de un paciente». Se inició en la semana 3 y crecerá en las semanas siguientes. Todos los datos de prueba son ficticios.

## Estructura por capas

```
micro-his-pacientes/
├── public/index.php              ← front controller (único punto de entrada HTTP)
├── bootstrap/
│   ├── autoload.php              ← autoload PSR-4 mínimo
│   └── contenedor.php            ← raíz de composición: conecta las capas
├── config/config.php             ← configuración desde variables de entorno
├── database/schema.sql           ← pacientes (documento cifrado + hash, versión) y bitácora append-only
├── bin/
│   ├── generar-claves.php        ← claves de hash y cifrado del documento
│   └── cargar-demo.php           ← pacientes ficticios cargados a través de los casos de uso
├── src/
│   ├── Presentacion/Http/        ← Kernel, rutas, acciones, traductor de resultados, autenticación
│   ├── Aplicacion/               ← casos de uso, comandos, Resultado, ControlAcceso, puertos
│   ├── Dominio/                  ← modelo, políticas, puertos de repositorio, excepciones
│   └── Persistencia/             ← adaptadores PDO, protección del documento, configuración
└── tests/                        ← pruebas por capa + arquitectura + integración opcional + mutaciones
```

Regla de dependencias: **Presentación → Aplicación → Dominio ← Persistencia**. La verifican automáticamente las pruebas `ARQ-01` a `ARQ-05`.

## Ejecutar las pruebas

No necesitan base de datos: usan dobles en memoria y dobles de PDO.

```bash
cd docs/modulos/pacientes/micro-his-pacientes
php tests/ejecutar.php      # 38 pruebas (1 omitida si no hay PostgreSQL)
php tests/mutaciones.php    # 8 defectos introducidos a propósito; todos deben detectarse
```

### Pruebas de integración con PostgreSQL (opcional)

Crean un esquema temporal, aplican `schema.sql`, ejecutan las pruebas y eliminan el esquema.

```powershell
$env:PACIENTES_DB_DSN = "pgsql:host=127.0.0.1;port=5432;dbname=postgres"
$env:PACIENTES_DB_USUARIO = "postgres"
$env:PACIENTES_DB_CLAVE = "<tu contraseña>"
php tests/ejecutar.php
```

## Ejecutar la API

1. Crear la base y cargar el esquema:

   ```bash
   createdb -U postgres micro_his_pacientes
   psql -U postgres -d micro_his_pacientes -f database/schema.sql
   ```

2. Generar las claves del documento y definir las variables de entorno:

   ```bash
   php bin/generar-claves.php
   # Copiar PACIENTES_CLAVE_HASH y PACIENTES_CLAVE_CIFRADO, más PACIENTES_DB_DSN, PACIENTES_DB_USUARIO y PACIENTES_DB_CLAVE
   ```

3. Cargar los pacientes de demostración y levantar el servidor:

   ```bash
   php bin/cargar-demo.php
   php -S 127.0.0.1:8081 -t public
   ```

4. Probar con los tokens de demostración (`token-admision-oriente`, `token-clinico-oriente`, `token-supervisor-oriente`, `token-admision-norte`):

   ```bash
   # 200: búsqueda con datos mínimos y documento enmascarado
   curl -i "http://127.0.0.1:8081/api/v1/pacientes?nombre=lopez" \
        -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-clinico-oriente"

   # 200 con ETag: "1"
   curl -i http://127.0.0.1:8081/api/v1/pacientes/P-000001 \
        -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-admision-oriente"

   # 200 con ETag: "2"; repetir con If-Match: "1" da 409 VERSION_DESACTUALIZADA
   curl -i -X PUT http://127.0.0.1:8081/api/v1/pacientes/P-000001 \
        -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-admision-oriente" \
        -H 'If-Match: "1"' -H "Content-Type: application/json" \
        -d '{"nombres":"Ana Lucía","apellidos":"López Pérez","fecha_nacimiento":"1985-03-14","sexo":"female","telefono":"+502 4444 0001","documento":{"tipo":"DPI","numero":"2547896320101"}}'
   ```

Si faltan las claves, la API responde `503 CONFIGURACION_INCOMPLETA`. Si la base de datos no está disponible, responde `503 SERVICIO_NO_DISPONIBLE`. En ningún caso expone detalles internos.

## Rutas

| Método | Ruta | Permiso | Caso de uso | Respuestas |
|---|---|---|---|---|
| POST | `/api/v1/pacientes` | `pacientes.registrar` (+ `.confirmar_duplicado`) | CU-01, CU-05, CU-06 | 201 · 400 · 403 · 409 · 422 · 503 |
| GET | `/api/v1/pacientes?nombre=&nacimiento=&codigo=&tipo_documento=&numero_documento=&pais_documento=` | `pacientes.buscar` | CU-02 | 200 · 403 · 422 · 503 |
| GET | `/api/v1/pacientes/{codigo}` | `pacientes.leer` | CU-03 | 200 (ETag) · 403 · 404 · 503 |
| PUT | `/api/v1/pacientes/{codigo}` con `If-Match` | `pacientes.editar` (+ `.editar_restringido`) | CU-04 | 200 (ETag) · 400 · 403 · 404 · 409 · 422 · 428 · 503 |

Todas exigen `X-Tenant-ID` (400) y `Authorization: Bearer` (401), y el token debe ser del mismo hospital (403).
