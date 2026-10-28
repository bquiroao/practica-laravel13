# Reportes y analytics básicos — Semana 14

**Tema:** confiabilidad y aseguramiento de calidad.
**Tarea individual:** definir el plan SQA del módulo: calidad de «cálculo y visualización trazable de un indicador hospitalario», confiabilidad, seguridad, trazabilidad y riesgos de indicadores, agregaciones, filtros, fuentes, precisión y visualización, con responsable, umbral, fuente y acción para cada métrica y control.
**Entregable:** plan SQA con 5–7 métricas, matriz de riesgos, controles preventivos/detectivos, responsables y calendario de seguimiento.

## 1. Objetivos de calidad

| Atributo | Objetivo |
|---|---|
| Exactitud | Ningún indicador publicado con desglose que no sume su numerador (RN-07) |
| Confiabilidad | El módulo responde aunque la fuente falle: error controlado, nunca un dato inventado |
| Seguridad | Ningún acceso fuera del rol o del tenant; ningún dato clínico en los registros |
| Rendimiento | Indicador de un mes en menos de 2 segundos (RNF-01) |
| Mantenibilidad | Un reporte nuevo = una clase nueva que pasa las mismas pruebas de contrato |

## 2. Métricas (6)

| # | Métrica | Fórmula | Umbral | Fuente | Responsable | Acción si no se cumple |
|---|---|---|---|---|---|---|
| M-01 | Integridad de trazabilidad | Consultas con desglose = numerador / consultas totales | 100 % | Pruebas de contrato del Micro-HIS (hoy) y verificación en cada respuesta (desarrollo real) | Erick Ramazzini (autor) | Se bloquea el merge; se corrige el reporte antes de publicarlo |
| M-02 | Pruebas de contrato aprobadas | Reportes que pasan las pruebas de contrato / reportes registrados | 100 % | `php pruebas/ejecutar.php` y `composer test` en cada PR | Erick Ramazzini | El PR no se envía a revisión |
| M-03 | Tiempo de respuesta p95 | Percentil 95 del campo `ms` del log por petición | < 2 000 ms para un mes | Log JSON por petición (`correlationId`, `ruta`, `ms`) | Erick Ramazzini | Revisar índices; si persiste, activar la fase 2 de la migración (réplica) |
| M-04 | Accesos denegados indebidos | Respuestas 200 a un rol sin permiso en pruebas de permisos | 0 | Pruebas de permisos (Médico → Ocupación = 403) | Boris Quiroa (RBAC) | Bloqueo del PR y revisión de `PoliticaPermisos` |
| M-05 | Disponibilidad efectiva | Respuestas no 5xx / respuestas totales | ≥ 99 % semanal | Log por petición | Boris Quiroa (líder) | Revisar conexión a la base y `statement_timeout` |
| M-06 | Defectos mayores de revisión | Hallazgos «Mayor» abiertos de la revisión técnica formal | 0 al cerrar la revisión | Registro de hallazgos de la semana 13 | Boris Quiroa (moderador) | Nueva revisión del material afectado |

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; exposición = P × I. Se atienden primero los de exposición 6 o más.

| # | Riesgo | Área | P | I | Exp. | Respuesta |
|---|---|---|---|---|---|---|
| R-01 | El desglose no suma el total por un cambio en la consulta | Agregaciones | 2 | 3 | 6 | Controles CP-01 y CD-01 |
| R-02 | Se suman o promedian promedios (ALOS, tasas) | Agregaciones / precisión | 2 | 3 | 6 | CP-02 |
| R-03 | El Médico ve citas de otro médico | Permisos | 2 | 3 | 6 | CP-03 y CD-02 (hoy abierto: hallazgo RTF-01 de la semana 13) |
| R-04 | Datos de otro hospital en un indicador | Tenant | 1 | 3 | 3 | CP-04 |
| R-05 | Un cambio de esquema de un módulo dueño rompe la fuente | Fuentes | 2 | 2 | 4 | CP-05 y CD-03 |
| R-06 | Redondeos distintos según la vista | Precisión / visualización | 2 | 2 | 4 | CP-06 |
| R-07 | Consultas lentas que afectan la operación clínica | Rendimiento | 2 | 2 | 4 | CD-04 |
| R-08 | Un período sin datos se muestra como 0 | Visualización | 1 | 3 | 3 | CP-07 |

