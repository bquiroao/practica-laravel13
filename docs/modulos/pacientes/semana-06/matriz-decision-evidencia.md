# Matriz decisión → evidencia — Módulo Pacientes (ASII-03)

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «registro, búsqueda y actualización segura de un paciente»

Cada decisión de arquitectura de las semanas 1 a 6 está enlazada con:
- el requisito o la regla que la motiva;
- el lugar del código donde vive;
- la prueba que la comprueba.

Las pruebas se ejecutan con `php tests/ejecutar.php` desde `docs/modulos/pacientes/micro-his-pacientes/`. Hoy hay **80 pruebas: 78 pasan, 0 fallan y 2 se omiten** (requieren PostgreSQL). `php tests/mutaciones.php` introduce 22 defectos, y las pruebas detectan los 22.

| ID | Decisión | Por qué (requisito / regla) | Dónde vive | Evidencia | Sem. |
|---|---|---|---|---|:-:|
| D-01 | Modelar el proceso en **3 vistas UML** con identificadores compartidos (CU, A, D, E, F, R, S) | Trazabilidad entre casos de uso, actividad y secuencia | [`semana-01/…uml-proceso-pacientes.md`](../semana-01/semana-01-uml-proceso-pacientes.md) §9 | Matriz de trazabilidad de la semana 1 | 1 |
| D-02 | **El mismo documento bloquea; nombre y fecha iguales advierten** (y solo un supervisor confirma, con motivo) | R-02, R-03: una sola ficha por persona | `PoliticaDuplicados` | DOM-06, APP-02, CON-MEM-04, PER-04, PROT-05 | 1·3 |
| D-03 | **LSP**: sin documento = `identificador null` (identidad provisional), nunca un identificador «vacío» | RNF-01: los subtipos de `Identificador` deben cumplir su contrato | `Dominio/Modelo/Identificador.php`, `Paciente::identidadProvisional()` | DOM-04 (contrato), ejemplo LSP de la semana 2 (ANT-01, ANT-05) | 2 |
| D-04 | **LSP**: lectura separada de la escritura (`ConsultaPacientes ⊂ RepositorioPacientes`) | Una réplica de lectura no debe prometer que escribe | `Dominio/Puertos/*`, `BuscarPacientes` recibe `ConsultaPacientes` | Semana 2: LSP-02, LSP-05; hoy CON-MEM-01..02 | 2·4 |
| D-05 | **Concurrencia optimista** en una sola sentencia (`UPDATE … WHERE version`) + `ETag`/`If-Match` (428 sin él) | R-06: no perder la edición de otro usuario | `PdoRepositorioPacientes::actualizar()`, `FormularioPaciente::actualizacion()` (If-Match), `PacienteVista` (ETag) | PER-05, APP-03, CON-MEM-03, HTTP-06, M1, M9 | 1·3·5 |
| D-06 | **Motivo obligatorio** al cambiar datos de identidad | R-07: la identidad no cambia sin justificación | `PoliticaEdicion` | DOM-07, DOM-08, APP-04 | 1·3 |
| D-07 | El **estado es un dato**: fallecido o inactivo solo lo edita el supervisor | R-08; evitar `PacienteFallecido extends Paciente` (LSP) | `PoliticaEdicion` | DOM-07, APP-04 | 2·3 |
| D-08 | **Documento protegido**: HMAC para buscar y para la unicidad, cifrado libsodium para leer, claves solo en Persistencia | R-04, RNF-03 | `ProteccionDocumentos` | PER-01, PER-07, ARQ-05, M2 | 3 |
| D-09 | **4 capas** con regla de dependencias verificada | Aislar las reglas de HTTP y de la base | `src/{Presentacion,Aplicacion,Dominio,Persistencia}` | ARQ-01, ARQ-02, ARQ-04, M5 | 3 |
| D-10 | El **Dominio no conoce HTTP**: errores por categoría; las vistas eligen el código | Reutilizar el dominio fuera de la API | `ErrorDominio`, `ErrorVista::POR_CATEGORIA` | ARQ-04, MVC-02, M6 | 3·4 |
| D-11 | **PDO con sentencias preparadas reales** y comodines del `LIKE` escapados | Inyección SQL; búsqueda que no devuelve de más | `RepositorioPdo::ejecutar()`, `FabricaConexion` | ARQ-03, PER-02, M7 | 3 |
| D-12 | **MVC**: controlador sin SQL, reglas, `new` ni JSON; formulario = formato (400); vistas = único lugar con HTTP | Tarea de la semana 4 | `Presentacion/Http/{Controladores,Formularios,Vistas}` | ARQ-07, ARQ-08, MVC-01..04, M10, M11 | 4 |
| D-13 | **Interfaces Repository en el Dominio**; adaptadores PDO **e** InMemory con una suite de contrato común | Inversión de dependencias; probar sin base de datos | `Dominio/Puertos/*`, `Persistencia/{Pdo,EnMemoria}` | ARQ-06, CON-MEM-01..08 (CON-PDO con credenciales), M12 | 4 |
| D-14 | Dato + auditoría (+ clave de idempotencia) en **una transacción** | R-09: sin evidencia no hay cambio | Puerto `Transaccion`; `PdoTransaccion`, `TransaccionEnMemoria` | APP-07, PER-06, CON-MEM-06, CON-MEM-07, IDEM-04, M8 | 3·5 |
| D-15 | **Contrato OpenAPI 3.1** verificado contra el código | El contrato y la implementación no pueden divergir | `api/openapi.yaml` (v1.1.0) | API-01..03, [lint](evidencia/salida-lint-openapi.txt), M15, M16 | 5·6 |
| D-16 | **`Idempotency-Key`** en el registro | Un reintento tras un timeout no debe dar un 409 engañoso | `RegistrarPaciente`, `AlmacenIdempotencia` | IDEM-01..04, CLI-03, M18 | 5 |
| D-17 | **No dividir** en microservicio (decisión medida) | 0,003 ms en proceso frente a 18 ms por red; 4 FK clínicas; 0/5 criterios | `bin/medir.php`; criterios P1–P5 | [`semana-05/evidencia/salida-medicion.txt`](../semana-05/evidencia/salida-medicion.txt) | 5 |
| D-18 | **Cliente resiliente**: el POST se reintenta (con clave), el PUT no; lecturas degradadas con aviso | Llamadas remotas seguras sin efectos dobles | `src/Cliente/*` | CLI-01..08, M13, M14 | 5 |
| D-19 | **Observabilidad sin datos personales**: `X-Request-ID` + registro de acceso sin query, cuerpo ni token | Trazar entre módulos sin filtrar pacientes | `Kernel::manejar()`, `RegistroAccesoJson` | OBS-01..03, M17, [registro real](../semana-05/evidencia/salida-servidor-real.txt) | 5 |
| D-20 | **Aislamiento por hospital**: el mismo 404 para «no existe» y «es de otro hospital» | OWASP API1 (BOLA) | `ConsultaPacientes::porCodigo()`, `Kernel` (tenant) | HTTP-02, HTTP-05, APP-05, CON-MEM-01 | 1·3 |
| **D-21** | **Cambio práctico: identidad protegida** como **política nueva** (`PoliticaConfidencialidad`). Reducción de la demografía **por defecto** (fail-closed). Código `RESERVADO` en los duplicados. Campo ausente en el `PUT` = sin cambio. | R-11 · RF-12..RF-16 · RNF-10 | `PoliticaConfidencialidad`, `Paciente::resumen()/detalle()`, `CriterioBusqueda::incluyendoProtegidos()`, `IndiceDuplicados::protegidos()` | PROT-01..06, DOM-09, CON-MEM-08, M19..M22; [rojo](evidencia/cambio-practico-1-antes-rojo.txt) → [verde](evidencia/cambio-practico-2-despues-verde.txt) | 6 |

## Cómo leer la matriz en la defensa

1. Elegir una decisión.
2. Abrir el archivo de la columna «Dónde vive».
3. Ejecutar las pruebas de la columna «Evidencia».
4. Para demostrar que la prueba no pasa por casualidad, aplicar la mutación indicada (`php tests/mutaciones.php`): el defecto inyectado debe hacerla fallar.

**Ejemplo en vivo:** D-05. `PdoRepositorioPacientes::actualizar()` compara la versión dentro del `UPDATE`. PER-05 y HTTP-06 lo prueban, y la mutación M1, que quita `AND version = :version`, hace fallar PER-05.
