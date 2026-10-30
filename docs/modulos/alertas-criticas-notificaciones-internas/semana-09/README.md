# Semana 9 — Evaluación del diseño, usabilidad y accesibilidad

**Tarea:** evaluar el flujo UX con heurísticas de usabilidad y WCAG (teclado, foco, contraste, etiquetas, mensajes y prevención de errores) y priorizar correcciones según su impacto en alertas críticas, destinatarios, prioridad, entrega, acuse, escalamiento y reintentos.
**Objeto evaluado:** la interfaz MVC real de la semana 4 (`micro-his/plantillas/`) y los wireframes de la semana 8.
**Método:**
- Revisión del código de las plantillas.
- Recorrido con teclado automatizado en Chromium (Playwright) contra el servidor PHP y PostgreSQL 16.
- Contraste calculado con la fórmula de luminancia relativa de WCAG 2.2.

## 1. Evidencia medida

**Contraste** (mínimo AA: 4.5:1 para texto normal y 3:1 para componentes):

| Par de colores (plantilla `layout.php`) | Contraste | Resultado |
|---|---|---|
| Texto `#1a1a1a` sobre fondo `#f6f7f9` | 16.24:1 | Pasa AA y AAA |
| Cabecera: blanco sobre `#12355b` | 12.46:1 | Pasa |
| Prioridad CRITICA `#b42318` sobre blanco | 6.57:1 | Pasa (y no depende solo del color: lleva el texto «CRITICA») |
| Texto de aviso sobre `#e6f4ea` / de error sobre `#fdecea` | 15.33:1 / 15.22:1 | Pasa |
| Enlace por omisión `#0000ee` sobre `#f6f7f9` | 8.77:1 | Pasa |
| Barra lateral del aviso `#1e7e34` sobre `#e6f4ea` | 4.52:1 | Pasa (justo) |
| Borde de tabla `#d0d5dd` sobre blanco | 1.47:1 | Decorativo; no es requisito 1.4.11 porque la tabla se entiende sin el borde |

**Teclado** (detalle de una alerta, 7 pulsaciones de Tab): enlace de inicio → selector de usuario → «Cambiar» → «Acusar como enf.turno01» → «Volver a la lista» → vuelve al inicio. El orden es lógico y todos los controles son nativos y operables con teclado. El foco usa el indicador del navegador (no se quitó `outline`). **No existe enlace para saltar al contenido.**

**Prevención:** con la nota de cierre vacía, el atributo `required` bloquea el envío en el navegador. Una nota de solo espacios sí llega al servidor y se rechaza con 422 (RN-09).

## 2. Checklist

| # | Criterio | Fuente | Resultado |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | Nielsen 1 | Parcial (H-03) |
| 2 | Lenguaje del usuario | Nielsen 2 | Cumple: «Acusar», «Cerrar», estados en español |
| 3 | Control y libertad | Nielsen 3 | Parcial (H-04) |
| 4 | Consistencia | Nielsen 4 | Parcial (H-05) |
| 5 | Prevención de errores | Nielsen 5 | Parcial (H-04, H-06) |
| 6 | Reconocer antes que recordar | Nielsen 6 | Parcial (H-07) |
| 7 | Ayuda para recuperarse de errores | Nielsen 9 | Parcial (H-02) |
| 8 | 1.3.1 Información y relaciones | WCAG A | Parcial (H-08) |
| 9 | 1.4.1 Uso del color | WCAG A | Cumple (prioridad con texto) |
| 10 | 1.4.3 Contraste mínimo | WCAG AA | Cumple (tabla de arriba) |
| 11 | 2.1.1 Teclado | WCAG A | Cumple (recorrido medido) |
| 12 | 2.4.1 Evitar bloques | WCAG A | **No cumple** (H-01) |
| 13 | 2.4.7 Foco visible | WCAG AA | Cumple (indicador del navegador) |
| 14 | 3.3.1 Identificación de errores | WCAG A | Parcial (H-02) |
| 15 | 3.3.2 Etiquetas o instrucciones | WCAG A | Parcial (H-07) |
| 16 | 4.1.3 Mensajes de estado | WCAG AA | Cumple (`role="status"` y `role="alert"`) |

## 3. Hallazgos (8)

| ID | Hallazgo | Evidencia | Impacto en el flujo |
|---|---|---|---|
| H-01 | No hay enlace «Saltar al contenido»; con teclado hay que pasar por el selector de usuario en cada página | Recorrido de Tab; `layout.php` | Acuse más lento con teclado |
| H-02 | Un error de cierre (422) abre una página aparte y **se pierde la nota escrita**; el foco no va al campo | `AlertaController::atrapar` → `error.php` | Cierre: el médico debe reescribir |
| H-03 | El detalle no muestra cuánto falta para escalar ni a quién | `detalle.php` (sin «vence en») | Acuse y escalamiento: no se percibe urgencia |
| H-04 | Cerrar no pide confirmación en el MVC (el wireframe W3 sí la tiene) | `detalle.php` | Cierre irreversible por error |
| H-05 | En la web, enfermería ve y puede usar el formulario de cierre; la API sí lo prohíbe (RF-08) | Captura `semana-04/capturas/04-detalle-acusada.png` | Inconsistencia de permisos entre canales |
| H-06 | Una nota de solo espacios pasa la validación del navegador y vuelve con error del servidor | Prueba manual descrita arriba | Error evitable |
| H-07 | El formato `PAC-00000` solo aparece como *placeholder*, que desaparece al escribir | `lista.php` | Registro manual: el usuario no recuerda el formato |
| H-08 | La tabla de historial del detalle no tiene `<caption>` (la de la lista sí) | `detalle.php` | Lectores de pantalla: contexto incompleto |

## 4. Backlog priorizado

Prioridad = impacto en la seguridad del paciente y en el flujo × frecuencia.

| # | Hallazgo | Prioridad | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|
| 1 | H-05 | Alta | Mostrar el formulario de cierre solo a médico o jefe y validar el rol en el controlador, igual que `Permisos` | Prueba: enfermería → 403 en `POST /alertas/{id}/cierre` web; el formulario no aparece en el HTML |
| 2 | H-03 | Alta | Mostrar «Vence en mm:ss → escala a <rol>» con el plazo de `PoliticaEscalamiento` | El detalle de una alerta ENVIADA contiene «Vence en» y el siguiente nivel |
| 3 | H-02 | Alta | Ante 422, volver a pintar el detalle con la nota y el mensaje junto al campo; foco al campo | Prueba: cierre con espacios → 422 con la nota escrita en el `textarea` |
| 4 | H-04 | Media | Paso de confirmación antes de cerrar | El cierre requiere un segundo envío con `confirmar=1` |
| 5 | H-01 | Media | Enlace «Saltar al contenido» como primer elemento y `<main id="contenido">` | El primer Tab enfoca el enlace; Enter lleva al `main` |
| 6 | H-06 | Media | `pattern="\S.*"` o validación al escribir, además de RN-09 en el servidor | Una nota de espacios no se envía |
| 7 | H-07 | Baja | Texto de ayuda visible «Formato PAC-00000» con `aria-describedby` | El texto existe y está referenciado por el campo |
| 8 | H-08 | Baja | `<caption>Historial de la alerta</caption>` | El HTML del detalle contiene el caption |

**Seguimiento:** H-05 se registra también como defecto **D-03** en la semana 15. El prototipo de la semana 11 ya incorpora H-01, H-03, H-04, H-07 y H-08.