## 4. Controles

### Preventivos (evitan el defecto)

| ID | Control | Riesgo | Responsable |
|---|---|---|---|
| CP-01 | Postcondición en el dominio: `Desglose::total()` debe ser igual al numerador; las pruebas de contrato lo exigen a todo reporte | R-01 | Erick Ramazzini |
| CP-02 | Checklist de revisión C-05: los promedios se desglosan por numerador y denominador | R-02 | Boris Quiroa (moderador) |
| CP-03 | `PoliticaPermisos` exige que el `medicoId` del filtro sea el del usuario autenticado | R-03 | Boris Quiroa (RBAC) |
| CP-04 | El tenant sale del token; la conexión `hospital` es la del tenant | R-04 | Boris Quiroa (líder) |
| CP-05 | Leer por vistas `reportes.*` acordadas con los módulos dueños | R-05 | Erick Ramazzini y Josué Morales (salas y camas) |
| CP-06 | Formateador único y precisión declarada en el dominio | R-06 | Erick Ramazzini |
| CP-07 | Estado `sin_datos` separado del valor; la vista nunca dibuja 0 en ese caso | R-08 | Erick Ramazzini |

### Detectivos (encuentran el defecto si ocurre)

| ID | Control | Riesgo | Frecuencia |
|---|---|---|---|
| CD-01 | Pruebas de contrato sobre PostgreSQL en cada PR | R-01, R-02 | Cada PR |
| CD-02 | Prueba automática «Médico pide otro médico → 403» (pendiente de agregar con la corrección de RTF-01) | R-03 | Cada PR |
| CD-03 | Prueba de integración del Repository contra la base de prueba con el esquema vigente | R-05 | Cada PR y cada cambio de migraciones |
| CD-04 | Revisión del p95 de `ms` en el log | R-07 | Semanal |
| CD-05 | Revisión técnica formal con el checklist de la semana 13 | Todos | Antes de cada entrega mayor |

## 5. Responsables (RACI)

| Actividad | Erick Ramazzini (autor) | Boris Quiroa (líder y RBAC) | Josué Morales (salas y camas) | Joshua García (reportes exportables) |
|---|---|---|---|---|
| Pruebas de contrato y evidencia | R | A | I | I |
| Revisión y merge de PR | C | R/A | I | I |
| Permisos y tenant | C | R/A | I | I |
| Fuentes de datos (vistas `reportes.*`) | R | A | C | I |
| Seguimiento de métricas | R | A | I | C |
| Revisión técnica formal | R (autor) | R/A (moderador) | R (revisor) | R (revisor) |

R = responsable, A = aprueba, C = consultado, I = informado.

## 6. Calendario de seguimiento

| Fecha | Actividad | Métricas y controles |
|---|---|---|
| 27/10/2026 | Línea base: resultados de las pruebas del Micro-HIS (11, 23 y 29 pruebas correctas en las semanas 3, 4 y 5) | M-01, M-02, M-04 |
| 28/10/2026 | Revisión técnica formal con el plan de la semana 13 | M-06, CD-05 |
| 29–30/10/2026 | Revisión del ingeniero: presentar este plan y la evidencia; cierre de hallazgos mayores | M-06 y todas |
| Cada PR del desarrollo real | Pruebas de contrato, permisos e integración | M-01, M-02, M-04, CD-01 a CD-03 |
| Cada lunes durante el desarrollo | Revisión del log: p95 y disponibilidad | M-03, M-05, CD-04 |
| Antes de cada entrega mayor | Nueva revisión técnica formal | M-06, CD-05 |