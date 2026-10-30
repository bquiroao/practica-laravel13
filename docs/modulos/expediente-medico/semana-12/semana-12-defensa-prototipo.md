# Semana 12 — Defensa de componentes, UX, accesibilidad y movilidad del Expediente médico electrónico base

| | |
|---|---|
| **Tarea Odoo** | 2514 — Semana 12: Segunda evaluación parcial |
| **Flujo** | «Apertura y consulta autorizada del expediente longitudinal» |
| **Presentación** | [semana-12-presentacion.md](semana-12-presentacion.md) (5 diapositivas) |
| **Prototipo** | [semana-11/prototipo/index.html](../semana-11/prototipo/index.html): se abre en el navegador, sin servidor |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Recorrido navegable

Se abre `index.html` y se sigue la tabla. El rol se cambia en el panel del prototipo, al pie de la página. Los pacientes y documentos son ficticios.

| Paso | Ruta | Rol | Qué demuestra | Evidencia |
|---|---|---|---|---|
| 1 | `#pacientes` | Tratante | Una tarjeta por paciente, con «Documento terminado en 4521» y la acción dentro de la tarjeta (B-01, B-06) | [S11, captura 01](../semana-11/capturas/escritorio/01-pacientes.png) |
| 2 | `#expediente/HC-000123` | Tratante | Camino feliz: atención E-77 activa, episodios y nombre abreviado hasta «Mostrar nombre» (R-05) | [S11, captura 02](../semana-11/capturas/escritorio/02-expediente.png) |
| 3 | `#externa/HC-000123` | Tratante | Historia de otros hospitales con consentimiento vigente y su alcance (R-06) | [S11, captura 03](../semana-11/capturas/escritorio/03-historia-externa.png) |
| 4 | `#expediente/HC-000098` | Tratante | Sin atención activa no se ve ningún dato, y el intento se audita (R-05, R-08) | [S11, captura 04](../semana-11/capturas/escritorio/04-error-sin-atencion.png) |
| 5 | `#crear/P1` | Admisión | Confirmar con nombre, nacimiento y documento para no confundir a dos «M. López» (R-01, R-04) | [S11, captura 05](../semana-11/capturas/escritorio/05-admision-confirmar.png) |
| 6 | «Crear expediente» | Admisión | «Expediente HC-000124 abierto», anunciado y auditado | [S11, captura 06](../semana-11/capturas/escritorio/06-expediente-creado.png) |
| 7 | `#externa/HC-000105` | Tratante | **Cambio solicitado**: sin consentimiento (E5) no se ve nada; «Volver» es la opción principal y «Es una emergencia» la secundaria | [escritorio](capturas/escritorio/01-sin-consentimiento.png) · [móvil](capturas/movil/01-sin-consentimiento.png) |
| 8 | Justificación «urgente» | Tratante | Con menos de 20 caracteres no se concede nada: «faltan 13 caracteres», con el foco en el campo (E6, B-05) | [escritorio](capturas/escritorio/02-justificacion-corta.png) · [móvil](capturas/movil/02-justificacion-corta.png) |
| 9 | Justificación completa | Tratante | Historia externa con «Acceso de emergencia hasta las 11:40 · revisión pendiente» (R-07, R-08) | [escritorio](capturas/escritorio/03-acceso-emergencia.png) · [móvil](capturas/movil/03-acceso-emergencia.png) |
| 10 | `#externa/HC-000123` | Tratante | Regresión: con consentimiento vigente la pantalla no cambió | [escritorio](capturas/escritorio/04-con-consentimiento.png) · [móvil](capturas/movil/04-con-consentimiento.png) |

## 2. Matriz decisión → RF/RNF → evidencia

