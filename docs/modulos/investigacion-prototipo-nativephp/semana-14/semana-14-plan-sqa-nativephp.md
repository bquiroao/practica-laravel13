# Semana 14 — Plan de aseguramiento de calidad (SQA)

**Estudiante y responsable del módulo:** Lopez Perez, Mercedes Azucena (Azucena17)

**Módulo:** Investigación y prototipo NativePHP para extensión móvil/escritorio

**Flujo:** Evaluación y prototipo acotado de un flujo HIS mediante NativePHP: consultar el estado de una orden ficticia y notificarlo.

**Versión del plan:** 1.0 · 27 de octubre de 2026 · Horarios de Guatemala

## 1. Alcance y evidencia de partida

Este plan controla la calidad de la evaluación de viabilidad y del prototipo de contrato, no de todo el hospital. Laravel/HIS debe seguir siendo fuente de verdad; el cliente no decide reglas clínicas ni valida resultados de laboratorio. El ejemplo PHP de [semana 2](../semana-02/semana-02-isp-nativephp.md) usa puertos segregados y adaptadores en memoria. NativePHP no está instalado en esa entrega: no hay evidencia de empaquetado, permisos del SO, notificaciones reales ni conectividad autenticada.

Se distinguen dos niveles de evidencia: **contrato** (PHP CLI y dobles) y **nativo** (runtime, plataforma y dispositivo elegidos). Superar contrato no aprueba el nivel nativo. La revisión local de semana 15, commit c0bd24e en su rama independiente, aporta 13 casos de contrato exitosos y dos defectos reproducidos; su evidencia se incorporará mediante su propia entrega. Este plan no depende de archivos de semana 15 aún no integrados.

RF-01..RF-07 y RNF-01..RNF-06 mantienen los identificadores de semana 2. Se agregan criterios SQA-EV de viabilidad, recuperación y trazabilidad del experimento, sin alterar requisitos de otros módulos. Los umbrales siguientes son criterios de aceptación propuestos para este proyecto, no garantías del fabricante ni de una norma.

## 2. Responsabilidades y tratamiento de hallazgos

| Rol | Responsable / alcance |
|---|---|
| R1 Desarrollo y ejecución | Mercedes Azucena Lopez Perez: preparar casos y entorno, ejecutar mediciones, conservar salidas y corregir defectos del módulo. |
| R2 Revisión técnica | Revisor del PR por asignar: comprobar trazabilidad y reproducir una muestra independiente. Mercedes solicita la revisión; no se presume que otra persona ya aceptó el cargo. |
| R3 Aceptación académica | Docente: evaluar evidencia y alcance de la entrega; no se presume autorización para despliegue clínico. |

Hasta asignar R2, la revisión independiente queda **pendiente**; no se contabiliza como aprobada. Cada evidencia registra fecha/hora, commit, SO, PHP, distribución/versión NativePHP si aplica, escenario, entrada ficticia, resultado esperado/observado, responsable y enlace al defecto. No incluir tokens ni datos de pacientes.

Severidad: crítica = filtración o modificación clínica indebida; alta = cierre inesperado o éxito falso; media = capacidad opcional indisponible sin degradación explícita; baja = documentación o presentación sin impedir el flujo. Todo defecto recibe ID, pasos, impacto, propietario R1, prioridad, corrección y reejecución. El cierre requiere evidencia de retest y revisión R2; no se cierra por tener un commit.

## 3. Métricas: fórmula, umbral, fuente y acción

N/D significa que falta evidencia o no existe denominador: nunca equivale a 0 fallos ni a 100 % de cumplimiento. Los conteos excluyen defectos reproducidos del total de casos funcionales aprobados.

