# Semana 14 — Plan SQA de recepción de muestras

**Estudiante / responsable:** Lopez Perez, Mercedes Azucena (Azucena17).

**Flujo:** Recepción, identificación inequívoca y rechazo controlado de muestra.

**Versión:** 1.0 · 28 de octubre de 2026 · Horarios de Guatemala.

## Alcance y base

Plan de calidad de la recepción y su relación con orden/examen/paciente, código único, aceptación/rechazo y cadena de custodia. No incluye procesamiento, resultados ni validación bioquímica. Los criterios clínicos y catálogo de motivos requieren acuerdo del laboratorio, no se inventan aquí.

Base de esta rama: 499154e de semana 13. Fuentes locales: [reglas R-01..R-08 de semana 1](../semana-01/semana-01-uml-recepcion-muestras.md) y [revisión de semana 13](../semana-13/semana-13-revision-tecnica-formal.md). Esa revisión inspeccionó el commit histórico d0eed5698f5de27aab218ff518c2052a837a1642 mediante referencias inmutables; el código histórico no está incorporado en esta base. Este plan no presenta pruebas de ese código como ejecutadas de nuevo.

Los seis hallazgos H-01..H-06 siguen abiertos o pendientes según revisión; este documento propone controles, no los corrige. Los umbrales son criterios del proyecto. Se separa evidencia del prototipo de evidencia de integración con LIS/EMR, operador, tenant, etiqueta y custodia reales. No se autoriza uso clínico por aprobar una prueba académica.

## Responsables y evidencia

| Rol | Responsable / tarea |
|---|---|
| R1 Desarrollo y pruebas | Mercedes Azucena Lopez Perez: mantener matriz, preparar datos ficticios, medir y corregir el módulo. |
| R2 Revisión independiente | Revisor del PR por asignar y aceptar: reproducir escenarios y validar cierre; R1 solicita revisión. |
| R3 Dueño funcional de laboratorio | Por designar: acordar aceptación, rechazo, motivos y eventos mínimos de custodia. No se presume aprobación de criterios clínicos. |
| R4 Evaluación académica | Docente: revisar entrega y límites; no aprobación de producción. |

Cada evidencia registra commit, fecha/hora, entorno/adaptador, entradas ficticias, esperado/observado, código de salida y hallazgo. Evitar datos reales de paciente, credenciales y etiquetas utilizables fuera del experimento. Hallazgos: abierto → en corrección → listo para retest → cerrado por R2; decisiones clínicas requieren además R3. Reproducción del defecto no significa cierre.

## Siete métricas

N/D indica evidencia pendiente o denominador vacío, nunca cumplimiento. Cada medida se calcula por nivel contrato/integración; los resultados de dobles no aprueban el nivel clínico real.

