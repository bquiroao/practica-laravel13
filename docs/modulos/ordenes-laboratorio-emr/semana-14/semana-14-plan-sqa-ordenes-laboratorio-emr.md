# Semana 14 — Plan SQA de órdenes de laboratorio desde EMR

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Órdenes de laboratorio desde EMR |
| **Proceso bajo aseguramiento** | Emisión de orden desde el expediente y seguimiento de su estado |
| **Tema** | Confiabilidad y aseguramiento de calidad (SQA) |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## 1. Propósito, alcance y estado de evidencia

Este plan establece cómo revisar la calidad del flujo desde que un profesional autorizado prepara pruebas para un paciente y episodio del expediente, confirma la emisión completa de la orden y consulta posteriormente su estado confirmado. Incluye integridad de orden y partidas, autorización y contexto, actualización de seguimiento, trazabilidad, disponibilidad y respuesta ante fallos.

El plan cubre el módulo de órdenes EMR. No decide criterios clínicos de solicitud, catálogo de pruebas, reglas de diagnóstico, toma/recepción de muestras, procesamiento, resultados ni validación bioquímica. Tampoco define una máquina de estados: las transiciones válidas, su origen autorizado y sus nombres deben ser aprobados por el contrato institucional entre órdenes y laboratorio.

**Estado de evidencia:** las semanas 1 y 2 documentan el flujo y un ejemplo PHP verificable con dobles; la Semana 15 aporta pruebas unitarias, de caja blanca y caja negra del prototipo. No se acreditó en esta entrega un servicio productivo integrado con el expediente, un EMR externo, telemetría operacional, almacenamiento de auditoría ni despliegue. Por ello, los controles de producción y fuentes operacionales de este plan son requisitos propuestos, no controles que se afirme que ya estén activos.

Las metas cuantitativas son **objetivos iniciales propuestos para aprobación**, no mediciones del sistema ni SLA institucionales. Se revisan tras una línea base de cuatro semanas de piloto; si no existe fuente confiable, la métrica se marca «sin dato» y se abre una acción de instrumentación en lugar de estimar valores.

## 2. Política de calidad y responsabilidades

1. Una orden solo se informa como emitida cuando la orden completa y sus partidas requeridas se guardan y el componente responsable confirma la operación.
2. Una falla al validar contexto, catálogo o autorización no se transforma en una orden válida ni en un estado supuesto.
3. La consulta distingue resultado vacío, dato confirmado e indisponibilidad. No mostrar una lectura fallida como estado actual.
4. Cada lectura y escritura aplica autorización al usuario, expediente y hospital correspondientes. Un identificador no sustituye autorización.
5. Las actualizaciones de estado se aceptan únicamente de un origen permitido y conforme al ciclo de vida aprobado. Una transición rechazada no altera el estado previo.
6. Los datos de órdenes se minimizan en logs y reportes de calidad. No registrar nombres, identificadores directos ni payloads clínicos cuando basten códigos de correlación.
7. Las metas de este plan no definen prioridad clínica, tiempos de respuesta a pacientes ni SLA de integración. Se aplican los procedimientos institucionales aprobados.

| Rol | Responsabilidad SQA |
|---|---|
| Responsable técnico del módulo | Mantener contratos, validaciones, pruebas, instrumentación y análisis de defectos. |
| Jefatura de laboratorio | Aprobar el ciclo de vida compartido, revisar riesgos de operación y asignar responsables de seguimiento. |
| Profesional solicitante autorizado | Revisar paciente/episodio y selección antes de confirmar; reportar discrepancias de expediente u orden. |
| Responsable del EMR / integración | Acordar la fuente autoritativa, correlación, autenticación entre servicios y manejo de reintentos. |
| Seguridad de aplicaciones | Revisar roles, ámbito hospitalario, control de acceso, secretos y eventos de denegación. |
| Operaciones de plataforma/datos | Vigilar disponibilidad, transacciones, respaldos, reloj, colas y recuperación donde se implanten. |
| Responsable SQA | Consolidar métricas, muestrear evidencia, registrar hallazgos y verificar acciones correctivas. Puede ser una función asignada; no implica que exista actualmente. |

La jefatura designará titular y suplente para cada responsabilidad antes del piloto. Quien ejecuta un cambio no será la única persona que apruebe evidencia de su propio control.

