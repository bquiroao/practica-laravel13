# Micro-HIS QA funcional, pruebas manuales y matriz de regresión

PHP 8.2+ vanilla (probado con 8.4), sin framework ni Composer. Persistencia en PostgreSQL con PDO y sentencias preparadas.

## Requisitos

| Requisito | Para qué | Si falta |
|---|---|---|
| PHP 8.2 o superior | Enums, `readonly`, `match` | No arranca |
| Extensión `mbstring` | Validaciones del Dominio (`mb_strlen`, `mb_check_encoding`) | El arranque falla con un mensaje que la nombra |
| Extensión `pdo_pgsql` | Persistencia `pdo` (por defecto) | `ErrorPersistencia` al abrir la conexión; el modo `memoria` funciona sin ella |
| PostgreSQL 14 o superior | Base de datos | Las pruebas de integración se omiten sin `QA_DB_DSN` |

Verificación rápida: `php -m | grep -E "mbstring|pdo_pgsql"`.

Implementa el flujo de las semanas 1 y 2: casos manuales versionados, campañas de regresión tomadas de la matriz, resultados con evidencia verificable, defectos con severidad, GO / NO-GO y trazabilidad.

## Estructura

```
src/
  Dominio/        Modelo (CasoPrueba, Campana, Intento, Defecto, Decision), Evidencia (semana 2), Excepciones, Puertos (repositorios, Reloj)
  Aplicacion/     CasosDeUso (9), Puertos (AlmacenEvidencias, PoliticaAcceso, RegistroAuditoria, Transaccion), Resultados\Salida
  Persistencia/   Pdo (repositorios, auditoría, transacción), EnMemoria (los mismos puertos sin base), Archivos (almacén de
                  evidencias), Configuracion, FabricaAdaptadores (pdo | memoria)
  Presentacion/   Http: Kernel y autenticación; MVC: Formularios, Controladores (+ Rutas), Vistas
bootstrap/        autoload PSR-4 mínimo y raíz de composición
database/         schema.sql (esquema «qa»), datos-demo.sql / datos-demo.php, integracion-central.sql (semana 4)
public/index.php  front controller
bin/              instalar-base.php, demo.php (en memoria), demo-http.sh, validar-integracion-central.php
tests/            ejecutar.php, mutaciones.php, Dobles/ (decoradores con fallas), Dominio/, Aplicacion/, Persistencia/,
                  Contratos/ (Repository: memoria y PDO), Mvc/, Presentacion/, Arquitectura/, Integracion/,
                  Semana15/ (unitarias, caja blanca y caja negra trazadas a RF/RNF)
```

**Sin base de datos:** `QA_PERSISTENCIA=memoria` usa los adaptadores en memoria con `database/datos-demo.php`. Como la memoria no sobrevive entre peticiones de `php -S`, para recorrer el flujo completo use `php bin/demo.php`.

## Uso

```bash
# 1. Base de prueba (PostgreSQL 14+)
createdb micro_his_qa
export QA_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=micro_his_qa"   # QA_DB_USUARIO / QA_DB_CLAVE si hace falta
php bin/instalar-base.php

# 2. Servidor
php -S 127.0.0.1:8093 -t public

# 3. Una petición
curl -s http://127.0.0.1:8093/api/v1/qa/campanas -H "Authorization: Bearer token-lider-qa" -H "X-Tenant-ID: hosp-norte" \
  -H "Content-Type: application/json" -d '{"nombre":"Regresion H4","funcionalidades":["expediente"],"casos":["TC-107","TC-108"]}'
```

**Tokens de demostración** (todos de `hosp-norte`, salvo `token-tester-sur`):

| Token | Rol |
|---|---|
| `token-ana-qa` | Analista QA |
| `token-lider-qa` | Líder QA |
| `token-tito-tester` | Tester |
| `token-dev-luis` | Desarrollador |
| `token-producto` | Responsable de producto |
| `token-tester-sur` | Tester de `hosp-sur` |

## Pruebas

```bash
php tests/ejecutar.php                 # dominio, aplicación, persistencia, contrato (memoria), MVC, HTTP y arquitectura
QA_DB_DSN=... php tests/ejecutar.php   # además, integración con PostgreSQL real
QA_DB_DSN=... php tests/mutaciones.php # defectos sembrados (40): la suite debe detectarlos todos
QA_DB_DSN=... bash bin/demo-http.sh    # recorrido HTTP con servidor real
QA_DB_DSN=... php bin/validar-integracion-central.php   # integración con la base central del HIS (semana 4)
```

Las pruebas de integración **borran y recrean el esquema `qa`**. Úselas solo contra una base de prueba.

Arquitectura y evidencia: [semana 3](../semana-03/semana-03-arquitectura-micro-his.md) (capas, PDO, C4) · [semana 4](../semana-04/semana-04-capas-mvc-repositorio.md) (MVC, Repository, repositorio compartido) · [semana 5](../semana-05/semana-05-cliente-servidor-microservicios.md) (contrato [OpenAPI](api/openapi.yaml), cliente, almacén remoto, medición: `php bin/medir.php`).
