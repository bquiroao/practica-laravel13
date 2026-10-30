# Semana 13 — Revisión técnica formal de órdenes de laboratorio desde EMR

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Órdenes de laboratorio desde EMR |
| **Proceso revisado** | Emisión de orden desde el expediente y seguimiento de su estado |
| **Tema** | Calidad del software y revisiones técnicas formales |
| **Autora** | Lopez Perez, Mercedes Azucena |
| **Fecha** | 26 de octubre de 2026 |

## 1. Propósito y dictamen

Esta revisión inspecciona los requisitos y el prototipo académico de las semanas 1 y 2 con foco en reglas de dominio, contrato API, permisos y tenant, integridad de la orden, pruebas, estados, trazabilidad y dependencias estrictamente necesarias. El resultado distingue los controles demostrados por el prototipo de los que requieren contrato institucional o una implementación persistente antes de cualquier uso integrado.

**Dictamen de escritorio:** el caso de uso separa validación, autorización, catálogo, consulta y actualización de estado mediante interfaces; el runner asociado reporta 34 aserciones. La evidencia es apropiada para demostrar reglas de aplicación con dobles en memoria, pero no acredita un API, un tenant/hospital como ámbito explícito, atomicidad en base de datos ni auditoría histórica. Los hallazgos REV13-01 y REV13-02 son bloqueadores antes de liberar una integración multi-tenant o persistente; REV13-03 y REV13-04 requieren decisión y cierre contractual antes de integrar.

La revisión se limita a los archivos citados. No representa una reunión celebrada ni aprobaciones de revisores reales; los roles siguientes son la composición propuesta para la revisión formal. No se inspeccionaron sistemas EMR, datos clínicos reales, infraestructura ni despliegues.

## 2. Alcance y referencias inspeccionadas

### Incluido

- Validación de campos y reglas de emisión; relación de orden, paciente, episodio, solicitante y pruebas.
- Autorización del actor y consistencia del contexto antes de emitir o consultar.
- Separación entre estado vacío, denegación, conflicto e indisponibilidad.
- Política de ciclo de vida, autorización del origen de actualización y control de estado esperado.
- Contrato de repositorio, límites del fixture y suficiencia de pruebas frente a los criterios de aceptación.
- Dependencias requeridas para emitir desde el expediente y consultar el seguimiento.

### Excluido

- Aprobación de reglas clínicas, indicaciones, catálogo real o nombres/transiciones definitivos de estados.
- Pruebas de penetración, certificación regulatoria, desempeño, disponibilidad de producción o revisión de un EMR conectado.
- Módulos de recepción de muestras, procesamiento, resultados, validación bioquímica, facturación y notificaciones.

### Evidencia de referencia

- [Modelo UML y alcance de Semana 1](../semana-01/semana-01-uml-ordenes-laboratorio-emr.md).
- [Requerimientos, criterios de aceptación y límites de Semana 2](../semana-02/semana-02-srp-ordenes-laboratorio-emr.md).
- [README del prototipo SRP](../semana-02/ejemplo-srp/README.md).
- [Workflow de órdenes](../semana-02/ejemplo-srp/src/LabOrderWorkflow.php).
- [Fixture en memoria](../semana-02/ejemplo-srp/src/InMemoryAdapters.php).
- [Runner de verificación](../semana-02/ejemplo-srp/verificar.php) y [salida registrada](../semana-02/evidencia/salida-verificar.txt).
- [Rutas API actuales](../../../../routes/api.php), [middleware tenant](../../../../app/Http/Middleware/TenantMiddleware.php), [middleware JWT](../../../../app/Http/Middleware/JwtAuth.php) y [controlador base](../../../../app/Http/Controllers/Controller.php).

Las rutas API actuales exponen autenticación; no se encontró ruta ni controlador de órdenes de laboratorio. El middleware tenant/JWT existente constituye infraestructura genérica, pero no demuestra autorización por paciente/episodio para un flujo de órdenes inexistente. El README de Semana 2 declara que el prototipo no requiere base de datos y que su fixture en memoria no sustituye una transacción. Por tanto, las garantías de producción no se deducen de los nombres de las interfaces ni de los dobles.

