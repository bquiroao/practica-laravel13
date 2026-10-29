# Semana 9 — Evaluación del diseño, usabilidad y accesibilidad

Módulo: **Admisión hospitalaria y asignación de cama** · Flujo: **admisión de paciente y asignación de una cama disponible**

**Qué se evaluó:** el flujo UX de la semana 8 (W-01 a W-06) y la **web real del Micro-HIS** (`micro-his/public/index.php`), con heurísticas de Nielsen y WCAG 2.1 nivel AA (RNF-07).

**Cómo se evaluó:**
1. Revisión experta de cada pantalla contra el checklist de la sección 1.
2. Auditoría automática en Chromium (Playwright) del flujo real: tabulación, foco, etiquetas, errores y conflicto. Script: `evidencia/auditoria-navegador.py`; salida obtenida en mi entorno: `evidencia/auditoria-navegador.txt`; capturas: `evidencia/h-*.png`.
3. Contraste **calculado** con la fórmula de luminancia relativa de WCAG: `php contraste.php` (salida en `evidencia/salida-contraste.txt`). La fórmula se validó con el par de referencia `#767676` sobre blanco, que da 4.54:1.

## 1. Checklist completado

| # | Criterio | Fuente | Resultado | Evidencia |
|---|---|---|---|---|
| 1 | Todo se opera con teclado (2.1.1) | WCAG | Cumple | Tabulación: usuario > paciente > servicio > tipo > aislamiento > origen > botón |
| 2 | Orden de foco lógico (2.4.3) | WCAG | Cumple en el formulario; **no cumple tras un error** | H-03 |
| 3 | Foco visible (2.4.7) y contraste del foco (1.4.11) | WCAG | Cumple: contorno sólido `#b45309`, 4.68:1 sobre el fondo | `salida-contraste.txt` |
| 4 | Contraste de texto ≥ 4.5:1 (1.4.3) | WCAG | Cumple: los 7 pares de texto evaluados dan entre 6.02:1 y 14.76:1 | `salida-contraste.txt` |
| 5 | Etiquetas en todos los controles (3.3.2, 1.3.1) | WCAG | Cumple: 0 controles sin etiqueta | `auditoria-navegador.txt` |
| 6 | Error identificado y asociado al campo (3.3.1) | WCAG | **No cumple** | H-02 |
| 7 | Sugerencia para corregir (3.3.3) | WCAG | Cumple: el mensaje da el formato `PAC-NNNNN` | `h-error-formato.png` |
| 8 | Prevención de errores en datos importantes (3.3.4) | WCAG | **No cumple** | H-01, H-04 |
| 9 | Mensajes de estado anunciados (4.1.3) | WCAG | Parcial: hay `role="alert"`/`status`, pero sin mover el foco | H-03 |
| 10 | Idioma y título de página (3.1.1, 2.4.2) | WCAG | Cumple: `lang="es"` y un título por paso | `auditoria-navegador.txt` |
| 11 | Visibilidad del estado del sistema | Nielsen 1 | Parcial: no hay estado de carga en el servidor HTML | H-05 |
| 12 | Lenguaje del usuario | Nielsen 2 | **No cumple**: códigos técnicos en pantalla | H-06, H-07 |
| 13 | Prevención de errores | Nielsen 5 | **No cumple** | H-01, H-04, H-05 |
| 14 | Ayudar a reconocer y recuperarse de errores | Nielsen 9 | Parcial: hay lista actualizada, pero no nombra la cama perdida | H-06 |
| 15 | Protección de datos en pantalla | RNF-03 | Cumple: solo código de paciente y salida escapada; observación en H-08 | `prueba-mvc-controlador.php` |

## 2. Hallazgos (H-01 a H-08)

