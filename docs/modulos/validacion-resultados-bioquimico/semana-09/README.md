# Semana 9 — Evaluación UX y accesibilidad de validación bioquímica

**Estudiante:** Lopez Perez, Mercedes Azucena · **Fecha:** 28 de octubre de 2026.

## Alcance y método

Inspección de [semana 8](../semana-08/semana-08-ux-validacion-bioquimica.md), base a27c516: seis SVG del flujo de revisión, validación/rechazo y trazabilidad. Integrar esa base antes de esta entrega. Se aplican heurísticas de visibilidad de estado, consistencia, reconocimiento, prevención de errores y recuperación. No hubo participantes ni evaluación clínica.

Se renderiza con Edge headless/Playwright, se inspecciona color calculado y getBBox para texto fuera del lienzo de 1280×760 y se guardan seis capturas. Los controles son dibujados, no operables: teclado, foco dinámico y anuncios se registran como no verificables/brecha de especificación, no como aplicación existente automáticamente incumplidora. No se certifica WCAG.

## Checklist completado

| ID | Aspecto / referencia | Resultado |
|---|---|---|
| C01 | Contexto antes de decidir | Observado: WF02 muestra paciente mínimo, orden, examen, muestra, valor/unidad y versión. |
| C02 | Contraste 1.4.3 | Hallazgo: cabecera 1.18:1 y números 2.07:1; CSS impone texto oscuro. H-01. |
| C03 | Contexto de rol legible | Hallazgo: Perfil bioquímico excede lienzo en los seis SVG. H-02. |
| C04 | Prevención de decisión equivocada | Parcial: dos confirmaciones usan la misma etiqueta genérica Confirmar. H-03. |
| C05 | Firma/actor antes de confirmar | Parcial: perfil genérico antes de decisión, ID de actor solo en recibo. H-04. |
| C06 | Motivo de rechazo | Observado: obligatorio y mensaje específico dibujados; asociación semántica pendiente. |
| C07 | Estados de éxito simétricos | Parcial: recibo muestra solo validación, sin variante visual de rechazo/motivo. H-07. |
| C08 | Hora de trazabilidad | Parcial: “hora del servidor” no expresa zona u offset. H-05. |
| C09 | Recuperación de conflicto | Observado: no se registró decisión, recargar y revisar contexto. No prueba concurrencia real. |
| C10 | Respuesta desconocida/reintento | Parcial: WF06 mezcla revisar estado con reintento por indisponibilidad sin precondición inequívoca. H-06. |
| C11 | Teclado 2.1.1 | No verificable: cero controles HTML; falta secuencia por vista/diálogo. H-08. |
| C12 | Foco visible 2.4.7 | Brecha: regla genérica sin estado dibujado y manejo concreto de diálogo/retorno. H-08. |
| C13 | Etiquetas 3.3.2 | Etiqueta visible de motivo y búsqueda; nombre/relación programáticos pendientes en implementación. |
| C14 | Mensajes 4.1.3 | No verificable dinámicamente; falta especificación de región/urgencia por carga/éxito/error. H-08. |
| C15 | Privacidad/movilidad | Datos ficticios y mínimo necesario; no prueba permisos/tenant ni reflow a 320 px. Las mediciones son de boceto fijo. |

## Ocho hallazgos y evidencia

