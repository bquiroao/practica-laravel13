# Semana 12 — Defensa de componentes, UX, accesibilidad y movilidad del Dashboard base

| | |
|---|---|
| **Tarea Odoo** | 2439 — Semana 12: Segunda evaluación parcial |
| **Flujo** | «Navegación del dashboard con estados de carga, vacío, error y resumen inicial» |
| **Presentación** | [semana-12-presentacion.md](semana-12-presentacion.md) (5 diapositivas) |
| **Prototipo** | [semana-11/prototipo/index.html](../semana-11/prototipo/index.html): se abre en el navegador, sin servidor |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Recorrido navegable

Se abre `index.html` y se sigue la tabla. El rol, la respuesta de «Citas de hoy» y la sesión se simulan en el panel del prototipo, al pie de la página.

| Paso | Ruta | Panel | Qué demuestra | Evidencia |
|---|---|---|---|---|
| 1 | `#inicio` | Respuesta «Lenta» | Cada tarjeta carga por su cuenta, con esqueleto y `aria-busy` (R-03, R-04) | [S11, captura 01](../semana-11/capturas/escritorio/01-inicio-cargando.png) |
| 2 | `#inicio` | Normal | Resumen de las tres tarjetas, ordenado por rol | [S11, captura 02](../semana-11/capturas/escritorio/02-inicio-resumen.png) |
| 3 | «Ver camas» | Normal | El menú marca la sección actual con `aria-current` (B-07) | [S11, captura 03](../semana-11/capturas/escritorio/03-abrir-modulo.png) |
| 4 | `#inicio` | «Falla (E5)» | Error de una tarjeta, sin detalles técnicos; las demás siguen (R-05, R-09) | [S11, captura 04](../semana-11/capturas/escritorio/04-error-tarjeta.png) |
| 5 | «Reintentar» | Normal | Solo esa tarjeta recarga y el foco va a su título (R-08, B-05) | [S11, captura 05](../semana-11/capturas/escritorio/05-reintento-recupera.png) |
| 6 | `#modulo/usuarios` | Enfermería | «No tienes acceso a esa sección» dentro del layout (E6) | [S11, captura 06](../semana-11/capturas/escritorio/06-sin-permiso.png) |
| 7 | «Simular: la sesión vence…» | — | **Cambio solicitado**: aviso con `role="alertdialog"` y foco en «Seguir conectado» | [escritorio](capturas/escritorio/01-aviso-sesion.png) · [móvil](capturas/movil/01-aviso-sesion.png) |
| 8 | Tab en «Cerrar sesión» | — | Tab no sale del aviso: vuelve a «Seguir conectado» | [S12, captura 02](capturas/escritorio/02-tab-no-sale-del-aviso.png) |
| 9 | «Seguir conectado» | — | El aviso se cierra y el foco vuelve donde estaba; nada se pierde | [S12, captura 03](capturas/escritorio/03-seguir-conectado.png) |
| 10 | «Cerrar sesión» | — | «Tu sesión terminó»: se limpia la sesión (R-07) | [S12, captura 04](capturas/escritorio/04-sesion-terminada.png) |

## 2. Matriz decisión → RF/RNF → evidencia

| # | Decisión (componentes, UX, accesibilidad, movilidad) | RF/RNF y regla | Evidencia |
|---|---|---|---|
| D-01 | Layout con menú del rol, tarjetas independientes y cargador con límite de 10 s (S7) | RF-06, RNF-02 | Componentes de la S7 (PR #458, sin integrar) y `vistas` de `app.js` |
| D-02 | Cuatro estados visibles: carga, resumen, vacío con acción y error con «Reintentar» | RF-01 a RF-03 (R-04, R-05) | Pasos 1, 2 y 4 |
| D-03 | Reintentar solo a pedido y solo esa tarjeta | RF-03, RF-06 (R-08) | Paso 5 |
| D-04 | Lo que el rol no permite se explica dentro del layout | RF-05 (R-02) | Paso 6 |
| D-05 | Foco en el `<h1>` al navegar, título por pantalla y «Saltar al contenido» | B-01, B-09, B-10; WCAG 2.4.2, 2.4.3 | [Medición S11](../semana-11/evidencia/salida-medicion.txt) |
| D-06 | Resultado de cada tarjeta en una región viva y `aria-busy` mientras carga | B-06; WCAG 4.1.3 | Paso 1 |
| D-07 | Tarjetas en 1, 2 o 3 columnas según el ancho; objetivos de 44 px | Movilidad (S10); WCAG 1.4.10 | Mediciones de la S11 y 12 de 12 de la S12 sin scroll |
| D-08 | **Cambio solicitado**: aviso de sesión por vencer antes del 401 | RF-04 (R-01, R-07), B-04 (P1) | §4 y [salida-medicion.txt](evidencia/salida-medicion.txt) |

## 3. Roles, estados, errores y riesgos en el prototipo

| Aspecto | Cómo lo trata el prototipo | Riesgo que cubre |
|---|---|---|
| Roles | Enfermería y administrador ven menú y tarjetas distintos | Mostrar accesos que el backend rechazaría (R-02) |
| Estados | Carga, resumen, vacío y error, cada uno con texto | Confundir «vacío» con «error» (R-05) |
| Errores | E5 por tarjeta, E2 por tiempo, E6 sin permiso | Que un fallo bloquee todo el dashboard (R-03) |
| Sesión | Aviso 2 minutos antes y cierre limpio | Perder el trabajo por un 401 inesperado |

## 4. Respuesta documentada al cambio solicitado

**Solicitud (personal de enfermería):** «En medio del turno la sesión vence sin avisar, y volvemos al inicio de sesión perdiendo lo que estábamos viendo.» Es B-04 (P1) de la S9, que el prototipo de la S11 no tenía.

| | Antes | Después |
|---|---|---|
| Sesión por vencer | No se avisaba: llegaba el 401 | Aviso «Tu sesión vence en 2 minutos» con `role="alertdialog"`, `aria-modal` y foco en «Seguir conectado» |
| Teclado | — | Tab y Mayús+Tab no salen del aviso; Esc lo cierra y el foco vuelve donde estaba |
| Seguir | — | Cierra el aviso sin recargar: la pantalla queda igual |
| Cerrar sesión | — | «Tu sesión terminó», con la sesión limpia (R-07) y «Iniciar sesión» |

El cambio está en [`index.html`](../semana-11/prototipo/index.html) (el aviso y el botón del panel), [`app.js`](../semana-11/prototipo/app.js) (apertura, foco, Tab, Esc y la vista `sesion-terminada`) y [`estilos.css`](../semana-11/prototipo/estilos.css). Se midió con el mismo `capturar.mjs` de la S11: 4 rutas × 3 anchos, 12 de 12 sin scroll, con el aviso visible y el foco correcto en cada paso.

**Lo que no cambia:** la sesión real la controla el backend (R-01). El prototipo simula el aviso con el panel, y el aviso es una ayuda para no perder el trabajo, no un control de seguridad.

## 5. Bibliografía

W3C (2023). *WAI-ARIA Authoring Practices: Alert and Message Dialogs* · W3C (2023). *WCAG 2.2*: 2.2.1 (tiempo ajustable), 2.4.3 y 1.4.10.
