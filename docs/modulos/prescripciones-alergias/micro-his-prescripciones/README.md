# Micro-HIS Prescripciones electrónicas con validación de alergias

Micro-HIS en **PHP 8.2+ vanilla** (sin framework ni Composer) para el flujo «creación de prescripción con verificación previa de alergias». Tiene:
- cuatro capas: Presentación (MVC), Aplicación, Dominio y Persistencia;
- puertos con adaptadores **PDO** (PostgreSQL) e **InMemory**;
- pruebas con dobles, una suite de contrato y una base real.

**Estudiante:** Boris Alexander Quiroa Orellana · Arquitectura: [semana 3](../semana-03/semana-03-arquitectura-micro-his.md) · Capas, MVC y Repository: [semana 4](../semana-04/semana-04-capas-mvc-repositorio.md) · Cliente-servidor y frontera: [semana 5](../semana-05/semana-05-cliente-servidor-microservicios.md)

## Estructura

```
micro-his-prescripciones/
├── api/openapi.yaml              ← contrato OpenAPI 3.1 (1.1.0), verificado contra el código (API-01..03)
├── public/index.php              ← front controller (php -S)
├── bootstrap/contenedor.php      ← raíz de composición: único lugar con clases concretas
├── config/config.php             ← persistencia (pdo|memoria), DSN por entorno, tokens demo y permisos provisionales
├── database/
│   ├── schema.sql                ← allergies (columnas reales del HIS), medications, prescriptions (inmutable), bandeja
│   ├── datos-demo.sql / .php     ← los mismos datos ficticios para PDO y para memoria
│   └── integracion-hospital.sql  ← propuesta H5 sobre la base real del hospital (repositorio compartido)
├── bin/
│   ├── instalar-base.php         ← recrea el esquema y carga los datos
│   ├── demo.php                  ← recorrido sin base (modo memoria)
│   ├── demo-anulacion.php        ← cambio práctico de la semana 6, sin base
│   ├── demo-http.sh              ← recorrido HTTP real con curl y PostgreSQL
│   ├── medir.php                 ← medición de la frontera (en proceso, HTTP, Expediente remoto y caído)
│   ├── expediente-simulado.php   ← Expediente como servicio aparte, solo para evaluar la frontera
│   └── validar-integracion-hospital.php ← aplica las migraciones reales del hospital + integracion-hospital.sql y comprueba 13 afirmaciones
├── src/
│   ├── Presentacion/Http/        ← Kernel; Formularios, Controladores y Vistas (MVC); autenticación
│   ├── Aplicacion/               ← CasosDeUso, Servicios/EvaluacionClinica, Puertos, Resultados
│   ├── Dominio/                  ← Modelo, Reglas, Puertos (RepositorioPrescripciones, Reloj), Excepciones
│   ├── Persistencia/             ← Pdo/* y EnMemoria/* (6 + 6), Http/HttpFuenteAlergias + CircuitoInterruptor, FabricaAdaptadores
│   └── Cliente/                  ← ClientePrescripciones (reintento idempotente, sin bandeja) y transporte HTTP
└── tests/                        ← arnés propio, decoradores de prueba, suite de contrato, 107 pruebas y 33 mutaciones
```

## API

| Método y ruta | Caso de uso | Respuestas |
|---|---|---|
| `POST /api/v1/prescripciones/verificaciones` | Verificación previa (CU-02), sin efectos | 200 `SIN_HALLAZGOS` · `ADVERTENCIA` · `BLOQUEO` · `CONFIRMACION_REQUERIDA` · `VERIFICACION_NO_DISPONIBLE` |
| `POST /api/v1/prescripciones` + `Idempotency-Key` | Emitir (CU-01) | 201 + `Location` · 200 `YA_EMITIDA` · 400 · 409 · 422 · 503 |
| `GET /api/v1/prescripciones/{id}` | Consultar (CU-09) | 200 · 404 |
| `POST /api/v1/prescripciones/{id}/anulacion` | Anular con motivo (CU-07, semana 6) | 200 `ANULADA` · 400 · 403 · 404 · 409 · 422 · 503 |

Todas exigen `Authorization: Bearer <token>` y `X-Tenant-ID` igual al hospital del token, y devuelven `X-Request-ID`. Contrato completo: [api/openapi.yaml](api/openapi.yaml).

## Ejecutar

```bash
cd docs/modulos/prescripciones-alergias/micro-his-prescripciones
php tests/ejecutar.php                                   # sin base: 89 pasan, 18 omitidas
php bin/demo.php                                         # demo sin base (adaptadores InMemory)

export PRESCRIPCIONES_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_prescripciones"
export PRESCRIPCIONES_DB_USUARIO=postgres
php tests/ejecutar.php                                   # 107/107 con PostgreSQL
php tests/mutaciones.php                                 # 33/33 mutaciones detectadas
php bin/medir.php 200                                    # medición de la frontera (semana 5)
php bin/validar-integracion-hospital.php                 # 13/13 sobre las migraciones reales del hospital
bash bin/demo-http.sh                                    # recorrido HTTP con php -S
PRESCRIPCIONES_PERSISTENCIA=memoria php -S 127.0.0.1:8080 -t public   # servidor sin base
```

Las pruebas de integración y de contrato **recrean el esquema `prescripciones`** en la base indicada, así que hay que usar una base de prueba.
