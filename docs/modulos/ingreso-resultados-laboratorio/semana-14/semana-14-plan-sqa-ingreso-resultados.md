# Semana 14 — Plan SQA de ingreso de resultados

**Estudiante y responsable del módulo:** Lopez Perez, Mercedes Azucena (Azucena17)

**Flujo:** Captura y corrección controlada de resultados pendientes.

**Versión:** 1.0 · 27 de octubre de 2026 · Horarios de Guatemala.

## 1. Objetivo y línea base

Asegurar confiabilidad, seguridad y trazabilidad de captura inicial y corrección de resultados que siguen pendientes, incluyendo valor, unidad, rango, autoría e historial. Se excluye validar/publicar por bioquímico y modificar módulos ajenos. Los rangos de prueba son ficticios; no constituyen reglas clínicas.

Esta rama parte del commit 88a642b de semana 7, que contiene el refactor de valores/unidades/rangos y los ejemplos de dominio, MVC y repositorios de semanas anteriores. [Semana 2](../semana-02/semana-02-lsp-ingreso-resultados.md) define RF-01..RF-07 y RNF-01..RNF-05; [semana 7](../semana-07/semana-07-componentes-refactorizacion-ingreso.md) aporta el diseño del flujo. La entrega requiere integrar esa base antes de este plan.

La rama independiente de semana 15, commit 1dcb590, registra 13 casos exitosos, 46 aserciones de regresión y tres defectos abiertos. Es evidencia de evaluación posterior de la misma base; no se presume integrada en esta rama ni equivale a aprobación para uso clínico.

Los umbrales son criterios propuestos del proyecto. N/D significa medición pendiente, no cumplimiento. No hay integración con autenticación hospitalaria real en este ejemplo.

## 2. Responsables y gestión de evidencia

| Rol | Asignación y responsabilidad |
|---|---|
| R1 Desarrollo / pruebas | Mercedes Azucena Lopez Perez: mantener matriz, ejecutar mediciones, corregir y documentar retest del módulo. |
| R2 Revisión independiente | Revisor de PR por asignar: comprobar reglas, trazabilidad y reproducir casos; no se presume aceptación de un tercero. R1 solicita revisión. |
| R3 Validación académica | Docente: evaluar entrega; su evaluación no autoriza uso con pacientes. |

Cada corrida debe registrar commit, fecha/hora, versión PHP/PDO, adaptador, datos ficticios, casos, resultados, código de salida y defectos. Cada hallazgo lleva ID, pasos, esperado/observado, severidad, propietario, fecha objetivo, commit de corrección y retest. Estados: abierto → en corrección → listo para retest → cerrado por R2. Una reproducción exitosa del defecto no lo cierra.

Severidad: crítica para filtración o escritura clínica cruzada; alta para pérdida de precisión/integridad, éxito falso o cambio de validado; media para falla recuperable sin resultado explícito; baja para presentación sin impacto de datos. La revisión puede elevar severidad según impacto real.

## 3. Siete métricas

| ID / objetivo | Fórmula / medición | Umbral de aceptación | Fuente / frecuencia | Responsable y acción ante incumplimiento |
|---|---|---|---|---|
| M1 Cobertura trazable | RF/RNF aplicables con caso, esperado y resultado / total aplicable × 100 | 100 %; mínimo 3 unitarias, 2 caja blanca y 4 caja negra | Matriz RF/RNF y salidas por commit; al cambiar requisito y antes de entregar | R1 mantiene, R2 revisa. Agregar evidencia o justificar exclusión; no aprobar requisito sin probar. |
| M2 Integridad de valor/unidad/rango | Casos de formato, unidad, límites y precisión coherentes / casos ejecutados × 100 | 100 %; 0 cambios silenciosos de valor; clasificación sobre el mismo valor persistido | Casos de coma/punto, unidad incompatible, bordes y vecino de redondeo; cada cambio de catálogo/formato | R1 ejecuta/corrige, R2 reproduce. Bloquear aceptación, definir precisión y repetir límites; no convertir unidades automáticamente sin regla. |
| M3 Corrección y estado pendiente | Casos de motivo, enlace, incremento, inmutabilidad y estado correctos / ejecutados × 100 | 100 %; 0 versiones validadas editadas, 0 históricos sobrescritos | Suite de dominio y repositorio, conteo de historial y estado; cada cambio de corrección | R1 ejecuta, R2 revisa. Detener integración y reparar append condicional/historial antes de aprobar. |
| M4 Concurrencia y atomicidad | Escenarios de duplicado, versión obsoleta, conflicto de escritura y falla con estado íntegro / ejecutados × 100 | 100 %; 0 escrituras parciales y 0 éxitos falsos | InMemory/PDO SQLite, inyección de fallos y observación del estado antes/después; por cambio de repositorio | R1 ejecuta, R2 reproduce. Revisar transacción y contrato de error, corregir y retestear ambos adaptadores. |
| M5 Seguridad de rol/hospital | Casos de autor vacío, rol sin permiso, otro hospital y sesión ausente/expirada bloqueados / ejecutados × 100 | 100 % de escenarios; 0 accesos cruzados; autor no vacío no basta | Pruebas negativas del límite autenticado y logs mínimos; al integrar y por cambio de permisos | R1 implementa/prueba, R2 revisa. Si falta control, estado no aprobado; impedir conexión con datos reales hasta corregir. |
| M6 Trazabilidad y privacidad | Versiones de prueba con autor/hora/enlace/motivo correctos / total creado × 100; hallazgos de datos sensibles por separado | 100 % de metadatos aplicables; 0 secretos/datos reales en evidencia o logs | Historial y registros del experimento; inspección antes de cada PR | R1 inspecciona, R2 confirma. Completar auditoría; retirar evidencia sensible y contener exposición antes de compartir. |
| M7 Reproducibilidad y defectos | Corridas completas repetibles / intentos; cantidad de críticos/altos abiertos | 3/3 corridas por commit/adaptador; 0 críticos/altos para recomendar integración | Salidas de ejecución, entorno y registro de defectos; antes de cierre y tras correcciones | R1 repite y registra, R2 verifica al menos una. Investigar diferencias y mantener bloqueo por defectos abiertos. |