| ID / calidad | Medición | Umbral de aceptación | Fuente y frecuencia | Responsable / acción al incumplir |
|---|---|---|---|---|
| M1 Trazabilidad | Requisitos RF/RNF aplicables con caso, esperado y evidencia / total aplicable × 100 | 100 %; mínimo 3 unitarias, 2 caja blanca y 4 caja negra | Matriz RF/RNF de semana 15 y salidas por commit; cada cambio de requisito | R1 mantiene, R2 revisa; detener aceptación, agregar caso/evidencia o justificar exclusión antes de aprobar. |
| M2 Confiabilidad del contrato | Casos funcionales exitosos / ejecutados × 100; defectos altos/críticos abiertos por separado | 100 % de casos y 0 altos/críticos abiertos en el flujo seleccionado | Suite PHP, inyección de fallos y registro de defectos; por cambio y antes de entrega | R1 ejecuta y corrige; R2 verifica. Bloquear integración de adaptador real hasta resolver defectos bloqueantes. |
| M3 Viabilidad por destino | Destinos con instalación, arranque, consulta demo y capacidad nativa comprobados / destinos declarados × 100 | 100 % de los destinos que se anuncien como soportados; al menos un destino concreto para afirmar ejecución nativa | Checklist versionado por SO/dispositivo y capturas de runtime; al elegir o actualizar paquete/plataforma | R1 registra; R2 reproduce. Si N/D o incompleto, concluir “viabilidad nativa no demostrada” o reducir alcance explícitamente. |
| M4 Permisos y degradación | Escenarios concedido, denegado, revocado y capacidad ausente con resultado explícito / 4 × 100 | 100 %, sin notificación de éxito falsa ni cierres inesperados | Pruebas con dobles más evidencia separada en destino nativo; por cambio de adaptador | R1 ejecuta; R2 revisa. Traducir errores conocidos en el adaptador y repetir los cuatro escenarios. |
| M5 Seguridad y privacidad | Hallazgos de token/dato personal/valor clínico en logs, notificación, evidencia y almacenamiento local de prueba | 0 hallazgos; todas las superficies enumeradas deben revisarse | Inspección manual, prueba de contenido de notificación y revisión de permisos/almacenamiento del destino; antes de cada PR | R1 inspecciona; R2 confirma. Retirar evidencia sensible, revocar secreto si lo hubiera y repetir revisión antes de compartir. |
| M6 Recuperación de lectura | Casos red caída, timeout, recuperación con estado nuevo y snapshot previo marcados correctamente / 4 × 100 | 100 %; 0 snapshots obsoletos mostrados como actuales; 0 escrituras offline | Pruebas de política y, al integrar transporte, fallos controlados de red con tiempos y resultado; por cambio de sincronización | R1 ejecuta; R2 compara. Marcar antigüedad/origen, impedir éxito ambiguo y reconsultar al reconectar. |
| M7 Reproducibilidad | Ejecuciones completas reproducibles con mismo commit/configuración / intentos × 100 | 3 de 3 en contrato; en nativo repetir por cada destino declarado | Salidas con versiones, commit y código de salida; cierre de entrega | R1 registra tres corridas; R2 repite al menos una. Fijar dependencia/configuración y corregir diferencias antes de aceptar. |

## 4. Matriz de riesgos

Escala propuesta: probabilidad P e impacto I de 1 a 3; prioridad = P × I. 6–9 alta, 3–4 media, 1–2 baja. La probabilidad es una estimación de planificación, no una frecuencia medida. El riesgo residual se vuelve a valorar después de ejecutar controles; por ahora no se declara reducido.

| ID | Riesgo / causa | P × I / prioridad | Métrica / controles | Responsable y tratamiento |
|---|---|---|---|---|
| R-01 | Declarar soporte móvil/escritorio usando solo dobles; runtime o toolchain incompatibles | 3 × 3 = 9 alta | M3, C1/C2 | R1: seleccionar distribución/versión/destino, registrar instalación. No prometer soporte no ejecutado. |
| R-02 | Permiso revocado o capacidad ausente produce excepción o éxito falso | 3 × 2 = 6 alta | M2/M4, C3/C4 | R1: normalizar resultado y degradación; bloquear integración si interrumpe el flujo. |
| R-03 | Caída/timeout del HIS deja snapshot desactualizado presentado como actual | 2 × 3 = 6 alta | M6, C5/C6 | R1: marcar obsolescencia, no escribir offline y reconsultar. |
| R-04 | Notificaciones, logs o archivos exponen información clínica o credenciales | 2 × 3 = 6 alta | M5, C7/C8 | R1: datos ficticios y mínimo contenido; R2 inspecciona antes de compartir. |
| R-05 | Se amplía el prototipo a módulos clínicos sin criterio ni autorización | 2 × 2 = 4 media | M1, C9 | R1: mantener consulta de orden demo y excluir reglas clínicas ajenas. |
| R-06 | Actualización del paquete/SO altera el comportamiento o impide reproducir resultados | 2 × 2 = 4 media | M3/M7, C1/C10 | R1: fijar versiones, registrar entorno y repetir por destino. |
| R-07 | Defectos reproducidos se cuentan como funcionalidades aprobadas y se oculta una falla | 2 × 3 = 6 alta | M1/M2, C11 | R1: separar suite funcional de --defectos; R2 comprueba estado y retest. |

## 5. Controles preventivos y detectivos

Cada control tiene propietario, criterio, fuente y respuesta. Los controles de runtime quedan pendientes hasta elegir y ejecutar un destino; un doble no los satisface.

