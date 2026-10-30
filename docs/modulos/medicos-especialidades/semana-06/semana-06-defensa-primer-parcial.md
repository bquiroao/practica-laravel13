# Semana 6 – Primera evaluación parcial: defensa de la arquitectura y cambio práctico

Módulo: Médicos, especialidades y disponibilidad (módulo 4, reasignado dentro del grupo G1) · Grupo G1 · Autor: Joshua Eduardo García Reyes · Cubre las semanas 1 a 5.
Tema defendido: «registro de médico, asociación de especialidad y consulta de disponibilidad».

## 1. Presentación breve (7 diapositivas, 8 minutos)

Archivo: `semana-06/presentacion/semana-06-defensa-medicos.pptx` (y su PDF).

| # | Diapositiva | Mensaje en una frase | Evidencia que se muestra |
|---|---|---|---|
| 1 | Qué se defiende | Administración registra al médico y le asocia especialidades. Recepción y médicos consultan franjas libres de 30 minutos: el horario menos las citas activas. | pruebas APP-01 y APP-02 en `micro-his-medicos/tests/ejecutar.php` |
| 2 | Actores y casos de uso (semana 1) | Administración escribe; recepción y médico consultan; Citas (M05) aporta las citas ocupadas. Siete casos de uso en el UML de la semana 1. | `semana-01/semana-01-uml-medicos-disponibilidad.md` |
| 3 | Requisitos y SOLID (semana 2) | RF y RNF con criterios de aceptación. El principio defendido es ISP: cada cliente depende de una interfaz pequeña, y el ejemplo antes/después lo comprueba. | `semana-02/ejemplo-isp/verificar.php` |
| 4 | Capas (semana 3) | Dominio, Aplicación, Persistencia y Presentación. Las dependencias van hacia adentro: el Dominio no conoce a nadie y la prueba ARQ-01 lo vigila. | prueba ARQ-01 en `micro-his-medicos/tests/ejecutar.php` |
| 5 | MVC y Repository (semana 4) | El caso de uso depende de `RepositorioMedicos`, lo que escribe, y de `FuenteHospital`, lo que lee de otros módulos. PDO y memoria responden igual (REP-01). | `micro-his-medicos/src/Aplicacion/Puertos.php` |
| 6 | API y microservicio (semana 5) | Contrato con registro, especialidades, disponibilidad y ausencias. Decisión: seguir dentro del monolito modular; se dividiría si p95 supera 2 s, hay más de 20 consultas por segundo, otro equipo a cargo o M05 exige otra disponibilidad. | `semana-05/semana-05-cliente-servidor-microservicios.md` |
| 7 | Cambio práctico y límites | Leer las citas desde la API de Citas en vez de la base se resuelve con una fuente nueva. Límites: desactivar médico, horario y ausencias (RF-02, RF-04 y RF-05) están en el contrato y el prototipo, pero no en el Micro-HIS PHP. | `semana-06/demostrar-cambio.php` |

## 2. Matriz decisión → RF/RNF → evidencia

Todas las rutas son relativas a `docs/modulos/medicos-especialidades/`; `semana-06/demostrar-cambio.php` comprueba que existen y que las pruebas citadas están en `tests/ejecutar.php` (las carpetas de las semanas 1 a 5 que no estén en la copia que se revisa se cuentan aparte).

