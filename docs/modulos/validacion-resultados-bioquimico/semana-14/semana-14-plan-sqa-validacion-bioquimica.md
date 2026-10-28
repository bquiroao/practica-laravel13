# Semana 14 — Plan SQA de validación de resultados por bioquímico

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Validación de resultados por bioquímico |
| **Tema** | Confiabilidad y aseguramiento de calidad (SQA) |
| **Proceso bajo aseguramiento** | «Revisión, validación o rechazo de un resultado por bioquímico» |
| **Estudiante según la captura** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## 1. Propósito y alcance

Este plan define cómo vigilar la calidad del proceso desde que un bioquímico abre una versión pendiente hasta que la valida o la rechaza con motivo. Asegura revisión profesional, autorización en el hospital correspondiente, consistencia de versión, integridad de la decisión, registro de autoría y seguimiento de errores y riesgos.

Se limita al flujo del módulo. No incluye captura/corrección del resultado, recepción de muestras, órdenes, interpretación diagnóstica, comunicación al paciente ni procesos de otros módulos. La validación es una decisión profesional: este plan no asigna intervalos de referencia, valores críticos ni reglas clínicas automáticas. La «firma» es la asociación auditable del usuario autenticado, hospital, versión, decisión y fecha/hora; no se afirma que sea firma criptográfica o certificación legal.

**Estado de la evidencia:** las semanas 1, 2 y 15 documentan el proceso, el prototipo DIP y pruebas repetibles. No se ha confirmado un servicio productivo, almacén de auditoría, monitoreo operacional ni integración clínica. Por tanto, este documento especifica controles y fuentes que deberán aprobarse e implantarse; no afirma que ya estén en producción. Los números de meta son objetivos iniciales de gestión para el piloto, sujetos a aprobación institucional y a revisión tras una línea base de cuatro semanas. No son umbrales clínicos ni sustituyen una política institucional.

## 2. Política de calidad y responsabilidades

1. Ningún resultado se considera validado o rechazado si la operación falla, la autorización no corresponde al hospital o la versión dejó de ser la vigente.
2. Una validación conserva el valor y unidad revisados. Un rechazo requiere motivo y conserva el resultado para seguimiento.
3. La decisión se registra con actor, hospital, resultado, versión y fecha/hora. Si falta evidencia o contexto, el resultado queda pendiente y se escala; no se completa con valores supuestos.
4. Los errores de concurrencia, autorización o persistencia se distinguen de una decisión clínica y se investigan.
5. El bioquímico conserva la responsabilidad del juicio profesional. Las reglas de negocio no pueden crear una decisión clínica a partir de una cifra aislada.

| Rol responsable | Responsabilidad en este plan |
|---|---|
| Responsable técnico del módulo / calidad de software | Mantener reglas, controles, fuentes de datos, pruebas, incidentes técnicos y cambios de versión. |
| Jefatura de laboratorio | Aprobar procedimientos, priorización operativa, metas y medidas correctivas; asignar responsables clínicos. |
| Bioquímico revisor | Revisar contexto y versión, decidir, justificar el rechazo y escalar anomalías clínicas o de integridad. |
| Administrador de seguridad | Aprovisionar roles por hospital, retirar accesos, revisar eventos de acceso y preservar registros. |
| Operación de plataforma / datos | Vigilar disponibilidad, transacciones, respaldos, sincronización de reloj y recuperación. |
| Responsable SQA | Consolidar métricas, seleccionar muestras, registrar hallazgos y verificar acciones correctivas. Puede ser una función asignada; no implica que exista hoy un puesto desplegado. |

La jefatura de laboratorio designa una persona titular y suplente para cada responsabilidad antes de iniciar el piloto. El bioquímico que tomó una decisión no debe aprobar su propia investigación de incidente crítico.

## 3. Métricas y umbrales

Los objetivos «100 %» son condiciones de integridad, seguridad o evidencia: toda excepción debe investigarse, incluso si la muestra mensual es pequeña. El SLO de tiempo es operativo y no define prioridad clínica. Para priorizar resultados sensibles al tiempo se aplica el procedimiento institucional aprobado, que está fuera de la definición de umbrales de este documento.

