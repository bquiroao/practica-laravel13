# Micro-HIS Prescripciones electrónicas con validación de alergias

Micro-HIS en **PHP 8.2+ vanilla** (sin framework ni Composer) para el flujo «creación de prescripción con verificación previa de alergias». Tiene cuatro capas (Presentación, Aplicación, Dominio y Persistencia), usa PDO con sentencias preparadas sobre PostgreSQL e incluye pruebas con dobles y con una base real.

**Estudiante:** Boris Alexander Quiroa Orellana · Arquitectura: [semana 3](../semana-03/semana-03-arquitectura-micro-his.md)

## Estructura

```
micro-his-prescripciones/
├── public/index.php            ← front controller (php -S)
├── bootstrap/contenedor.php    ← raíz de composición: único lugar con clases concretas
├── config/config.php           ← DSN por variables de entorno, tokens demo y permisos provisionales
├── database/schema.sql         ← allergies (columnas reales del HIS), medications, prescriptions (inmutable), outbox
├── database/datos-demo.sql     ← pacientes P-001..P-007 y P-900, alergias, catálogo (ficticios)
├── bin/instalar-base.php       ← recrea el esquema y carga los datos
├── bin/demo-http.sh            ← recorrido HTTP real con curl
├── src/
│   ├── Presentacion/Http/      ← Kernel, AccionesPrescripciones, TraductorResultado, autenticación
│   ├── Aplicacion/             ← CasosDeUso, Servicios/EvaluacionClinica, Puertos, Resultados
│   ├── Dominio/                ← Modelo, Reglas (validador y verificador), Puertos (repositorio, reloj), Excepciones
│   └── Persistencia/           ← Pdo/* (6 adaptadores), Configuracion/* (stand-ins de RBAC y reloj)
└── tests/                      ← arnés propio, dobles, 55 pruebas y mutaciones
```

## API

| Método y ruta | Caso de uso | Respuestas |
|---|---|---|
| `POST /api/v1/prescripciones/verificaciones` | Verificación previa (CU-02), sin efectos | 200 `SIN_HALLAZGOS` · `ADVERTENCIA` · `BLOQUEO` · `CONFIRMACION_REQUERIDA` · `VERIFICACION_NO_DISPONIBLE` |
| `POST /api/v1/prescripciones` + `Idempotency-Key` | Emitir (CU-01) | 201 + `Location` · 200 `YA_EMITIDA` · 409 · 422 · 503 |
| `GET /api/v1/prescripciones/{id}` | Consultar (CU-09) | 200 · 404 |

Todas exigen `Authorization: Bearer <token>` y `X-Tenant-ID` igual al hospital del token.

## Ejecutar

```bash
cd docs/modulos/prescripciones-alergias/micro-his-prescripciones
php tests/ejecutar.php                                   # sin base: 46 pasan, 9 omitidas (integración)

export PRESCRIPCIONES_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_prescripciones"
export PRESCRIPCIONES_DB_USUARIO=postgres
php tests/ejecutar.php                                   # 55/55 con PostgreSQL
php tests/mutaciones.php                                 # 14/14 mutaciones detectadas
bash bin/demo-http.sh                                    # recorrido HTTP con php -S
php bin/instalar-base.php && php -S 127.0.0.1:8080 -t public
```

Las pruebas de integración **recrean el esquema `prescripciones`** en la base indicada: use una base de prueba.