| ID / tipo | Control | Responsable | Umbral / fuente | Acción ante desviación |
|---|---|---|---|---|
| C1 Preventivo | Registrar distribución/versión, dependencias, SO y destino antes de instalar | R1 | 100 % de campos; ficha de entorno/lockfile cuando aplique | No iniciar afirmación de compatibilidad; completar ficha. |
| C2 Detectivo | Ejecutar arranque y consulta/notificación en cada destino declarado | R1, revisión R2 | Todos los pasos pasan; logs y capturas nativas | Marcar destino no demostrado, investigar instalación y repetir. |
| C3 Preventivo | Contratos ISP y errores conocidos traducidos en adaptadores | R1 | Sin dependencia de capacidades ajenas; revisión de puertos y contrato de errores | Refactorizar límite; no añadir métodos ficticios de plataforma. |
| C4 Detectivo | Probar permisos concedido/denegado/revocado/ausente | R1 | 4/4 con resultado explícito; salidas y capturas | Abrir defecto y bloquear adaptación real si hay cierre o éxito falso. |
| C5 Preventivo | HIS fuente de verdad; distinguir fresco/obsoleto; prohibir escritura offline en este alcance | R1 | 0 rutas de escritura offline; diseño de sincronización | Retirar ruta, aclarar alcance y actualizar pruebas. |
| C6 Detectivo | Inyectar desconexión, timeout y recuperación | R1 | 4/4 de M6; evidencia del transporte cuando exista | No anunciar estado actual sin respuesta; corregir recuperación. |
| C7 Preventivo | Usar solo datos ficticios y notificación genérica; no registrar secretos | R1 | 0 datos reales/secretos; checklist de datos de prueba | Reemplazar datos y contener exposición antes de publicar. |
| C8 Detectivo | Inspeccionar mensajes, logs, evidencia y almacenamiento | R1, revisión R2 | 0 hallazgos; inventario y revisión de las cuatro superficies | Abrir defecto crítico si hay filtración, retirar evidencia y repetir. |
| C9 Preventivo | Vincular cada caso/cambio al flujo de orden demo y a RF/RNF | R1 | 100 % trazados; matriz y diff | Sacar cambios ajenos y completar trazabilidad. |
| C10 Detectivo | Repetir suite en mismo entorno y conservar versiones/exit code | R1, revisión R2 | 3/3 de M7; bitácora de ejecuciones | Investigar dependencia variable, fijar configuración y reejecutar. |
| C11 Detectivo | Revisar defectos abiertos y diferenciar reproducción de corrección | R1, revisión R2 | 0 altos/críticos para aprobar integración; registro y retest | Mantener bloqueo y comunicar limitación en conclusión. |

## 6. Calendario de seguimiento propuesto

Fechas propuestas dentro del plazo de la tarea (30 de octubre, 23:59 Guatemala). No se presentan como actividades ya realizadas ni compromisos aceptados por terceros.

| Fecha / corte | Actividad y salida | Responsable | Decisión |
|---|---|---|---|
| 27 oct 2026 | Inventario del prototipo, alcance, métricas y riesgos; registrar entorno no nativo | R1 | Aprobar plan para revisión, sin declarar ejecución nativa. |
| 28 oct 2026, 18:00 | Completar matriz y casos mínimos; medir contrato y reproducir errores de gateway/notificador | R1 | Abrir defectos con severidad y propietario; separar PASS de reproducción. |
| 29 oct 2026, 18:00 | Revisar permisos, sincronización, privacidad y viabilidad por destino | R1; solicitar R2 | Si no hay runtime ejecutado, documentar “no demostrado”; no sustituirlo por un doble. |
| 30 oct 2026, 18:00 | Tres corridas reproducibles, retest de correcciones y revisión de evidencia | R1 / R2 por asignar | Si persisten defectos bloqueantes o no hay R2, mantener aprobación técnica pendiente. |
| 30 oct 2026, 22:00 | Cerrar informe, capturas, riesgos residuales y recomendación académica | R1; R3 evalúa | Entregar evidencia y limitaciones antes de 23:59. |
| Después de cada cambio | Reejecutar casos afectados y actualizar métricas/riesgos | R1 | No reutilizar resultados de otro commit como prueba del cambio. |

Si no se dispone de R2 o destino nativo dentro del plazo, entregar el plan y evidencia de contrato con esa limitación explícita. No inventar aprobaciones ni extender el alcance para aparentar cumplimiento.

## 7. Estado de línea base y criterios de salida

| Área | Estado observado / criterio |
|---|---|
| Contrato de semana 2 | Disponible con adaptadores en memoria; RF/RNF y ejemplo verificable. |
| Hallazgos de evaluación posterior | Rama independiente de semana 15: c0bd24e; 13 casos exitosos y defectos NP-D01/NP-D02 abiertos sobre excepciones del gateway/notificador. No equivale a aprobación integral de M2. |
| Empaquetado/capacidades nativas | N/D: sin runtime verificado. M3/M4 nativas no aprobadas. |
| Red y sincronización | Política exploratoria comprobable con dobles; transporte real y recuperación nativa pendientes. |
| Seguridad del destino | Datos ficticios y diseño de mínima exposición; almacenamiento y permisos reales pendientes. |
| Revisión independiente | R2 por asignar. |

La entrega académica puede contener defectos abiertos y límites honestos, junto con su evidencia. Para recomendar un **prototipo nativo integrado** se exige trazabilidad completa, mínimos de pruebas, M1–M7 satisfechas por el nivel declarado, cero defectos altos/críticos abiertos y revisión R2 registrada. Para afirmar soporte en dos destinos, ambos deben superar M3; aprobar uno no aprueba el otro. No se contempla despliegue clínico ni producción en este plan.