## 3. Métricas y umbrales propuestos

Los objetivos de integridad y autorización son de tolerancia cero para fallas confirmadas. Los porcentajes de disponibilidad y tiempos de respuesta son objetivos operativos provisionales, sujetos a aceptación por Operaciones y al diseño de la integración. Los resultados se segmentan por entorno y origen, sin incluir datos clínicos identificables en tableros.

| ID | Métrica y cálculo | Objetivo / umbral de alerta propuesto | Fuente requerida | Responsable / frecuencia | Acción al incumplir |
|---|---|---|---|---|---|
| M-01 | **Emisión íntegra:** órdenes confirmadas con encabezado, episodio y todas las partidas solicitadas persistidas ÷ total de emisiones confirmadas × 100. Contar escrituras parciales por separado. | 100 % de emisiones confirmadas completas; cualquier parcial o confirmación falsa es incidente crítico. | Identificador de operación, resultado transaccional y conteos de encabezado/partidas en entorno de pruebas; en producción, auditoría técnica minimizada. Hoy la suite Semana 15 verifica fallos de guardado en el prototipo, no escrituras reales del EMR. | Responsable técnico; cada release y resumen semanal. | Bloquear release ante fallo; reconciliar o anular de forma controlada registros parciales, preservar evidencia y abrir análisis de causa raíz. |
| M-02 | **Autorización y ámbito correctos:** escrituras aprobadas con actor, rol y hospital válidos ÷ total de escrituras intentadas × 100; contar intentos denegados aparte. | 100 % de escrituras permitidas correctamente autorizadas y cero escrituras fuera de ámbito. Una escritura no autorizada confirmada es incidente crítico. | Eventos de autorización unidos a membresías/roles vigentes y eventos de emisión; acceso restringido. Requiere telemetría productiva no acreditada hoy. | Seguridad de aplicaciones; alertas continuas y revisión semanal. | Contener acceso, preservar logs, evaluar órdenes afectadas y escalar a seguridad/jefatura según procedimiento institucional. |
| M-03 | **Trazabilidad de emisión:** emisiones con actor, hospital, orden, episodio, solicitante, partidas, resultado de operación y fecha/hora ÷ emisiones confirmadas × 100. | 100 % de registros requeridos; todo campo de auditoría ausente genera alerta. | Registro de emisión/auditoría y reporte de integridad; confirmar previamente el esquema definitivo. El ejemplo Semana 2 modela datos, pero no acredita un almacén de auditoría productivo. | Responsable técnico y SQA; verificación diaria automatizada si se instrumenta, consolidación semanal. | Antes del cierre, bloquear emisión si falta un dato obligatorio. Después, abrir incidente y preservar evidencia; no inferir autoría ni reconstruir a partir de datos no autorizados. |
| M-04 | **Consulta de estado sustentada:** respuestas de seguimiento servidas desde una lectura válida de la fuente autoritativa o caché con frescura aprobada ÷ respuestas no vacías × 100. Registrar por separado `EMPTY`, `AVAILABLE` y `UNAVAILABLE`. | 100 % de respuestas etiquetadas según su resultado real; cero respuestas de estado inventado tras error de lectura. Frescura máxima se define con el propietario de integración antes de producción. | Resultado y latencia del repositorio/EMR con ID de correlación; suite Semana 15 para pruebas de `FOUND`, `EMPTY` y `UNAVAILABLE` en el prototipo. | Responsable de integración; revisión semanal y prueba en cada release. | Corregir traducción de resultados; invalidar caché obsoleta si aplica; impedir presentación de un estado como actual cuando la fuente no pudo verificarse. |
| M-05 | **Actualizaciones de estado válidas:** actualizaciones de origen autorizado y aceptadas por la política acordada, aplicadas exactamente una vez ÷ actualizaciones válidas recibidas × 100. Contabilizar transiciones inválidas denegadas por separado. | 100 % de actualizaciones válidas aplicadas una vez; cero cambios persistidos por origen no autorizado o transición rechazada. Catálogo y transiciones quedan pendientes de aprobación institucional. | Eventos de entrada, resultado de autorización, política/versionado y estado antes/después con identificador de operación; fixtures de pruebas no equivalen a eventos productivos. | Responsable de integración y jefatura; por release y revisión semanal. | Rechazar sin mutar el estado previo; investigar duplicación o pérdida; suspender el consumidor afectado si no se puede confiar en el estado publicado. |
| M-06 | **Idempotencia y duplicidad:** reintentos de una misma emisión que conservan un único resultado de orden ÷ reintentos identificados × 100; contar órdenes duplicadas atribuibles a reintentos. | 100 % de reintentos reconocidos o consultables por clave de operación; cero órdenes duplicadas confirmadas por repetición del mismo intento. La clave durable aún debe diseñarse. | Correlation/operation ID, restricciones de unicidad y resultados del repositorio; pruebas de timeout/reintento en integración. | Responsable técnico; cada release y revisión mensual de duplicados. | Detener reintentos automáticos, localizar órdenes duplicadas sin borrado directo, reconciliar con el propietario clínico y documentar causa/acción. |
| M-07 | **Disponibilidad y latencia del flujo técnico:** solicitudes técnicas exitosas ÷ solicitudes elegibles × 100; medir p95 de respuesta por operación y dependiente. Excluir denegaciones esperadas y entradas inválidas del denominador, pero reportarlas aparte. | Objetivo provisional de disponibilidad ≥99.5 % por mes y p95 ≤2 s para operaciones internas; alertar ante cualquier incumplimiento. Excluir o reportar separadamente la latencia del EMR externo. Aprobar antes del piloto; no es un SLO vigente. | Métricas de servicio, health checks, trazas y errores con códigos no clínicos. No hay evidencia de telemetría productiva disponible en esta entrega. | Operaciones; tablero continuo y resumen semanal/mensual. | Aplicar runbook, identificar dependencia y duración, comunicar degradación; no aceptar una orden como confirmada si no existe confirmación durable. |