| ID | Métrica y cálculo | Meta / umbral de alerta | Fuente de datos | Responsable y frecuencia | Acción si se incumple |
|---|---|---|---|---|---|
| M-01 | **Oportunidad de revisión:** decisiones registradas dentro del plazo operativo aprobado ÷ resultados pendientes elegibles × 100. Excluir pausas documentadas y separar por prioridad institucional. | Meta inicial ≥95 % en un día hábil para la cola ordinaria; alerta si <95 % en cualquier semana. La prioridad urgente obedece al procedimiento institucional, no a este SLO. | Marca de tiempo de ingreso a cola, asignación/prioridad aprobada y decisión en registro de auditoría; reporte de cola. | Jefatura de laboratorio; semanal. | Revisar antigüedad, carga y cobertura; asignar revisión; documentar causa y plan. Si un procedimiento de prioridad se incumplió, escalar de inmediato por ese procedimiento. |
| M-02 | **Decisiones con autorización y ámbito correctos:** decisiones cuya identidad autenticada tiene el rol requerido y acceso al hospital del resultado ÷ total de decisiones × 100; contar además intentos denegados. | 100 % de decisiones autorizadas y cero escrituras no autorizadas. Cualquier caso confirmado es incidente crítico, no una tolerancia estadística. | Registro de decisiones unido a historial de roles/membresías vigentes y eventos de autorización; acceso restringido. | Administrador de seguridad; alertas continuas y revisión semanal. | Suspender el acceso afectado, proteger resultados, preservar evidencia, notificar a seguridad/jefatura y evaluar impacto y obligaciones institucionales antes de reanudar. |
| M-03 | **Completitud de trazabilidad:** decisiones con actor, hospital, identificador de resultado, versión, decisión y fecha/hora ÷ decisiones totales × 100. | 100 %; una decisión sin cualquiera de los campos requeridos dispara alerta. | Registro de auditoría de decisiones y reporte de integridad. El prototipo Semana 15 enumera esos datos como requisito; no acredita un registro productivo. | Responsable técnico del módulo; revisión diaria automatizada y consolidación semanal SQA. | Bloquear el cierre del flujo si se detecta antes de confirmar. Si ya ocurrió, mantener el resultado en revisión, abrir incidente y restaurar evidencia desde fuentes autorizadas; nunca reconstruir autoría por inferencia. |
| M-04 | **Rechazos justificados:** rechazos con motivo no vacío y seguimiento asignado ÷ rechazos totales × 100. Verificar también que se conserve el valor y la versión revisados. | 100 % con motivo y responsable de seguimiento. | Eventos de rechazo, campo/motivo, cola de pendientes de seguimiento y muestra de revisión SQA. | Bioquímico revisor registra; jefatura revisa semanalmente. | No aceptar rechazo incompleto; devolver para completar antes del cierre. Si ya fue cerrado, mantenerlo visible como no conformidad y solicitar documentación complementaria sin alterar la decisión original. |
| M-05 | **Control de versión concurrente:** envíos basados en versión obsoleta que se rechazan como conflicto ÷ envíos obsoletos probados o identificados × 100. Contar por separado conflictos legítimos. | 100 % de los casos obsoletos detectados; cero decisiones aplicadas sobre una versión obsoleta. | Resultado esperado de pruebas de concurrencia en preproducción; eventos de conflicto y comparación de versión al guardar en producción, si está disponible. | Responsable técnico del módulo; antes de cada liberación y revisión mensual de eventos. | Detener liberación ante fallo de prueba. En producción, suspender decisiones afectadas, volver a cargar la versión vigente y abrir análisis de causa raíz. |
| M-06 | **Atomicidad de decisión y auditoría:** operaciones fallidas que dejan cero cambios parciales ÷ operaciones fallidas verificadas × 100. | 100 % en pruebas; cero estado/auditoría parcial en operación. | Pruebas del adaptador transaccional, logs de transacción y conciliación entre estado final e historial de decisiones. | Operación de plataforma/datos y responsable técnico; por liberación, conciliación diaria y simulacro trimestral. | Poner el flujo en modo seguro, impedir nuevas decisiones si no se puede garantizar consistencia, preservar logs, restaurar/conciliar mediante procedimiento revisado y reabrir solo tras verificación. |
| M-07 | **Falla técnica del flujo:** intentos fallidos por error técnico (excluye denegación, rechazo clínico, entrada inválida y conflicto válido) ÷ intentos de decisión × 100. | Meta inicial ≤1 % por semana; alerta inmediata ante indisponibilidad o aumento sostenido, y siempre que el fallo impida una decisión segura. | Logs de aplicación/servicio con códigos de resultado, monitoreo de disponibilidad y tickets de incidente, correlacionados sin registrar datos clínicos innecesarios. | Operación de plataforma; monitoreo continuo, revisión semanal por SQA. | Activar respuesta a incidente, comunicar indisponibilidad, preservar la cola sin aparentar éxito y recuperar siguiendo el procedimiento aprobado. Investigar causa y verificar con pruebas antes de volver a operar. |

