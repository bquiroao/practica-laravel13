# Semana 2 — Catálogo de medicamentos: RF/RNF, criterios de aceptación e ISP

**Flujo:** alta, búsqueda y control de vigencia de un medicamento.
**Principio SOLID asignado:** ISP (Interface Segregation Principle). Código antes/después y evidencia
verificable en `ejemplo-isp/` (parte A); diagramas en `diagramas/` y justificación en `ISP.md` (parte B).

Los IDs continúan los de la semana 1 (CU-01…CU-06, RN-01…RN-06).

## 1. Requisitos funcionales

| ID | Requisito | CU | Reglas |
|---|---|---|---|
| RF-01 | Registrar un medicamento con nombre, principio activo, concentración (valor y unidad), forma farmacéutica, vía, presentación, registro sanitario opcional y fecha de inicio de vigencia. El sistema asigna el código `MED-NNNNNN` y la versión 1. | CU-01 | RN-06 |
| RF-02 | Validar cada dato con los límites de la tabla 3 y devolver todos los errores por campo en una sola respuesta. | CU-01, CU-03 | RN-05 |
| RF-03 | Rechazar un alta o versión cuya clave de equivalencia coincida con otro medicamento vigente en alguna fecha de su vigencia. | CU-01, CU-03 | RN-04 |
| RF-04 | Buscar versiones vigentes a una fecha (por defecto hoy) por texto contenido en nombre, principio activo o código, y/o por forma farmacéutica exacta. Máximo 50 resultados, ordenados por nombre y código. | CU-02 | RN-03 |
| RF-05 | Registrar una nueva versión de un código vigente, cerrando la versión anterior en la fecha de inicio de la nueva. | CU-03 | RN-01 |
| RF-06 | Dar de baja un medicamento indicando fecha y motivo; la versión vigente se cierra y el registro se conserva. | CU-04 | RN-01, RN-02 |
| RF-07 | Consultar el historial completo de versiones de un código, de la más antigua a la más reciente. | CU-05 | — |
| RF-08 | Eliminar un registro erróneo solo si su código nunca se usó en prescripciones; si se usó, rechazar e indicar que corresponde una baja. | CU-06 | RN-02 |
| RF-09 | Consultar por código la versión vigente a una fecha (lectura para otros módulos). | CU-02 | RN-03 |

## 2. Requisitos no funcionales

| ID | Atributo | Requisito medible | Cómo se verifica |
|---|---|---|---|
| RNF-01 | Seguridad (autorización) | Solo `QF_CATALOGO` modifica. `CONSULTA_CLINICA` y `AUDITOR_CALIDAD` solo leen. Sin token → 401; rol no autorizado → 403. | Pruebas de API (semana 5) y caja negra (semana 15). |
| RNF-02 | Aislamiento por tenant | El hospital se toma del token, nunca del cuerpo ni de la URL. Un código de otro hospital responde 404. | Prueba de tenant (semanas 5 y 15). |
| RNF-03 | Rendimiento | Búsqueda con p95 ≤ 300 ms sobre 5 000 versiones en el Micro-HIS local. | Medición en la semana 15 (por verificar hasta entonces). |
| RNF-04 | Integridad | La base repite RN-01 y RN-04 con restricciones (`CHECK`, `EXCLUDE`); si se violan, la respuesta es 409, no 500. | Prueba de persistencia (semana 3). |
| RNF-05 | Trazabilidad | Cada versión guarda usuario y momento de creación; cada respuesta de la API lleva `X-Request-Id`. | Revisión de esquema y prueba de API. |
| RNF-06 | Mantenibilidad | El dominio no depende de PDO. Los adaptadores PDO e InMemory pasan la misma prueba de contrato. | Prueba de contrato (semana 4). |
| RNF-07 | Accesibilidad | La interfaz cumple WCAG 2.1 AA en teclado, foco, contraste y etiquetas. | Evaluación (semana 9) y prototipo (semana 11). |
| RNF-08 | Protección de datos | El catálogo no guarda datos de pacientes. Los errores 5xx no muestran SQL ni trazas. | Revisión técnica (semana 13). |

## 3. Límites de datos (RN-05): dominio = columnas propuestas

