# Semana 13 — Revisión técnica formal de recepción de muestras

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Módulo** | Recepción de muestras y códigos identificadores |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | 28 de octubre de 2026 |
| **Tipo de revisión** | Inspección documental y ejecución reproducible de pruebas |
| **Alcance revisado** | Artefactos de las semanas 2, 3, 11 y 12 disponibles al commit `d0eed5698f5de27aab218ff518c2052a837a1642` |

## 1. Objetivo y alcance

Evaluar si las reglas de recepción, el registro de aceptación o rechazo, la identidad de la muestra y los límites de integración están suficientemente definidos y respaldados por evidencia. La revisión se limita al prototipo académico PHP de la Semana 3 y a sus evidencias de las Semanas 11 y 12; no es una auditoría de un sistema clínico productivo.

La inspección se hizo sobre el commit de referencia indicado arriba, no sobre archivos de esta rama. El proyecto de Semana 13 parte de `origin/main`, donde esos artefactos históricos no están incluidos. Los enlaces de evidencia apuntan al commit inmutable que sí contiene el código y los documentos examinados.

La revisión fue de escritorio y no incluyó entrevista, reunión, aprobación de terceros, despliegue, EMR/LIS real ni datos clínicos. Los roles de revisión de la sección 3 son propuestos, no personas que hayan participado.

## 2. Plan de revisión

| Fase | Actividad | Evidencia / resultado |
|---|---|---|
| Preparación | Delimitar alcance, fijar el commit fuente y leer las decisiones de dominio de la Semana 2. | Alcance y referencias de fuente consignados en este informe. |
| Inspección | Seguir el caso de uso, sus puertos, dominio, adaptador PDO y esquema. | Checklist de 15 puntos y hallazgos H-01 a H-06. |
| Comprobación | Ejecutar lint, suite de Semana 3 y recorrido de Semana 12 contra el commit fuente. | [verificacion-semana-13.txt](evidencia/verificacion-semana-13.txt). |
| Clasificación | Separar comportamientos demostrados, brechas y decisiones aún pendientes. | Estado, impacto y acción propuesta por hallazgo. |
| Cierre | Registrar límites, responsables propuestos y condiciones para aceptar una futura corrección. | Criterios de salida en la sección 5. |

### Referencias examinadas