| # | Decisión (componentes, UX, accesibilidad, movilidad) | RF/RNF y regla | Evidencia |
|---|---|---|---|
| D-01 | Lista de pacientes, encabezado del expediente, tabla de episodios e historia externa (S7) | RF-01, RF-02, RF-03 | Componentes de la S7 (PR #462, sin integrar) y `vistas` de `app.js` |
| D-02 | Datos mínimos: nombre abreviado hasta «Mostrar nombre» (`aria-pressed`) y documento «terminado en» | RF-02; B-06 | Pasos 1 y 2 |
| D-03 | Sin atención activa no se ve nada, ni siquiera si hay episodios; el rechazo también se audita | RF-02 (R-05), RF-05 | Paso 4 |
| D-04 | Crear pide confirmación con tres datos; «Ver expediente» y «Crear expediente» nunca comparten texto | RF-01 (R-01, R-04); B-01, B-04 | Pasos 5 y 6 |
| D-05 | Historia externa solo con consentimiento vigente, con su fecha y alcance | RF-03 (R-06) | Pasos 3 y 10 |
| D-06 | Foco en el `<h1>`, títulos distintos, «Saltar al contenido» y objetivos de 44 px | B-02; WCAG 2.4.2, 2.4.3, 2.5.8 | [Medición S11](../semana-11/evidencia/salida-medicion.txt) |
| D-07 | Tablas como tarjetas en móvil, sin scroll horizontal | Movilidad (S10); WCAG 1.4.10 | 18 de 18 en la S11 y 12 de 12 en la S12 |
| D-08 | **Cambio solicitado**: emergencia sin consentimiento, con justificación de 20 caracteres y 1 hora | RF-04 (R-07), RF-05, RNF-02; B-05 | §4 y [salida-medicion.txt](evidencia/salida-medicion.txt) |

## 3. Roles, estados, errores y riesgos en el prototipo

| Aspecto | Cómo lo trata el prototipo | Riesgo que cubre |
|---|---|---|
| Roles | El tratante consulta; admisión crea y nunca ve episodios | Ver datos clínicos sin necesidad |
| Estados | Atención activa, consentimiento vigente o ausente, emergencia con hora de cierre | Acceder fuera del motivo clínico (R-05, R-06) |
| Errores | Sin atención (E1), sin consentimiento (E5), justificación corta (E6): cada uno dice qué hacer | Un rechazo silencioso que empuja a pedir la contraseña de otro |
| Integridad | Confirmación antes de crear, porque un expediente no se borra (R-04) | Expediente duplicado o del paciente equivocado |
| Datos sensibles | Documento enmascarado, nombre abreviado y auditoría de cada acceso (R-08) | Exposición en pantalla compartida |

## 4. Respuesta documentada al cambio solicitado

**Solicitud (jefatura de emergencias):** «Llega un paciente inconsciente, sin consentimiento firmado, y el prototipo solo dice que no hay consentimiento. No tenemos cómo ver sus alergias de otro hospital.» Es CU-04 y RF-04 (R-07), el wireframe W4 de la S8 y B-05 de la S9. El prototipo de la S11 no lo tenía.

| | Antes | Después |
|---|---|---|
| Sin consentimiento | No había caso: todo paciente con historia externa tenía consentimiento | Pantalla E5 que no muestra nada y ofrece «Es una emergencia» como opción secundaria |
| Justificación | — | Mínimo 20 caracteres, contador y ejemplo; si falta, «No se abrió: faltan N caracteres», el foco en el campo y `aria-invalid` |
| Acceso | — | Historia externa con «Acceso de emergencia hasta las 11:40 · revisión pendiente de un auditor» |
| Con consentimiento | Pantalla de la S11 | Sin cambios (paso 10) |

**Criterio de aceptación:** con menos de 20 caracteres no se concede nada y se dice cuántos faltan; con 20 o más se ve la historia externa con la hora de cierre y la revisión pendiente; con consentimiento vigente nada cambia.

El cambio está en [`app.js`](../semana-11/prototipo/app.js) (paciente ficticio HC-000105, la pantalla E5 en `externa` y la vista `emergencia`) y en [`estilos.css`](../semana-11/prototipo/estilos.css) (borde del campo inválido). La hora sale de un reloj fijo (10:40), como el `Reloj` inyectado de la S2 (RNF-02). La medición, con el mismo `capturar.mjs` de la S11, da 12 de 12 sin scroll horizontal. El foco queda en el `<h1>`, salvo en el paso 8, donde va al campo a propósito. **Decisión de revisión:** el error de la justificación se ve arriba, en la región de estado, y junto al campo. Se dejó así, porque en móvil el aviso de arriba puede quedar fuera de la pantalla (WCAG 3.3.1).

**Lo que no cambia:** la hora de cierre la impone el backend (`valid_to <= valid_from + 1 hour`, S1), y la revisión la hace un auditor (S6, PR #448, sin integrar). Queda pendiente B-03: el aviso 5 minutos antes del cierre.

## 5. Bibliografía

Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design*. Nielsen Norman Group (prevención de errores) · W3C (2023). *Web Content Accessibility Guidelines (WCAG) 2.2*: 1.4.10, 3.3.1 y 3.3.3.
