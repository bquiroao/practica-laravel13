# Matriz decisión → RF/RNF → evidencia (semanas 7 a 12)

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73-svg`) · **Flujo:** «registro de médico, asociación de especialidad y consulta de disponibilidad»

Cada decisión de componentes, UX, accesibilidad y movilidad enlaza el requisito que la motiva ([semana 2](../semana-02/semana-02-isp-requerimientos-diseno.md)) y la evidencia que la comprueba. Las pruebas del prototipo se corren con `node semana-11/prototipo/probar-prototipo.js` (28 de 28) y el refactor con `php semana-07/ejemplo-refactor/verificar.php`.

| ID | Decisión | RF / RNF | Evidencia | Sem. |
|---|---|---|---|:-:|
| D-14 | La regla de franjas libres sale a una función pura; el caso de uso solo lee y entrega | RF-04, RF-06, RNF-04 | `verificar.php` REF-01 a REF-06 | 7 |
| D-15 | Un solo cliente de la API conoce rutas y códigos; las pantallas no | RNF-04 | `app.js` solo llama a `ApiSimulada` | 7, 11 |
| D-16 | Los botones de escritura no se dibujan para recepción y el servidor igual responde 403 | RNF-01 | E2E-11; E2E-14 | 8, 11, 12 |
| D-17 | Mensajes de error junto al campo, con el foco en el campo | RF-02, RF-03 (CA-02, CA-05) | E2E-01, E2E-04 | 8, 11 |
| D-18 | Una ausencia que choca con citas las muestra y exige confirmar que Citas no fue avisada | RF-05 (CA-09) | E2E-06, E2E-07, E2E-08 | 8, 10, 11 |
| D-19 | Si Citas no responde o no hay conexión no se muestran franjas, ni las guardadas | RNF-02 (CA-03) | E2E-09, E2E-10 | 8, 10, 11 |
| D-20 | «Último día» inclusivo en la pantalla; se envía un día más | RF-05 | E2E-13 (último día 08 → hasta 09) | 9, 12 |
| D-21 | Hallazgos de usabilidad priorizados y corregidos en el prototipo | RNF-06 (propuesto) | `verificar-hallazgos.php`, 9 hallazgos | 9 |
| D-22 | Una sola pantalla se adapta de 320 a 1024 px (tarjetas o tabla) | RNF-06 (propuesto) | `verificar-responsive.js` R-320 a R-1024 | 10 |
| D-23 | El médico registra solo sus propias ausencias (cambio solicitado) | RF-05, RNF-01 | E2E-12 a E2E-14; [registro](registro-cambio-solicitado.md) | 12 |

**Hueco encontrado:** los RNF actuales (seguridad, disponibilidad, rendimiento, mantenibilidad, verificabilidad) no piden accesibilidad ni uso en celular. Se propone **RNF-06**: «las pantallas cumplen WCAG 2.2 AA y funcionan entre 320 y 1024 px sin desplazamiento horizontal», con las dos verificaciones de arriba como criterio.

## Cobertura

| Requisito | Decisiones |
|---|---|
| RF-02, RF-03 | D-17 |
| RF-04, RF-06 | D-14 |
| RF-05 | D-18, D-20, D-23 |
| RNF-01 | D-16, D-23 |
| RNF-02 | D-19 |
| RNF-04 | D-14, D-15 |
| RNF-06 (propuesto) | D-21, D-22 |