| ID / objetivo | Fórmula / medida | Umbral | Fuente / frecuencia | Responsable y acción al incumplir |
|---|---|---|---|---|
| M1 Concordancia clínica | Casos de orden/examen/paciente válidos o inválidos resueltos correctamente / ejecutados ×100 | 100 %; 0 aceptaciones con orden inexistente o asociación errónea | Casos positivos/negativos del gateway de órdenes y contexto; cada cambio de integración | R1 prueba, R3 define regla, R2 revisa. Bloquear aceptación e integrar validación confiable antes de recibir datos reales. |
| M2 Identificación única | Códigos sin duplicado / generados ×100; colisiones sin sobrescritura por separado | 100 % de unicidad persistida y 100 % de colisiones controladas; 0 registros sobrescritos | Restricción del almacén y pruebas concurrentes/colisión inyectada; cada cambio de generador/esquema | R1 ejecuta, R2 reproduce. Mantener restricción, abortar/reintentar de forma acotada según contrato y verificar estado antes de emitir etiqueta. |
| M3 Recepción/rechazo correctos | Casos de conformidad/no conformidad/motivo inválido correctos / ejecutados ×100 | 100 %; 0 aceptaciones ante no conformidad confirmada; rechazo con motivo explícito | Matriz de criterios/motivos aprobada y suite; por cambio funcional | R3 acuerda catálogo, R1 implementa/prueba, R2 revisa. No inventar catálogo; corregir regla y repetir casos. |
| M4 Custodia y trazabilidad | Eventos de prueba con actor, hora, referencia, orden y origen/destino aplicables / total eventos ×100 | 100 %; 0 borrados/ediciones que reescriban historia | Registro append-only de recepción/transferencia/rechazo y pruebas de alteración; cada cambio de custodia | R1 desarrolla/prueba, R3 define eventos, R2 verifica. Mantener H-03 abierto hasta existir historial y retest; fila de recepción no basta. |
| M5 Autorización y tenant | Casos de rol/tenant/sesión no válidos bloqueados / ejecutados ×100 | 100 %; 0 lecturas/escrituras cruzadas | Casos de técnico autorizado, rol ajeno, hospital ajeno y sesión vencida en límite integrado; por cambio de permisos | R1 prueba, R2 verifica. Bloquear integración clínica mientras falte contexto confiable o exista acceso cruzado. |
| M6 Fallos y consistencia | Casos de persistencia/validación técnica/etiqueta con resultado correcto / ejecutados ×100 | 100 %; 0 éxito falso, 0 recepción parcial confirmada y 0 identificaciones duplicadas por reintento | Fallos inyectados, consulta de operación y estado de impresión cuando exista; por cambio de repositorio/etiqueta | R1 ejecuta, R2 revisa. No confirmar aceptación ante fallo; definir pendiente de resolución y recuperación sin recrear muestra. |
| M7 Evidencia y defectos | Reglas R-01..R-08 con caso/evidencia / total aplicable ×100; corridas repetibles; críticos/altos abiertos | 100 % trazado, 3/3 corridas por commit/adaptador y 0 críticos/altos para recomendar integración | Matriz, salidas, inventario sin datos sensibles y registro de defectos; cierre y tras correcciones | R1 consolida, R2 reproduce una corrida. Completar evidencia, corregir bloqueantes y mantener aceptación pendiente. |

## Matriz de riesgos

P e I de 1 a 3; prioridad=P×I, 6–9 alta, 3–4 media, 1–2 baja. Estimaciones, no frecuencias observadas. Riesgo residual pendiente de controles y retest.

| ID | Riesgo / causa | P×I / prioridad | Métricas / controles | Responsable y tratamiento |
|---|---|---|---|---|
| R-01 | Recepción para orden/examen/paciente incorrectos sin fuente clínica válida | 3×3=9 alta | M1, C1/C2 | R1/R3: validar asociación en fuente autorizada antes de decidir. |
| R-02 | Código repetido o colisión con sobrescritura/etiqueta ambigua | 2×3=6 alta | M2, C3/C4 | R1: unicidad y manejo de conflicto sin perder identidad de muestra. |
| R-03 | Aceptar no conformidad o rechazar sin motivo entendible | 2×3=6 alta | M3, C5/C6 | R3 acuerda criterios; R1 distingue código y observación sin borrar decisión. |
| R-04 | Custodia no reconstruible por falta de actor/eventos o edición de historial | 3×3=9 alta | M4, C7/C8 | R1/R3: eventos mínimos append-only y controles de integridad. |
| R-05 | Operador/tenant no autorizado consulta o registra muestra | 3×3=9 alta | M5, C9/C10 | R1: contexto autenticado y permisos en límite servidor, nunca solo formulario. |
| R-06 | Falla de guardado/etiqueta deja recepción aceptada falsamente o duplica reintento | 2×3=6 alta | M6, C11/C12 | R1: estados explícitos y recuperación por referencia; impresión no se asume exitosa. |
| R-07 | Evidencia incompleta o API inexistente se presenta como integración aprobada | 2×3=6 alta | M7, C12 | R1/R2: conservar límites, trazabilidad y hallazgos; API real requiere contrato seleccionado. |

## Controles preventivos y detectivos

