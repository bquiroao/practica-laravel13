# Semana 9 — Evaluación de usabilidad y accesibilidad: órdenes desde EMR

**Estudiante:** Lopez Perez, Mercedes Azucena · **Fecha:** 28 de octubre de 2026.

## Método y alcance

Inspección heurística del flujo de [semana 8](../semana-08/semana-08-ux-ordenes-emr.md), base f5eb814, y sus seis SVG anotados. Esta rama depende de esa entrega. Se evalúan visibilidad del estado, prevención/recuperación de errores, control del usuario, consistencia y reconocimiento del contexto. Son criterios de inspección UX, no resultados de participantes ni una sesión con usuarios.

Referencia WCAG 2.2 para teclado, foco, contraste, etiquetas y mensajes. Los SVG son imágenes, no controles operables; la falta de interacción no se declara automáticamente incumplimiento de una aplicación inexistente. Se distinguen hallazgos visuales/UX y brechas de especificación que deben verificarse al implementar. No se certifica conformidad WCAG del HIS ni se evalúan módulos ajenos.

## Checklist completado

Estados: observado en diseño, parcial, brecha de especificación o no verificable en estático. Todos los ítems tienen resultado; no verificable no significa aprobado.

| ID | Aspecto / referencia | Resultado y evidencia |
|---|---|---|
| C01 | Visibilidad de hospital/expediente/episodio | Observado: WF01/03 muestran contexto. Hay datos ficticios mínimos. |
| C02 | Reconocimiento de selección / prevención | Parcial: WF02 cuenta selección; WF03 lista prueba pero no total explícito. H-01. |
| C03 | Control de usuario al confirmar | Parcial: volver existe como texto; el único botón prominente es confirmar, sin cancelación explícita del flujo. H-02. |
| C04 | Explicación de prueba no disponible | Parcial: WF02 distingue estado, pero no motivo/acción por prueba. H-03. |
| C05 | Vacío, carga y error distintos | Observado en anotaciones, no ejecutado. No afirmar anuncio accesible implementado. |
| C06 | Recuperación de error específica | Parcial: WF06 agrupa timeout, catálogo y sesión; una sola acción principal. H-04. |
| C07 | Seguimiento entendible | Parcial: WF04/05 muestran ESTADO_DEMO_INICIAL sin explicación para usuario operativo. H-05. |
| C08 | Teclado — 2.1.1 | No verificable como flujo: SVG sin controles HTML. Falta tabla de orden/teclas por acción. H-06. |
| C09 | Foco visible — 2.4.7 | Brecha: requisito escrito, sin muestra visual de foco ni destino concreto para cada transición. H-06. |
| C10 | Contraste — 1.4.3 | Pares de colores medidos en fuente superan 4.5:1; ver inspeccion.txt. No cubre futuros estados hover/foco/deshabilitado. |
| C11 | Etiquetas/instrucciones — 3.3.2 | Parcial: “Buscar prueba [ Glucosa ]” no separa etiqueta y entrada de ejemplo; falta especificación del campo. H-07. |
| C12 | Mensajes — 4.1.3 / errores | Brecha: anotaciones mencionan avisos, pero no contrato de anuncio para carga/error/éxito sin cambio de vista. H-08. |
| C13 | Prevención de éxito falso/duplicado | Observado: WF03/06 exigen persistencia y comprobación ante timeout; contrato real pendiente. |
| C14 | Privacidad y alcance | Observado: no resultados clínicos en listado; contexto limitado; sin cambio manual de estado. Autorización servidor no probada por diseño. |
| C15 | Movilidad/zoom | No verificable: SVG 1100×700 fijo; no afirmar reflow 320 px ni zoom probado. Incorporar al retest de implementación. |

## Hallazgos y evidencia (ocho)

Cada hallazgo es una observación del diseño o de su especificación, no una vulnerabilidad/defecto clínico demostrado. Evidencia: archivo fuente, texto observable y captura del mismo SVG.

| ID | Evidencia concreta | Hallazgo e impacto en el flujo |
|---|---|---|
| H-01 | WF03 “Prueba seleccionada: P-DEMO-01 · Glucosa”; [fuente](../semana-08/wireframes/03-confirmar-orden.svg), captura 03 | No hay total explícito ni resumen para múltiples partidas en confirmación. Puede dificultar detectar omisiones antes de emitir. |
| H-02 | WF03 “← Volver a selección sin guardar” es texto, botón “Confirmar emisión”; captura 03 | Falta diferenciar visualmente volver a editar de cancelar orden por completo y especificar conservación/descarte. Riesgo de avanzar por no encontrar salida clara. |
| H-03 | WF02 “Prueba demo · No disponible”; [fuente](../semana-08/wireframes/02-seleccionar-pruebas.svg), captura 02 | No hay motivo o ruta de ayuda para esa indisponibilidad. Puede provocar intentos repetidos o selección errónea alternativa. No se recomienda otra prueba clínica automáticamente. |
| H-04 | WF06 combina “No sabemos si…” con “Si una prueba…” y “Si la sesión…”; [fuente](../semana-08/wireframes/06-error-recuperable.svg), captura 06 | Estados distintos comparten Comprobar operación como única acción destacada. La recuperación podría no corresponder al error real. |
| H-05 | WF04/05 “ESTADO_DEMO_INICIAL”; [fuente](../semana-08/wireframes/05-seguimiento.svg), captura 05 | Código sintético sin glosa comprensible en área principal. Es honesto como demo, pero no defendible como etiqueta operativa final. No inventar ciclo clínico para resolverlo. |
| H-06 | Seis SVG con role=img, sin controles/tabindex; [inspección](evidencia/inspeccion.txt) | Orden de teclado/foco solo genérico, sin estados dibujados ni tabla por pantalla. Brecha de especificación de 2.1.1/2.4.7; no dictamen de incumplimiento de app inexistente. |
| H-07 | WF02 “Buscar prueba [ Glucosa ]”; captura 02 | La entrada y su etiqueta no están distinguidas; el ejemplo puede confundirse con contenido existente. Falta asociación programática en futura implementación. |
| H-08 | WF01 anotación “Con carga, mostrar esqueleto”; [fuente](../semana-08/wireframes/01-inicio-expediente.svg), captura 01 | No especifica qué mensajes se anuncian al completar carga, validar selección o emitir. Puede dejar invisible el progreso a quien no ve la pantalla. |

