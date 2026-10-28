# Semana 9 — Evaluación UX y accesibilidad del prototipo NativePHP

**Estudiante:** Lopez Perez, Mercedes Azucena · **Fecha:** 28 de octubre de 2026.

## Alcance y método

Inspección de [semana 8](../semana-08/semana-08-ux-nativephp.md) y sus seis wireframes, base 2dc45d4. Integrar esa rama antes de esta entrega. Flujo acotado: evaluar destino, consultar una orden ficticia y notificar estado. Se consideran visibilidad del estado, consistencia, prevención/recuperación de errores, control del usuario y reconocimiento del contexto.

Se renderizan los SVG en Edge headless/Playwright para medir color calculado/contraste y cajas de texto, y guardar capturas sin modificar la fuente. No hubo pruebas con participantes. Los hallazgos de interacción son brechas de especificación pendientes de verificar en controles reales; no se afirma una aplicación NativePHP operable ni una auditoría WCAG completa.

## Checklist completado

| ID | Aspecto / referencia | Resultado observado |
|---|---|---|
| C01 | Alcance y fuente de verdad | Observado: consulta demo, no escritura clínica; HIS fuente de verdad. |
| C02 | Destino y evidencia de plataforma | Parcial: destino “escritorio demo” sin sistema/arquitectura/entorno definidos. H-01. |
| C03 | Viabilidad y resultado separados | Observado: WF04/06 dicen no demostrada aunque consulta simulada pase. |
| C04 | Confirmación/control del usuario | Parcial: volver se menciona, pero no hay control secundario dibujado frente a ejecutar. H-02. |
| C05 | Error de red frente a capacidad nativa | Parcial: WF05 mezcla caída y permiso denegado con una acción de reintentar consulta. H-03. |
| C06 | Frescura y sincronización | Observado: snapshot obsoleto con hora y sin notificación; lectura previa debe corresponder a orden/hospital. |
| C07 | Evidencia mínima reproducible | Parcial: reporte visual no contiene versiones/entorno/identificador de corrida suficientes. H-04. |
| C08 | Exportación segregada | Parcial: se describe exportar pero no se dibuja acción ni resultado/falla independiente. H-05. |
| C09 | Roles por operación | Parcial: explicación distingue roles pero falta variante visual de revisor y operador sin configuración. H-06. |
| C10 | Teclado — 2.1.1 | No verificable como interacción: seis SVG con cero controles. Falta secuencia/teclas por pantalla. H-07. |
| C11 | Foco visible — 2.4.7 | Brecha: regla escrita, sin estado dibujado ni destino por transición/error. H-07. |
| C12 | Etiquetas — 3.3.2 | Parcial: placeholder y etiqueta no distinguen requisitos de destino/versión pendiente. H-01. Asociación semántica requiere implementación. |
| C13 | Mensajes — 4.1.3 | Brecha de especificación: texto de carga/fallo existe, falta estrategia por estado de anuncio sin cambio de contexto. H-08. |
| C14 | Contraste y recorte | Medido: cabecera 11.56:1; título de anotación 10.87:1; cero textos fuera del lienzo. Solo estos pares, no todos los estados futuros. |
| C15 | Privacidad/movilidad | Privacidad diseñada con datos ficticios y notificación genérica; columna móvil propuesta, no reflow ejecutado del SVG 1100×700. |

No verificable significa pendiente, no aprobado. El checklist está completado con resultados, no con casillas que inventen conformidad.

## Ocho hallazgos con evidencia

| ID | Evidencia concreta | Hallazgo e impacto |
|---|---|---|
| H-01 | WF02 “Destino del experimento: [ escritorio demo ]” y distribución/versión pendiente; [fuente](../semana-08/wireframes/02-destino.svg), [captura](evidencia/02-destino.png) | Se permite documentar destino con nombre genérico sin campos de SO/arquitectura/entorno ni identificación de corrida. Dificulta atribuir evidencia a una plataforma concreta. No se debe obligar a inventar versión si todavía no se eligió. |
| H-02 | WF03 tiene acción Ejecutar demostración y texto “Volver conserva configuración sin ejecutar”; [captura](evidencia/03-confirmacion.png) | Falta acción secundaria visual/ubicación para cancelar o volver. Puede inducir ejecución involuntaria al buscar salida, especialmente en móvil. |
| H-03 | WF05 agrupa “HIS no disponible” y “Permiso denegado: revisar ajustes” con botón Reintentar consulta; [captura](evidencia/05-recuperacion.png) | Una acción única no corresponde a causas diferentes; reconsultar por permiso nativo puede duplicar efectos o confundir causa. |
| H-04 | WF06 enumera destino genérico y resultados, sin runtime/versión/configuración exacta o referencia de corrida; [captura](evidencia/06-evidencia.png) | La conclusión no permite reproducir evidencia del experimento aun cuando reconoce límites. Es brecha de trazabilidad de evaluación, no un fallo clínico demostrado. |
| H-05 | WF06 dice “Exportar solo evidencia técnica” pero su único botón es Volver a evaluaciones; [fuente](../semana-08/wireframes/06-evidencia.svg) | Exportación propuesta carece de interacción visible y estados propios; no se ve cómo recuperarse de fallo sin repetir consulta/notificación. |
| H-06 | WF01 Nueva evaluación, WF02 configuración y WF06 informe comparten cabecera Evaluadora DEMO; [capturas 01](evidencia/01-inicio.png) y [06](evidencia/06-evidencia.png) | Roles están descritos en documento, sin variantes que muestren acciones ocultas/bloqueadas para operador/revisor. Una regla escrita no demuestra prevención de modificación no autorizada. |
| H-07 | Todos los SVG role=img sin input/button/select/tabindex; [inspección](evidencia/inspeccion.json) | No existe especificación por vista del orden de teclado, foco de error y retorno desde confirmación. Brecha para futura implementación, no incumplimiento automático de un flujo inexistente. |
| H-08 | WF01 “Carga usa esqueleto y mensaje”; WF04 éxito; [captura 04](evidencia/04-resultado.png) | Falta matriz de anuncios para carga/fin/permiso/resultado desconocido/exportación y coordinación con cambio de vista. Riesgo de mensajes inaccesibles o duplicados al implementar. |

