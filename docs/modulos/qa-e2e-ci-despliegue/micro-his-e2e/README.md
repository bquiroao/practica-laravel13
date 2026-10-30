# Micro-HIS QA, pruebas E2E, CI y guía de despliegue final

PHP 8.2+ vanilla (probado con 8.4), sin framework ni Composer. Persistencia en PostgreSQL con PDO y sentencias preparadas.

Implementa el flujo de las semanas 1 y 2:
- escenarios E2E con datos sintéticos;
- versiones identificadas por el digest del artefacto;
- plan con todos los críticos, congelado al iniciar;
- ejecuciones con resultados que solo se agregan (INESTABLE cuando un PASA necesitó reintentos);
- evidencia por SHA-256 (fail-closed);
- quality gate con las reglas digest y a–e;
- trazabilidad.

## Requisitos

| Requisito | Para qué |
|---|---|
| PHP 8.2+ con `mbstring` y `pdo_pgsql` | Dominio y persistencia (el arranque verifica `mbstring`) |
| PostgreSQL 14+ | Esquema `e2e` (`database/schema.sql`) |

## Estructura

```
src/
  Dominio/        Modelo (EscenarioE2E, Version, Ejecucion, ResultadoEscenario, ReporteResultado (semana 7), Evidencia, PoliticaGate, Decision, QualityGate),
                  Puertos (repositorios, Reloj), Excepciones
  Aplicacion/     CasosDeUso (9; RegistrarDespliegue desde la semana 6), Puertos (PoliticaAcceso, AlmacenEvidencias, RegistroAuditoria, Transaccion), Resultados\Salida
  Persistencia/   Pdo, EnMemoria, Archivos (almacén por contenido), Configuracion, FabricaAdaptadores
  Cliente/        ReportadorCi (lado del pipeline: reintentos idempotentes y bandeja de pendientes), transportes
  Presentacion/   Http: Kernel (autentica y enruta), Formularios (forma, 400), Controladores (MVC), Vistas (códigos HTTP)
bootstrap/        autoload.php, contenedor.php (raíz de composición)
database/         schema.sql, datos-demo.sql / datos-demo.php, integracion-compartida.sql (semana 4)
api/              openapi.yaml (contrato 1.1.0: 1.0.0 en la semana 5 + despliegues en la 6; verificado por tests/Api)
bin/              instalar-base.php, demo-http.sh, medir.php y proxy-ingesta.php (frontera de microservicio, semana 5),
                  acoplamiento.php y caracterizar.php (refactor de la semana 7)
tests/            ejecutar.php, mutaciones.php, Dobles/, Dominio/, Aplicacion/, Persistencia/, Contratos/ (memoria y PDO), Mvc/,
                  Caracterizacion/ (35 respuestas grabadas antes del refactor), Presentacion/, Arquitectura/, Integracion/
```

## Ejecutar

```bash
php tests/ejecutar.php                                       # sin base: dominio, aplicación (dobles), HTTP y arquitectura
E2E_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" php tests/ejecutar.php     # + integración (recrea el esquema e2e)
E2E_DB_DSN=... php tests/mutaciones.php                      # 33 defectos sembrados: la suite debe detectarlos todos
E2E_DB_DSN=... bash bin/demo-http.sh                         # recorrido por HTTP real (php -S + curl)
```

## API (contrato completo en api/openapi.yaml)

| Método y ruta | Caso de uso | Permiso |
|---|---|---|
| `POST /api/v1/e2e/escenarios` | RegistrarEscenario | `e2e.disenar` |
| `POST /api/v1/e2e/versiones` | RegistrarVersion | `e2e.versionar` |
| `POST /api/v1/e2e/versiones/{v}/plan` | PlanificarPruebas | `e2e.planificar` |
| `POST /api/v1/e2e/versiones/{v}/ejecuciones` | IniciarEjecucion | `e2e.ejecutar` |
| `POST /api/v1/e2e/ejecuciones/{id}/resultados` (con `Idempotency-Key`) | ReportarResultado | `e2e.reportar` |
| `POST /api/v1/e2e/ejecuciones/{id}/cierre` | CerrarEjecucion | `e2e.ejecutar` |
| `POST /api/v1/e2e/versiones/{v}/gate` | EvaluarGate | `e2e.evaluar` |
| `GET /api/v1/e2e/versiones/{v}/trazabilidad` | ConsultarTrazabilidad | `e2e.consultar` |
| `POST /api/v1/e2e/versiones/{v}/despliegues` (semana 6) | RegistrarDespliegue | `e2e.desplegar` |