Capturas: [01](evidencia/01-inicio-expediente.png), [02](evidencia/02-seleccionar-pruebas.png), [03](evidencia/03-confirmar-orden.png), [04](evidencia/04-exito-trazabilidad.png), [05](evidencia/05-seguimiento.png), [06](evidencia/06-error-recuperable.png). Provienen del render de fuentes sin modificaciones, no de una app interactiva.

## Backlog priorizado

P1: corregir antes de prueba de emisión con usuarios; P2: corregir antes de aceptación UX; P3: mejora posterior. Prioridad considera posibilidad de orden errónea/duplicada, recuperación y acceso equivalente. Responsable propuesto de todos: autora del módulo; revisor independiente por asignar. Estado inicial: abierto/propuesto; no se implementan cambios silenciosos en semana 8.

| Orden / ID | Prioridad / corrección propuesta | Criterio verificable de aceptación |
|---|---|---|
| 1 / H-04 | P1: separar vistas/mensajes para timeout, catálogo y sesión | Timeout ofrece comprobar sin reenviar; catálogo vuelve a selección; sesión exige reautenticación antes de recuperar contexto. Ninguna acción crea orden duplicada. |
| 2 / H-01 | P1: resumen completo y contador de partidas en confirmación | Con 0 no permite confirmar; con 1 y varias, total coincide con lista/selección y todas las partidas se revisan antes de emitir. |
| 3 / H-06 | P1: especificar y luego implementar recorrido de teclado/foco | Completar selección, volver y confirmar usando Tab/Shift+Tab/Espacio/Enter, sin trampa; foco visible y destino coherente en cada vista/error. Registrar evidencia de implementación. |
| 4 / H-08 | P1: definir anuncios por tipo de mensaje | Carga/fin/éxito sin cambio de contexto se anuncian mediante región de estado apropiada; error identifica grupo y foco. Verificar con lector de pantalla, sin anuncios repetidos ni interrupciones injustificadas. |
| 5 / H-02 | P2: botones diferenciados Editar selección y Cancelar orden | Editar conserva pruebas; cancelar no persiste y explica descarte si hay cambios; ambos operables por teclado sin crear orden. |
| 6 / H-07 | P2: etiqueta persistente y ayuda separada del valor de búsqueda | Campo vacío mantiene “Buscar prueba”, ejemplo es ayuda; nombre accesible incluye etiqueta visible y permite búsqueda con teclado. |
| 7 / H-03 | P2: explicar indisponibilidad y ruta de ayuda | Cada prueba inactiva tiene motivo autorizado o mensaje genérico seguro y ayuda; no permite seleccionarla ni sustituye por otra sin decisión profesional. |
| 8 / H-05 | P2: glosa para demo y mapeo de estados acordado para operación | Demo dice “Estado de demostración”; producción usa catálogo de estados aprobado y conserva código técnico en detalle autorizado. No afirmar transición clínica no acordada. |

## Retest propuesto

Aplicar P1 y P2 sobre una implementación seleccionada con commit exacto. Repetir escenario con varias pruebas, cambio de disponibilidad, timeout, sesión vencida y consulta. Probar teclado completo, zoom y 320 px, nombres accesibles y lector de pantalla. Volver a medir contraste incluyendo estados de foco/error/deshabilitado. Cerrar hallazgo solo con evidencia y revisión independiente; el presente informe entrega evaluación/backlog, no correcciones aplicadas.

## Referencias y evidencia

Fuentes oficiales consultadas el 28 de octubre de 2026:

- [W3C: teclado 2.1.1](https://www.w3.org/WAI/WCAG22/Understanding/keyboard.html): funcionalidad operable por teclado.
- [W3C: foco visible 2.4.7](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible): indicador visible del control enfocado.
- [W3C: contraste 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html): 4.5:1 para texto normal y 3:1 para texto grande, con excepciones del criterio.
- [W3C: etiquetas 3.3.2](https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html): instrucciones/etiquetas visibles para entradas.
- [W3C: mensajes 4.1.3](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html): cambios de estado identificables para tecnología asistida sin forzar foco.

La asociación de etiquetas se debe comprobar además en la implementación; una etiqueta visual no prueba semántica. Los resultados de contraste son cálculo de colores fuente, no de antialiasing ni de una UI final. Ejecutar node docs/modulos/ordenes-laboratorio-emr/semana-09/verificacion/inspeccionar.cjs para regenerar la inspección.