Los defectos reproducidos se cuentan como hallazgos, separados de la tasa de casos funcionales exitosos. Un denominador vacío se registra N/D. No se declara cobertura porcentual de código sin instrumentación.

## 4. Matriz de riesgos

P e I se estiman de 1 a 3; prioridad=P×I. 6–9 alta, 3–4 media, 1–2 baja. Son estimaciones del plan; el riesgo residual queda pendiente hasta comprobar los controles.

| ID | Riesgo / causa | P×I / prioridad | Métrica y controles | Responsable / tratamiento |
|---|---|---|---|---|
| R-01 | Redondeo altera valor guardado o contradice clasificación del rango | 3×3=9 alta | M2, C1/C2 | R1: definir precisión por prueba/catálogo, evitar redondeo silencioso y clasificar valor persistido. |
| R-02 | Unidad incompatible interpretada con rango de otra unidad | 2×3=6 alta | M2, C1/C2 | R1: unidad de catálogo y validación previa; no conversiones implícitas. |
| R-03 | Corrección sin motivo, sin enlace o sobre validado pierde trazabilidad | 2×3=6 alta | M3/M6, C3/C4 | R1: append inmutable con motivo y estado; revisión R2. |
| R-04 | Dos operadores sobrescriben la misma versión o duplican captura | 2×3=6 alta | M4, C5/C6 | R1: versión esperada y escritura condicional/transaccional. |
| R-05 | Actor arbitrario o hospital no autorizado accede al resultado | 3×3=9 alta | M5, C7/C8 | R1: contexto autenticado y control de hospital; no integrar datos reales sin prueba negativa. |
| R-06 | Fallo de persistencia queda sin manejo o deja éxito falso | 2×3=6 alta | M4, C5/C6 | R1: contrato explícito de error y verificación de rollback/estado. |
| R-07 | Evidencia incompleta o filtración en logs impide auditar o expone información | 2×3=6 alta | M1/M6/M7, C9/C10 | R1: metadatos mínimos, datos ficticios y evidencia ligada al commit. |

## 5. Controles preventivos y detectivos

