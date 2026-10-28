# Matriz decisión → evidencia — Módulo RBAC

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «asignación de rol, autorización de una operación y denegación trazable»

Cada decisión de arquitectura de las semanas 1 a 6 está enlazada con el requisito que la motiva, el lugar donde vive y la evidencia que la comprueba. Las pruebas se ejecutan con `php tests/ejecutar.php` desde `docs/modulos/rbac/micro-his-rbac/`: hoy hay **67 pruebas; 65 pasan, 0 fallan y 2 se omiten** (las omitidas requieren PostgreSQL).

| ID | Decisión | Por qué (requisito / regla) | Dónde vive | Evidencia | Sem. |
|---|---|---|---|---|:-:|
| D-01 | Modelar el proceso en **3 vistas UML** con identificadores compartidos (CU, A, D, E, S, R) | Trazabilidad entre casos de uso, actividad y secuencia | [`semana-01/…uml-proceso-rbac.md`](../semana-01/semana-01-uml-proceso-rbac.md) §8 | Matriz de trazabilidad de la semana 1 | 1 |
| D-02 | La **denegación es un caso de uso** (CU-04 «extend» de CU-03) con `correlation_id` y evento `ACCESO_DENEGADO` | R-06: toda denegación deja evidencia | `AutorizarOperacion::denegar()` | APP-03, HTTP-03, CLI-07, INT-02 | 1·3 |
| D-03 | **OCP**: cada regla es una clase que implementa `ReglaAutorizacion` / `ReglaAsignacion` / `ReglaRevocacion` | RNF-05: agregar reglas sin modificar el decisor | `Dominio/Reglas/*`, `Dominio/Servicios/Politica*` | DOM-02, OCP-01..03 (S2), **REV-01..06** (extensión S6) | 2·6 |
| D-04 | **Fail-closed** en todas las capas | RNF-01 / R-05: ante la duda, denegar | `PoliticaAutorizacion`, `AutorizarOperacion`, `Kernel`, `ClienteRbac` | DOM-03, PER-08, PER-10, HTTP-08, CLI-03, CLI-06 | 2·3·5 |
| D-05 | Cambio de rol + evidencia en **una transacción** | R-07: no hay rol sin evidencia | Puerto `Transaccion`; `PdoTransaccion`, `TransaccionEnMemoria` | PER-04, PER-06, INT-05, **REV-05** | 3·6 |
| D-06 | El 403 **no revela el motivo** (el resultado no tiene ese campo) | R-08 / RNF-04 | `ResultadoAutorizacion`, `ErrorVista::accesoDenegado` | APP-03, HTTP-03, MVC-02, REV-06 | 3·4 |
| D-07 | **4 capas**: Presentación → Aplicación → Dominio ← Persistencia | Aislar las reglas de HTTP y de la base de datos | `src/{Presentacion,Aplicacion,Dominio,Persistencia}` | ARQ-01, ARQ-02, ARQ-04 | 3 |
| D-08 | **Interfaces Repository en el Dominio**; adaptadores PDO **e** InMemory | Inversión de dependencias; pruebas sin base de datos | `Dominio/Puertos/*`, `Persistencia/{Pdo,EnMemoria}` | ARQ-06, CON-MEM-01..07, (CON-PDO) | 4 |
| D-09 | **PDO con sentencias preparadas** reales (`EMULATE_PREPARES=false`) | Seguridad (inyección SQL) | `RepositorioPdo::ejecutar()`, `FabricaConexion` | ARQ-03, PER-01 | 3 |
| D-10 | **MVC**: controladores delgados, formularios (formato) y vistas (formato de la respuesta) | Controlador sin SQL ni reglas de negocio | `Presentacion/Http/{Controladores,Formularios,Vistas}` | ARQ-05, ARQ-07, MVC-01..03 | 4 |
| D-11 | **Permiso declarado por ruta**; solo el PDP queda sin permiso propio | Protección de rutas revisable en un solo lugar | `Rutas::definir()` | ARQ-08, API-01 | 3·5 |
| D-12 | **Contrato OpenAPI 3.1** verificado contra el código | El contrato y la implementación no pueden divergir | `api/openapi.yaml` (v1.1.0) | API-01..03, lint de Redocly ([evidencia](evidencia/salida-lint-openapi.txt)) | 5·6 |
| D-13 | **No dividir** en microservicio (decisión medida) | La tarea exige justificación medible: 0,006 ms en proceso frente a 33–62 ms por red | `bin/medir.php`; criterios C1–C5 | [`semana-05/evidencia/salida-medicion.txt`](../semana-05/evidencia/salida-medicion.txt) | 5 |
| D-14 | **Cliente resiliente**: timeout, 1 reintento, circuit breaker, fail-closed | Llamadas remotas seguras | `src/Cliente/*` | CLI-01..06 | 5 |
| D-15 | **Observabilidad**: `X-Request-ID` + registro de acceso sin secretos | Trazar entre módulos | `Kernel::manejar()` | OBS-01..03, [registro real](../semana-05/evidencia/salida-registro-acceso.txt) | 5 |
| D-16 | **Aislamiento por tenant** (hospital) | R-01, OWASP API1 | `ReglaTenantCoincide`, `ConsultarEventoAuditoria` | DOM-02, HTTP-07, APP-06, HTTP-06 | 1·3 |
| **D-17** | **Cambio práctico: revocar rol** como **extensión**: permiso nuevo `roles.revocar`, `PoliticaRevocacion` + 3 reglas, puerto `revocar()`, ruta protegida, evento `ROL_REVOCADO` | RF-11 (nuevo): revocar con motivo auditable; la decisión de autorización debe cambiar de inmediato | `RevocarRol`, `Dominio/Reglas/Revocacion/*`, `RevocacionRolControlador`, `POST /api/v1/roles/revocaciones` | REV-01..06, CON-MEM-07, API-01/03; [rojo](evidencia/cambio-practico-1-antes-rojo.txt) → [verde](evidencia/cambio-practico-3-despues-verde.txt) | 6 |

## Cómo leer la matriz en la defensa

1. Elegir una decisión.
2. Abrir el archivo de la columna «Dónde vive».
3. Ejecutar las pruebas de la columna «Evidencia».
4. Si la decisión se rompiera, esa prueba fallaría. Las semanas 3, 4 y 5 lo comprobaron inyectando violaciones a propósito (ARQ-01/02/03/05/06/07, API-01/03).
