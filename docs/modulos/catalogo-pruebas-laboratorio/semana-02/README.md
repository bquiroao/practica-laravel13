# Catálogo de pruebas de laboratorio — Semana 2

**Tema:** requisitos funcionales y no funcionales, criterios de aceptación y principio SOLID
**Proceso:** alta y consulta de prueba con requisitos de muestra y vigencia
**Estudiante:** Erick Rolando Ramazzini Muralles (ErickRamazzini)

Los IDs continúan los de la semana 1: casos de uso CU-01 a CU-06 y reglas RN-01 a RN-08. El principio SOLID con su ejemplo antes/después está en [`solid-srp/`](solid-srp/README.md).

## 1. Requisitos funcionales

| ID | Requisito | CU | Reglas | Prioridad |
|---|---|---|---|---|
| RF-01 | El sistema debe permitir al Jefe de laboratorio registrar una prueba con código, nombre, sección y tiempo de respuesta. | CU-01 | RN-02 | Alta |
| RF-02 | El sistema debe registrar los requisitos de muestra de la prueba: tipo, contenedor, volumen mínimo, preparación del paciente, conservación y estabilidad. | CU-02 | RN-03 | Alta |
| RF-03 | El sistema debe registrar la vigencia de cada prueba con fecha desde obligatoria y fecha hasta opcional. | CU-03 | RN-04 | Alta |
| RF-04 | El sistema debe registrar un cambio como versión nueva, cerrar la versión anterior el día previo y rechazar vigencias superpuestas. | CU-03 | RN-05 | Alta |
| RF-05 | El sistema debe listar las pruebas vigentes a una fecha (por defecto hoy) con filtros por texto, sección y tipo de muestra. | CU-04 | RN-06 | Alta |
| RF-06 | El sistema debe mostrar el detalle y los requisitos de una prueba vigente o indicar su vigencia cuando no lo está. | CU-05 | RN-06 | Media |
| RF-07 | El sistema debe permitir cada acción solo a quien tenga el permiso correspondiente, dentro de su hospital. | CU-06 | RN-01 | Alta |
| RF-08 | El sistema debe guardar quién registró cada versión, cuándo y en qué hospital. | CU-01 | RN-08 | Media |

## 2. Requisitos no funcionales

| ID | Categoría | Requisito | Cómo se verifica |
|---|---|---|---|
| RNF-01 | Integridad | Una versión, sus requisitos y el cierre de la anterior se guardan en una sola transacción; nunca quedan registros parciales (RN-07). | Prueba que fuerza una falla al guardar y comprueba que no cambió nada. |
| RNF-02 | Rendimiento | La consulta de pruebas vigentes responde en 1 segundo o menos (percentil 95) con 500 pruebas por hospital. | Medición con datos ficticios de 500 pruebas. |
| RNF-03 | Seguridad | Toda petición requiere token válido; un hospital no puede ver ni modificar el catálogo de otro. El catálogo no guarda datos de pacientes. | Pruebas de 401, 403 y aislamiento por hospital. |
| RNF-04 | Usabilidad y accesibilidad | Los errores se muestran junto a cada campo y el formulario conserva lo capturado; la interfaz cumple WCAG 2.1 nivel AA. | Revisión con checklist WCAG (semana 9). |
| RNF-05 | Mantenibilidad | Las reglas viven en el dominio y la persistencia detrás de una interfaz Repository; cada regla RN-02 a RN-07 tiene al menos una prueba automática. | Revisión de dependencias y matriz regla→prueba. |
| RNF-06 | Observabilidad | Cada petición a la API registra un identificador (`request_id`), la ruta y el estado, sin datos clínicos. | Revisión del registro tras ejecutar las pruebas de la API. |

## 3. Criterios de aceptación

| ID | RF | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-01, RF-02, RF-03 | un Jefe de laboratorio con permiso y datos completos de `HEM-001` vigente desde 2027-01-04 | guarda la prueba | se crea la versión 1, responde 201 y la prueba aparece en la consulta del 2027-01-15. |
| CA-02 | RF-01 | un código `hem1` sin el formato AAA-000 | intenta guardar | responde 422 con el error en el campo código y no guarda nada. |
| CA-03 | RF-02 | un volumen mínimo de 0 mL o una estabilidad de 0 h | intenta guardar | responde 422 con un error por cada campo inválido. |
| CA-04 | RF-03 | una fecha hasta anterior a la fecha desde, o una fecha desde anterior a hoy | intenta guardar | responde 422 con el error en el campo de fecha correspondiente. |
| CA-05 | RF-04 | `GLU-001` versión 1 vigente desde 2027-01-04 sin fecha hasta | registra una versión vigente desde 2027-02-01 | se crea la versión 2 y la versión 1 queda vigente hasta el 2027-01-31. |
| CA-06 | RF-04 | la misma `GLU-001` versión 1 | registra una versión vigente desde 2027-01-04 | responde 409 «Vigencia superpuesta» y no guarda nada. |
| CA-07 | RF-05 | `HEM-001` vigente desde 2027-01-04 | un médico consulta con fecha 2027-01-03 | la lista viene vacía. |
| CA-08 | RF-06 | `HEM-001` vigente desde 2027-01-04 | un laboratorista pide su detalle con fecha 2027-01-03 | responde 404 e indica que es vigente desde el 2027-01-04. |
| CA-09 | RF-07 | un médico autenticado | intenta registrar una prueba | responde 403; sin token responde 401. |
| CA-10 | RNF-01 | una falla de la base al guardar la versión 2 | se registra el cambio | responde 500 y la versión 1 conserva su vigencia original. |

## 4. Trazabilidad

| CU | RF | RNF | CA |
|---|---|---|---|
| CU-01 Registrar prueba | RF-01, RF-08 | RNF-01, RNF-05 | CA-01, CA-02, CA-10 |
| CU-02 Definir requisitos de muestra | RF-02 | RNF-04 | CA-03 |
| CU-03 Definir vigencia | RF-03, RF-04 | RNF-01 | CA-04, CA-05, CA-06 |
| CU-04 Consultar pruebas vigentes | RF-05 | RNF-02 | CA-07 |
| CU-05 Ver detalle y requisitos | RF-06 | RNF-04 | CA-08 |
| CU-06 Verificar permiso | RF-07 | RNF-03, RNF-06 | CA-09 |
