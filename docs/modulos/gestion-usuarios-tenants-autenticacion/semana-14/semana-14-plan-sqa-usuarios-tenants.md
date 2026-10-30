# Semana 14 — Plan SQA: usuarios, tenants y autenticación

**Estudiante / responsable:** Lopez Perez, Mercedes Azucena (Azucena17)

**Flujo:** Alta de usuario por tenant, autenticación segura y revocación de sesión.

**Versión:** 1.0 · 28 de octubre de 2026 · Zona America/Guatemala.

## 1. Alcance y línea base

Plan de aseguramiento de calidad del módulo, sin evaluar módulos clínicos ajenos. Incluye alta atómica de identidad/membresía, autorización administrativa, credenciales, contexto de tenant, sesión limitada, revocación e integridad de acceso. No autoriza despliegue productivo ni uso con pacientes.

Base de esta rama: b32f57b de semana 7. Referencias: [RF-01..RF-10 y RNF-01..RNF-08 de semana 2](../semana-02/semana-02-srp-usuarios-tenants.md), [flujo de contrato](../semana-02/ejemplo-srp/despues/IdentityWorkflow.php), [adaptadores en memoria](../semana-02/ejemplo-srp/despues/InMemoryAdapters.php) y [componentes de semana 7](../semana-07/semana-07-componentes-refactorizacion.md).

SessionToken es un objeto de contrato, no un JWT firmado. La verificación de firma/claims y revocación persistente requiere un adaptador real seleccionado y evidencia independiente. Las pruebas en memoria no se contarán como validación JWT ni aislamiento de datos clínicos en servidor.

Antecedente separado: semana 15, commit 507beee, tiene 14 casos exitosos, 33 aserciones de regresión, dos defectos abiertos (AU-D01/02) y brecha G-JWT. Esa rama aún requiere integración propia; este plan registra sus hallazgos sin presumir aprobada la seguridad. Los umbrales son criterios propuestos del proyecto, no certificaciones.

## 2. Responsables y evidencia

| Rol | Asignación / función |
|---|---|
| R1 Desarrollo y pruebas | Mercedes Azucena Lopez Perez: mantener matriz, preparar escenarios, ejecutar mediciones, corregir y documentar retest. |
| R2 Revisión independiente | Revisor del PR por asignar y aceptar: reproducir pruebas de seguridad, comprobar evidencia y confirmar cierre. R1 solicita revisión; no se presume disponibilidad de terceros. |
| R3 Evaluación académica | Docente: evaluar evidencia y límites de la entrega. No equivale a autorización de producción. |

Registrar por ejecución: commit, fecha/hora Guatemala, PHP, adaptador, configuración de prueba sin secretos, escenario, esperado/observado, código de salida y hallazgos. Evitar contraseña, token completo y datos personales en logs/capturas. Identificadores ficticios bastan para reproducir.

Cada defecto lleva ID, pasos, impacto, severidad, responsable, plazo, commit de corrección y retest. Estados: abierto → en corrección → listo para retest → cerrado por R2. Un PASS de reproducción confirma el defecto y no lo cierra. Severidad propuesta: crítica para acceso entre tenants o filtración real; alta para autenticación/revocación insegura; media para validación inconsistente sin exposición demostrada; baja para documentación sin efecto de seguridad. R2 ratifica según impacto.

## 3. Siete métricas

N/D significa falta de evidencia/denominador y nunca equivale a cumplimiento. Se distingue nivel contrato y nivel integración; no se promedian para ocultar una brecha de seguridad.

