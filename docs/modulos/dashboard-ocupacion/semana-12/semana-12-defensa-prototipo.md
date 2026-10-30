# Semana 12 — Defensa de componentes, UX, accesibilidad y movilidad del Dashboard de ocupación hospitalaria

| | |
|---|---|
| **Tarea Odoo** | 2499 — Semana 12: Segunda evaluación parcial |
| **Flujo** | «Consolidación y consulta de ocupación por sala y estado de cama» |
| **Presentación** | [semana-12-presentacion.md](semana-12-presentacion.md) (5 diapositivas) |
| **Prototipo** | [semana-11/prototipo/index.html](../semana-11/prototipo/index.html): se abre en el navegador, sin servidor |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Recorrido navegable

Se abre `index.html` y se sigue la tabla. El rol, la antigüedad del corte y la falla al generar se simulan en el panel del prototipo, al pie de la página.

| Paso | Ruta | Panel | Qué demuestra | Evidencia |
|---|---|---|---|---|
| 1 | `#salas` | Jefatura | Ocupación por sala con nivel en texto y color, hora del corte y fuente (R-01, R-05, B-04) | [S11, captura 01](../semana-11/capturas/escritorio/01-salas.png) |
| 2 | Filtrar «Disponibles» | Jefatura | Solo las salas con camas en ese estado, anunciado (CU-03, B-05) | [S11, captura 02](../semana-11/capturas/escritorio/02-filtro-disponible.png) |
| 3 | `#sala/A` | Jefatura | Detalle con la fórmula a la vista (R-01) | [S11, captura 03](../semana-11/capturas/escritorio/03-detalle-sala-a.png) |
| 4 | «Último corte: de hace 20 min» | Jefatura | Aviso de datos viejos con su hora (R-06, B-01) | [S11, captura 04](../semana-11/capturas/escritorio/04-error-datos-viejos.png) |
| 5 | `#corte` | Administrador | Generar un corte, con el resultado anunciado (CU-01, B-07) | [S11, captura 05](../semana-11/capturas/escritorio/05-corte-generado.png) |
| 6 | «Generar un corte falla» | Administrador | Se conserva el corte anterior (R-09, E2) | [S11, captura 06](../semana-11/capturas/escritorio/06-corte-falla.png) |
| 7 | `#sala/A` | Jefatura | **Cambio solicitado**: «Camas por revisar (R-04)» con A/104, ocupada con 2 estancias abiertas | [escritorio](capturas/escritorio/01-sala-a-por-revisar.png) · [móvil](capturas/movil/01-sala-a-por-revisar.png) |
| 8 | «Dónde se corrige» | Jefatura | Explica qué pasa, qué hacer y dónde (admisión); el tablero no corrige (R-09) | [S12, captura 02](capturas/escritorio/02-donde-se-corrige.png) |
| 9 | `#sala/C` | Jefatura | Sin inconsistencias, lo dice en texto | [S12, captura 03](capturas/escritorio/03-sala-c-sin-inconsistencias.png) |

## 2. Matriz decisión → RF/RNF → evidencia

| # | Decisión (componentes, UX, accesibilidad, movilidad) | RF/RNF y regla | Evidencia |
|---|---|---|---|
| D-01 | Encabezado del corte, tabla de salas y detalle de sala (S7) | RF-01, RNF-03 | Componentes de la S7 (PR #459, sin integrar) y `vistas` de `app.js` |
| D-02 | Nivel de ocupación en texto y color, nunca solo color | RF-01, B-04; WCAG 1.4.1 | Paso 1 |
| D-03 | Hora absoluta y fuente siempre visibles; aviso a los 15 minutos | RF-04 (R-05, R-06), B-01, B-03 | Pasos 1 y 4 |
| D-04 | Filtro por estado con el resultado anunciado | RF-05, B-05; WCAG 4.1.3 | Paso 2 |
| D-05 | Solo el administrador genera cortes; si falla, queda el anterior | RF-06 (R-09), RNF-04 | Pasos 5 y 6 |
| D-06 | Foco en el `<h1>`, títulos distintos y «Saltar al contenido» | B-02; WCAG 2.4.2, 2.4.3 | [Medición S11](../semana-11/evidencia/salida-medicion.txt) |
| D-07 | Tarjetas en móvil, objetivos de 44 px y sin scroll horizontal | Movilidad (S10); WCAG 1.4.10 | Mediciones de la S11 y 9 de 9 de la S12 |
| D-08 | **Cambio solicitado**: camas inconsistentes con «dónde se corrige» | RF-03 (R-04), B-08 | §4 y [salida-medicion.txt](evidencia/salida-medicion.txt) |

## 3. Roles, estados, errores y riesgos en el prototipo

| Aspecto | Cómo lo trata el prototipo | Riesgo que cubre |
|---|---|---|
| Roles | La jefatura consulta; el administrador además genera cortes | Que cualquiera dispare consolidaciones |
| Estados de cama | Disponibles, ocupadas, mantenimiento e inactivas, con su conteo | Confundir lo operativo con lo no operativo (R-01) |
| Errores | Datos viejos avisados; corte fallido sin perder el anterior | Decidir ingresos con datos viejos (B-01) |
| Procedencia | Fuente del corte y camas cuyo estado no coincide con sus estancias | Una cifra de ocupación que esconde a un paciente (R-04) |

## 4. Respuesta documentada al cambio solicitado

**Solicitud (jefatura de enfermería):** «La semana 6 hizo que el tablero detecte dos pacientes en una misma cama, pero en la pantalla no lo vemos ni sabemos a quién avisar.» Es R-04 con B-08 (P3) de la S9. El prototipo de la S11 no mostraba las camas inconsistentes.

| | Antes | Después |
|---|---|---|
| Detalle de sala | Solo cifras | Sección «Camas por revisar (R-04)» con cada cama y el motivo en texto |
| Acción | — | «Dónde se corrige: Admisión, cama A/104» lleva a una pantalla con qué pasa y qué hacer |
| Sin problemas | — | «Ninguna: el estado de cada cama coincide con sus estancias abiertas.» |
| Corrección | — | El tablero no corrige: lo dice en pantalla (R-09) |

El cambio está en [`app.js`](../semana-11/prototipo/app.js) (`INCONSISTENTES`, la sección del detalle y la vista `corregir`) y en [`estilos.css`](../semana-11/prototipo/estilos.css). **La revisión visual encontró un detalle propio:** el subtítulo `<h2>` se veía más grande que el `<h1>`, y se ajustó. Se midió con el mismo `capturar.mjs` de la S11: 3 rutas × 3 anchos, 9 de 9 sin scroll horizontal y el foco en `H1`.

**Lo que no cambia:** las camas inconsistentes las calcula el backend. En el prototipo son datos ficticios con la misma forma que el campo `inconsistentes` del corte (S5 y S6, PR #447).

## 5. Bibliografía

Few, S. (2013). *Information Dashboard Design* (2.ª ed.). Analytics Press · W3C (2023). *WCAG 2.2*: 1.3.1, 1.4.10 y 2.4.6 (encabezados descriptivos).
