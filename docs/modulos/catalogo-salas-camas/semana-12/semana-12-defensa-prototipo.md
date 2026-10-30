# Semana 12 — Defensa de componentes, UX, accesibilidad y movilidad del Catálogo de salas, wards y camas

| | |
|---|---|
| **Tarea Odoo** | 2454 — Semana 12: Segunda evaluación parcial |
| **Flujo** | «Creación de sala y cama con control de estado operativo» |
| **Presentación** | [semana-12-presentacion.md](semana-12-presentacion.md) (5 diapositivas) |
| **Prototipo** | [semana-11/prototipo/index.html](../semana-11/prototipo/index.html): se abre en el navegador, sin servidor |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Recorrido navegable

Se abre `index.html` y se sigue la tabla. El rol se cambia en el panel del prototipo, al pie de la página.

| Paso | Ruta | Rol | Qué demuestra | Evidencia |
|---|---|---|---|---|
| 1 | `#salas` | Supervisor | Capacidad por sala en texto: «5 de 11 operativas» | [S11, captura 01](../semana-11/capturas/escritorio/01-salas.png) |
| 2 | `#sala/A` | Supervisor | Estado de cada cama en texto; la ocupada no tiene acción (R-06) | [S11, captura 02](../semana-11/capturas/escritorio/02-sala-a.png) |
| 3 | `#cambiar/A101` | Supervisor | Solo «Mantenimiento» (R-05 por rol); aviso de impacto en la capacidad | [S11, captura 03](../semana-11/capturas/escritorio/03-cambiar-estado.png) |
| 4 | Confirmar con motivo | Supervisor | Cambio guardado y anunciado en `role="status"` | [S11, captura 04](../semana-11/capturas/escritorio/04-cambio-guardado.png) |
| 5 | Confirmar con «simular conflicto» | Supervisor | E7: admisión ocupó la cama; el motivo se conserva | [S11, captura 05](../semana-11/capturas/escritorio/05-error-conflicto.png) |
| 6 | `#cambiar/A101`, elegir «Inactiva» | Administrador | **Cambio solicitado**: aparece la confirmación | [S12, captura 01](capturas/escritorio/01-inactivar-pide-confirmacion.png) |
| 7 | Confirmar sin marcarla | Administrador | Error junto al campo, `aria-invalid` y foco en la casilla | [escritorio](capturas/escritorio/02-inactivar-sin-confirmar.png) · [móvil](capturas/movil/02-inactivar-sin-confirmar.png) |
| 8 | Marcarla y confirmar | Administrador | Cama inactiva, guardada y anunciada | [escritorio](capturas/escritorio/03-inactivar-confirmado.png) · [móvil](capturas/movil/03-inactivar-confirmado.png) |

## 2. Matriz decisión → RF/RNF → evidencia

| # | Decisión (componentes, UX, accesibilidad, movilidad) | RF/RNF y regla | Evidencia |
|---|---|---|---|
| D-01 | Componentes por pantalla: salas, sala, cambio de estado, resultado y conflicto (S7) | RF-03, RNF-02 | Componentes de la S7 (PR #452, sin integrar) y `vistas` de `app.js` |
| D-02 | El destino depende del rol: el supervisor no inactiva; nadie toca una ocupada | RF-04, RF-05 (R-05, R-06) | Pasos 3 y 6 del recorrido |
| D-03 | Aviso de impacto en la capacidad antes de confirmar y botón con verbo (B-02 de la S9) | RF-03, RNF-04 | Paso 3 |
| D-04 | Motivo obligatorio con error junto al campo, «Error:» y foco devuelto | RF-03 (R-09), WCAG 3.3.1 | [Medición S11](../semana-11/evidencia/salida-medicion.txt): foco en `TEXTAREA` |
| D-05 | Conflicto E7 explicado, sin perder el motivo escrito | RF-05, RNF-04 | Paso 5 |
| D-06 | Estados siempre en texto, no solo color; foco en el `<h1>` y «Saltar al contenido» | RNF accesibilidad (B-04, B-06), WCAG 1.4.1 y 2.4.3 | Medición S11: foco en `H1` en las 15 rutas |
| D-07 | Tabla en escritorio y tarjetas en móvil, objetivos de 44 px y sin scroll horizontal | Movilidad (S10), WCAG 1.4.10 | 15 de 15 (S11) y 12 de 12 (S12) mediciones sin scroll a 320, 360 y 430 px |
| D-08 | **Cambio solicitado**: inactivar exige una confirmación explícita | RF-04, R-05; prevención de errores (Nielsen 5) | §4 y [salida-medicion.txt](evidencia/salida-medicion.txt) |

## 3. Roles, estados, errores y riesgos en el prototipo

| Aspecto | Cómo lo trata el prototipo | Riesgo que cubre |
|---|---|---|
| Roles | Supervisor: disponible ↔ mantenimiento. Administrador: además inactivar y reactivar | Que un rol saque camas de servicio sin autoridad |
| Estados | Disponible, Ocupada, Mantenimiento e Inactiva, siempre escritos | Leer mal el estado solo por color |
| Errores | Motivo vacío (R-09), conflicto E7 y confirmación faltante al inactivar | Cambios sin rastro o sobre una cama que ya cambió |
| Capacidad y consistencia | Impacto previo («de 5 a 4 disponibles») y confirmación al inactivar | Bajar la capacidad real de la sala por error |

## 4. Respuesta documentada al cambio solicitado

**Solicitud (jefatura de enfermería):** «Aunque el aviso dice "pasará de 5 a 4", se siguen inactivando camas por error al elegir la opción equivocada. Pidan una confirmación antes de inactivar.» Amplía B-02 de la S9, que tiene prioridad P1.

| | Antes | Después |
|---|---|---|
| Inactivar | Un clic en «Inactivar cama» | Además, la casilla «Confirmo que la cama queda fuera de servicio hasta que se reactive» |
| Sin marcarla | — | «Error: confirma que la cama queda fuera de servicio…», `aria-invalid` y foco en la casilla |
| Mantenimiento y disponible | Un clic | Sin cambios: la confirmación solo aparece con «Inactiva» |

Cambio en [`app.js`](../semana-11/prototipo/app.js): el bloque `confirmacion` del formulario, `hidden` según el destino, y una validación más en el envío. Se midió con el mismo `capturar.mjs` de la S11: 4 rutas × 3 anchos, 12 de 12 sin scroll horizontal, y el foco en `INPUT` cuando falta la confirmación. El paso 04 comprueba que pasar a mantenimiento sigue sin confirmación.

**Lo que no cambia:** la regla R-05 y quién puede inactivar. La confirmación es prevención de errores de la interfaz; el backend sigue validando la transición (E6). Por la S7, la UI debería recibir los destinos del backend, y la confirmación se mantiene igual.

## 5. Bibliografía

Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design* (5: prevención de errores) · W3C (2023). *WCAG 2.2*: 1.4.10, 2.4.3 y 3.3.1.