Si la instrumentación no está disponible, la persona responsable registra `N/D — fuente no implementada`, el riesgo y una fecha/acción para habilitar evidencia. No se calcula un porcentaje a partir de pruebas sintéticas ni se sustituye producción por el resultado del prototipo.

## 4. Matriz de riesgos

Escala inicial: probabilidad (P) e impacto (I) de 1 a 5; puntuación = P × I. Es una priorización cualitativa que SQA y los responsables deben revisar con datos del piloto, no una evaluación clínica.

| ID | Riesgo | Causa / consecuencia | P | I | Nivel | Control preventivo | Control detectivo / respuesta | Responsable |
|---|---|---|---:|---:|---|---|---|---|
| R-01 | Orden ligada al paciente o episodio incorrecto | Contexto desactualizado, selección incorrecta o referencias cruzadas; solicitudes de pruebas en expediente equivocado. | 2 | 5 | 10 Alto | Obtener paciente/episodio desde contexto autoritativo; revalidar antes de confirmar; no confiar solo en IDs enviados por cliente. | Prueba negativa de mismatch; muestreo de órdenes con correlación; contener y escalar toda asociación errónea confirmada. | Jefatura clínica y responsable EMR |
| R-02 | Orden parcialmente guardada o confirmada sin persistencia | Fallo entre guardar encabezado y partidas o timeout ambiguo; seguimiento inconsistente. | 3 | 5 | 15 Crítico | Transacción atómica; contrato de éxito tras commit; clave idempotente aprobada. | Inyección de fallos, comparación de encabezado/partidas, alertas de resultados inciertos; reconciliación sin duplicar. | Responsable técnico |
| R-03 | Acceso o escritura fuera del hospital autorizado | Confianza en identificador o ámbito aportado por cliente; exposición/modificación de información clínica. | 2 | 5 | 10 Alto | Autorización por acción y recurso del lado servidor; mínimo privilegio; pruebas por tenant/hospital. | Eventos de denegación y escrituras, revisión de ámbito; contener, investigar y notificar conforme a política. | Seguridad |
| R-04 | Estado presentado como actual aunque la consulta falló | Confundir lista vacía con error, usar caché obsoleta o traducir excepción como resultado válido. | 3 | 4 | 12 Alto | Tipos explícitos para `EMPTY`, `AVAILABLE`, `UNAVAILABLE`; frescura de caché acordada. | Pruebas de lectura fallida y alerta de frescura; retirar indicador de estado actual y recuperar desde fuente autoritativa. | Responsable de integración |
| R-05 | Actualización duplicada, perdida o fuera de secuencia | Reenvío de mensajes, origen no autorizado o ciclo de vida no acordado. | 3 | 4 | 12 Alto | Autenticar origen; idempotencia/correlación; política de transición inyectada y versionada. | Comparar eventos recibidos y persistidos; pruebas de repetición y transición inválida; pausar consumidor y reconciliar. | Responsable de integración y jefatura |
| R-06 | Emisión duplicada tras timeout/reintento | El cliente desconoce si el primer intento confirmó commit. | 3 | 4 | 12 Alto | ID de operación persistente y restricción de unicidad; flujo de consulta de resultado antes de repetir. | Pruebas de respuesta perdida; tablero de posibles duplicados y revisión por responsable clínico. | Responsable técnico |
| R-07 | Exposición de datos sensibles en logs o reportes | Registrar payload, paciente o detalle de pruebas en telemetría general. | 2 | 5 | 10 Alto | Lista permitida de campos; IDs de correlación no clínicos; control de acceso y retención aprobada. | Escaneo de logs, revisión de permisos y muestras; restringir acceso, sanear copias y gestionar incidente. | Seguridad y Operaciones |
| R-08 | Catálogo o contratos incompatibles entre EMR y laboratorio | Versiones de códigos, campos obligatorios u origen de estado no acordados. | 3 | 4 | 12 Alto | Contrato versionado, validación de códigos, entornos de prueba y gestión formal de cambios. | Pruebas de contrato, métricas de rechazo por incompatibilidad y conciliación con propietarios. | Responsable de integración |

