# Semana 9 — Evaluación UX y accesibilidad de ingreso de resultados

**Estudiante:** Lopez Perez, Mercedes Azucena · **Fecha:** 28 de octubre de 2026.

## Alcance y método

Evaluación de los seis wireframes y reglas de [semana 8](../semana-08/semana-08-ux-ingreso-resultados.md), base 49749e9. La rama depende de esa entrega. Se inspeccionan captura/corrección de pendientes, valor, unidad, rango, errores y trazabilidad con heurísticas de visibilidad, prevención de errores, consistencia, control del usuario y reconocimiento del contexto.

Se renderizan los SVG en Edge headless con Playwright, se lee el color calculado por CSS y se compara cada caja de texto con el lienzo. Evidencia en inspeccion.json y seis PNG. No son pruebas con usuarios. Los SVG son imágenes estáticas sin controles: teclado/foco/avisos de una aplicación no pueden aprobarse ni reprobarse como si hubiera un formulario implementado. Sí pueden detectarse defectos visuales y brechas de especificación.

## Checklist completado

| ID | Aspecto / referencia WCAG | Resultado observado |
|---|---|---|
| C01 | Visibilidad de estado y contexto | Parcial: orden/ítem aparecen; la cabecera hospital/rol tiene contraste bajo y textos recortados. H-01/H-03. |
| C02 | Contraste texto — 1.4.3 | Hallazgo: cabecera 1.18:1, debajo de 3:1 incluso para texto grande. H-01. |
| C03 | Legibilidad de anotaciones | Hallazgo: números 2.07:1 y anotaciones fuera del lienzo. H-02/H-03. |
| C04 | Etiquetas — 3.3.2 | Etiquetas persistentes dibujadas; asociación programática no verificable. |
| C05 | Consistencia unidad/catálogo | Parcial: WF02 dice unidad de catálogo pero dibuja flecha de selector. H-04. |
| C06 | Valor y rango durante corrección | Parcial: nuevo valor en WF04 no muestra unidad/rango junto al campo. H-05. |
| C07 | Motivo obligatorio | Observado en texto/anotaciones; error específico no dibujado. Ver H-07. |
| C08 | Confirmación antes de guardar | Parcial: WF03 muestra solo captura, no anterior/nuevo/motivo de corrección. H-06. |
| C09 | Teclado — 2.1.1 | No verificable como flujo: cero controles operables en SVG; especificar orden y teclas. H-08. |
| C10 | Foco visible — 2.4.7 | Brecha de especificación: frase genérica sin ejemplo de foco/destino por error. H-08. |
| C11 | Mensajes — 4.1.3 | No verificable dinámicamente; falta contrato concreto de anuncios sin cambio de vista. H-08. |
| C12 | Recuperación/prevenir duplicado | Parcial: WF06 agrupa errores y ofrece Reintentar sin distinguir timeout de falla confirmada. H-07. |
| C13 | Estado pendiente y validado inmutable | Observado: captura/corrección quedan pendientes; validado bloqueado en mensajes. No prueba backend. |
| C14 | Privacidad y trazabilidad | Datos ficticios/versión presentes; legibilidad de hospital/rol afectada por H-01/H-03. Autorización real no verificada. |
| C15 | Movilidad/zoom | No verificable: 1280×760 fijo no demuestra reflow a 320 px; texto ya excede lienzo nativo. H-03. |

## Ocho hallazgos con evidencia