| Artefacto | Evidencia y ubicación relevante |
|---|---|
| Contrato de dominio de Semana 2 | [Semana 2, reglas y alcance](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/2a876ea3301740f13a3472df783aad9eb4fe4797/docs/modulos/recepcion-muestras/semana-02/semana-02-lsp-recepcion-muestras.md). |
| Caso de uso | [`ReceiveSpecimen.php`, líneas 14-37](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-03/ejemplo/src/Application/ReceiveSpecimen.php#L14-L37). |
| Puertos de aplicación | [`ReceptionPorts.php`, líneas 9-16](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-03/ejemplo/src/Application/ReceptionPorts.php#L9-L16). |
| Solicitud y reglas del dominio | [`ReceptionDecision.php`, líneas 22-45 y 82-106](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-03/ejemplo/src/Domain/ReceptionDecision.php#L22-L45). |
| Persistencia y esquema | [`PdoSpecimenRepository.php`, líneas 17-34](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-03/ejemplo/src/Persistence/PdoSpecimenRepository.php#L17-L34) y [`schema.sql`, líneas 1-10](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-03/ejemplo/database/schema.sql#L1-L10). |
| Pruebas y recorrido | [Suite de Semana 3](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-03/ejemplo/tests/run.php) y [recorrido de Semana 12, límites de orden y catálogo](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-12/recorrido/recorrido.php#L97-L109). |
| Límites declarados | [Defensa de Semana 12, recorrido y matriz de evidencia](https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1/blob/d0eed5698f5de27aab218ff518c2052a837a1642/docs/modulos/recepcion-muestras/semana-12/semana-12-defensa-recepcion-muestras.md#L28-L57). |

## 3. Roles propuestos

| Rol | Responsabilidad | Independencia requerida |
|---|---|---|
| Autora | Presentar requisitos, contexto, fuentes y responder observaciones; no cerrar sus propios hallazgos. | No aprueba los cambios que implementó. |
| Moderador/a | Confirmar alcance, facilitar la sesión, resolver desacuerdos de clasificación y registrar decisiones. | No ser autora de los componentes revisados. |
| Revisor/a de dominio y laboratorio | Contrastar criterios de aceptación/rechazo, catálogo y reglas por tipo/examen con el laboratorio. | Con conocimiento del flujo de muestras. |
| Revisor/a de seguridad y tenant | Verificar identidad, permisos, separación de tenant y exposición de datos. | Independiente de la implementación de recepción. |
| Revisor/a de API e integración | Verificar contrato, referencias a órdenes/pacientes/exámenes e interoperabilidad con EMR/LIS. | Confirmar contratos con los módulos propietarios. |
| Revisor/a de persistencia y pruebas | Revisar restricciones, concurrencia, transacciones, errores y cobertura de pruebas. | Reproducir los comandos y conservar resultados. |

## 4. Checklist de revisión

Estados: **Cumple** significa demostrado en el prototipo; **Parcial** indica evidencia limitada o una salvaguarda incompleta; **No cumple** señala una brecha comprobada frente al objetivo de integración; **Pendiente** requiere una decisión de negocio o un contrato externo. La numeración H se enlaza con los hallazgos.

| # | Punto de control | Estado | Evidencia / observación |
|---:|---|---|---|
| 1 | Las reglas de aceptación se ejecutan en el dominio y no en el adaptador PDO. | Cumple | `SpecimenAcceptancePolicy` determina la decisión; pruebas de sangre, etiqueta y recipiente pasan. |
| 2 | Un rechazo conserva al menos una razón y una aceptación no conserva razones de rechazo. | Cumple | Invariantes de `ReceptionDecision` y 13 aserciones de Semana 3. |
| 3 | Una regla específica de tipo de muestra no se aplica a otros tipos. | Cumple | Sello roto rechaza sangre y no rechaza orina en el recorrido de Semana 12. |
| 4 | El motivo introducido por el operador está normalizado contra un catálogo aprobado. | No cumple / decisión pendiente | El paso 8 demuestra que se guarda texto libre; ver H-04. El catálogo debe definirlo el laboratorio. |
| 5 | La recepción requiere una orden existente y vigente. | No cumple | El paso 9 acepta `ORD-NO-EXISTE`; no existe puerto de consulta de órdenes; ver H-01. |
| 6 | Orden, paciente y examen se verifican como una asociación autorizada y consistente. | No cumple | Solo se validan referencias no vacías; el caso de uso las copia sin consulta; ver H-01. |
| 7 | La operación exige identidad y autorización de rol en el límite de aplicación/servicio. | No cumple | Los puertos solo cubren repositorio y generador. La guarda del paso 1 está escrita en el recorrido, no en el prototipo; ver H-02. |
| 8 | La autorización limita el acceso a la institución/tenant correspondiente. | No cumple | La solicitud no lleva tenant ni hay política/puerto de tenant; ver H-02. |
| 9 | Cada recipiente recibe un identificador generado y el almacenamiento evita identificadores duplicados. | Parcial | El generador produce valores aleatorios y `identifier` es clave primaria. No se probó colisión al guardar ni recuperación controlada; ver H-05. |
| 10 | La persistencia usa parámetros y no oculta errores técnicos como éxito. | Cumple | PDO preparado; el error de persistencia se propaga y la suite verifica que no se simula éxito. |
| 11 | Se conservan eventos de custodia inmutables con actor, momento y transferencia. | No cumple | El esquema persiste una fila de recepción y `received_at`; no hay ledger/eventos append-only; ver H-03. |
| 12 | El esquema impone integridad referencial para orden, paciente y examen. | No cumple | Son columnas `NOT NULL` de texto; no se muestran claves foráneas ni tablas relacionadas en el esquema revisado; ver H-01. |
| 13 | Existe un contrato API documentado para consumidores e integración con EMR/LIS. | Pendiente / fuera del prototipo | La Semana 3 declara una CLI y excluye API e integración; no se puede aprobar interoperabilidad; ver H-06. |
| 14 | Las pruebas verifican reglas, rechazo, error de guardado y recorrido de límites conocidos. | Parcial | 13 aserciones de Semana 3 y 12 comprobaciones de Semana 12 pasan; no cubren autorización real, orden integrada, tenant ni custodia. |
| 15 | El resultado de revisión permite distinguir lo demostrado de las propuestas y límites. | Cumple | Los pasos 1, 8 y 9 de Semana 12 advierten explícitamente cuáles límites son demostraciones o controles de recorrido. |

## 5. Criterios de entrada y salida

### Entrada para una revisión de equipo

- Versión de requisitos de recepción y criterios aprobados por el laboratorio.
- Código, esquema, pruebas y configuración fijados a un commit identificable.
- Contratos vigentes de identidad/tenant, órdenes, pacientes, exámenes y EMR/LIS, o declaración formal de que no aplican.
- Entorno de prueba con datos sintéticos, sin credenciales ni datos clínicos reales.
- Autora, moderador/a y revisores asignados; conflictos de interés y alcance registrados.
- Suite reproducible y procedimiento para registrar defectos, decisiones y evidencia.

### Salida propuesta

- Checklist completo con cada punto en Cumple, No cumple, Parcial o Pendiente y una referencia.
- Hallazgos con severidad, condición reproducible, impacto, responsable y prueba de retest.
- Ningún hallazgo crítico abierto antes de un piloto; hallazgos altos con mitigación y responsable aceptados antes de integrar.
- Decisiones pendientes asignadas a un dueño de producto/laboratorio, sin inventar reglas clínicas.
- Evidencia de reejecución y aprobación registrada por las personas que realmente participen. Este documento no representa tal aprobación.

## 6. Hallazgos

La severidad refleja el riesgo si el prototipo se integra o reutiliza como servicio clínico; no afirma que el prototipo académico esté desplegado. **Abierto** significa que esta entrega identifica la brecha, no que la corrija.

### H-01 — No se valida la existencia ni la relación clínica de la orden

| Campo | Detalle |
|---|---|
| Severidad / estado | **Alta / Abierto** |
| Tipo | Brecha funcional y de integridad referencial |
| Evidencia | `ReceiveSpecimen::execute()` recibe la solicitud, aplica la política y guarda las referencias sin consultar órdenes. `ReceptionRequest` solo exige que orden, paciente y examen no estén vacíos. El paso 9 del recorrido persiste una muestra asociada a `ORD-NO-EXISTE`. El esquema solo define esas referencias como texto `NOT NULL`. |
| Condición | Enviar una referencia no vacía pero inexistente o perteneciente a otro paciente/examen. |
| Impacto | La recepción podría quedar asociada a una orden clínica inválida o inconsistente, impidiendo una trazabilidad fiable aguas abajo. |
| Acción propuesta | Definir un puerto de consulta de órdenes; comprobar existencia, estado, paciente, episodio y examen antes de aceptar/guardar. Añadir restricciones referenciales donde la arquitectura y propiedad de datos lo permitan. |
| Responsable propuesto | API/integración y dominio, con validación del dueño del módulo de órdenes. |
| Retest | Casos para orden inexistente, cerrada, de otro paciente, de otro tenant y examen incompatible; comprobar que no se inserta ninguna recepción. |

### H-02 — La autorización y el aislamiento por tenant no forman parte del caso de uso

| Campo | Detalle |
|---|---|
| Severidad / estado | **Alta / Abierto** |
| Tipo | Brecha de control de acceso |
| Evidencia | `ReceptionRequest` no contiene identidad ni tenant y `ReceiveSpecimen` no depende de un autorizador. `ReceptionPorts.php` expone solo repositorio y generador. El paso 1 de Semana 12 declara que su guarda de rol vive únicamente en el propio script y que el prototipo no implementa autorización. |
| Condición | Invocar el caso de uso fuera del recorrido de demostración, sin un límite autenticado/autorizado que aplique rol y tenant. |
| Impacto | No puede demostrarse quién registró la muestra ni que un operador esté restringido a su institución; riesgo de acceso o escritura no autorizados al integrar. |
| Acción propuesta | Definir el límite de autenticación y autorización, el contexto confiable de tenant y la regla de acceso para técnico de recepción. No aceptar el tenant como dato confiable del formulario. |
| Responsable propuesto | Seguridad/tenant y API/integración. |
| Retest | Pruebas de rol permitido/denegado y acceso entre tenants sobre el endpoint/caso integrado, verificando que los denegados no escriben datos. |

### H-03 — No existe un registro de cadena de custodia append-only

| Campo | Detalle |
|---|---|
| Severidad / estado | **Alta / Abierto** |
| Tipo | Brecha de trazabilidad |
| Evidencia | La tabla `specimen_receptions` guarda estado y `received_at`; no se identifica tabla de eventos, actor, ubicación, transferencia ni historial inmutable. La Semana 12 declara que la custodia append-only no está implementada. |
| Condición | Necesitar reconstruir quién recibió, transfirió o cambió la ubicación/estado de una muestra. |
| Impacto | El registro único no permite reconstruir de forma verificable la secuencia de custodia ni atribuir cada transferencia. |
| Acción propuesta | Acordar eventos mínimos de custodia con laboratorio; guardar actor, tiempo, evento y origen/destino en un registro append-only con controles de integridad. |
| Responsable propuesto | Dominio/laboratorio y persistencia. |
| Retest | Registrar recepción y transferencia; demostrar orden temporal, actor asociado y que una edición/borrado no reescribe el historial. |

### H-04 — El motivo manual de rechazo acepta texto libre

| Campo | Detalle |
|---|---|
| Severidad / estado | **Media / Abierto, decisión pendiente** |
| Tipo | Calidad y normalización de datos |
| Evidencia | La política agrega `rejectionReason` sin validar un catálogo. El paso 8 persiste `no me gustó` como motivo. La Semana 12 plantea CC-REC-01 como cambio hipotético y aclara que el catálogo real debe definirlo el laboratorio. |
| Condición | Registrar motivos manuales con ortografía, categorías o criterios inconsistentes. |
| Impacto | Reportes y análisis de causas pueden fragmentarse; no se debe inventar un catálogo sin aprobación del laboratorio. |
| Acción propuesta | Obtener el catálogo y su dueño; separar código de motivo de observación libre y migrar los valores existentes conservándolos como observación. |
| Responsable propuesto | Laboratorio/producto; dominio y persistencia implementan después de la decisión. |
| Retest | Aceptar códigos habilitados, rechazar códigos inexistentes y conservar la observación por separado. |

### H-05 — Una colisión de identificador detiene la recepción sin recuperación definida

| Campo | Detalle |
|---|---|
| Severidad / estado | **Baja / Abierto** |
| Tipo | Resiliencia ante conflicto |
| Evidencia | El identificador se genera aleatoriamente y `identifier` es clave primaria, por lo que la base impide guardar un duplicado. Las pruebas verifican valores generados distintos, pero no simulan una colisión al insertar ni una política de reintento. |
| Condición | El generador produce un valor ya persistido. |
| Impacto | La restricción evita sobrescribir la muestra existente, pero la recepción falla y requiere recuperación explícita; no se observó pérdida silenciosa. |
| Acción propuesta | Mantener la restricción única y definir un reintento acotado solo para conflicto de identificador, diferenciándolo de otros errores de persistencia. |
| Responsable propuesto | Persistencia y pruebas. |
| Retest | Inyectar un generador determinista con colisión y confirmar que no se altera el registro previo, que el reintento es acotado y que otros errores se propagan. |

### H-06 — No hay contrato de API ni de integración revisable en este prototipo

| Campo | Detalle |
|---|---|
| Severidad / estado | **Media / Pendiente de alcance** |
| Tipo | Dependencia de integración |
| Evidencia | La Semana 3 define un prototipo PHP CLI y declara que no hay API ni integración con EMR/órdenes. Los consumidores y formatos de interoperabilidad no aparecen en los puertos revisados. |
| Condición | Solicitar aprobación para integrar recepción con un cliente o sistema externo. |
| Impacto | No es posible evaluar compatibilidad, errores de contrato, idempotencia de solicitudes ni responsabilidades de datos del consumidor. |
| Acción propuesta | Antes de integrar, acordar contrato versionado, autenticación, identificadores, errores, idempotencia y propietario del dato de orden/paciente. Si la CLI es el alcance académico final, registrar formalmente la exclusión. |
| Responsable propuesto | API/integración y responsable del producto. |
| Retest | Revisar contrato aprobado y pruebas de consumidor/productor; demostrar manejo de solicitud repetida y errores de orden. |

## 7. Registro de validación

Los comandos se ejecutaron contra el worktree de Semana 12, en el commit fuente declarado, y no contra la rama documental actual. El resultado completo está en [verificacion-semana-13.txt](evidencia/verificacion-semana-13.txt). Las pruebas usan datos sintéticos y SQLite en memoria; no acreditan controles de producción.

## 8. Conclusión

El prototipo demuestra reglas básicas de aceptación/rechazo, generación de identificadores, persistencia parametrizada y propagación de errores técnicos. La revisión no recomienda tratarlo como un flujo listo para integración clínica: siguen abiertas brechas de orden, autorización/tenant y cadena de custodia, además de decisiones sobre catálogo e interoperabilidad. La prioridad de cierre es H-01, H-02 y H-03; la severidad no sustituye la aprobación de los responsables clínicos y técnicos.

## 9. Formato para registrar nuevos hallazgos

Cada hallazgo adicional debe incluir:

`ID | severidad | estado | tipo | ubicación/evidencia | condición reproducible | impacto | acción acordada | responsable | retest | fecha de cierre`

No cerrar un hallazgo solo porque se implementó un cambio: registrar commit, prueba de retest y resultado. Los desacuerdos se documentan como decisión pendiente, con responsable y fecha objetivo.