## 3. Plan de revisión

| Fase | Actividad | Salida |
|---|---|---|
| Preparación | Confirmar versión y alcance; reunir RF/RNF, criterios, modelo de proceso, código y evidencia de pruebas. | Paquete de revisión identificado y sin datos clínicos reales. |
| Revisión individual | Cada revisor inspecciona la lista asignada y anota evidencia concreta, pregunta o hallazgo; no modifica código durante la sesión. | Checklist con estado y referencia por ítem. |
| Reunión formal | Moderador repasa discrepancias, separa defectos de decisiones pendientes y acuerda severidad, responsable y fecha objetivo. | Acta breve y hallazgos únicos, accionables y no duplicados. |
| Retrabajo | La autora responde a cada hallazgo, aporta cambio o justificación y evidencia verificable. | Respuesta vinculada al ID del hallazgo. |
| Cierre | Revisores validan correcciones y criterios de salida; el moderador registra aceptación, rechazo justificado o aplazamiento aprobado. | Dictamen final y checklist firmado por función/fecha. |

La inspección es orientada a riesgo. Se revisa primero el ámbito de autorización/tenant, la integridad de persistencia y la fidelidad del estado; luego API, trazabilidad, cobertura de pruebas y acoplamientos. Los desacuerdos sobre reglas clínicas o estados se escalan al propietario del proceso y no se resuelven por inferencia del revisor.

## 4. Roles y responsabilidades

| Rol | Asignación propuesta | Responsabilidad | Restricción |
|---|---|---|---|
| Autora / responsable del artefacto | Mercedes Azucena Lopez Perez | Presentar requisitos y código, explicar decisiones, registrar respuestas y proponer correcciones. | No aprobar por sí sola sus propios hallazgos. |
| Moderador | Persona técnica independiente designada por el curso/equipo | Controlar alcance y tiempo, conducir checklist, resolver duplicados, registrar acuerdos y verificar criterios de salida. | No sustituye la aprobación clínica, de seguridad ni del propietario del API. |
| Revisor de dominio | Profesional o analista designado por el módulo | Verificar paciente/episodio, selección de pruebas, cardinalidad y fronteras con laboratorio. | No inventar política clínica o catálogo. |
| Revisor API e integración | Responsable EMR/API designado | Acordar payload, errores, versionado, correlación, reintentos y fuente autoritativa de contexto. | No considerar existente un endpoint que no está implementado. |
| Revisor de seguridad y tenant | Responsable de seguridad/RBAC designado | Evaluar actor, rol, hospital/tenant, expediente y aislamiento de consultas/escrituras. | Requiere contrato real de identidad y tenant para aprobación de producción. |
| Revisor de datos y pruebas | Responsable de persistencia/SQA designado | Revisar transacciones, restricciones, concurrencia, pruebas y evidencia reproducible. | Las pruebas en memoria no se aceptan como evidencia de rollback en DB. |

Los nombres reales de moderador y revisores, su disponibilidad y cualquier aprobación permanecen **pendientes de asignación**. Para aprobar una liberación, el revisor de seguridad y el propietario de persistencia deben ser independientes de quien implementa sus controles.

## 5. Dependencias estrictamente necesarias

| Dependencia | Necesaria para | Límite y control requerido |
|---|---|---|
| Contexto autenticado del EMR (paciente y episodio) | Iniciar emisión en el expediente correcto y consultar las órdenes de ese episodio. | Fuente autoritativa; comprobar que paciente, episodio y hospital corresponden en cada operación. |
| Autorización institucional (actor, rol y ámbito hospitalario/tenant) | Autorizar emisión, lectura y actualización de estado. | Denegar por defecto y evaluar permisos sobre el recurso, no solo la identidad o el ID recibido. |
| Catálogo de pruebas vigente | Validar todos los códigos seleccionados al momento de emitir. | La selección completa falla si un código no existe, no está vigente o el catálogo no está disponible. |
| Repositorio transaccional de órdenes | Guardar encabezado y partidas como unidad; leer orden por paciente/episodio; actualizar estado de forma condicional. | Integridad referencial, atomicidad, control de concurrencia y resultado explícito de persistencia. |
| Origen confiable de actualizaciones del laboratorio | Aplicar una transición de seguimiento originada en un proceso autorizado. | Autenticar el servicio de origen y validar la transición contra el contrato aprobado. |
| Contrato de ciclo de vida de órdenes | Interpretar estado inicial, estados permitidos y transiciones. | Aprobación conjunta de órdenes/laboratorio; los `TEST_*` del fixture no son estados operativos. |