| Campo | Regla del dominio | Columna propuesta (HOSPITAL) |
|---|---|---|
| código | `^MED-\d{6}$` | `codigo varchar(10)` |
| versión | 1 a 999 | `version smallint` |
| nombre | 3 a 150 caracteres (sin espacios extremos) | `nombre varchar(150)` |
| principio activo | 3 a 120 caracteres | `principio_activo varchar(120)` |
| concentración | > 0 y ≤ 999 999,999; máximo 3 decimales | `concentracion_valor numeric(9,3)` |
| unidad | `mg`, `g`, `mcg`, `UI`, `mL`, `%`, `mg/mL`, `mg/5mL` | `concentracion_unidad varchar(10)` |
| forma farmacéutica | lista cerrada de 12 valores (semana 3) | `forma_farmaceutica varchar(30)` |
| vía | `ORAL`, `INTRAVENOSA`, `INTRAMUSCULAR`, `SUBCUTANEA`, `TOPICA`, `OFTALMICA`, `INHALATORIA`, `RECTAL` | `via_administracion varchar(20)` |
| presentación | 3 a 120 caracteres | `presentacion varchar(120)` |
| registro sanitario | opcional; `^[A-Z0-9-]{4,30}$` | `registro_sanitario varchar(30) null` |
| vigente desde / hasta | fechas entre 2000-01-01 y 2099-12-31; hasta > desde | `vigente_desde date`, `vigente_hasta date null` |
| motivo de baja | 5 a 200 caracteres | `motivo_cierre varchar(200) null` |
| texto de búsqueda | 2 a 60 caracteres | (no se guarda) |

Los nombres de columnas son **propuesta del Micro-HIS**: el esquema real de HOSPITAL no se pudo
consultar al preparar esta entrega.

## 4. Criterios de aceptación

Datos ficticios del Hospital Norte, con "hoy" = 2026-10-15 (ver semana 1, sección 9).

| ID | RF/RN | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-01 | un QF del Hospital Norte y datos válidos de "Loratadina 10 mg tableta oral" | registra el alta | se guarda con el siguiente código libre (`MED-000107`), versión 1 y vigencia abierta (201). |
| CA-02 | RF-02, RN-05 | un nombre de 151 caracteres | registra el alta | responde 422 con error en `nombre` y no guarda nada. |
| CA-03 | RF-02, RN-05 | concentración `0` o `1000000` | registra el alta | responde 422 con error en `concentracion_valor`. |
| CA-04 | RF-03, RN-04 | `MED-000102` Amoxicilina 500 mg cápsula oral vigente | se registra otra "amoxicilina " 500 mg cápsula oral | responde 409 `DUPLICADO` e indica `MED-000102`. |
| CA-05 | RF-03, RN-04 | `MED-000105` Omeprazol 20 mg cápsula oral dado de baja el 2026-09-01 | se registra Omeprazol 20 mg cápsula oral desde 2026-10-15 | se acepta, porque las vigencias no se cruzan. |
| CA-06 | RF-04, RN-03 | `MED-000105` dado de baja | se busca "omepra" hoy | no aparece. |
| CA-07 | RF-04, RN-03 | `MED-000104` con versión 1 hasta 2026-07-01 y versión 2 desde 2026-07-01 | se busca "ibupro" con fecha 2026-03-10 | aparece solo la versión 1; con fecha 2026-07-01, solo la versión 2. |
| CA-08 | RF-04, RN-03 | "Cloruro de sodio 0,9 % solución inyectable" y "Amoxicilina 250 mg/5 mL suspensión" | se busca `% s` y luego `a_o` | `% s` devuelve solo el cloruro de sodio y `a_o` no devuelve nada (como comodines traerían también la suspensión y la amoxicilina); igual en PDO e InMemory. |
| CA-09 | RF-05, RN-01 | `MED-000101` versión 1 vigente desde 2026-01-01 | se registra la versión 2 desde 2026-11-01 | la versión 1 queda hasta 2026-11-01 y la 2 desde esa fecha; en cualquier fecha hay una sola vigente. |
| CA-10 | RF-05, RN-01 | `MED-000101` versión 1 vigente desde 2026-01-01 | se registra una versión desde 2025-12-31 o 2026-01-01 | responde 409 `SOLAPE_VIGENCIA` y nada cambia. |
| CA-11 | RF-08, RN-02 | `MED-000101` usado en la prescripción ficticia `PRE-000231` | se intenta eliminar | responde 409 `EN_USO`; el registro sigue y la baja sí se permite. |
| CA-12 | RNF-01, RNF-02 | un usuario `CONSULTA_CLINICA`, y otro usuario del Hospital Sur | el primero intenta un alta; el segundo pide `MED-000102` del Norte | 403 para el primero; 404 para el segundo. |
| CA-13 | RNF-04 | la base de datos no responde | se registra un alta | responde 503 `PERSISTENCIA` sin datos parciales ni detalles internos. |

## 5. Trazabilidad

| CU | RF | RN | CA |
|---|---|---|---|
| CU-01 | RF-01, RF-02, RF-03 | RN-04, RN-05, RN-06 | CA-01…CA-05, CA-13 |
| CU-02 | RF-04, RF-09 | RN-03 | CA-06, CA-07, CA-08 |
| CU-03 | RF-02, RF-03, RF-05 | RN-01, RN-04 | CA-09, CA-10 |
| CU-04 | RF-06 | RN-01, RN-02 | CA-11 |
| CU-05 | RF-07 | — | (semana 15) |
| CU-06 | RF-08 | RN-02 | CA-11 |
| Todos | — | — | CA-12 (RNF-01, RNF-02) |
