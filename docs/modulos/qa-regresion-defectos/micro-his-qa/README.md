# Micro-HIS QA funcional, pruebas manuales y matriz de regresión

PHP 8.2+ vanilla (probado con 8.4), sin framework ni Composer. Persistencia en PostgreSQL con PDO y sentencias preparadas.

Implementa el flujo de las semanas 1 y 2: casos manuales versionados, campañas de regresión tomadas de la matriz, resultados con evidencia verificable, defectos con severidad, GO / NO-GO y trazabilidad.

## Estructura

```
src/
  Dominio/        Modelo (CasoPrueba, Campana, Intento, Defecto, Decision), Evidencia (semana 2), Excepciones, Puertos (repositorios, Reloj)
  Aplicacion/     CasosDeUso (9), Puertos (AlmacenEvidencias, PoliticaAcceso, RegistroAuditoria, Transaccion), Resultados\Salida
  Persistencia/   Pdo (repositorios, auditoría, transacción), Archivos (almacén de evidencias), Configuracion
  Presentacion/   Http (Kernel, AccionesQa, TraductorSalida, autenticación)
bootstrap/        autoload PSR-4 mínimo y raíz de composición
database/         schema.sql (esquema «qa») y datos-demo.sql
public/index.php  front controller
bin/              instalar-base.php, demo-http.sh
tests/            ejecutar.php, mutaciones.php, Dobles/, Dominio/, Aplicacion/, Persistencia/, Presentacion/, Arquitectura/, Integracion/
```

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
php tests/ejecutar.php                 # dominio, aplicación (dobles), persistencia (dobles de PDO), HTTP y arquitectura
QA_DB_DSN=... php tests/ejecutar.php   # además, integración con PostgreSQL real
QA_DB_DSN=... php tests/mutaciones.php # 16 defectos sembrados: la suite debe detectarlos todos
QA_DB_DSN=... bash bin/demo-http.sh    # recorrido HTTP con servidor real
```

Las pruebas de integración **borran y recrean el esquema `qa`**. Úselas solo contra una base de prueba.

La arquitectura, las decisiones y la evidencia están en [semana-03](../semana-03/semana-03-arquitectura-micro-his.md).