| ID | Hallazgo | Criterio | Impacto en el flujo | Severidad | Evidencia |
|---|---|---|---|---|---|
| H-01 | Un código inválido (`PAC-451`) llega hasta la lista de camas; recién se rechaza al confirmar | 3.3.4, Nielsen 5 | **Pacientes / admisiones:** el usuario elige cama para un paciente inválido y pierde el paso | Alta | `h-codigo-invalido-llega-a-camas.png`; auditoría: «radios de cama: 2» |
| H-02 | El error de formato aparece arriba, pero el campo no tiene `aria-invalid` ni `aria-describedby` hacia el mensaje | 3.3.1, 1.3.1 | Un lector de pantalla no relaciona el error con el campo | Alta | Auditoría: «input aria-invalid: None | aria-describedby: ayuda-paciente» |
| H-03 | Tras un error o un conflicto el foco queda en `BODY` | 2.4.3, 4.1.3 | Quien usa teclado debe recorrer la página para encontrar el aviso de la cama perdida | Media | Auditoría: «foco activo tras conflicto: BODY» |
| H-04 | No hay confirmación (W-03): un clic en «Confirmar admisión y asignar cama» registra directamente | 3.3.4, Nielsen 5 | **Asignación:** una cama equivocada queda asignada y no hay anulación en el módulo | Alta | Auditoría: «tras un clic -> titulo: Admisión registrada» |
| H-05 | El botón no se deshabilita al enviar; un doble clic manda dos POST | Nielsen 1 y 5 | **Concurrencia:** el segundo envío responde «ya tiene una admisión activa» justo después del éxito y confunde. RN-02 evita el duplicado en el servidor | Media | Auditoría: «boton deshabilitado al enviar? None» |
| H-06 | El aviso de conflicto no dice qué cama se perdió y muestra el código técnico `(CAMA_YA_ASIGNADA)` | Nielsen 9 y 2 | **Camas / concurrencia:** el usuario no sabe qué cambió | Media | `h-conflicto-cama.png` |
| H-07 | Se muestran valores técnicos: `MEDICINA_INTERNA`, `GENERAL`, `CONSULTA_EXTERNA` | Nielsen 2 | Lectura más lenta y riesgo de elegir mal el servicio | Baja | `h-inicio-w01.png`, `h-conflicto-cama.png` |
| H-08 | El selector «Usuario ficticio» vive en el mismo formulario | RNF-02 (observación) | Si se copiara al HIS, el rol y el hospital se podrían cambiar desde la pantalla. En el Micro-HIS es solo demostración | Baja | `h-inicio-w01.png` |

## 3. Backlog priorizado

La prioridad combina severidad e impacto en admisiones, pacientes, camas, asignación, concurrencia y trazabilidad clínica.

| Prio | Backlog | Hallazgo | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|
| 1 | B-01 | H-04 | Agregar la pantalla de confirmación W-03 entre la lista y el registro | Playwright: elegir cama → aparece «Confirmar admisión» con el resumen; sin confirmar, no existe fila en `admision` |
| 2 | B-02 | H-01 | Validar el formato del código antes de listar camas (en `GET /camas` y en el cliente al salir del campo) | `GET /camas?paciente=PAC-451…` responde 422 con el formulario, y la lista no aparece |
| 3 | B-03 | H-02 | `aria-invalid="true"` y `aria-describedby="error-paciente ayuda-paciente"` en el campo con error; mensaje junto al campo | Auditoría: `aria-invalid` = `true` y `aria-describedby` contiene el id del mensaje |
| 4 | B-04 | H-03 | Tras un error, mover el foco al aviso (`tabindex="-1"` + foco) o al campo con error | Auditoría: `document.activeElement` es el aviso o el campo, no `BODY` |
| 5 | B-05 | H-06 | Mensaje con la cama: «La cama B-202 ya fue asignada…»; el código técnico solo en un atributo `data-` | Prueba del controlador: el cuerpo contiene «B-202 ya fue asignada» y no «(CAMA_YA_ASIGNADA)» visible |
| 6 | B-06 | H-05 | Deshabilitar el botón al enviar y mostrar «Guardando…» | Playwright: después del primer clic, el botón tiene `disabled`; un segundo clic no genera otra petición |
| 7 | B-07 | H-07 | Etiquetas legibles («Medicina interna», «General») con el valor técnico solo en `value` | Ninguna etiqueta visible contiene `_` |
| 8 | B-08 | H-08 | En el HIS el usuario viene de la sesión; en el Micro-HIS, mover el selector a un aviso de «modo demostración» | Revisión: el formulario de admisión no tiene un campo `usuario` en el HIS |

El backlog **no se aplica en esta semana**. Los puntos B-01 a B-07 alimentan el prototipo de la semana 11.