**Reglas de medición:** conservar numerador, denominador, periodo, exclusiones justificadas, versión del sistema y responsable de extracción. No calcular una métrica cuando falte la fuente; marcarla «sin datos», asignar acción para instrumentarla y no reportarla como 100 %. Los reportes deben usar identificadores seudonimizados cuando baste para la revisión.

## 4. Matriz de riesgos

Escala cualitativa inicial para priorizar controles, no estadística clínica: probabilidad **P** e impacto **I** de 1 (bajo) a 5 (muy alto); nivel = P × I. 1–4 bajo, 5–9 medio, 10–15 alto, 16–25 crítico. La jefatura de laboratorio y SQA reevalúan P/I con incidentes reales durante el piloto; no son frecuencias demostradas.

| ID | Riesgo / efecto | P × I | Nivel | Control preventivo | Control detectivo | Responsable del riesgo / respuesta |
|---|---|---:|---|---|---|---|
| R-01 | Usuario sin rol o de otro hospital valida/rechaza; exposición o alteración de un resultado. | 2 × 5 = 10 | Alto | Autorización por acción y hospital antes de leer/decidir; mínimo privilegio y alta/baja de accesos revisada. | M-02, alertas de denegación y revisión semanal de decisiones vs membresías vigentes. | Administrador de seguridad; contener acceso, preservar logs y evaluar todas las decisiones afectadas. |
| R-02 | Se decide sobre contexto incorrecto o incompleto (orden, examen, muestra, paciente) y se atribuye al resultado equivocado. | 2 × 5 = 10 | Alto | Lista de revisión de asociaciones e identidad/contexto; impedir la decisión cuando falte una asociación requerida. | Muestreo de decisiones y reporte de contexto incompleto; revisar no conformidades. | Bioquímico revisor y jefatura; mantener pendiente y corregir la asociación por proceso autorizado, nunca editar a ciegas. |
| R-03 | La versión cambia entre lectura y escritura y se acepta una decisión obsoleta. | 3 × 4 = 12 | Alto | Comparación atómica de versión esperada y estado pendiente al guardar; el conflicto obliga a recargar y revisar. | M-05 y pruebas de concurrencia por liberación; tendencia de conflictos semanales. | Responsable técnico; bloquear versión fallida, identificar operaciones afectadas y ejecutar análisis de causa raíz. |
| R-04 | Decisión, autoría o fecha/hora no queda registrada o puede alterarse; se pierde trazabilidad. | 3 × 5 = 15 | Alto | Validaciones obligatorias, registro de auditoría protegido contra modificación por el usuario de negocio y reloj sincronizado. | M-03, conciliación de decisión/estado y revisión de accesos al registro. | Responsable técnico y seguridad; conservar fuentes, marcar registro incompleto y abrir incidente de integridad. |
| R-05 | Se persiste el estado sin auditoría, o auditoría sin estado, tras un fallo parcial. | 2 × 5 = 10 | Alto | Transacción única para transición y evento de decisión; ante fallo, rollback y respuesta explícita de error. | M-06, conciliación diaria y simulacro trimestral de fallo. | Operación de plataforma/datos; detener flujo seguro y conciliar con doble verificación antes de reabrir. |
| R-06 | Rechazo sin motivo o sin seguimiento; el equipo no conoce qué revisar y el resultado queda abandonado. | 3 × 3 = 9 | Medio | Motivo obligatorio y asignación al flujo autorizado de seguimiento antes de cerrar. | M-04 y revisión semanal de rechazos abiertos y antigüedad. | Jefatura de laboratorio; reclamar documentación y asignar responsable con fecha objetivo. |
| R-07 | Falla del servicio o cola acumulada retrasa revisión; se pierde una prioridad institucional o se muestra éxito falso. | 3 × 4 = 12 | Alto | Manejo explícito de errores, conservación de pendientes y procedimiento de continuidad/priorización aprobado. | M-01 y M-07, alertas de cola/errores y revisión diaria de indisponibilidad. | Jefatura de laboratorio y operación; aplicar contingencia aprobada, comunicar impacto y recuperar la cola priorizada. |
| R-08 | Una regla automática o un umbral genérico se interpreta como decisión clínica, validando/rechazando por cifra aislada. | 2 × 5 = 10 | Alto | Prohibir umbrales no aprobados; revisión humana y catálogo/procedimiento institucional controlado por examen. | Revisión SQA de cambios, muestreo de decisiones y pruebas que aseguran que el prototipo no decide por rango. | Jefatura de laboratorio; retirar regla no autorizada, evaluar decisiones afectadas y requerir aprobación profesional antes de reactivar. |