No son dependencias directas para **emitir y consultar**: recepción de muestras, analizador, procesamiento/validación de resultados, notificaciones, analítica, facturación o módulos diagnósticos. Se integran solo si un requisito aprobado lo justifica. La autenticación no reemplaza autorización por tenant, y la auditoría técnica no debe copiar payloads clínicos innecesarios.

## 6. Checklist de revisión técnica (15 ítems)

Estados: **C** = cumple en la evidencia inspeccionada; **P** = parcial; **NV** = no verificable o no definido. Un estado C en el prototipo no equivale a certificación de producción.

| # | Criterio de revisión | Estado | Evidencia / observación |
|---:|---|:---:|---|
| 1 | La orden exige actor, paciente, episodio y al menos una prueba no vacía. | C | `IssueLabOrder` valida campos antes de autorizar y persistir; CA-02. |
| 2 | La orden vincula paciente, episodio, solicitante, pruebas e identificador. | C | `NewLabOrder`/`LabOrder` conservan esas referencias; CA-01. |
| 3 | La consistencia de paciente/episodio se valida contra contexto autoritativo antes de emitir. | P | Existe puerto `ClinicalContextValidator`; el fixture simula la respuesta, no integra con EMR. |
| 4 | Lecturas y escrituras se autorizan para actor, expediente y hospital/tenant. | P | Hay middleware genérico tenant/JWT en la aplicación, pero no una ruta de órdenes ni autorización de orden por paciente/episodio; el prototipo no propaga tenant. |
| 5 | Todos los códigos se validan contra catálogo vigente antes de emitir. | P | El puerto existe y el flujo rechaza catálogo inválido/indisponible; no hay catálogo real. |
| 6 | Orden y partidas se confirman juntas en una transacción; falla parcial deja rollback. | P | El contrato se llama `createAtomically`; fixture en memoria no demuestra transacción ni rollback real. |
| 7 | El contrato API define operación, esquema, autenticación, errores y versionado. | NV | Las rutas actuales se limitan a autenticación; Semana 2 declara que el prototipo de órdenes no implementa API HTTP. No hay contrato de órdenes verificable. |
| 8 | Los reintentos de emisión no crean órdenes duplicadas; hay idempotencia o decisión documentada. | NV | No se define clave de idempotencia/deduplicación del request. |
| 9 | El estado inicial y transiciones provienen de política aprobada, no de estados inventados. | P | Se inyecta `OrderLifecyclePolicy`; `TEST_INITIAL/NEXT/FINAL` son sintéticos. |
| 10 | La actualización exige origen confiable y valida transición antes de escribir. | C | `ApplyLabOrderStatus` consulta origen y política; CA-11/12. La confianza productiva no está integrada. |
| 11 | La actualización condicional protege ante estado concurrente obsoleto y comunica conflicto. | C | `updateStatus` recibe `expectedStatus`; fixture y CA-12 ejercitan conflicto simulado. Falta adaptador de DB. |
| 12 | La consulta distingue hallazgo, lista vacía, denegación e indisponibilidad sin estado supuesto. | C | `SearchStatus` y `FindLabOrders`; CA-08–CA-10. |
| 13 | Cada cambio de estado conserva actor/origen, estado previo/nuevo, instante y correlación de auditoría. | P | El modelo expone el estado actual; el fixture reemplaza el objeto y no mantiene historial de eventos. |
| 14 | Pruebas cubren camino feliz, validaciones, permisos, errores, conflictos y trazabilidad con evidencia reproducible. | P | CA-01–CA-16 y 34 aserciones en dobles; no prueban API, tenant, transacción real ni EMR. |
| 15 | Dependencias se limitan al contexto, autorización, catálogo, repositorio y política necesarios. | C | Puertos separados por responsabilidad; recepción, resultados y otros módulos están fuera de alcance. |