| ID / objetivo | Fórmula / medición | Umbral | Fuente / frecuencia | Responsable y acción ante incumplimiento |
|---|---|---|---|---|
| M1 Trazabilidad | RF/RNF aplicables con caso, esperado y resultado / total aplicable ×100 | 100 %; mínimo 3 unitarias, 2 caja blanca y 4 caja negra | Matriz RF/RNF, salidas por commit; por cambio y antes de entregar | R1 mantiene, R2 revisa. Agregar caso o justificar exclusión; JWT pendiente no se marca aprobado. |
| M2 Integridad del alta | Casos de permiso/rol, duplicado y atomicidad correctos / ejecutados ×100; conteo de registros parciales | 100 % y 0 identidades/membresías parciales | Pruebas de transacción, fallo antes/durante escritura y conteos antes/después; por cambio de alta/repositorio | R1 ejecuta, R2 reproduce. Bloquear integración, reparar transacción/restricción y repetir; no basta inyectar fallo antes del callback. |
| M3 Credenciales y respuesta genérica | Escenarios de secreto incorrecto, sujeto inexistente/inactivo, tenant/membresía inválidos sin sesión y con respuesta genérica / ejecutados ×100 | 100 %; 0 secretos en salida/almacenamiento en claro | Pruebas del autenticador y revisión del adaptador de credenciales/logs; por cambio de login | R1 ejecuta/inspecciona, R2 revisa. Eliminar filtración, corregir mapeo y repetir sin exponer secretos. |
| M4 Aislamiento tenant | Casos negativos de alta/login/acceso cruzado bloqueados / ejecutados ×100 | 100 %; 0 permisos heredados entre tenants; postcondición sujeto/tenant del emisor consistente | Matriz con H1/H2 y casos de emisor mal contextualizado; pruebas del límite real al integrar | R1 prueba, R2 verifica. Detener uso con datos reales y corregir contexto confiable/autorización. |
| M5 Integridad de sesión y JWT | Casos de campos vacíos/blancos, exp límite, JWT válido/manipulado/algoritmo no admitido y claims requeridos correctos / ejecutados ×100 | 100 % por nivel; 0 tokens inválidos admitidos; nivel JWT no aprobado sin adaptador real | Pruebas de SessionToken separadas de pruebas del verificador JWT elegido; por cambio de sesión/clave/configuración | R1 ejecuta, R2 reproduce. Corregir validación y verificar firma/claims; no implementar un doble y llamarlo JWT validado. |
| M6 Revocación y fallo seguro | Casos de logout, almacén caído, jti/tenant aislados y rechazo posterior correctos / ejecutados ×100 | 100 %; 0 cierres confirmados sin persistencia; rechazo tras logout en segundo proceso para integración | Salidas y almacén de prueba persistente cuando exista; por cambio de sesión/revocación | R1 ejecuta, R2 confirma. No anunciar cierre si falla el almacén; impedir acceso cuando la comprobación no puede confiarse según política acordada. |
| M7 Reproducibilidad y defectos | Corridas completas repetibles / intentos; críticos/altos abiertos por separado | 3/3 por commit/adaptador y 0 críticos/altos para recomendar integración | Entorno/salidas, registro y retest de defectos; antes de cierre y tras correcciones | R1 registra, R2 repite al menos una. Investigar variabilidad y mantener aprobación pendiente si hay bloqueantes. |

Para M3 no se interpreta igualdad de mensaje como prueba de resistencia a diferencias temporales; esa evaluación queda fuera del ejemplo y requiere entorno de integración. Para M5/M6 la firma y el almacenamiento persistente no están demostrados hoy. No se atribuyen garantías a una biblioteca no seleccionada ni se modifican políticas clínicas.

## 4. Matriz de riesgos

Probabilidad P e impacto I de 1 a 3, prioridad=P×I. 6–9 alta, 3–4 media, 1–2 baja. Son estimaciones de planificación; residual por valorar después del retest.

| ID | Riesgo / causa | P×I / prioridad | Métrica / controles | Responsable / tratamiento |
|---|---|---|---|---|
| R-01 | Alta parcial o membresía duplicada por fallo/concurrencia | 2×3=6 alta | M2, C1/C2 | R1: transacción, restricción de unicidad y casos de fallo intermedio. |
| R-02 | Enumeración de identidades o credenciales expuestas en logs | 2×3=6 alta | M3, C3/C4 | R1: respuesta genérica, verificador de hash y evidencia sin secretos. |
| R-03 | Tenant incorrecto en login/emisión o acceso cruzado | 3×3=9 alta | M4, C5/C6 | R1: contexto confiable y postcondición del emisor; probar H1/H2. |
| R-04 | Token malformado/manipulado aceptado por falta de validación real | 3×3=9 alta | M5, C7/C8 | R1: cerrar brecha G-JWT con adaptador real; no aprobar integración con objetos de prueba. |
| R-05 | Logout falso o token revocado aceptado por otro proceso | 2×3=6 alta | M6, C9/C10 | R1: persistencia y consulta de revocación antes de permitir acceso. |
| R-06 | Membresía expirada/inactiva o sesión vencida conserva acceso | 2×3=6 alta | M3/M5, C6/C8 | R1: límites de tiempo y política de reevaluación en integración. |
| R-07 | Resultados de demo se presentan como seguridad productiva aprobada | 2×3=6 alta | M1/M7, C11 | R1: separar cobertura contrato/JWT y evidenciar defectos abiertos; R2 ratifica cierre. |

## 5. Controles preventivos y detectivos