R-01, R-04 y R-05 se tratan como riesgos de tolerancia cero para decisiones no autorizadas, evidencia de auditoría omitida y estados parciales. «Cero tolerancia» requiere detener/escalar el caso; no significa que el riesgo de ocurrencia sea cero.

## 5. Controles preventivos y detectivos

| Control | Tipo | Ejecución y responsable | Umbral / evidencia | Acción ante desviación |
|---|---|---|---|---|
| C-P01 Autorización y separación por hospital | Preventivo | Servicio verifica rol, actor y hospital para cada lectura/decisión; administrador revisa accesos y bajas semanalmente. | 100 % de decisiones con autorización vigente; evidencia: evento de autorización unido a la decisión. | Denegar antes de consultar/escribir; evento confirmado de acceso indebido se gestiona como incidente de seguridad R-01. |
| C-P02 Lista de revisión profesional y contexto | Preventivo | Bioquímico confirma resultado, orden, examen, muestra, identidad, versión y datos disponibles; aplica procedimiento institucional vigente. | Todos los campos/asociaciones requeridos disponibles; si no, no decide. Evidencia: checklist o registro equivalente sin copiar innecesariamente datos sensibles. | Dejar pendiente y solicitar corrección por flujo autorizado; escalar discrepancia de identidad/contexto. |
| C-P03 Motivo obligatorio y seguimiento del rechazo | Preventivo | El bioquímico documenta motivo y asigna el siguiente responsable según procedimiento. | 100 % de rechazos con motivo y seguimiento. Evidencia: evento de rechazo y asignación. | Impedir cierre incompleto o registrar no conformidad si el sistema no lo permite. |
| C-P04 Versión vigente y escritura atómica | Preventivo | Aplicación y adaptador verifican versión/estado en la misma operación que registra estado y auditoría; responsable técnico valida cambios. | Pruebas de concurrencia y rollback pasan en cada liberación; ninguna versión obsoleta se acepta. Evidencia: suite, log transaccional y versión liberada. | Bloquear liberación/decisión hasta corregir; preservar y recargar la versión vigente. |
| C-P05 Protección de auditoría y tiempo | Preventivo | Operación restringe escritura/lectura a roles necesarios, protege respaldos y sincroniza reloj del servicio. | 100 % de decisiones con campos M-03; alerta si desfase de reloj supera 60 segundos (objetivo técnico provisional para investigar, no dato clínico). Evidencia: controles de acceso, monitoreo y registro de sincronización. | Marcar eventos temporalmente dudosos, escalar a operación, no corregir marcas históricas sin rastro. |
| C-D01 Revisión de autorización y acceso | Detectivo | Seguridad concilia semanalmente eventos y roles vigentes; atiende alerta de intento fuera de ámbito. | Toda decisión coincidente; una escritura no autorizada o una inconsistencia activa incidente. Evidencia: reporte firmado por revisor distinto al operador. | Contención, investigación y notificación según política institucional. |
| C-D02 Muestreo de trazabilidad y calidad de revisión | Detectivo | SQA y jefatura revisan cada mes 10 % de decisiones, con mínimo 30 si hay ese volumen; si hay menos de 30, revisar todas. Selección estratificada por validación/rechazo, turno y hospital, sin reemplazar el juicio clínico. | 100 % campos obligatorios; toda discrepancia contextual o de autorización se escala. Evidencia: ficha de muestreo y hallazgos seudonimizados. | Abrir no conformidad, ampliar muestra al 100 % del grupo afectado cuando se sospeche patrón, asignar acción y fecha de cierre. |
| C-D03 Seguimiento de cola, conflictos y errores | Detectivo | Jefatura revisa cola y rechazos cada día hábil; operación revisa errores/indisponibilidad diariamente; SQA consolida semanalmente. | Incumplimientos de M-01/M-04/M-05/M-07 generan alerta y responsable asignado. | Repriorizar según procedimiento aprobado, comunicar retraso o indisponibilidad y registrar causa/plan. |
| C-D04 Conciliación de integridad | Detectivo | Operación compara diariamente decisiones confirmadas contra estado vigente y evento de auditoría; SQA verifica mensualmente evidencia de la conciliación. | Cero discrepancias de M-03/M-06. | Poner en modo seguro el flujo afectado, abrir incidente y conciliar con revisión independiente. |
| C-D05 Pruebas de resiliencia del flujo | Detectivo | Responsable técnico ejecuta pruebas de denegación, contexto incompleto, versión obsoleta, fallo de escritura y recuperación antes de cada liberación y trimestralmente. | 100 % de pruebas críticas aprobadas; no liberar con fallo en autorización, atomicidad o versión. | Rechazar liberación, corregir y repetir conjunto completo; registrar versión, salida y aprobación. |

