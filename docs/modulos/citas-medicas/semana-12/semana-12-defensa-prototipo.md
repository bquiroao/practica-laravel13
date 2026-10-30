# Semana 12 — Defensa de componentes, UX, accesibilidad y movilidad de Citas médicas y agenda

| | |
|---|---|
| **Tarea Odoo** | 2469 — Semana 12: Segunda evaluación parcial |
| **Flujo** | «Solicitud, validación de disponibilidad y confirmación o cancelación de cita» |
| **Presentación** | [semana-12-presentacion.md](semana-12-presentacion.md) (5 diapositivas) |
| **Prototipo** | [semana-11/prototipo/index.html](../semana-11/prototipo/index.html): se abre en el navegador, sin servidor |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Recorrido navegable

Se abre `index.html` y se sigue la tabla. El rol se cambia en el panel del prototipo, al pie de la página.

| Paso | Ruta | Rol | Qué demuestra | Evidencia |
|---|---|---|---|---|
| 1 | `#agenda` | Recepcionista | Citas del día con su estado en texto | [S11, captura 01](../semana-11/capturas/escritorio/01-agenda.png) |
| 2 | `#nueva`, elegir paciente y médico | Recepcionista | Solo horarios libres; «3 horarios libres» se anuncia (B-04) | [S11, captura 02](../semana-11/capturas/escritorio/02-horario.png) |
| 3 | Revisar | Recepcionista | Motivo obligatorio (R-06) y resumen antes de confirmar | [S11, captura 03](../semana-11/capturas/escritorio/03-revisar.png) |
| 4 | Agendar | Recepcionista | Cita programada y anunciada | [S11, captura 04](../semana-11/capturas/escritorio/04-cita-agendada.png) |
| 5 | Agendar con «simular» | Recepcionista | Otra persona tomó el horario: el motivo se conserva y se ofrecen otros | [S11, captura 05](../semana-11/capturas/escritorio/05-error-horario-ocupado.png) |
| 6 | `#agenda` | Recepcionista | **Cambio solicitado**: columna «Acción» con «Cancelar»; las canceladas muestran «—» (R-07) | [S12, captura 01](capturas/escritorio/01-agenda-con-cancelar.png) |
| 7 | «Cancelar» de las 08:00 | Recepcionista | Resumen, aviso «el horario queda libre» (R-08), «Cancelar la cita de A. Pérez» frente a «Volver» (B-02) | [S12, captura 02](capturas/escritorio/02-cancelar-cita.png) |
| 8 | Confirmar sin motivo | Recepcionista | Error junto al campo, `aria-invalid` y foco en el motivo (R-10) | [escritorio](capturas/escritorio/03-cancelar-sin-motivo.png) · [móvil](capturas/movil/03-cancelar-sin-motivo.png) |
| 9 | Con motivo | Recepcionista | Cita cancelada, anunciada, y el horario vuelve a estar libre | [escritorio](capturas/escritorio/04-cita-cancelada.png) · [móvil](capturas/movil/04-cita-cancelada.png) |
| 10 | `#agenda` | Médica | Solo ve y cancela sus propias citas (R-09) | [S12, captura 05](capturas/escritorio/05-medica-solo-sus-citas.png) |

## 2. Matriz decisión → RF/RNF → evidencia

| # | Decisión (componentes, UX, accesibilidad, movilidad) | RF/RNF y regla | Evidencia |
|---|---|---|---|
| D-01 | Asistente de 3 pasos (paciente, horario, revisar) con «Paso n de 3» | RF-01 (R-06) | Pasos 2 a 4 |
| D-02 | Solo se ofrecen horarios libres, y se vuelve a revisar al confirmar | RF-02, RF-03 (R-02, R-03) | Pasos 2 y 5 |
| D-03 | El conflicto de horario conserva el motivo y ofrece los siguientes horarios | RF-02, RNF-04 | Paso 5 |
| D-04 | Cancelar con motivo obligatorio y el horario liberado | RF-05, RF-06 (R-07, R-08, R-10) | Pasos 7 a 9 |
| D-05 | «Cancelar la cita de A. Pérez» en rojo frente a «Volver» | B-02 (P1) de la S9 | Paso 7; contraste 6,47:1 en [salida-medicion.txt](evidencia/salida-medicion.txt) |
| D-06 | Cada «Cancelar» de la fila nombra la hora y el paciente en su `aria-label` | B-03 (P1) de la S9 | Atributo en `accionCancelar` de `app.js` |
| D-07 | Mensajes en `role="status"` y foco en el `<h1>` o en el campo con error | B-01, B-04; WCAG 2.4.3, 3.3.1 | Medición: foco en `H1` o en `TEXTAREA` |
| D-08 | Tarjetas en móvil, objetivos de 44 px y sin scroll horizontal | Movilidad (S10); WCAG 1.4.10 | 15 de 15 (S11) y 15 de 15 (S12) mediciones sin scroll a 320, 360 y 430 px |

## 3. Roles, estados, errores y riesgos en el prototipo

| Aspecto | Cómo lo trata el prototipo | Riesgo que cubre |
|---|---|---|
| Roles | La recepcionista agenda y cancela; la médica solo ve y cancela sus citas | Que alguien cancele citas ajenas (R-09) |
| Estados | Programada, Confirmada y Cancelada en texto; la cancelada ya no tiene acción | Cancelar dos veces o «revivir» una cita (R-07) |
| Errores | Motivo vacío al agendar y al cancelar; horario tomado por otra persona | Citas sin motivo o con cruces |
| Disponibilidad | Cancelar devuelve el horario a la lista de libres | Horarios perdidos (R-08) |

## 4. Respuesta documentada al cambio solicitado

**Solicitud (recepción):** «Desde la agenda no podemos cancelar una cita cuando el paciente avisa que no viene. Necesitamos hacerlo ahí mismo, sin confundirlo con salir del formulario.» El flujo incluye la cancelación (CU-03), pero el prototipo de la S11 solo tenía el camino de agendar.

| | Antes | Después |
|---|---|---|
| Agenda | Sin acciones por fila | Columna «Acción»: «Cancelar», con `aria-label` de hora y paciente, solo en citas activas propias del rol |
| Cancelar | No existía | Pantalla con resumen, motivo obligatorio y la acción destructiva en rojo, separada de «Volver» |
| Resultado | — | «Cita de A. Pérez a las 08:00 cancelada. El horario quedó libre.» en `role="status"` |

El cambio está en [`app.js`](../semana-11/prototipo/app.js) (vistas `cancelar` y `cancelada` y la columna de acción) y en [`estilos.css`](../semana-11/prototipo/estilos.css) (clase `peligro`). Se midió con el mismo `capturar.mjs` de la S11: 5 rutas × 3 anchos, 15 de 15 sin scroll horizontal. Sin motivo, el foco va al `TEXTAREA`.

**Lo que no cambia:** las reglas del backend (R-07 a R-10 se validan en el Micro-HIS con E2 y E7) y el camino de agendar. El panel aún dice «error E4» para el horario tomado; la semana 6 (PR #435, sin integrar) define E6 para ese caso, y el texto se alineará cuando se integre.

## 5. Bibliografía

Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design* (3: control y libertad; 5: prevención de errores) · W3C (2023). *WCAG 2.2*: 1.4.3, 1.4.10, 2.4.3 y 3.3.1.
