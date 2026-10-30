# Semana 8 — Diseño de experiencia de usuario

**Tarea:** flujo UX de «detección, envío, acuse y escalamiento de una alerta crítica» por roles autorizados, con inicio, decisión, confirmación, estados vacío/carga/éxito, errores recuperables, ayuda contextual y protección de datos.
**Entregable:** user flow por rol y **5 wireframes** anotados (`.puml` en Salt + `.png`).

## 1. User flow por rol

`diagramas/user-flow-roles.png`: enfermería (recibe, acusa y reintenta), médico tratante (cierra con nota) y jefe de servicio (recibe el escalamiento). El sistema origen y el planificador no tienen interfaz: entran por la API (semana 5).

| Rol | Puede | No puede (protección) |
|---|---|---|
| Enfermería de turno | Ver la bandeja de su tenant, acusar si fue notificada | Cerrar (RF-08), ver otros hospitales |
| Médico tratante | Acusar, cerrar con nota | Ver otros hospitales |
| Jefe de servicio | Acusar escalamientos de nivel 1 y cerrar | Acusar alertas de nivel 0 donde no fue notificado (RN-07) |
| Coordinación médica | Ver y acusar escalamientos de nivel 2 y agotados | Cerrar |

## 2. Wireframes anotados

| # | Wireframe | Anotaciones (los números aparecen en el wireframe) |
|---|---|---|
| W1 | `wireframes/w1-bandeja.png` | (1)(2) La prioridad se muestra con símbolo **y** texto, no solo color. «Vence en» muestra el plazo (RN-04); hay ayuda contextual sobre su significado. Solo aparece el código del paciente. |
| W2 | `wireframes/w2-detalle-acuse.png` | (3) Estado y nivel visibles. (4) «Acusar» es la acción primaria; repetirla no duplica (RN-06), así que no necesita confirmación. |
| W3 | `wireframes/w3-cierre-confirmacion.png` | (5) Nota obligatoria (RN-09). (6) La validación se muestra junto al campo. Hay confirmación explícita porque el cierre no se deshace. |
| W4 | `wireframes/w4-estados.png` | (7) Estados de carga (esqueleto), vacío, error recuperable (503 con «Reintentar»), éxito y sin permiso (RN-07) con ayuda de a quién corresponde. |
| W5 | `wireframes/w5-escalamiento-coordinacion.png` | (8) Escalamiento agotado como aviso destacado, con historial de los niveles. |

## 3. Reglas de interacción y mensajes

| Situación | Mensaje | Regla |
|---|---|---|
| Acuse correcto | «Acuse registrado HH:MM» | RN-06 |
| Acuse repetido | «Ya estaba acusada; no hubo cambios» | RN-06 |
| Sin permiso | «No fue notificado de esta alerta» + a quién corresponde | RN-07 |
| Cierre sin nota | «La nota es obligatoria» (en el campo, foco al campo) | RN-09 |
| Sin conexión o 503 | «No se registró. Sus datos no se perdieron. Reintentar» | RNF-05 |
| Otro tenant | «La alerta no existe en su hospital» (no se revela si existe en otro) | RNF-01 |

Protección de datos: solo códigos de paciente, sin nombres; el historial muestra usuarios ficticios; las acciones dependen del rol del token.

Los .png se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.