Los contrastes se calculan con color renderizado, no con el atributo fill aislado. La regla CSS text{fill:#172b4d} prevalece sobre fill="#fff" de los textos. La conclusión es sobre estos bocetos, no una certificación de la aplicación final.

| ID | Evidencia | Hallazgo / impacto |
|---|---|---|
| H-01 | Todos los SVG, texto HIS · Laboratorio: rgb(23,43,77) sobre #153956; 1.18:1 en inspeccion.json; [captura 01](evidencia/01-bandeja-pendientes.png) | Cabecera ilegible por cascada CSS. Dificulta reconocer hospital/rol y contexto antes de capturar. |
| H-02 | Números de anotaciones rgb(23,43,77) sobre #165d9c; 2.07:1; [captura 02](evidencia/02-captura-resultado.png) | Marcadores de 13 px con contraste insuficiente para leer las reglas de diseño. Mismo origen CSS, superficie y retest distintos de cabecera. |
| H-03 | getBBox detecta 4,3,4,5,4,4 textos fuera de 1280 px en WF01..06; lista exacta en inspeccion.json | Anotaciones y cabecera se recortan; se pierde contenido de validación y privacidad. Es defecto observable del documento, no solo adaptación móvil pendiente. |
| H-04 | WF02 “Unidad * [ mg/dL ▾ ]” frente a anotación de catálogo; [fuente](../semana-08/wireframes/02-captura-resultado.svg) | Flecha sugiere edición libre pese a regla de unidad de catálogo fija. Puede inducir valor/rango con unidad incompatible. |
| H-05 | WF04 “Nuevo valor * [96]”, historial “94 mg/dL”, sin unidad/rango junto al campo; [captura 04](evidencia/04-correccion-controlada.png) | Obliga a recordar unidad y rango desde otra zona/vista. Aumenta carga cognitiva y error al corregir. |
| H-06 | WF03 solo “Resultado 94 mg/dL”, “Quedará PENDING”; [captura 03](evidencia/03-confirmar-captura.png) | No se dibuja confirmación de corrección con anterior/nuevo, versión esperada y motivo. El flujo escrito la menciona pero no permite revisar visualmente esa decisión. |
| H-07 | WF06 “Error de guardado: conserva lo escrito. [Reintentar]” y “409”, “503”, “VALIDATED” en una vista; [captura 06](evidencia/06-errores-recuperables.png) | Mensajes técnicos y reintento ambiguo no distinguen respuesta perdida de fallo confirmado. Riesgo de duplicar operación o intentar sobre validado. |
| H-08 | Todos los SVG role=img y cero input/button/select/textarea/tabindex; texto genérico “Foco visible” y “validación en línea” | No hay especificación por pantalla para recorrido, foco de error y anuncios. Brecha de diseño/implementación a probar, no una app real declarada incumplidora por ser estática. |

[Resultado de inspección](evidencia/inspeccion.json). Fuentes originales en semana-08/wireframes. Capturas [01](evidencia/01-bandeja-pendientes.png), [02](evidencia/02-captura-resultado.png), [03](evidencia/03-confirmar-captura.png), [04](evidencia/04-correccion-controlada.png), [05](evidencia/05-resultado-registrado.png), [06](evidencia/06-errores-recuperables.png). No se modifican esas fuentes en esta evaluación.

## Backlog priorizado

P1 antes de prueba de captura/corrección con usuarios; P2 antes de aceptación UX. Responsable propuesto de todos: autora del módulo, revisión independiente por asignar. Estado: abierto/propuesto, no correcciones aplicadas. Se prioriza contexto, integridad de valor/unidad y evitar éxito o reintento ambiguos.

| Orden / ID | Prioridad / corrección propuesta | Criterio verificable |
|---|---|---|
| 1 / H-01 | P1: clase CSS explícita para textos de cabecera | Color calculado blanco o equivalente con ≥4.5:1 para texto normal; verificar todos los textos de hospital/rol en seis SVG, no solo atributo fill. |
| 2 / H-07 | P1: vistas/acciones específicas por conflicto, validado, falla confirmada y respuesta desconocida | Conflicto recarga/revisa; validado solo lectura; falla confirmada conserva borrador; timeout comprueba operación antes de reenviar. Cero versión creada por error o duplicado por reintento. |
| 3 / H-04 | P1: unidad fija de catálogo, sin affordance de edición | Campo no editable muestra unidad junto al valor; no permite combinación distinta; si selección autorizada es necesaria, definir regla y actualizar rango a la misma unidad. |
| 4 / H-05 | P1: mostrar unidad y rango informativo junto al nuevo valor | Corrección muestra anterior/nuevo con unidad, rango y estado pendiente sin cambiar de vista; fuera de rango no valida automáticamente. |
| 5 / H-06 | P1: agregar variante de confirmación de corrección | Mostrar v anterior, v nueva enlazada, valor anterior/nuevo, unidad y motivo; cancelar no guarda ni pierde borrador; confirmar no duplica. |
| 6 / H-03 | P2: envolver textos/ancho de panel y cabecera | Cero cajas de texto fuera de lienzo/panel en las seis fuentes; captura completa sin recorte. Al implementar, probar 320 px y zoom sin ocultar unidad/estado. |
| 7 / H-08 | P2: especificar e implementar teclado/foco/avisos | Recorrer captura/corrección solo con teclado sin trampa; foco visible, error enfoca campo; avisos de carga/éxito anunciados apropiadamente sin forzar foco cuando no hay cambio de contexto. Retest con lector de pantalla. |
| 8 / H-02 | P2: color específico de números sobre marcador | Contraste calculado ≥4.5:1 para texto de 13 px; números y anotaciones legibles en los seis SVG. |

H-01/H-02 comparten causa CSS y pueden resolverse en un mismo cambio; se mantienen separados por evidencia de superficie y criterio de retest. No inflar avance contando esa corrección dos veces.

## Retest y reproducción

Seleccionar implementación/commit después de aplicar backlog, repetir medición de color calculado y textos, y ejecutar casos de múltiples estados de error. Revisar usuario sin permiso y resultado validado, confirmación anterior/nuevo y conservación de borrador. Probar teclado, foco, lector de pantalla, zoom y 320 px sobre controles reales; no usar la ausencia de controles en una imagen como un test interactivo fallido.

Para regenerar inspección/capturas (Node, Playwright y Edge):
```powershell
$qa = Join-Path $env:TEMP 'shi-browser-qa'
npm install --prefix $qa playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE = Join-Path $qa 'node_modules/playwright'
node docs/modulos/ingreso-resultados-laboratorio/semana-09/verificacion/inspeccionar.cjs
```
BROWSER_PATH permite otro Chromium. La inspección de fuente/medición es reproducible; no constituye auditoría WCAG completa ni resultados de usuarios.

## Referencias oficiales

Consultadas el 28 de octubre de 2026:

- [W3C 1.4.3 contraste](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html): texto normal 4.5:1 y texto grande 3:1, con excepciones del criterio.
- [W3C 2.1.1 teclado](https://www.w3.org/WAI/WCAG22/Understanding/keyboard.html): funcionalidad operable mediante teclado.
- [W3C 2.4.7 foco visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible): indicador visible al operar por teclado.
- [W3C 3.3.2 etiquetas](https://www.w3.org/WAI/WCAG22/Understanding/labels-or-instructions.html): etiquetas o instrucciones para entradas; asociación semántica requiere comprobación adicional.
- [W3C 4.1.3 mensajes](https://www.w3.org/WAI/WCAG22/Understanding/status-messages.html): mensajes de estado identificables para tecnología asistida cuando no reciben foco.