Riesgos altos/críticos requieren dueño, evidencia y aceptación formal antes del piloto. La aceptación no elimina la obligación de detección ni respuesta.

## 5. Controles preventivos y detectivos por fase

| Fase | Preventivos | Detectivos | Evidencia |
|---|---|---|---|
| Preparar emisión | Contexto autoritativo de paciente/episodio; validar permisos, catálogo y datos requeridos; revisión de selección antes de confirmar. | Casos de autorización denegada, mismatch, catálogo no disponible y solicitud incompleta. | Lista de criterios aprobados, resultado de autorización y suite de contrato. |
| Confirmar persistencia | Guardado transaccional de orden y partidas; validación del resultado del commit; clave idempotente. | Pruebas de fallo del repositorio/timeout; reconciliación de cabecera y partidas. | ID de operación, cantidad de partidas y resultado no clínico de persistencia. |
| Consultar seguimiento | Consultar fuente autorizada; no derivar estado de la ausencia de datos; definir frescura si existe caché. | Casos de `EMPTY`, `AVAILABLE`, `UNAVAILABLE`; latencia y antigüedad de dato. | Resultado tipado de consulta y evidencia sintética del test. |
| Actualizar estado | Origen autenticado, ciclo de vida aprobado, deduplicación y autorización por orden. | Pruebas de transición no permitida, origen no autorizado, repetición, orden concurrente y fallo de escritura. | Evento de recepción, decisión de política y confirmación de persistencia. |
| Operar y liberar | Revisión de cambios, pruebas de contrato, revisión de datos sensibles, reversión documentada. | Tablero de disponibilidad, alertas, auditoría de acceso, muestreo SQA e incidentes. | Aprobación de release, resultados del pipeline, runbook y acta de revisión. |

## 6. Calendario de seguimiento

El calendario es una propuesta relativa al inicio del piloto. La jefatura asigna fechas y nombres reales; no se asume que los servicios de observabilidad estén ya desplegados.

