# Semana 2 — Alertas críticas y notificaciones internas

**Proceso:** detección, envío, acuse y escalamiento de una alerta crítica.
**Entregable:** RF/RNF, criterios de aceptación y ejemplo SOLID (principio asignado: **SRP**).

| Parte | Contenido | Archivo |
|---|---|---|
| A | Requisitos funcionales y no funcionales, criterios Dado/Cuando/Entonces y trazabilidad | este README |
| B | Ejemplo ejecutable y verificación del antes/después | `srp/ejemplo/` |
| C | Diseño antes/después aplicando SRP, responsabilidades y dependencias (documentación) | `srp/README.md` y `srp/diagramas/` |

Los IDs (CU, RN) vienen de la semana 1. Se agrega **RN-11** (límites de configuración de plazos).

## 1. Requisitos funcionales

| ID | Requisito | CU | Reglas |
|---|---|---|---|
| RF-01 | El sistema registra una alerta crítica a partir de un evento del sistema origen y la deja en `CREADA` con su evento «creada». | CU-01 | RN-01 |
| RF-02 | El sistema no duplica una alerta cuando llega otra vez el mismo origen + id de origen en el mismo tenant; devuelve la existente. | CU-01 | RN-02 |
| RF-03 | El sistema resuelve los destinatarios por rol, servicio y nivel de escalamiento; sin destinatarios en un nivel, pasa al siguiente. | CU-02 | RN-03 |
| RF-04 | El sistema envía la notificación a cada destinatario por el puerto de notificación y registra el evento «enviada». | CU-03 | — |
| RF-05 | El sistema reintenta una entrega fallida hasta 3 intentos por destinatario; agotados, escala de inmediato. | CU-09 | RN-08 |
| RF-06 | Un destinatario autorizado acusa la alerta; un segundo acuse no crea registros nuevos. | CU-04 | RN-06, RN-07 |
| RF-07 | El sistema escala un nivel cada alerta enviada o escalada cuyo plazo de acuse venció; en el nivel 2 registra «escalamiento agotado». | CU-05 | RN-04, RN-05 |
| RF-08 | El médico tratante o el jefe de servicio cierra una alerta acusada con una nota obligatoria. | CU-06 | RN-09 |
| RF-09 | Un usuario autorizado del mismo tenant consulta el historial completo de una alerta. | CU-07 | RN-10 |
| RF-10 | El administrador configura el plazo de acuse por prioridad dentro de los límites permitidos. | CU-08 | RN-04, RN-11 |

**RN-11:** el plazo configurable de acuse va de 5 a 60 minutos; fuera de ese rango se rechaza.

## 2. Requisitos no funcionales

| ID | Atributo | Requisito medible | Cómo se verifica |
|---|---|---|---|
| RNF-01 | Seguridad (tenant) | Toda lectura y escritura filtra por el tenant del usuario; una alerta de otro tenant se responde como inexistente. | Prueba de acceso cruzado (S5, S15) |
| RNF-02 | Privacidad | La notificación lleva solo código de paciente, prueba, valor y prioridad; nunca nombre del paciente. | Revisión del mensaje simulado (S3) |
| RNF-03 | Trazabilidad | El 100 % de las transiciones guarda evento, actor y momento (`timestamptz`). | Prueba del historial (S3, S15) |
| RNF-04 | Testabilidad | El tiempo se obtiene de un reloj inyectable; ninguna prueba espera minutos reales. La suite completa corre en menos de 10 s. | Pruebas con reloj fijo (S3) |
| RNF-05 | Confiabilidad | Un fallo del canal de notificación no pierde la alerta: queda guardada y pasa a reintento o escalamiento. | Prueba con adaptador que falla (S3) |
| RNF-06 | Mantenibilidad | Cambiar el canal de envío solo requiere un adaptador nuevo del puerto de notificación, sin tocar casos de uso. | Ejemplo SRP (parte C) y S4 |
| RNF-07 | Persistencia segura | Acceso a datos solo con PDO, `ERRMODE_EXCEPTION`, `prepare()` + `execute()`. | Revisión técnica (S13) |
| RNF-08 | Desempeño | Registrar una alerta (sin contar el envío) toma menos de 500 ms con la base local. | **Por verificar** en S15 midiendo con `microtime()` |