Los registros de evidencia deben tener acceso limitado, retención según la política institucional y contenido mínimo necesario. Este plan no establece un plazo legal de retención ni presupone que exista una plataforma específica de auditoría.

## 6. Calendario de seguimiento

| Momento | Actividad | Responsable | Evidencia / salida |
|---|---|---|---|
| Antes del piloto (semana 0) | Aprobar procedimiento y prioridad institucional; nombrar titulares/suplentes; confirmar fuentes, permisos, mecanismo de reporte y plan de continuidad; ejecutar pruebas C-D05. | Jefatura de laboratorio, SQA, responsable técnico, seguridad y operación. | Aprobación del plan, matriz de roles, lista de fuentes y acta de pruebas. No iniciar si autorización, trazabilidad o atomicidad no pasan. |
| Diario, cada día hábil | Revisar cola pendiente, errores, denegaciones anómalas, decisiones incompletas y conciliación de estado/auditoría. | Jefatura de laboratorio y operación; seguridad atiende alertas. | Registro diario, incidentes y asignaciones. |
| Semanal | Calcular M-01, M-02 y M-07; revisar rechazos abiertos, conflictos de versión, incidentes y acciones vencidas. | SQA consolida; responsables de métrica explican desviaciones. | Tablero semanal y acciones con dueño/fecha. |
| Mensual (primeros 5 días hábiles del mes siguiente) | Calcular las siete métricas, efectuar muestreo C-D02, revisar matriz de riesgos, causas raíz y tendencias; aprobar acciones o aceptar riesgo residual. | Jefatura de laboratorio preside; SQA documenta; seguridad y operación participan. | Acta de revisión, ficha de muestreo y plan CAPA (acción correctiva/preventiva) con verificación. |
| Cada trimestre y antes de cambios mayores | Simular error/recuperación, revisar permisos, volver a ejecutar casos críticos, evaluar metas contra la línea base y actualizar riesgos. | Responsable técnico, seguridad, operación y SQA; aprobación de jefatura. | Informe de prueba, lista de accesos revisada, cambios de metas justificados y aprobados. |
| Inmediatamente ante incidente crítico | Contener, conservar registros, estimar alcance, comunicar por canales institucionales, decidir continuidad segura y realizar análisis causal. | Responsable del riesgo y jefatura; seguridad/operación según evento. | Ticket con cronología, resultados afectados, acciones y criterios verificables de cierre. |

## 7. No conformidades y mejora

Toda desviación de una meta o control crea un registro con identificador, fecha, proceso/versiones afectadas, severidad, evidencia, responsable y fecha objetivo. La severidad combina impacto y alcance; una decisión no autorizada, una pérdida de trazabilidad o una escritura parcial se escalan inmediatamente. El responsable propone contención, causa raíz y acciones correctivas/preventivas. SQA valida el cierre con evidencia objetiva y confirma en el siguiente ciclo que la desviación no se repite. No se cierra un incidente porque la métrica vuelva a su meta sin explicar el evento.

La línea base se obtiene durante las primeras cuatro semanas del piloto, con revisión semanal y aprobación mensual. Si no hay telemetría o denominador fiable, la métrica se reporta «sin datos» y se crea una tarea de instrumentación; no se sustituyen datos faltantes por una estimación. Los cambios a objetivos, formularios o procedimientos requieren control de versión, responsable aprobador y fecha de vigencia.

## 8. Trazabilidad con el módulo

| Artefacto | Relación con el plan |
|---|---|
| [Semana 1 — modelo del proceso](../semana-01/semana-01-uml-validacion-resultados.md) | Define actor, flujo, decisiones, errores, alcance y límites clínicos. |
| [Semana 2 — requisitos y DIP](../semana-02/semana-02-dip-validacion-resultados.md) | Define autorización, versión, rechazo con motivo, auditoría, atomicidad y respuestas de error. |
| [Semana 15 — pruebas del flujo](../semana-15/semana-15-pruebas-validacion-bioquimica.md) | Evidencia los casos de autorización, contexto, versión, decisión, motivos, trazabilidad y persistencia del prototipo. Sus dobles no son evidencia de operación productiva. |

Este documento es un plan propuesto para revisión académica e institucional. Debe aprobarse, asignarse y adaptarse a los procedimientos vigentes antes de usarlo como política operacional.