| Momento | Actividad | Responsable principal | Salida / criterio |
|---|---|---|---|
| Semana -2 | Confirmar alcance, propietario del EMR, ciclo de vida de órdenes, autoridad de datos, roles y responsables titulares/suplentes. | Jefatura + Integración + Seguridad | Matriz RACI, contratos pendientes y riesgos aprobados. |
| Semana -1 | Acordar fuente por métrica, campos de auditoría permitidos, retención y runbook; revisar instrumentación y preparar datos sintéticos. | SQA + Operaciones + Seguridad | Plan de medición y controles de privacidad aprobados. |
| Semana 0 | Ejecutar pruebas de contrato, integración, atomicidad, autorización, concurrencia, timeout y recuperación en preproducción. | Responsable técnico + Integración | Evidencia por caso; ningún fallo crítico abierto para el piloto. |
| Semanas 1–2 | Piloto controlado y revisión diaria de alertas/incidentes; registrar línea base sin extrapolar desde tests. | Operaciones + Jefatura | Registro de métricas con valores observados o `N/D`, incidentes y acciones. |
| Semanas 3–4 | Revisión semanal de riesgos, duplicados, integridad, autorización y disponibilidad; ajustar metas con evidencia. | SQA + dueños de métricas | Tendencia, causa raíz y plan de tratamiento con fecha. |
| Fin de semana 4 | Evaluar línea base, aprobar o recalibrar objetivos operativos y decidir continuidad/ampliación. | Jefatura + SQA + Integración | Acta de decisión; riesgos residuales aceptados explícitamente. |
| Mensual y por release | Revisar métricas, incidentes, cambios de contrato, efectividad de controles, restauración y pruebas regresivas. | Dueños por métrica + SQA | Informe mensual y aprobación de release o acciones de bloqueo. |

Ante un incidente de impacto alto, seguridad, integridad o trazabilidad, no se espera a la revisión periódica: se activa inmediatamente el procedimiento institucional correspondiente.

## 7. Trazabilidad con RF/RNF y evidencia disponible

| Requisito del módulo | Métrica/control asociado | Evidencia existente |
|---|---|---|
| RF-01, RF-02: contexto autorizado y solicitud completa | M-02, R-01, controles de preparación | Semana 2 documenta autorización, expediente/episodio y datos requeridos; suite Week 15 incluye casos del prototipo. |
| RF-03: prueba disponible en catálogo | R-08, control de contrato | Ejemplo de Semana 2 utiliza doble de catálogo; catálogo productivo no está acreditado. |
| RF-04 / RNF-03: orden y partidas atómicas | M-01, R-02 | Semana 15 prueba fallo de persistencia en el caso de uso; transacción productiva no se ha confirmado. |
| RF-05, RF-06 / RNF-05: consulta fiable | M-04, R-04 | Semana 15 prueba resultados de consulta encontrados, vacíos e indisponibles en dobles. |
| RF-07, RF-08: actualización autorizada y confirmada | M-05, R-05 | Semana 2 define política inyectada; estados y transiciones reales quedan por acordar. |
| RNF-02: autorización y contexto | M-02, R-03 | Reglas y casos conceptuales; integración productiva con identidad/EMR no acreditada. |
| RNF-04: trazabilidad | M-03, controles de auditoría | El prototipo demuestra datos del modelo, no almacenamiento de auditoría productivo. |
| RNF-07: testabilidad | Todos | Suite Semana 15 repetible con fixtures sintéticos, sin EMR real ni datos clínicos. |

## 8. Validación documental y limitaciones

El Plan SQA se cotejó con las definiciones del módulo en semanas 1, 2 y 15. Se ejecutaron desde la raíz del repositorio:

```powershell
php docs/modulos/ordenes-laboratorio-emr/semana-15/pruebas/run.php
# Resultado: 9/9 pruebas aprobadas; 0 fallidas.
php docs/modulos/ordenes-laboratorio-emr/semana-02/ejemplo-srp/verificar.php
# Resultado: OK: 34 aserciones.
```

Estos comandos validan el prototipo y sus dobles; no miden disponibilidad de producción, autorización real, integración EMR ni calidad de datos clínicos. El plan no inventa estados, prioridades clínicas, reglas de catálogo ni SLA. La jefatura, seguridad, operaciones y propietarios del EMR/laboratorio deben aprobar responsables, fuentes y metas antes de usar las métricas para liberar o evaluar una operación real.

## 9. Referencias del módulo

- [Semana 1: proceso UML y alcance](../semana-01/semana-01-uml-ordenes-laboratorio-emr.md)
- [Semana 2: SRP, requerimientos y criterios](../semana-02/semana-02-srp-ordenes-laboratorio-emr.md)
- [Semana 15: pruebas y trazabilidad](../semana-15/semana-15-pruebas-ordenes-emr.md)
- [Suite de pruebas de órdenes](../semana-15/pruebas/README.md)