No se inventaron defectos de contraste para alcanzar el mínimo de hallazgos. Las observaciones H-01/H-04 están relacionadas pero distinguen captura de contexto y reporte de evidencia, con criterios de cierre propios.

## Backlog priorizado

P1 antes de evaluar el experimento con usuarios; P2 antes de aceptación UX. Responsable propuesto: autora del módulo; revisor independiente por asignar. Estado inicial de todos: abierto/propuesto. No se modifican silenciosamente los SVG de semana 8.

| Orden / ID | Prioridad / corrección propuesta | Criterio verificable |
|---|---|---|
| 1 / H-03 | P1: separar recuperación de HIS, capacidad y resultado desconocido | Caída HIS reconsulta sin notificar dato viejo; permiso denegado explica ajustes sin bucle de permiso ni reconsulta innecesaria; envío desconocido no se repite a ciegas. Probar cada causa con conteo de efectos. |
| 2 / H-01 | P1: campos de destino y evidencia pendiente explícitos | Capturar nombre del destino, SO/arquitectura/entorno cuando se conocen y referencia de corrida; ausente queda pendiente, nunca aprobado. No afirmar soporte por escribir nombre de plataforma. |
| 3 / H-04 | P1: reporte reproducible por corrida/destino | Informe conserva ID, versión/configuración o pendientes, fecha/zona, fuente y resultado de consulta/capacidad/evidencia por separado. Otra persona puede identificar exactamente qué se probó y qué no. |
| 4 / H-07 | P1: tabla por pantalla de foco/teclas y estado de foco visible | Navegar configuración→confirmación→resultado/cancelación solo con teclado, sin trampa y sin efecto al enfocar. Error enfoca campo; volver restaura contexto. Registrar retest en controles reales. |
| 5 / H-02 | P2: dibujar botones Volver/Cancelar separados de Ejecutar | Volver conserva configuración sin consultar ni notificar; cancelar descarta con aviso si procede; controles accesibles por teclado y sin ejecución accidental. |
| 6 / H-05 | P2: acción Exportar evidencia autorizada con estado independiente | Exportar existe solo con permiso; éxito/fallo específicos; fallo no borra consulta ni reenvía notificación. Conteos de consulta/notificación permanecen sin cambios al reintentar exportación. |
| 7 / H-06 | P2: variantes por rol y permiso | Revisor solo consulta/exporta si autorizado; operador no configura; evaluador configura. Sin permiso no muestra datos del hospital. Retest visual no reemplaza autorización servidor. |
| 8 / H-08 | P2: especificar mensaje/región/foco por estado | Estados sin cambio de contexto se anuncian apropiadamente; cambios de vista gestionan foco sin anuncio doble. Probar carga, éxito, permiso denegado, fallo y desconocido con lector de pantalla. |

## Evidencia y retest

Seis capturas del render original: [01](evidencia/01-inicio.png), [02](evidencia/02-destino.png), [03](evidencia/03-confirmacion.png), [04](evidencia/04-resultado.png), [05](evidencia/05-recuperacion.png), [06](evidencia/06-evidencia.png). JSON incluye color calculado, contraste, recorte y conteo de controles.

Después de cambios, seleccionar implementación/commit y retestear backlog con teclado, foco, anuncios, zoom y 320 px. Verificar permisos de cada acción, dato actual/obsoleto, recuperación y efectos de notificación/exportación. No cerrar hallazgos por existir un commit sin evidencia independiente. Runtime y permisos reales requieren destino NativePHP verificado, fuera de estas capturas.

Reproducir medición/capturas con Node, Playwright y Edge:
```powershell
$qa = Join-Path $env:TEMP 'shi-browser-qa'
npm install --prefix $qa playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE = Join-Path $qa 'node_modules/playwright'
node docs/modulos/investigacion-prototipo-nativephp/semana-09/verificacion/inspeccionar.cjs
```
BROWSER_PATH permite otro Chromium. Los colores medidos corresponden a dos pares concretos; cero recorte horizontal no garantiza que todas las anotaciones estén dentro de su panel ni conformidad de una app futura.

## Referencias oficiales

Guías WCAG consultadas en esta conversación para evaluaciones de semana 9:

- [W3C 2.1.1 teclado](https://www.w3.org/WAI/WCAG22/Understanding/keyboard.html): funcionalidad operable por teclado.
- [W3C 2.4.7 foco visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible): indicador de foco visible.
- [W3C 1.4.3 contraste](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html): mínimo 4.5:1 texto normal y 3:1 texto grande, con excepciones del criterio.
- [W3C 3.3.2 etiquetas](https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html): etiquetas/instrucciones para entradas; semántica requiere verificación adicional.
- [W3C 4.1.3 mensajes](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html): cambios de estado identificables para tecnología asistida sin recibir foco.

No se certifica WCAG ni se afirman pruebas con usuarios/lector de pantalla. La evaluación se limita a diseño y mediciones de imágenes, con backlog verificable para implementación.