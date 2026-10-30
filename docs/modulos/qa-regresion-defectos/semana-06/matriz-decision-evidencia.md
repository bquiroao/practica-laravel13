# Matriz decisión → evidencia (semanas 1–6)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión

Cada decisión de arquitectura de las semanas 1 a 6 tiene:
- el requisito o la regla que la motiva;
- el lugar donde vive;
- una prueba que la verifica;
- cuando existe, una **mutación** que la rompe y que la suite detecta. Así se sabe que la prueba no pasa por casualidad.

Rutas relativas a `micro-his-qa/`. Evidencia: [cambio-3-suite-completa.txt](evidencia/cambio-3-suite-completa.txt) (99/99) y [mutaciones.txt](evidencia/mutaciones.txt) (33/33).

## Matriz

| # | Decisión | Sem. | Regla / RF / RNF | Dónde vive | Prueba | Mutación |
|---|---|---|---|---|---|---|
| D-01 | No existen los casos de uso «editar resultado», «borrar evidencia» ni «el desarrollador cierra su defecto» | 1 | R-08, R-11 | Casos de uso de la semana 1; ausencia de métodos en `RepositorioCampanas` | DOM-08, INT-03 | M8, M14 |
| D-02 | El aprobador de un caso no puede ser su autor | 1 | R-02 / RF-02 | `CasoPrueba::aprobar`, `CHECK casos_aprobador_no_autor` | DOM-02, APP-07, INT-04 | M1 |
| D-03 | El alcance incluye todos los críticos de la matriz para las funcionalidades del build | 1 | R-03 / RF-03 | `Campana::iniciar`, `RepositorioCasos::criticosDe` | DOM-03, APP-06, INT-02 | M2 |
| D-04 | Contrato `EvidenciaVerificable` con métodos `final`; la URL externa fuera de la jerarquía | 2 | RNF-01 (LSP) | `Dominio/Evidencia/*` | DOM-09, ARQ-07 | (sem. 2: 15/15) |
| D-05 | FALLA o BLOQUEADO sin evidencia → 422; una referencia no cuenta | 2 | R-05 / RF-05 | `RegistrarResultado` | APP-04 | M3, M4 |
| D-06 | **Fail-closed:** la evidencia va al almacén ANTES de la transacción; si no confirma, nada se escribe | 3 | R-05 | `RegistrarResultado`; `AlmacenEvidencias` | APP-11, CLI-05 | M5 |
| D-07 | Intento + defecto + traza en UNA transacción (outbox) | 3 | RNF de integridad | `RegistrarResultado`, `PdoRegistroAuditoria` | APP-12, INT-06, INT-09 | M12 |
| D-08 | Idempotencia en dos niveles: consulta previa + `UNIQUE (tenant, clave)` | 3 | R-12 / RF-12 | `RegistrarResultado`, `schema.sql` | APP-03, APP-15, INT-05 | M11 |
| D-09 | Las reglas críticas también en la base (triggers de solo agregar, versión aprobada inmutable) | 3 | R-02, R-11 | `schema.sql` | INT-03, INT-04 | M14 |
| D-10 | Cambios de estado condicionados al anterior (`UPDATE … WHERE estado = …`) | 3 | Concurrencia | `PdoRepositorioCampanas::actualizarEstado` | APP-14, INT-08, CON-04 | — |
| D-11 | El tenant sale del token; otro tenant = 404 | 3 | RNF de seguridad | `Kernel`, todos los repositorios | HTTP-02, APP-09, INT-07 | M13, M17 |
| D-12 | Cuatro capas con dependencia hacia el Dominio; PDO solo en Persistencia | 3 | — | `src/*` | ARQ-01..06 | — |
| D-13 | MVC: el formulario valida solo la forma (400), el dominio las reglas (422/409) | 4 | — | `Presentacion/Http/Formularios` | MVC-01, MVC-02 | M21 |
| D-14 | El controlador no tiene SQL ni reglas | 4 | — | `Presentacion/Http/Controladores` | ARQ-08, MVC-04 | M22 |
| D-15 | Repository en `Dominio\Puertos` con adaptadores PDO e InMemory que pasan la misma suite | 4 | — | `Persistencia/{Pdo,EnMemoria}` | CON-01..07 × 2, ARQ-09 | M18, M19, M20 |
| D-16 | Repositorio compartido: base central; acceso por membresía activa con trigger | 4 | R-14 (permisos) | `integracion-central.sql` | Validador 18/18 | — |
| D-17 | Contrato OpenAPI = código (operaciones, permisos, estados, códigos) | 5 | — | `api/openapi.yaml` | API-01..03 | M23, M24 |
| D-18 | Cliente con bandeja: un resultado no confirmado queda PENDIENTE y se reenvía con su clave | 5 | R-12 | `Cliente/ClienteQa` | CLI-01..07 | M25, M26 |
| D-19 | El almacén de evidencias es un servicio aparte con límite de 3 s, huella verificada y circuito | 5 | R-05 | `Persistencia/Http/*` | AHT-01..04 | M27, M28 |
| D-20 | QA **no** se separa como microservicio (C-1..C-5 no se cumplen) | 5 | — | Medición `bin/medir.php` | `1-medicion-frontera.txt` (sem. 5) | — |
| D-21 | Observabilidad: `X-Request-ID` y una línea JSON por petición, sin datos | 5 | RNF de privacidad | `Kernel`, `RegistroAccesoJson` | OBS-01..03 | M29 |
| D-22 | **R-15:** un MAYOR abierto en un caso crítico de la matriz bloquea el GO | 6 | R-15 (nueva) | `Campana::cerrar`, `CerrarCampana`, `mayoresAbiertos()` | CAM-01..03, CAM-05 | M30, M31, M32 |
| D-23 | La trazabilidad dice qué bloquea el GO **antes** de cerrar (`bloquea_go`) | 6 | R-15, CU-11 | `ConsultarTrazabilidad`; OpenAPI 1.1.0 | CAM-04 | M33 |

## Cobertura

| | Cantidad |
|---|---|
| Decisiones | 23 |
| Con prueba | 23 |
| Con al menos una mutación que la rompe | 18 |
| Sin mutación (y por qué) | D-10, D-12, D-16, D-20 y D-04 en esta suite: estructurales, de concurrencia o de medición. D-04 tiene sus 15 mutaciones en la semana 2 |

## Cómo usar la matriz en la defensa

1. Se elige una fila.
2. Se abre el archivo de «Dónde vive».
3. Se corre `php tests/ejecutar.php --solo=<prefijo>`.
4. Si hay mutación, se puede reintroducir a mano el defecto que describe y ver la prueba en rojo.