### Regla de aceptación del checklist

- Todo ítem debe tener estado y evidencia; **NV** no se convierte en C por falta de fallo observado.
- Los ítems 4 y 6 deben llegar a C con pruebas de aislamiento multi-tenant y persistencia transaccional antes de una integración productiva.
- Los ítems 7 y 8 deben resolverse o excluirse mediante decisión explícita del propietario API antes de habilitar reintentos desde clientes.
- Los ítems 9 y 13 requieren decisión de propietarios de órdenes/laboratorio y auditoría antes de producción.

## 7. Hallazgos de esta inspección

Se usa **Bloqueante** para riesgo que impide liberar el flujo integrado; **Alta** para corrección requerida antes de producción; **Media** para decisión/contrato que debe cerrarse antes de integrar; **Baja** para mejora no bloqueante. La severidad expresa prioridad de resolución y no afirma que el prototipo aislado esté desplegado.

| ID | Severidad | Hallazgo y evidencia | Acción requerida | Responsable propuesto / estado |
|---|---|---|---|---|
| REV13-01 | Alta | El prototipo no propaga hospital/tenant: `authorize` recibe actor, paciente y episodio; `LabOrder` no conserva tenant. La aplicación sí tiene middleware tenant/JWT genérico, pero aún no hay endpoint de órdenes que conecte ese contexto con permisos de expediente. Ver [ClinicalAuthorization](../semana-02/ejemplo-srp/src/LabOrderWorkflow.php#L148), [LabOrder](../semana-02/ejemplo-srp/src/LabOrderWorkflow.php#L93), [TenantMiddleware](../../../../app/Http/Middleware/TenantMiddleware.php) y [JwtAuth](../../../../app/Http/Middleware/JwtAuth.php). | Al definir el endpoint de órdenes, propagar el tenant autenticado a la autorización y al repositorio, validar el acceso paciente/episodio y limitar consultas/escrituras por ámbito. Añadir prueba negativa de acceso cruzado entre tenants. | Seguridad + EMR; **abierto**, bloqueo para integración multi-tenant. |
| REV13-02 | Alta | La atomicidad está expresada en el nombre `createAtomically`, pero el prototipo usa adaptador en memoria. El README reconoce que no sustituye transacción de base de datos. CA-07 no prueba rollback real ante fallo después de insertar encabezado o alguna partida. | Implementar repositorio transaccional con FK/constraints; probar fallo intermedio y verificar rollback íntegro en DB. | Persistencia + SQA; **abierto**, bloqueo para persistencia productiva. |
| REV13-03 | Media | No se encuentra contrato API HTTP de órdenes revisable: [las rutas actuales](../../../../routes/api.php) exponen autenticación, y [el controlador base](../../../../app/Http/Controllers/Controller.php) es abstracto. Faltan esquema de solicitud/respuesta, autorización de recurso, errores, versionado y correlación para órdenes. | Elaborar y aprobar contrato con propietario EMR/API, mapeando estados de dominio sin traducir indisponibilidad a éxito ni definir estados clínicos por conveniencia. | API + Integración; **pendiente de definición**. |
| REV13-04 | Media | La representación conserva estado actual, pero no historial verificable de transiciones con actor/origen y fecha; `updateStatus` reemplaza el `LabOrder` en el fixture. No es posible reconstruir una secuencia de cambios con esa estructura. | Definir requisitos mínimos de auditoría/retención y persistir eventos de transición correlacionados con la orden, sin duplicar datos sensibles innecesarios. | Órdenes + SQA/Seguridad; **requiere decisión**. |
| REV13-05 | Media | No se define idempotencia para reintentar una emisión tras timeout. Una repetición válida puede representar una segunda orden y las pruebas no cubren deduplicación. | Decidir semántica de reintento; si se requiere, aceptar una clave idempotente con unicidad por actor/tenant y probar repetición y concurrencia. | API + Persistencia; **requiere decisión antes de integración**. |

**No se clasifican como defectos** la falta de procesamiento, recepción, resultados, una UI o una base de datos en el prototipo: las entregas los declaran fuera de alcance. Sí se registran como límites de evidencia cuando una aceptación requiere integración real.

## 8. Criterios de entrada y salida

### Entrada

- [ ] Versión/commit y alcance del módulo identificados; excluir datos clínicos reales.
- [ ] RF/RNF, reglas, criterios de aceptación, modelo de estados y propietario de cada contrato disponibles, o ausencias registradas.
- [ ] Código, suite y comandos de ejecución accesibles; entorno PHP descrito.
- [ ] Autora, moderador y revisores asignados; independencia de aprobaciones confirmada.
- [ ] Plantilla de hallazgos, severidades, canal de registro y fecha objetivo acordados.

### Salida

- [ ] Checklist completo con evidencia y sin ítems en blanco.
- [ ] Cada hallazgo tiene ID único, severidad, evidencia reproducible, acción, responsable y estado.
- [ ] No hay hallazgos Bloqueantes/Altos abiertos para aprobar una liberación; cualquier riesgo residual tiene aceptación formal de su dueño.
- [ ] Contratos API, tenant, persistencia y ciclo de vida tienen dueño y decisión documentada; los pendientes no se presentan como controles implementados.
- [ ] Pruebas pertinentes repetidas después de correcciones; resultado y comando quedan adjuntos.
- [ ] Moderador y revisores requeridos registran dictamen, fecha y excepciones aprobadas.

**Resultado de esta entrega:** checklist y hallazgos iniciales completos para revisión de escritorio. Entrada/salida de una revisión de equipo formal **no certificada** hasta asignar participantes y obtener sus aprobaciones.

## 9. Formato obligatorio de hallazgo

```text
ID: REV13-###
Título:
Severidad: Bloqueante | Alta | Media | Baja
Estado: Abierto | En análisis | Corregido | Aceptado con excepción | Cerrado
Requisito/criterio relacionado:
Ubicación: archivo, símbolo y línea o sección
Condición para reproducir:
Resultado observado:
Resultado esperado:
Impacto/riesgo:
Evidencia adjunta (comando/caso/salida):
Acción correctiva verificable:
Responsable:
Fecha objetivo:
Revisor de cierre y evidencia de retest:
```

Un hallazgo debe describir una sola condición corregible, sin incluir datos de pacientes ni payloads sensibles. La severidad no sustituye prioridad clínica ni evaluación de riesgo institucional. Una excepción requiere responsable, justificación, alcance, fecha de expiración y aprobación explícita.

## 10. Validación y límites

La suite incluida en Semana 2 se ejecuta desde la raíz del repositorio:

```powershell
php docs/modulos/ordenes-laboratorio-emr/semana-02/ejemplo-srp/verificar.php
```

La salida versionada reporta **34 aserciones aprobadas**. Esta ejecución valida el prototipo y sus dobles, no una conexión real a EMR, API, RBAC/tenant, base transaccional, auditoría ni política clínica. Los estados `TEST_*` son datos de prueba. La evidencia de esta entrega se conserva en [validacion-semana-13.txt](evidencia/validacion-semana-13.txt).

## 11. Referencias

- [Semana 1 — proceso y reglas de alcance](../semana-01/semana-01-uml-ordenes-laboratorio-emr.md).
- [Semana 2 — requerimientos y criterios](../semana-02/semana-02-srp-ordenes-laboratorio-emr.md).
- [Semana 2 — prototipo](../semana-02/ejemplo-srp/README.md).
- [Salida de las 34 aserciones](../semana-02/evidencia/salida-verificar.txt).
