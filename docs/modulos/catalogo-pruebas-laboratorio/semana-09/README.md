# Catálogo de pruebas de laboratorio — Semana 9

**Tema:** evaluación del diseño, usabilidad y accesibilidad
**Tarea:** evaluar el flujo UX con heurísticas y WCAG (teclado, foco, contraste, etiquetas, mensajes y prevención de errores) y priorizar correcciones

- Checklist completo: [checklist.md](checklist.md) (10 heurísticas y 14 criterios WCAG).
- Evidencia: archivo y línea del Micro-HIS, o wireframe de la semana 8. Capturas de apoyo en [`evidencia/`](evidencia/) (ver sección 3).

## 1. Hallazgos

| ID | Hallazgo | Criterio | Evidencia | Impacto en el catálogo | Severidad |
|---|---|---|---|---|---|
| H-01 | Sección, tipo de muestra y conservación se escriben a mano aunque solo admiten valores de una lista | Nielsen 5 y 6; WCAG 3.3.2 | `formulario.php` línea 16: todos los campos son `<input>` | Errores 422 evitables; riesgo de registrar un tipo de muestra mal escrito y rechazado | Alta |
| H-02 | Tras un 422 no hay resumen de errores ni el foco va al primer campo con error | WCAG 2.4.3 y 3.3.1 | `formulario.php` líneas 17–18 | Con 13 campos, un lector de pantalla no se entera de qué falló | Alta |
| H-03 | Se guarda sin confirmación aunque la nueva versión cierre la anterior | Nielsen 3; WCAG 3.3.4 | `LabTestController::guardar` guarda directo; W-04 existe solo en el diseño | Se puede cerrar por error la vigencia de una prueba en uso | Alta |
| H-04 | Tabla de resultados sin `<caption>` ni `scope="col"` | WCAG 1.3.1 | `lista.php` líneas 11–12 | El lector de pantalla no asocia cada celda con «Muestra» o «Vigencia» | Media |
| H-05 | La fecha del alta es texto «AAAA-MM-DD» y la de la consulta es selector de fecha | Nielsen 4; WCAG 3.3.2 | `formulario.php` línea 7 frente a `lista.php` línea 4 | Errores de formato en la vigencia (RN-04) | Media |
| H-06 | Sin enlace «Saltar al contenido» y el mismo título en todas las páginas | WCAG 2.4.1 y 2.4.2 | `layout.php` líneas 6 y 14 | Navegación más lenta con teclado y lector de pantalla | Baja |
| H-07 | El botón de guardar no indica que está guardando ni evita un segundo clic | Nielsen 1 | `formulario.php` línea 20 | Un doble clic produce un 409 confuso en el segundo envío | Media |
| H-08 | La página «no vigente» solo ofrece volver al catálogo | Nielsen 9 | `mensaje.php` línea 3 | El laboratorista no llega a la versión que necesita | Baja |
| H-09 | Sin ayuda contextual en estabilidad y vigencia | Nielsen 10 | Vistas del Micro-HIS frente a W-03 | Dudas al registrar estabilidad o fechas | Baja |

## 2. Backlog priorizado

| Prioridad | ID | Corrección propuesta | Criterio verificable |
|---|---|---|---|
| 1 | H-03 | Paso de confirmación (W-04) con el aviso de cierre de la versión anterior | Al guardar una versión nueva de `GLU-001` aparece «La versión 1 quedará vigente hasta el 2027-01-31» y nada se guarda hasta pulsar «Confirmar» |
| 2 | H-01 | `<select>` con las listas de `LabTest::SECCIONES`, `RequisitoMuestra::TIPOS` y `RequisitoMuestra::CONSERVACION` | Los tres campos son listas; no es posible enviar un valor fuera de ellas desde la interfaz |
| 3 | H-02 | Resumen de errores con `role="alert"` al inicio del formulario, con enlaces a cada campo, y foco en el resumen | Tras enviar con volumen 0, el foco está en el resumen y NVDA lee «Hay 1 campo con error» |
| 4 | H-07 | Deshabilitar el botón y mostrar «Guardando…» mientras se envía | Un doble clic produce una sola petición POST (pestaña Red de DevTools) |
| 5 | H-04 | `<caption>` «Pruebas vigentes al <fecha>» y `scope="col"` en cada `<th>` | El validador de accesibilidad de DevTools no reporta la tabla; NVDA anuncia el encabezado de columna |
| 6 | H-05 | `type="date"` en vigente desde y vigente hasta | Las dos fechas del alta usan selector de fecha, igual que la consulta |
| 7 | H-08 | Si hay otra versión, enlace «Ver la versión vigente desde …» y campo para cambiar la fecha | Desde el 404 de `HEM-001` del 2027-01-03 se llega en un clic a la versión vigente desde el 2027-01-04 |
| 8 | H-06 | Enlace «Saltar al contenido» y título por vista («Nueva prueba — Catálogo…») | El primer Tab muestra el enlace; cada pestaña del navegador tiene un título distinto |
| 9 | H-09 | Texto de ayuda breve con `aria-describedby` en estabilidad y vigencia | Al enfocar el campo, NVDA lee la ayuda |

## 3. Evidencia a capturar

Las capturas se toman con el Micro-HIS corriendo (`php -S 127.0.0.1:8081 -t public`) y se guardan en `evidencia/`:

| Archivo | Qué mostrar | Hallazgo |
|---|---|---|
| `h01-campos-texto.png` | Formulario de alta con «Sección» como campo de texto | H-01 |
| `h02-errores-422.png` | Formulario tras enviar con volumen 0: mensajes por campo, sin resumen | H-02 |
| `h04-tabla.png` | DevTools → Elements mostrando la `<table>` sin `caption` | H-04 |
| `h05-fechas.png` | Campo «Vigente desde» como texto junto al selector de fecha de la consulta | H-05 |

Los puntos marcados **PV** en el checklist (teclado, foco visible y reflujo a 320 px) se completan al revisarlos en Chrome; el resultado se anota en el checklist.