| ID / tipo | Control | Responsable | Umbral / fuente | Acción ante desviación |
|---|---|---|---|---|
| C1 Preventivo | Acordar relación orden/examen/paciente y fuente confiable antes de recibir | R1/R3 | Reglas completas y fuente identificada; contrato revisado | No aceptar integración clínica sin regla/fuente. |
| C2 Detectivo | Probar orden inexistente, examen ajeno y paciente discordante | R1, revisión R2 | Todos rechazados antes de escribir; salidas/estado | Abrir defecto alto y retest del gateway integrado. |
| C3 Preventivo | Restricción única y código separado de paciente/orden | R1 | 0 rutas de sobrescritura; esquema y diseño de código | Corregir persistencia/identificador antes de etiquetar. |
| C4 Detectivo | Inyectar colisión y solicitudes concurrentes | R1, revisión R2 | 0 códigos duplicados; conflicto explícito sin pérdida | Comprobar identidad persistida; recuperación acotada y documentada. |
| C5 Preventivo | Criterios de conformidad y motivos con dueño funcional, observación separada | R3/R1 | Catálogo/reglas aprobados para alcance; documento versionado | Mantener decisión pendiente; no inventar motivos clínicos. |
| C6 Detectivo | Recibir conforme/no conforme y probar motivo vacío/inválido | R1/R3, revisión R2 | 100 % regla; rechazo explícito conservado | Corregir validación y repetir sin borrar evidencia histórica. |
| C7 Preventivo | Definir eventos de custodia con actor/tiempo/referencia/origen/destino aplicables | R3/R1 | Campos/eventos acordados; diseño append-only | No declarar cadena completa por una sola fila de recepción. |
| C8 Detectivo | Intentar editar/borrar evento y reconstruir recepción/transferencia | R1, revisión R2 | Historia íntegra, orden temporal y actor recuperables | Abrir defecto de integridad y bloquear recomendación clínica. |
| C9 Preventivo | Identidad/tenant confiables y autorización por operación | R1 | Ninguna autorización solo con input cliente; revisión de límite | Restringir uso a demo hasta integrar controles. |
| C10 Detectivo | Rol/tenant ajenos y sesión ausente/expirada | R1, revisión R2 | Todos bloqueados, sin datos ni escritura ajenos | Corregir contexto/permisos y repetir pruebas negativas. |
| C11 Preventivo | Definir estado ante fallo de persistencia/validación/etiqueta y reintento | R1/R3 | 0 éxitos anticipados; contrato de operación y confirmación | Dejar recepción sin aceptación confirmada y resolver antes de reintentar. |
| C12 Detectivo | Inyectar fallos, revisar privacidad, matriz y defectos antes de entregar | R1, revisión R2 | M6/M7 satisfechas; sin secretos/datos reales; evidencia por commit | Mantener bloqueantes abiertos, corregir y repetir; no usar resultados de otro commit. |

## Calendario propuesto

| Fecha / corte Guatemala | Actividad y evidencia | Responsable | Decisión |
|---|---|---|---|
| 28 oct 2026, 18:00 | Congelar alcance y matriz de métricas/riesgos | R1 | Plan listo para revisión, no integración aprobada. |
| 29 oct, 12:00 | Acordar criterios/motivos/eventos y contrato de orden/tenant | R1 solicita R3; revisión R2 | Sin acuerdo/evidencia, mantener M1/M3/M4 pendientes. |
| 29 oct, 18:00 | Ejecutar unicidad, permisos, fallos y privacidad sobre implementación seleccionada | R1 | Registrar estados/hallazgos; no copiar aprobación histórica. |
| 30 oct, 18:00 | Corregir prioridad alta, retest y tres corridas reproducibles | R1/R2 | Cerrar solo con evidencia y confirmación independiente. |
| 30 oct, 22:00 | Consolidar informe y riesgos residuales | R1; R4 evalúa | Entrega antes de 23:59 con pendientes honestos. |
| Tras cada cambio | Reejecutar casos afectados y actualizar evidencia | R1 | Datos/commit coinciden con versión revisada. |

Roles R2/R3 y fechas requieren aceptación; no son reuniones celebradas ni compromisos de terceros. Si faltan revisores o integración, entregar el plan con la limitación y sin declarar aprobada la recepción clínica.

## Estado y criterios de salida

La revisión de semana 13 identifica H-01 relación clínica ausente, H-02 autorización/tenant ausentes, H-03 custodia append-only ausente, H-04 catálogo de motivos pendiente, H-05 colisión sin recuperación definida y H-06 contrato API/integración no revisable. Sus evidencias están en la referencia de semana 13; este plan no cierra esos hallazgos.

Entrega académica del plan: siete métricas, riesgos, controles, responsables y calendario disponibles. Para recomendar integración: M1–M7 satisfechas al nivel declarado, cero críticos/altos abiertos, criterios de laboratorio acordados, unicidad/custodia/tenant comprobados y revisión R2 registrada. Una prueba de contrato no demuestra escaneo, etiqueta impresa, cadena física ni LIS real. La base actual mantiene esas brechas.