| ID / tipo | Control | Responsable | Umbral / fuente | Acción ante incumplimiento |
|---|---|---|---|---|
| C1 Preventivo | Definir formato, unidad de catálogo, precisión y límites informativos por prueba | R1 | 100 % de reglas usadas documentadas; contrato/catálogo ficticio versionado | No cambiar valor ni convertir unidades sin regla; completar diseño y casos. |
| C2 Detectivo | Probar coma/punto, inválidos, unidad distinta, bordes y vecinos de redondeo | R1, revisión R2 | 100 % esperado; salidas y valor/clasificación guardados | Abrir defecto, bloquear aceptación y repetir después de corregir. |
| C3 Preventivo | Modelo inmutable; corrección requiere motivo, versión vigente pendiente y enlace | R1 | 0 rutas de sobrescritura; revisión de dominio/append | Retirar ruta insegura y volver a revisar. |
| C4 Detectivo | Comparar historial antes/después de corrección válida e inválida/validada | R1, revisión R2 | 0 cambios ante rechazo; enlace/autor/fecha correctos | Mantener defecto abierto hasta retest y comprobación del historial. |
| C5 Preventivo | Escritura condicional por versión y transacción donde hay varios cambios; definir errores conocidos | R1 | 0 éxitos antes de persistencia; contrato y adaptador PDO | Corregir transacción/mapeo y bloquear integración. |
| C6 Detectivo | Inyectar conflicto y falla de escritura sobre adaptadores de prueba | R1, revisión R2 | 100 % sin parcialidad ni éxito falso; estados y salidas | Reproducir, corregir y repetir InMemory/PDO. |
| C7 Preventivo | Derivar identidad y hospital de sesión confiable, comprobar permiso antes de acceder | R1 | 0 decisiones de autorización basadas solo en actor enviado; diseño y límite autenticado | Impedir uso con datos reales hasta implementar autorización. |
| C8 Detectivo | Ejecutar rol denegado, hospital cruzado y sesión ausente/expirada | R1, revisión R2 | Todos bloqueados sin revelar otro hospital; pruebas negativas | Abrir defecto alto/crítico según exposición y repetir después de corregir. |
| C9 Preventivo | Evidencia por commit, datos ficticios y metadatos de historial mínimos | R1 | 100 % resultados con fuente/fecha/entorno; 0 datos reales/secretos | Completar evidencia, retirar información sensible y contener exposición. |
| C10 Detectivo | Revisar matriz, capturas, privacidad y estado de defectos antes del PR | R1, revisión R2 | 100 % trazabilidad; 0 críticos/altos para aprobar integración | No aprobar cierre técnico; registrar límites, responsable y retest. |

## 6. Calendario propuesto

Fechas/horas de Guatemala. Son actividades propuestas, no pruebas ni revisiones ya realizadas; R2 debe aceptar su asignación.

| Fecha / corte | Actividad y evidencia | Responsable | Decisión |
|---|---|---|---|
| 27 oct 2026 | Fijar base, inventario, riesgos y siete métricas | R1 | Plan listo para revisión; sin aprobación clínica. |
| 28 oct, 18:00 | Completar matriz y casos de valor/unidad/rango/corrección | R1 | Registrar resultados y defectos; no contar reproducción como corrección. |
| 29 oct, 18:00 | Ejecutar permisos/hospital, concurrencia, persistencia y privacidad | R1; solicitar R2 | Si falta control real, mantener M5 pendiente/no aprobado. |
| 30 oct, 18:00 | Corregir prioridad alta, repetir casos y tres corridas por adaptador | R1 / R2 por asignar | Cerrar solo con retest; no aprobar por tener un commit. |
| 30 oct, 22:00 | Consolidar evidencia, riesgos residuales y conclusión de alcance | R1; R3 evalúa | Entregar antes de 23:59; no declarar listo lo que sigue pendiente. |
| Tras cada cambio | Repetir casos afectados y actualizar matriz/riesgos | R1 | Resultados deben corresponder al nuevo commit. |

Si no hay revisor o no se completan controles de seguridad dentro del plazo, se entrega evidencia académica con esos pendientes explícitos, sin afirmar aprobación técnica.

## 7. Línea base de hallazgos y salida

| Hallazgo conocido | Estado / efecto en aceptación |
|---|---|
| IR-D01: actor arbitrario aceptado sin verificación de rol/hospital | Abierto en evaluación de semana 15; M5 no aprobada para integración. |
| IR-D02: 6.00001 se guarda como 6 mientras conserva HIGH en rango ficticio 4–6 | Abierto; M2 no aprobada hasta coherencia de precisión y retest. |
| IR-D03: excepción de persistencia escapa del servicio | Abierto; exige contrato de error y retest en M4. |
| 13 casos y 46 aserciones de regresión exitosos en rama de semana 15 | Evidencia parcial del flujo, no sustituyen correcciones ni autorización real. |
| Revisión independiente y tres corridas por adaptador | Pendientes; no se presume M7 satisfecha por una ejecución. |

La entrega del plan SQA se considera preparada cuando existen métricas, riesgos, controles, responsables y calendario. La recomendación de **integración** requiere M1–M7 satisfechas para el alcance, cero críticos/altos abiertos, valor/unidad/rango coherentes, historial intacto y revisión R2 registrada. Un resultado fuera de rango continúa pendiente; este módulo nunca lo valida/publica.

El plan no corrige código de otras semanas ni promete cumplimiento clínico. Sus fuentes son el código y requisitos locales citados; los umbrales son decisiones explícitas de proyecto.