## 3. Criterios de aceptación

| ID | RF/RNF | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-01 | un evento válido de laboratorio para `PAC-00451`, prioridad `CRITICA`, tenant `HOSP-GUA-01` | se registra | existe una alerta `CREADA` con un evento «creada» y actor `sistema:laboratorio` |
| CA-02 | RF-01 | un evento sin código de paciente | se registra | se rechaza con error de validación y no se guarda nada |
| CA-03 | RF-02 | una alerta ya registrada con id de origen `LAB-77801` | llega otra vez `LAB-77801` del mismo origen y tenant | se devuelve la misma alerta y el total de alertas no cambia |
| CA-04 | RF-03 | un nivel 0 sin personal disponible en `MED-INT` | se resuelven destinatarios | se registra «sin destinatarios» y se notifica al nivel 1 (`jefe.servicio01`) |
| CA-05 | RF-04 | una alerta `CREADA` con destinatarios `enf.turno01` y `med.tratante01` | se envía | el adaptador simulado registra 2 mensajes y la alerta queda `ENVIADA` |
| CA-06 | RF-05 | un adaptador que falla siempre para `enf.turno01` | se envía | hay exactamente 3 intentos para ese destinatario y luego un escalamiento inmediato |
| CA-07 | RF-06 | una alerta `ENVIADA` notificada a `enf.turno01` | `enf.turno01` acusa | queda `ACUSADA` con un evento «acusada» con su usuario y hora |
| CA-08 | RF-06 | una alerta ya acusada por `enf.turno01` | acusa otra vez | el historial conserva un solo evento «acusada» |
| CA-09 | RF-06 | una alerta del tenant `HOSP-GUA-01` | la intenta acusar un usuario no notificado o de `HOSP-XEL-02` | se rechaza y la alerta no cambia |
| CA-10 | RF-07 | una alerta `CRITICA` enviada a las 10:00 (reloj fijo) | el reloj marca 10:15 y corre la revisión | pasa a nivel 1 y queda `ESCALADA` |
| CA-11 | RF-07 | una alerta `CRITICA` enviada a las 10:00 | el reloj marca 10:14 y corre la revisión | no se escala (límite inferior del plazo) |
| CA-12 | RF-07 | una alerta acusada a las 10:05 | el reloj marca 10:30 y corre la revisión | no se escala |
| CA-13 | RF-07 | una alerta en nivel 2 con plazo vencido | corre la revisión | se registra «escalamiento agotado» y el nivel sigue en 2 |
| CA-14 | RF-08 | una alerta `ACUSADA` | `med.tratante01` la cierra sin nota | se rechaza; con nota, queda `CERRADA` |
| CA-15 | RF-09 | una alerta creada, enviada, acusada y cerrada | se consulta el historial | aparecen los 4 eventos en orden, cada uno con actor y momento |
| CA-16 | RF-10 | el plazo `CRITICA` en 15 min | el administrador intenta 4 o 61 min | se rechaza (RN-11); con 5 y 60 se acepta |

## 4. Trazabilidad CU → RF → CA

| CU | RF | CA |
|---|---|---|
| CU-01 | RF-01, RF-02 | CA-01, CA-02, CA-03 |
| CU-02 | RF-03 | CA-04 |
| CU-03 | RF-04 | CA-05 |
| CU-09 | RF-05 | CA-06 |
| CU-04 | RF-06 | CA-07, CA-08, CA-09 |
| CU-05 | RF-07 | CA-10, CA-11, CA-12, CA-13 |
| CU-06 | RF-08 | CA-14 |
| CU-07 | RF-09 | CA-15 |
| CU-08 | RF-10 | CA-16 |

Los datos son ficticios: pacientes solo por código y personal por usuario ficticio.
