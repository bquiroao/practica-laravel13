# Matriz decisión → evidencia — Módulo Notas SOAP y diagnósticos

**Estudiante:** Joshua Eduardo García Reyes · **Proceso:** «registro de nota SOAP y asociación de diagnóstico clínico»

Cada decisión de arquitectura de las semanas 1 a 6 está enlazada con:

- el requisito o la regla que la motiva;
- el lugar del código donde vive;
- la prueba o evidencia que la comprueba.

Las pruebas se ejecutan desde `docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap/` con `php tests/ejecutar.php`. Hoy hay **113 pruebas: 112 pasan, 0 fallan y 1 se omite** sin PostgreSQL (INT-06). Con PostgreSQL pasan las 113.

| ID | Decisión | Por qué (requisito / regla) | Dónde vive | Evidencia | Sem. |
|---|---|---|---|---|:-:|
| D-01 | Modelar el proceso en **3 vistas UML** con identificadores compartidos (CU, A, D, E, S) | Trazabilidad entre casos de uso, actividad y secuencia | [`semana-01/…uml-proceso-notas-soap.md`](../semana-01/semana-01-uml-proceso-notas-soap.md) §8 | Matriz de trazabilidad de la semana 1 | 1 |
| D-02 | Escribir las reglas del proceso como **R-01 a R-08** y citarlas en código y pruebas | Que cada regla se pueda seguir hasta su prueba | Semana 1 §2; comentarios `// R-0x` en los casos de uso | Las descripciones de DOM, APP y COR citan su R | 1 |
| D-03 | **SRP**: reglas en las entidades, coordinación en los casos de uso, acceso, persistencia y HTTP en clases distintas | RNF-04 (mantenibilidad) | `Dominio/Modelo/*`, `Aplicacion/CasosDeUso/*` | SRP-01..04 (semana 2); el cambio de la semana 6 agregó clases sin tocar los casos de uso existentes | 2·6 |
| D-04 | Nota + diagnóstico + evento de bitácora en **una transacción** | R-06, RNF-01: nunca una nota sin diagnóstico | Puerto `Transaccion`; `PdoTransaccion`, `TransaccionEnMemoria` | PER-03..05, INT-04, **COR-07** | 2·3·6 |
| D-05 | El **autor sale del token**, nunca del cuerpo | RF-06, RNF-02 | `FormularioRegistroNota`, `FormularioCorreccionNota` | HTTP-07; la corrección hereda el autor de la nota (COR-01) | 2·6 |
| D-06 | **4 capas**: Presentación → Aplicación → Dominio ← Persistencia | Que las reglas clínicas no dependan de HTTP ni de la base | `src/{Presentacion,Aplicacion,Dominio,Persistencia}` | ARQ-01..04, ARQ-07 | 3 |
| D-07 | **PDO con sentencias preparadas** reales (`EMULATE_PREPARES = false`) | Inyección SQL | `RepositorioPdo`, `FabricaConexion` | ARQ-05, ARQ-06, PER-01 | 3 |
| D-08 | La **nota firmada no cambia**, ni desde la aplicación ni desde la base | R-07, R-08 | `NotaSoap::firmar()`, `UPDATE … WHERE status = 'draft'`, triggers de `schema.sql` | DOM-07, APP-08, INT-05; paso 12 de la [demo en PostgreSQL](evidencia/salida-api-correccion-postgresql.txt) | 3·6 |
| D-09 | **MVC**: el controlador solo arma el comando, llama al caso de uso y entrega a la vista | Controlador sin SQL ni reglas de negocio | `Presentacion/{Controladores,Entrada,Vistas}` | ARQ-09, ARQ-11, HTTP-08, HTTP-09 | 4 |
| D-10 | **Interfaces Repository en el Dominio**, cada una con adaptador PDO **y** en memoria, y una suite de contrato común | Inversión de dependencias; pruebas sin base | `Dominio/Puertos/*`, `Persistencia/{Pdo,EnMemoria}` | REP-01..09 (los dos adaptadores), ARQ-10, ARQ-12 | 4·6 |
| D-11 | **Bandeja de salida**: `NotaSoapFirmada` con identificadores, en la misma transacción de la firma | Integrar con CENTRAL sin texto clínico | `RepositorioNotasConEventos`, `PdoBandejaSalida`, `EventoClinico` | APP-10, APP-11, INT-07, DOM-09 | 4 |
| D-12 | **Contrato OpenAPI 3.1** verificado contra el código | El contrato y la implementación no pueden divergir | `api/openapi.yaml` (v1.1.0) | API-01..04, [lint de Redocly](evidencia/salida-lint-openapi.txt); en el cambio detectaron la ruta sin documentar ([paso 2](evidencia/cambio-practico-2-contrato-desactualizado.txt)) | 5·6 |
| D-13 | **Permiso declarado por ruta**; las reglas finas (asignación, autor) siguen en el caso de uso | Dos barreras: lo grueso antes de tocar la base, lo fino con datos | `Rutas::definir()`, `PermisosPorRol`; `VerificadorAcceso` | HTTP-13, ARQ-14, **COR-04** | 5·6 |
| D-14 | **Idempotency-Key** en el registro | Un reintento no debe duplicar una nota clínica | `RegistroIdempotencia`, `idempotency_keys` | APP-13..16, HTTP-11, HTTP-12, INT-09, CLI-02 | 5 |
| D-15 | **No dividir** en microservicio (decisión medida) | La consigna exige justificación medible | `bin/medir.php`; criterios C1–C5 | [Medición Linux](../semana-05/evidencia/salida-medicion.txt): 0,039 ms en proceso frente a 0,549 ms por red local. En el equipo Windows del estudiante: 0,053 ms frente a 35 ms (PR #15) | 5 |
| D-16 | **Cliente resiliente**: timeout, un reintento, circuit breaker | Llamadas remotas que fallan sin colgar al que llama | `src/Cliente/*` | CLI-01..08 | 5 |
| D-17 | **Observabilidad**: `X-Request-ID` y registro de acceso sin token ni texto clínico | Trazar sin exponer datos (RNF-03) | `Kernel::manejar()`, `Observabilidad/*` | OBS-01..03 | 5 |
| **D-18** | **Cambio práctico: corregir = versión nueva.** La firmada no se toca; la nueva hereda expediente, episodio y autor, apunta con `supersedes_id` y explica el motivo | R-07 (semana 1), RF-11..15 (nuevos) | `NotaSoap::corregir()`, `CorregirNota`, `FormularioCorreccionNota`, `POST /api/v1/notas-soap/{id}/correcciones` | COR-01, COR-02, COR-05, COR-06, COR-08, DOM-10; [rojo](evidencia/cambio-practico-1-antes-rojo.txt) → [verde](evidencia/cambio-practico-3-despues-verde.txt) | 6 |
| **D-19** | **Una corrección por versión**, comprobada en la aplicación y garantizada por la base (`UNIQUE supersedes_id`), incluso con dos peticiones al mismo tiempo | RF-12: la historia es una cadena, no un árbol | `CorregirNota` (verifica y traduce el `23xxx` a 409), `schema.sql` | COR-03, REP-09, INT-11, INT-06 (PostgreSQL); paso 12 de la demo | 6 |

## Cómo leer la matriz en la defensa

1. Elegir una decisión.
2. Abrir el archivo de la columna «Dónde vive».
3. Ejecutar las pruebas de la columna «Evidencia».
4. Si la decisión se rompiera, esa prueba fallaría. Las semanas 3 a 5 lo comprobaron metiendo violaciones a propósito (ARQ-02, API-01, API-03, API-04, ARQ-13). En la semana 6 pasó sin provocarlo: al implementar la corrección antes de documentarla, API-01, API-03 y API-04 fallaron.