| ID / tipo | Control | Responsable | Umbral / fuente | Acción si incumple |
|---|---|---|---|---|
| C1 Preventivo | Alta autorizada y transaccional de identidad/membresía con unicidad | R1 | 0 rutas fuera de transacción; revisión de caso de uso/esquema/adaptador | Corregir antes de integrar; no ejecutar altas reales con atomicidad incierta. |
| C2 Detectivo | Duplicado y fallo intermedio de escritura; comparar ambos registros | R1, revisión R2 | 0 registros parciales; salidas/conteos/rollback | Abrir defecto y retest de adaptador real, además del doble. |
| C3 Preventivo | Separar credenciales del alta; no registrar secretos/token completo | R1 | 0 exposición; contratos e inventario de logs/capturas | Retirar evidencia sensible y contener exposición antes de compartir. |
| C4 Detectivo | Comparar errores de identidad/credencial/membresía y revisar almacenamiento | R1, revisión R2 | Respuesta genérica en todos los casos; 0 contraseñas en claro | Corregir mensaje/log/adaptador y repetir. |
| C5 Preventivo | Derivar identidad/tenant de contexto confiable; validar resultado del emisor | R1 | Sujeto/tenant/jti/exp acordes al contrato; revisión del límite | Bloquear integración hasta establecer y probar postcondiciones. |
| C6 Detectivo | Intentos de alta/login/acceso con H1/H2, sin membresía o con rol ajeno | R1, revisión R2 | Todos los negativos rechazados; salidas sin datos del otro tenant | Abrir defecto alto/crítico según exposición; repetir antes de recomendar uso. |
| C7 Preventivo | Seleccionar adaptador JWT y documentar firma, claims y errores; mantener SDK fuera del dominio | R1 | Contrato verificable y entorno versionado; 0 dependencia de token sin verificar | Mantener G-JWT pendiente y no afirmar cobertura criptográfica. |
| C8 Detectivo | Probar blancos, exp exacto, manipulación, firma/algoritmo no admitido y claims del adaptador real | R1, revisión R2 | 100 % esperado; resultados por nivel contrato/JWT | Corregir inconsistencia AU-D01 y bloquear integración ante token inválido aceptado. |
| C9 Preventivo | Confirmar cierre solo tras persistir revocación por tenant/jti | R1 | 0 cierres anticipados; revisión de flujo/almacén | Corregir confirmación y política de error sin presentar logout exitoso. |
| C10 Detectivo | Falla del almacén y reuso tras logout en otro proceso | R1, revisión R2 | No éxito falso y token revocado rechazado; evidencia persistente | Mantener integración pendiente; mapa en memoria no satisface prueba entre procesos. |
| C11 Detectivo | Auditar matriz, tres corridas, hallazgos y límites antes del PR | R1, revisión R2 | 100 % trazado, 3/3 corridas, 0 críticos/altos para aprobar | Solicitar corrección/retest y conservar aprobación pendiente. |

## 6. Calendario propuesto

| Fecha / corte Guatemala | Actividad / evidencia | Responsable | Decisión |
|---|---|---|---|
| 28 oct 2026, 18:00 | Fijar base, matriz, métricas y riesgos; registrar límites del token demo | R1 | Plan listo para revisión; no aprobación productiva. |
| 29 oct, 12:00 | Repetir contrato y defectos; preparar pruebas negativas de rol/tenant | R1 | Separar casos aprobados y defectos reproducidos. |
| 29 oct, 18:00 | Seleccionar/verificar adaptador JWT y almacén persistente o registrar ausencia | R1; solicitar R2 | Sin evidencia real M5/M6 de integración quedan no aprobadas. |
| 30 oct, 18:00 | Corregir prioridades altas; retest y tres corridas por adaptador declarado | R1 / R2 por asignar | Cierre solo con evidencia independiente. |
| 30 oct, 22:00 | Consolidar salidas, riesgos residuales, pendientes y recomendación | R1; R3 evalúa | Entrega académica antes de 23:59; no inventar aprobaciones. |
| Tras cada cambio | Actualizar resultados y repetir casos afectados | R1 | Evidencia debe corresponder al commit revisado. |

Fechas propuestas, no reuniones ni ejecuciones confirmadas. Si no se consigue revisor o integración JWT antes del plazo, entregar la evidencia parcial con esa limitación; no usar un doble para aparentar cobertura de firma.

## 7. Estado y criterios de salida

| Área / hallazgo | Estado observado / aceptación |
|---|---|
| Reglas del contrato | Semana 15 en rama independiente: 14 casos y 33 aserciones de regresión exitosos. No demuestra tres corridas ni seguridad productiva. |
| AU-D01 | Campos de sesión solo blancos aceptados por acceso pero rechazados en logout; abierto. M5 pendiente de corrección consistente. |
| AU-D02 | Emisor defectuoso puede devolver otro tenant y login informa éxito; abierto. M4 bloquea recomendación de integración. |
| G-JWT | Firma/claims JWT reales y revocación entre procesos no probadas; M5/M6 integración pendientes. |
| Revisión independiente | R2 por asignar; no se cuenta como realizada. |

La preparación académica del SQA exige métricas, riesgos, controles, responsables y calendario disponibles. Para recomendar integración del flujo: M1–M7 satisfechas en su nivel declarado, cero críticos/altos abiertos, evidencia de autorización/tenant, JWT real y revocación persistente, y R2 registrado. La base actual no alcanza ese criterio. Entregar un plan y pruebas de contrato no equivale a aprobar autenticación de producción.