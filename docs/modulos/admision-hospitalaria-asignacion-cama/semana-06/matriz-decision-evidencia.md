# Matriz decisión → evidencia (primer parcial)

Las decisiones de arquitectura usan el prefijo **DEC-NN**, para no confundirlas con los defectos **D-NN** de la semana 15. Cada fila apunta a evidencia que se puede abrir o ejecutar en el repositorio.

| ID | Decisión | Por qué | Requisito / regla | Evidencia verificable |
|---|---|---|---|---|
| DEC-01 | Identificar al paciente solo por código `PAC-NNNNN` | Minimizar datos clínicos expuestos | RNF-03 | `CodigoPaciente.php`; prueba «POST /admisiones: 201, Location, X-Request-Id y solo datos mínimos»; prueba «X-Request-Id inválido se reemplaza; el registro no contiene datos del paciente» |
| DEC-02 | Garantizar RN-01 en la base de datos con el índice único parcial `ux_asignacion_cama_activa` | Una validación previa no resiste dos solicitudes simultáneas | RN-01, RNF-01, CA-07 | `EsquemaMicroHis.php`; pruebas «RN-01: dos admisiones…» y «RN-01 simultáneo…» con dos conexiones |
| DEC-03 | Admisión y asignación en **una** transacción | Que no quede una admisión sin cama | RF-04, RNF-08, CA-12 | Prueba «Error de persistencia a mitad de la transacción revierte todo» (`22001` real) |
| DEC-04 | Criterios de compatibilidad con OCP | La compatibilidad es lo que más cambia | RN-03, RNF-06 | `semana-02/ocp/ejemplo/verificar-ocp.php`; prueba «OCP: un criterio nuevo…» |
| DEC-05 | Interfaces Repository en Domain con adaptadores PDO e InMemory | Aislar el dominio y probar sin base | RNF-06 | `pruebas/contrato/prueba-contrato-repositorio.php` (5 casos × 2 adaptadores) |
| DEC-06 | Controlador sin SQL ni reglas | Separar presentación de dominio | — | `prueba-dependencias.php` («Solo Persistence usa PDO y SQL») |
| DEC-07 | Hospital (tenant) tomado del token; `tenant_id` en el cuerpo se rechaza | Evitar acceso entre hospitales | RN-04, RNF-02, CA-09 | Prueba «Tenant del token: …»; prueba «Usuario de HOSP-02 no alcanza camas de HOSP-01» |
| DEC-08 | Autorización por rol en el caso de uso, no en la pantalla | Una sola regla para consola, web y API | RN-06, CA-08 | `ContextoUsuario::exigirRol`; pruebas «Rol MEDICO no puede admitir» y «MEDICO: no puede admitir (403)…» |
| DEC-09 | Eventos de trazabilidad de solo inserción (trigger) | Trazabilidad clínica confiable | RN-05, RF-06, CA-11 | Prueba «RN-05: los eventos de trazabilidad no se pueden modificar» |
| DEC-10 | Formato único de error con `reintentable` y `X-Request-Id` | Que el cliente sepa si reintentar y se pueda correlacionar | RNF-05, RF-05 | `semana-05/contrato-api.md` §3; pruebas «Cama ya asignada: 409…» y «X-Request-Id inválido…» |
| DEC-11 | Espera máxima por bloqueo de 2 s (`lock_timeout`) | No dejar al admisionista esperando sin límite | RNF-08 | Prueba «RN-01 simultáneo…» (`CONFLICTO_CONCURRENCIA`) |
| DEC-12 | Catálogo de camas y pacientes en solo lectura | Respetar la frontera con Josué y con Pacientes | Alcance S1 | `CatalogoCamas` y `DirectorioPacientes` solo tienen métodos de lectura; contrato con Josué **pendiente** |
| DEC-13 | No dividir en microservicio | Sin gatillo medible, solo agrega costo | — | `semana-05/frontera-microservicio.md` §3 (G-1 a G-5) |

Para reproducir toda la evidencia ejecutable, desde `micro-his/`: `php pruebas/ejecutar.php`.
