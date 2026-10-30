# Semana 9 — Evaluación UX y accesibilidad de recepción de muestras

**Estudiante:** Mercedes Azucena Lopez Perez. **Fecha:** 28-10-2026.

Se evalúa el flujo de [semana 8](../semana-08/semana-08-ux-recepcion-muestras.md): recepción individual, vínculo orden/ítem/recipiente, decisión y custodia. Seis SVG conceptuales, no aplicación conectada a órdenes, lector o impresora. Se preservan originales y se documentan hallazgos; no se atribuyen fallos clínicos a una interfaz operativa inexistente.

## Método y evidencia

Inspección experta con heurísticas de visibilidad, consistencia, control, prevención de errores y recuperación. Referencia de criterios: [W3C WCAG 2.2, guía de consulta](https://www.w3.org/WAI/WCAG22/quickref/), consultada para esta serie de evaluaciones. Se mapean obligaciones de implementación, sin declarar conformidad por observar dibujos.

Edge headless + Playwright captura 1280 × 820, cuenta controles y mide contraste de cabecera blanca contra #12304a; detecta textos fuera del lienzo. [Datos observados](evidencia/inspeccion.json) y [reproducción](evidencia/README.md). Contraste de un par no cubre todos los textos, bordes ni estados. Cero controles en SVG limita la evidencia de teclado/foco, no demuestra por sí solo incumplimiento de una aplicación productiva.

## Checklist completado

D = evidencia favorable de diseño; H = brecha observable del artefacto; P = prueba pendiente de interfaz operativa. D no equivale a conformidad WCAG.

| # | Aspecto / criterio | Resultado y evidencia |
|---|---|---|
| 1 | Contexto/visibilidad (heurística) | D: hospital y orden/ítem visibles WF01..06; contexto autorizado real P. |
| 2 | Vínculo inequívoco (prevención) | H04: WF02 confirma concordancia con “Paciente de ejemplo” sin especificar segundo identificador del procedimiento. |
| 3 | Teclado 2.1.1 | P/H01: controles dibujados, no operables; comprobar manual/lector alternativo en implementación. |
| 4 | Orden de foco 2.4.3 | P/H03: orden/retorno de foco tras confirmación no acreditado. |
| 5 | Foco visible/no oculto 2.4.7/2.4.11 | P/H03: sin estados de foco dibujados ni pruebas. |
| 6 | Etiquetas/relaciones 1.3.1, 3.3.2, 4.1.2 | H01: etiquetas visuales, sin asociación a controles nativos. |
| 7 | Contraste 1.4.3 | D parcial: cabecera medida en JSON; otros pares/estados P. No inventar fallos de contraste no medidos. |
| 8 | Reflujo 1.4.10 | H02: lienzo fijo 1280 px y tabla, sin variante móvil verificable en esta base. |
| 9 | Identificación de error 3.3.1 | H05: rechazo sin motivo descrito como bloqueo, sin ejemplo de error asociado. |
| 10 | Sugerencia/prevención 3.3.3 | D parcial: WF06 diferencia falla técnica de rechazo; H07 borrador no especificado. |
| 11 | Mensajes de estado 4.1.3 | P/H08: guardado/impresión distintos en texto, sin estrategia de anuncio/foco. |
| 12 | Confirmación y control (heurística) | D: WF03 confirma un recipiente y permite revisar; idempotencia real P. |
| 13 | Recuperación (heurística) | H06: estado incierto requiere referencia, pero no se muestra clave en recuperación de ese caso. |
| 14 | Privacidad/custodia (regla de módulo) | H07: guardar luego no define almacenamiento/retención; permisos/custodia real P. |
| 15 | Estado realista (heurística) | D: impresión pendiente no cambia aceptación; H08 falta variante efectiva tras reintento. |

## Hallazgos y backlog priorizado

Todos abiertos, autora como responsable propuesta de diseño; responsables clínicos, seguridad y accesibilidad por asignar/aceptar. P1 = antes de piloto con recipientes reales; P2 = antes de aceptar diseño implementable. Son ocho hallazgos de diseño/evidencia, no ocho fallos de producción certificados.

| ID / prioridad | Evidencia / reproducción | Impacto | Corrección y criterio verificable |
|---|---|---|---|
| H01 P1 | [WF01](evidencia/01-iniciar-recepcion.png), [WF02](evidencia/02-verificar-muestra.png); inspeccionar SVG, controles son texto/rectángulos. | Operador no puede buscar, seleccionar o inspeccionar por teclado en este artefacto. | Implementar form/label/input/checkbox/button nativos, nombres/estados accesibles. Recorrer búsqueda → selección → decisión con Tab/Enter/Espacio; lector anuncia etiqueta y estado. Lector físico opcional, alternativa manual disponible. |
| H02 P2 | Los seis SVG fijan 1280 × 820; WF01 usa columnas de tabla. | Reducir imagen en móvil dificulta distinguir código/orden/ítem y acciones. | Proponer tarjetas y columna única; probar 320/375/430 CSS px, zoom 200 %, sin scroll horizontal ordinario ni ocultar vínculo de muestra. Evidencia futura de implementación, no cierre por documento. |
| H03 P1 | [WF03](evidencia/03-confirmar-aceptacion.png): confirmación y checkbox, sin estado enfocado/retorno tras guardar o volver. | Pérdida de contexto o confirmación accidental con teclado. | Definir orden contexto → revisión → confirmación → guardar; al volver mantener recipiente/ítem correcto; foco al resultado tras persistencia o resumen al fallar. Si modal, Escape cancela y devuelve foco al disparador; probar foco visible/no tapado. |
| H04 P1 | [WF02](evidencia/02-verificar-muestra.png): “Paciente de ejemplo”, referencia externa y checkbox genérico de concordancia. | Un nombre por sí solo no acredita identificación; riesgo de elegir ítem/recipiente equivocado en diseño final. | Acordar identificadores mínimos y procedimiento con laboratorio; mostrar discriminadores autorizados y comparación explícita, sin inventar requisito clínico. Probar discrepancia de orden/ítem/recipiente: no confirmar por defecto, sin revelar datos ajenos. |
| H05 P1 | [WF04](evidencia/04-rechazar-muestra.png): anotación de botón deshabilitado sin motivo, sin error visible de ejemplo. | Operador no sabe por qué no puede continuar, especialmente con teclado/lector. | Ayuda “Selecciona motivo para continuar”, error asociado y resumen enfocable si intenta enviar; no solo deshabilitar sin explicación. Probar motivo vacío, seleccionado y retiro; conservar observación pertinente y rechazar solo no conformidad confirmada. |
| H06 P1 | [WF06](evidencia/06-errores-recuperables.png): instrucciones de guardado incierto al pie, pero vista muestra solo ORD-00482 y fallo de consulta. | Confundir consulta fallida con guardado desconocido puede duplicar specimenId/recepción. | Separar variantes: lectura fallida → reintentar consulta; guardado desconocido → “Comprobar recepción” con referencia técnica segura de operación. Simular respuesta perdida tras commit y verificar recuperación del mismo specimenId sin nueva recepción; no usar ID de orden como única clave idempotente. |
| H07 P1 | [WF06](evidencia/06-errores-recuperables.png): “Guardar para continuar luego” sin destino/retención/ámbito explícitos. | Persistencia inadvertida de referencias de paciente/orden en terminal compartido y falsa impresión de recepción confirmada. | Definir borrador mínimo con dueño/tenant, expiración y limpieza al salir; si offline no seguro, no ofrecer guardado. Mostrar “Borrador, no recepción” y probar cierre de sesión/cambio de hospital sin recuperar datos ajenos. Retención requiere acuerdo, no cifra inventada. |
| H08 P2 | [WF05](evidencia/05-confirmacion-custodia.png): impresión pendiente/reintento, sin estado tras pulsar ni confirmación de impresión. | Doble impresión o nueva recepción por confusión; lector no conoce progreso/resultado. | Separar registró/custodia e impresión en estados pendiente/en curso/confirmada/fallida/desconocida. Anuncio accesible por cambio; reintentar impresión conserva specimenId y no reenvía alta. Simular fallo y resultado desconocido de impresión sin afirmar etiqueta impresa. |

## Orden y aceptación

H04/H06/H07 → H01/H03/H05 → H02/H08, agrupando implementación común si conviene. No cerrar por escribir propuesta; conservar captura de base y añadir captura/prueba tras corrección. Entrada de revisión: procedimiento de identificación y permisos acordados, prototipo interactivo y errores simulables. Salida: criterios P1 verificados y pendientes aceptados por responsable identificado; pruebas de teclado/lector/zoom y seguridad en su frontera real.

Guion feliz futuro: técnico autorizado busca orden/ítem, contrasta recipiente según procedimiento, revisa y confirma una muestra; solo tras persistir ve specimenId/custodia, luego impresión aparte. Guion crítico: respuesta perdida de guardado → comprobar operación → recuperar mismo espécimen, sin reemitir por timeout. Son guiones de aceptación, no ensayos con usuarios ejecutados aquí.

## Trazabilidad

| Elemento del módulo | Hallazgos / evidencia |
|---|---|
| Muestra física y código único | H04/H06/H08, WF02/03/05/06 |
| Vínculo con orden e ítem | H02/H04, WF01/02 |
| Recepción consciente | H01/H03, WF02/03 |
| Rechazo con motivo | H05, WF04 |
| Cadena de custodia | H06/H07/H08, WF05/06; implementación real no acreditada por dibujo |
| Acceso/confidencialidad | H04/H07, discriminadores mínimos y borrador con ámbito |

Límites: no pruebas de lector físico, impresora, sesión/tenant, correspondencia clínica o cadena completa de custodia. Capturas reales de dibujos, no screenshots de sistema productivo. No se cambian reglas clínicas ni fuentes de semana 8 para mantener trazabilidad de hallazgos.