| ID | Evidencia concreta | Hallazgo / impacto |
|---|---|---|
| H-01 | CSS text{fill:#172b4d} prevalece sobre fill blanco. Cabecera sobre #153956: 1.18:1; números sobre #165d9c: 2.07:1. [Medición](evidencia/inspeccion.json), [captura 01](evidencia/01-bandeja-pendientes.png) | Texto difícil de leer, incluido contexto del hospital/rol. Causa CSS común, no dos defectos contados por la misma corrección. |
| H-02 | getBBox detecta Perfil bioquímico fuera de 1280 px en los seis SVG; [captura 02](evidencia/02-revision-contexto.png) | Rol cortado en cabecera dificulta reconocer identidad operativa antes de decisión. No es solamente adaptación móvil pendiente. |
| H-03 | WF03 y WF04 usan Confirmar sin verbo; [validación](evidencia/03-confirmar-validacion.png), [rechazo](evidencia/04-rechazo-motivo.png) | Misma etiqueta para decisiones distintas y finales, incrementa dependencia de memoria/contexto. Usabilidad preventiva, no criterio WCAG atribuido sin prueba. |
| H-04 | WF03 dice “Actor: perfil bioquímico”; ID BQ-028 aparece recién en WF05 | El firmante concreto no está claramente identificado al confirmar. Puede dificultar advertir sesión/perfil equivocados; no demuestra suplantación real. |
| H-05 | WF05 “25 oct 2026 · 10:42 (hora del servidor)”; [captura](evidencia/05-decision-registrada.png) | Sin zona/offset, auditoría puede interpretarse diferente en cliente o revisión posterior. Es problema de claridad trazable, no horario clínico inferido. |
| H-06 | WF06 “revisa el estado antes de reintentar” y “Servicio temporalmente no disponible… [Reintentar]”; [captura](evidencia/06-error-recuperable.png) | Recuperación no deja explícito cuándo se sabe que no hubo escritura y cuándo se debe comprobar. Riesgo de repetir una decisión con resultado desconocido. |
| H-07 | WF05 solo presenta “Validación guardada” y badge Validado; no hay variante dibujada de rechazo/motivo | Recibo no cubre visualmente ambos caminos principales. Puede ocultar motivo o rotular rechazo como validación si se reutiliza sin contrato claro. Es riesgo de diseño, no bug de app ejecutada. |
| H-08 | SVG role=img, sin controles ni tabindex; reglas mencionan foco/accesibilidad sin tabla por diálogo | Falta contrato de teclado, foco de error/cancelación y anuncios. Brecha para futura implementación; una imagen estática no permite certificar operabilidad. |

Fuentes editables originales en semana-08/wireframes. Capturas [01](evidencia/01-bandeja-pendientes.png), [02](evidencia/02-revision-contexto.png), [03](evidencia/03-confirmar-validacion.png), [04](evidencia/04-rechazo-motivo.png), [05](evidencia/05-decision-registrada.png), [06](evidencia/06-error-recuperable.png). Se evalúa sin modificar semana 8.

## Backlog priorizado

P1 antes de probar decisiones con usuarios; P2 antes de aceptación UX. Responsable propuesto: autora; revisor independiente por asignar. Todos abiertos/propuestos. Se prioriza prevenir decisión equivocada o repetida, contexto del firmante y accesibilidad del flujo.

| Orden / ID | Prioridad / corrección | Criterio verificable |
|---|---|---|
| 1 / H-06 | P1: separar falla confirmada y resultado desconocido | Timeout obliga a comprobar referencia; no reenvía automáticamente; confirmado no aplicado permite reintento seguro; final muestra recibo sin nueva firma. Probar conteo de decisiones y ausencia de éxito falso. |
| 2 / H-03 | P1: botones Confirmar validación / Confirmar rechazo | Texto visible y nombre accesible incluyen decisión; cancelar no escribe y regresa al contexto revisado. Verificar ambos caminos sin depender del color. |
| 3 / H-01 | P1: corregir clases de texto sobre fondos oscuros | Color calculado ≥4.5:1 para texto normal en cabecera/marcadores, comprobado en seis fuentes; no basta cambiar atributo que otra regla sobrescribe. |
| 4 / H-04 | P1: mostrar firmante concreto autorizado antes de decisión | Confirmación identifica usuario/ID, rol, hospital y versión; coincide con recibo persistido. Nunca permitir editar firmante libremente en cliente. |
| 5 / H-08 | P1: especificar/implementar teclado, foco y anuncios | Recorrer revisión→diálogo→confirmar/cancelar con teclado sin trampa; foco visible, error enfoca motivo y cancelación restaura origen; mensajes de estado anunciados apropiadamente. Retest real con lector de pantalla. |
| 6 / H-07 | P2: variante de recibo de rechazo | Rechazo muestra decisión, motivo y actor/hospital/versión/hora/referencia; validación no muestra motivo ficticio. Final no permite editar/firmar otra vez. |
| 7 / H-02 | P2: cabecera flexible y ajuste de texto | Perfil bioquímico y contexto completos dentro del panel/lienzo; al implementar, probar IDs/nombres largos, 320 px y zoom sin recorte ni ocultamiento de rol. |
| 8 / H-05 | P2: fecha con zona/offset y formato definido | Recibo incluye instante y zona inequívocos; escritorio/móvil representan el mismo instante y preservan hora de servidor confirmada sin inventarla. |

## Retest y reproducción

Tras corrección, seleccionar implementación/commit y repetir medición CSS/getBBox, validar y rechazar, error de motivo, cancelación, timeout y cambio de versión. Probar teclado, lector de pantalla, zoom y 320 px en controles operables. Cierre por evidencia y revisión independiente, no por tener un commit. Sin firma criptográfica ni backend/tenant clínico probado por estos SVG.

Reproducir con Node, Playwright y Edge:
```powershell
$qa = Join-Path $env:TEMP 'shi-browser-qa'
npm install --prefix $qa playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE = Join-Path $qa 'node_modules/playwright'
node docs/modulos/validacion-resultados-bioquimico/semana-09/verificacion/inspeccionar.cjs
```
BROWSER_PATH permite otro Chromium. El script mide dos pares de colores y desbordamiento del lienzo, no todos los estados ni confines internos de paneles.

## Referencias oficiales

Guías W3C consultadas en esta conversación para semana 9:

- [1.4.3 contraste](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html): 4.5:1 para texto normal, 3:1 grande y excepciones del criterio.
- [2.1.1 teclado](https://www.w3.org/WAI/WCAG22/Understanding/keyboard.html): funcionalidad operable por teclado.
- [2.4.7 foco visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible): indicador visible al operar por teclado.
- [3.3.2 etiquetas](https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html): etiquetas/instrucciones para entrada; semántica requiere comprobación adicional.
- [4.1.3 mensajes](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html): estado identificable por tecnología asistida sin recibir foco.

Las referencias orientan evaluación; no constituyen certificación WCAG ni pruebas con usuarios/tecnologías asistidas realizadas.