| Decisión de diseño | RF / RNF | Evidencia |
|---|---|---|
| Solo administración registra y asocia; recepción y médico solo consultan | RNF-01 | `micro-his-medicos/src/Aplicacion/CasosMedicos.php` (función `exigirRol`), prueba APP-03 en `micro-his-medicos/tests/ejecutar.php` |
| El colegiado es único y un usuario tiene un solo perfil activo | RF-01 | `micro-his-medicos/src/Aplicacion/CasosMedicos.php` (`RegistrarMedico`), prueba DOM-02 |
| El perfil y su especialidad principal se guardan juntos; si falla uno no queda el otro | RF-01, RF-03 | `micro-his-medicos/src/Aplicacion/CasosMedicos.php`, prueba PER-01 |
| Una especialidad no se cruza consigo misma y solo hay una principal a la vez | RF-03 | `micro-his-medicos/src/Dominio/Medicos.php` (`ReglasEspecialidad`), prueba DOM-01 |
| Las franjas libres son de 30 minutos: horario menos citas activas, en rangos de máximo 14 días | RF-06, RF-07 | `micro-his-medicos/src/Dominio/Medicos.php` (`Agenda`), pruebas APP-02 y DOM-02 |
| Solo aparecen médicos activos con la especialidad vigente ese día | RF-02, RF-06 | `micro-his-medicos/src/Aplicacion/CasosMedicos.php` (`ConsultarDisponibilidad`), prueba APP-02 |
| El Dominio no tiene dependencias y los casos de uso no tienen SQL | RNF-04 | prueba ARQ-01 en `micro-his-medicos/tests/ejecutar.php` |
| Los casos de uso dependen de interfaces: PDO y memoria responden igual y el doble que falla da 500 | RNF-04, RNF-05 | `micro-his-medicos/src/Aplicacion/Puertos.php`, pruebas REP-01 y PER-02 |
| Los códigos HTTP son estables: 201, 200, 401, 400, 403, 404, 405, 409 y 422 | RF-07, RNF-05 | prueba MVC-01 en `micro-his-medicos/tests/ejecutar.php` |
| Si Citas no responde no se deben ofrecer franjas que podrían estar ocupadas; el rendimiento aún no está medido | RNF-02, RNF-03 | `micro-his-medicos/src/Aplicacion/Puertos.php` (`FuenteHospital`), `semana-06/demostrar-cambio.php` |
| Desactivar al médico (RF-02), definir su horario (RF-04) y registrar ausencias (RF-05) están en el contrato y el prototipo, pero **no existen** como casos de uso del Micro-HIS PHP (el horario solo se lee); se declara como límite | RF-02, RF-04, RF-05 | `micro-his-medicos/src/Aplicacion/CasosMedicos.php` (solo tres casos de uso) |

## 3. Recorrido (lo que se hace frente al docente)

1. Correr `php micro-his-medicos/tests/ejecutar.php` desde la carpeta del módulo: todas las líneas en `PASA` (registro, disponibilidad, guardas, rollback y contrato del Repository).
2. Mostrar `semana-02/ejemplo-isp/verificar.php`: cada cliente depende solo de lo que usa.
3. Mostrar `src/Aplicacion/Puertos.php`: lo que el módulo escribe y lo que solo lee.
4. Correr `php semana-06/demostrar-cambio.php` y mostrar las líneas `PASA` del cambio práctico.

## 4. Registro del cambio práctico

| Campo | Contenido |
|---|---|
| Cambio pedido (simulado en la defensa) | «Citas (M05) publicará sus citas por su propia API: la disponibilidad debe leerlas de ahí y no de la base del hospital.» |
| Impacto en requisitos | RF-06 no cambia; RNF-02 se refuerza: si Citas no responde no se ofrecen franjas. |
| Decisión | Una fuente nueva `FuenteHospitalConCitasRemotas` que implementa `FuenteHospital`: delega usuario y especialidad a la fuente actual y obtiene `citasActivas` de un cliente de la API de Citas; se arma en `public/index.php`. |
| Qué se cambia | Una clase nueva y el cableado de quien arma `ConsultarDisponibilidad`. |
| Qué **no** se cambia | `ConsultarDisponibilidad`, `Agenda`, `Vigencia`, el Repository, el controlador, la vista, el esquema SQL y las pruebas existentes. |
| Evidencia | `semana-06/demostrar-cambio.php`: C-01 mismas franjas que con las citas de la base, C-02 cita cancelada no ocupa, C-03 si Citas falla no se devuelven franjas, C-04 `src/` no menciona la clase nueva. |
| Riesgo y límite | La respuesta de Citas se simula en la demostración con una función; falta definir el contrato real con M05, el tiempo máximo de espera y el código 503 (CA-03). |
| Conclusión | Separar lo que el módulo escribe de lo que lee (semana 4) permitió el cambio extendiendo, no modificando; los pasos de código quedan para otro PR si el docente lo aprueba. |

## 5. Preguntas probables

- **¿Qué es ISP aquí?** Que recepción no dependa de las operaciones de escritura de administración: cada cliente ve solo su interfaz.
- **¿Por qué no dividir en microservicio?** Porque no se cumple ningún criterio medible de la semana 5; la frontera ya está marcada para hacerlo si cambia.
- **¿Qué falta?** Desactivar médico, horario y ausencias en el Micro-HIS PHP, el 503 cuando Citas no responde y la medición de rendimiento.
