# Semana 2 — Requerimientos, criterios de aceptación y OCP

Módulo: **Alergias clínicas y alerta visual en expediente** · Flujo: «registro de alergia y emisión de alerta al consultar el expediente»

Este documento trae los RF, los RNF y los criterios de aceptación. La aplicación de **OCP** (diseño antes/después y evidencia ejecutable) está en [ocp.md](ocp.md) y en [ejemplo-ocp/](ejemplo-ocp/).

## 1. Requerimientos funcionales

| ID | Requerimiento | CU | Reglas |
|---|---|---|---|
| RF-01 | Registrar una alergia del paciente con alérgeno (código o nombre), tipo, severidad, reacción y fecha de inicio | CU-01 | RN-01, RN-02, RN-04, RN-05 |
| RF-02 | Rechazar el registro si el paciente no existe en el hospital o no está activo | CU-01, CU-02 | RN-06 |
| RF-03 | Rechazar una alergia activa equivalente a otra ya registrada | CU-02 | RN-03 |
| RF-04 | Al consultar el expediente, mostrar el nivel de alerta y las alergias activas ordenadas de mayor a menor nivel | CU-03, CU-04 | RN-08 |
| RF-05 | Mostrar «Sin alergias registradas (no verificado)» cuando no hay alergias activas, sin usar un estado verde | CU-03 | RN-08 |
| RF-06 | Exigir el reconocimiento de cada usuario cuando el nivel es ALTA o CRÍTICA | CU-05 | RN-09 |
| RF-07 | Invalidar los reconocimientos cuando cambia el conjunto de alergias activas (huella) | CU-05 | RN-09 |
| RF-08 | Permitir registrar solo al médico; consultar y reconocer a médico y enfermería | CU-01, CU-03, CU-05 | RN-07 |
| RF-09 | Inactivar o resolver una alergia con fecha, sin borrarla (solo diseño; fuera del Micro-HIS) | CU-06 | RN-10 |

## 2. Requerimientos no funcionales

| ID | Categoría | Requerimiento | Cómo se verifica |
|---|---|---|---|
| RNF-01 | Seguridad (tenant) | El hospital se toma del token; un token de otro hospital recibe 403 aunque el encabezado diga lo contrario | Prueba de API con token de H-02 (semana 5) |
| RNF-02 | Rendimiento | La consulta de la alerta tarda p95 < 300 ms por HTTP con los datos de prueba | `scripts/medir-frontera.php` (semana 5) |
| RNF-03 | Accesibilidad | El nivel se comunica con texto y no solo con color; contraste del texto ≥ 4.5:1 (WCAG 2.2 AA) | Revisión de la vista y cálculo de contraste (semana 9) |
| RNF-04 | Observabilidad | Toda respuesta de la API lleva `X-Request-Id`; el registro de acceso no contiene alérgenos, reacciones ni códigos de paciente | Prueba de API (semana 5) |
| RNF-05 | Integridad | Sentencias preparadas siempre; si la base falla no queda ningún dato a medias y se responde 503 | Prueba con escritura rechazada (semanas 3 y 5) |
| RNF-06 | Privacidad | Pantallas, registros y documentos identifican al paciente solo por código (PAC-NNNNN) | Revisión de vistas y del registro de acceso |
| RNF-07 | Mantenibilidad | Agregar una regla de nivel de alerta no requiere modificar el calculador (OCP) | `ejemplo-ocp/verificar.php` (esta semana) |

## 3. Criterios de aceptación

| ID | RF/RNF | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-01 | el paciente PAC-00451 activo y un médico autenticado | registra Penicilina, `life_threatening`, reacción «Edema de glotis», inicio 2019-03-02 | se guarda una fila activa con `verified_by_user_ref_id` = ese médico y la alerta queda CRÍTICA |
| CA-02 | RF-01 | el formulario de registro | se envía sin código ni nombre de alérgeno | se rechaza con `ALERGENO_REQUERIDO` y no se guarda nada |
| CA-03 | RF-01 | el formulario de registro | la fecha de inicio es mañana | se rechaza con `FECHA_INICIO_FUTURA` y se conserva lo escrito |
| CA-04 | RF-02 | el paciente PAC-00453 con estado `deceased` | se intenta registrar una alergia | se rechaza con `PACIENTE_INACTIVO` |
| CA-05 | RF-03 | PAC-00451 con Ibuprofeno activo | se registra «  IBUPROFENO » del mismo tipo | se rechaza con 409 `ALERGIA_DUPLICADA` |
| CA-06 | RF-04 | PAC-00451 con Penicilina (`life_threatening`) e Ibuprofeno (`moderate`) | enfermería abre el expediente | ve nivel CRÍTICA y la lista empieza por Penicilina |
| CA-07 | RF-05 | PAC-00452 sin alergias | se abre el expediente | se muestra «Sin alergias registradas» con la aclaración «no verificado» y nivel `SIN_REGISTRO` |
| CA-08 | RF-06 | una alerta CRÍTICA no reconocida | la enfermera pulsa «He leído las alergias» con la huella que vio | queda reconocida para ella y sigue pendiente para el médico |
| CA-09 | RF-07 | una alerta reconocida por el médico | se registra otra alergia | la alerta vuelve a pedir reconocimiento; reconocer con la huella anterior responde `ALERTA_CAMBIO` |
| CA-10 | RF-08 | un token de enfermería | intenta registrar una alergia | 403 `PERMISO_DENEGADO` y no se guarda nada |
| CA-11 | RNF-05 | la base rechaza escrituras | un médico registra una alergia | 503 `PERSISTENCIA_NO_DISPONIBLE` y la tabla no cambia |
| CA-12 | RNF-07 | el calculador de alertas ya probado | se agrega una regla nueva como clase aparte | el calculador no cambia (misma huella SHA-256 del archivo) y la regla se aplica |

## 4. Trazabilidad hacia las semanas siguientes

Los criterios CA-01 a CA-11 se convierten en pruebas del Micro-HIS desde la semana 3; la matriz completa RF/RNF → prueba se entrega en la semana 15. CA-12 se verifica esta misma semana con [ejemplo-ocp/verificar.php](ejemplo-ocp/verificar.php).
