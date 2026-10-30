# Registro del cambio solicitado — «Dígame antes si puedo justificar la advertencia»

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Fecha:** 27/10/2026

## 1. Solicitud

> «Me salió una advertencia por la cefalexina, escribí tres líneas de justificación, pulsé “Emitir” y me dijo que no tengo permiso. ¿Por qué no me lo dijo antes de escribir?»
> — Dra. Ruiz, médica sin el permiso `prescripcion.override` (escenario de la evaluación)

**Por qué pasa hoy:**
- La verificación previa responde `ADVERTENCIA` con los hallazgos, pero **no dice si el usuario puede autorizarla**. Solo la emisión lo sabe (409 `JUSTIFICACION_REQUERIDA`).
- La interfaz lo adivina con su copia de los permisos, que recibió al ingresar. Es la **brecha M-07**, anotada en la semana 8 y señalada como hallazgo H-11 en la semana 9.
- La semana 7 la dejó fuera a propósito: cambiar la respuesta de la API ampliaba el módulo, y aquella tarea era un refactor sin ampliarlo.
- La copia **puede quedar vieja**. Si RBAC retira el permiso durante la sesión, la interfaz le ofrece el área de texto a alguien que ya no puede usarla.

## 2. Requisitos nuevos

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| **RF-19** | Con resultado ADVERTENCIA, la previa informa `puede_justificar` | Dada P-002 con Cefalexina, cuando verifica el Dr. López, entonces `puede_justificar: true`; cuando verifica la Dra. Ruiz, `false`; los hallazgos son los mismos (JUS-01) |
| **RF-19 (límite)** | Sin advertencia no hay clave, y no se pregunta a RBAC | Con SIN_HALLAZGOS, BLOQUEO, CONFIRMACION_REQUERIDA o con el Expediente caído, la clave no aparece y RBAC recibe 0 consultas por override (JUS-02) |
| **RF-19 (promesa)** | Lo que la previa promete, la emisión lo cumple | Con una justificación válida: si la previa dijo `true`, 201; si dijo `false`, 409 (JUS-03) |
| **RF-20** | La interfaz decide la hoja de la advertencia con `puede_justificar`, no con su copia de los permisos | Si RBAC retira el permiso a mitad de la sesión, la hoja es M-07 aunque la copia diga que lo tiene (recorrido, paso 26) |
| **RNF-17** | Compatibilidad | Contrato 1.2.0, compatible con 1.1.0: una clave opcional nueva en una respuesta. En la caracterización de la semana 7 solo cambia lo que el cambio explica (JUS-04, CAR-01) |

## 3. Análisis de impacto (antes de programar)

| Pieza | ¿Cambia? | Por qué |
|---|---|---|
| `PoliticaEmision` (Dominio) | Sí: un método nuevo, `puedeJustificar()` | La regla «advertencia → hace falta override» ya vive aquí (semana 7). Si la previa la calculara por su cuenta, serían **dos** lugares que pueden divergir, justo lo que el refactor de la semana 7 eliminó |
| `VerificarAlergiasPrevia` | Sí, 3 líneas | Pide la respuesta a la política y la agrega al resultado |
| `EmitirPrescripcion`, `decidir()` | **No** | La emisión sigue decidiendo igual: la previa informa, no autoriza (R-03) |
| Controlador, vistas, kernel | **No** | La vista ya copia los datos del resultado |
| Base de datos | **No** | La previa no guarda nada |
| `api/openapi.yaml` | 1.1.0 → **1.2.0** | Una propiedad opcional nueva en `Verificacion`: cambio compatible |
| Caracterización (`esperado.json`) | Sí, controlado | El escenario «previa P-002 cefalexina» responde una clave más. Se vuelve a aprobar **solo** esa diferencia (§5) |
| Prototipo | Sí | API simulada, la decisión de la hoja, la simulación «RBAC retira un permiso», paridad y recorrido |

**Riesgos considerados:**
- **¿Revela información de permisos?** Solo al propio usuario autenticado y sobre su propio permiso; la emisión ya lo revelaba con el 409. No se informa nada sobre otros usuarios.
- **¿Puede quedar vieja la respuesta?** Sí: entre la previa y la emisión, RBAC puede cambiar. Por eso la emisión sigue decidiendo y la interfaz conserva el manejo del 409 (M-15).
- **Costo:** una consulta más a RBAC, solo con advertencia. La `Closure` lo garantiza y M35 lo vigila.

## 4. Implementación

| Archivo | Cambio |
|---|---|
| [`src/Dominio/Reglas/PoliticaEmision.php`](../micro-his-prescripciones/src/Dominio/Reglas/PoliticaEmision.php) | `puedeJustificar(verificación, expediente, Closure)`: `null` sin advertencia; con advertencia, el resultado de la `Closure` del override |
| [`src/Aplicacion/CasosDeUso/VerificarAlergiasPrevia.php`](../micro-his-prescripciones/src/Aplicacion/CasosDeUso/VerificarAlergiasPrevia.php) | Agrega `puede_justificar` al resultado cuando no es `null` |
| [`api/openapi.yaml`](../micro-his-prescripciones/api/openapi.yaml) | 1.2.0; `Verificacion.puede_justificar` (boolean, solo con ADVERTENCIA); nota de la versión |
| [`tests/Cambio/JustificarPrueba.php`](../micro-his-prescripciones/tests/Cambio/JustificarPrueba.php) | JUS-01..04 |
| `tests/mutaciones.php` | M34 (siempre `true`) y M35 (pregunta a RBAC sin advertencia) |
| `tests/Caracterizacion/esperado.json` | Una clave nueva en un escenario, regrabada con [`herramientas/reaprobar-caracterizacion.php`](herramientas/reaprobar-caracterizacion.php) |
| `tests/ejecutar.php`, `README.md` | Título del grupo «Cambios solicitados» y cifras |
| `semana-12/prototipo/*` | La hoja la decide `puede_justificar`; los permisos del servidor son una copia aparte; «RBAC retira un permiso» en «Más» |

## 5. Pruebas: rojo → verde

| Paso | Resultado | Evidencia |
|---|---|---|
| 1. Pruebas escritas, sin código | 111 pruebas · **3 fallan** (JUS-01, 03 y 04). JUS-02 pasa: hoy no hay clave ni consulta, y la prueba vigila que el cambio no agregue de más | [cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt) |
| 2. Código, contrato sin tocar | JUS-01 y 03 pasan. **Fallan JUS-04** (el contrato no documenta la clave) y **CAR-01**: la caracterización de la semana 7 detectó que la previa con advertencia ya no responde lo grabado | [cambio-2-codigo-sin-contrato.txt](evidencia/cambio-2-codigo-sin-contrato.txt) |
| 3. Re-aprobación controlada | 32 escenarios: 31 iguales, **1 con solo la clave nueva**, 0 otras diferencias → se regraba. El diff de `esperado.json` es una línea | [cambio-3-reaprobacion-caracterizacion.txt](evidencia/cambio-3-reaprobacion-caracterizacion.txt) |
| 4. Contrato 1.2.0 | 111 · 93 pasan · **0 fallan** · 18 omitidas (sin base) | [cambio-4-despues-verde.txt](evidencia/cambio-4-despues-verde.txt) |
| 5. PostgreSQL 18 | **111/111** | [cambio-5-postgres.txt](evidencia/cambio-5-postgres.txt) |
| 6. Mutaciones | **35/35**. M34 la detectan JUS-01 y JUS-03; M35, JUS-02 | [mutaciones.txt](evidencia/mutaciones.txt) |
| 7. Lint del contrato | Válido | [lint-openapi.txt](evidencia/lint-openapi.txt) |

**Por qué una herramienta para regrabar.** Regrabar la caracterización «porque falló» la vacía de sentido: deja de proteger. La herramienta compara los 32 escenarios y se niega a regrabar si cambió algo que el cambio no explica.

## 6. Demostración de punta a punta con el prototipo

| Paso | Escritorio | Móvil |
|---|---|---|
| 06 · Dr. López: ADVERTENCIA; la previa respondió `puede_justificar: true` → hoja con el área de texto | [captura](capturas/escritorio/07-justificar.png) | [captura](capturas/movil/07-justificar.png) |
| 24 · Dra. Ruiz: la previa respondió `false` → solo M-07, sin área de texto; no escribe en vano | [captura](capturas/escritorio/24-sin-override.png) | [captura](capturas/movil/24-sin-override.png) |
| 26 · RBAC retira el permiso al Dr. López **a mitad de la sesión**: su copia de permisos todavía dice «override», pero la API dice `false` → M-07 | [captura](capturas/escritorio/26-rbac-retira-override.png) | [captura](capturas/movil/26-rbac-retira-override.png) |

- **Paridad con el kernel real:** la huella incluye `puede_justificar`; 42 pasos, **0 diferencias** ([paridad.txt](evidencia/paridad.txt)). El paso 2 da `true` y el 11, `false`.
- **Control:** la misma paridad contra la API simulada de la semana 11, que no tiene el campo, detecta **2 diferencias**: exactamente los pasos 2 y 11 ([paridad-control.txt](evidencia/paridad-control.txt)).
- **Recorrido:** 86 comprobaciones, 0 fallas ([recorrido.txt](evidencia/recorrido.txt)).

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa descartada | Motivo |
|---|---|---|
| La regla vive en `PoliticaEmision` | Calcularla en `VerificarAlergiasPrevia` | Una sola fuente para «¿hace falta override?». Si la emisión cambia la regla, la previa cambia con ella |
| La clave aparece **solo** con ADVERTENCIA | Siempre presente (`false` sin advertencia) | «false» sin advertencia diría «no puede justificar» cuando no hay nada que justificar; además obligaría a preguntar siempre a RBAC (D-24) |
| La emisión sigue decidiendo | Que la emisión confíe en la previa | La previa es consultiva (R-03). El permiso puede cambiar entre las dos; la interfaz mantiene el manejo del 409 |
| Contrato 1.2.0 (menor) | 2.0.0 | Una clave opcional nueva en una respuesta no rompe a ningún cliente que ignore las claves que no conoce |
| Re-aprobar con una herramienta | Regrabar `esperado.json` con `bin/caracterizar.php` | Regrabar todo habría aceptado cualquier otra diferencia sin verla |

## 8. Riesgos y pendientes

- **El cliente debe ignorar las claves desconocidas.** Un cliente estricto con la versión 1.1.0 podría rechazar la respuesta. El cliente PHP del módulo (`ClientePrescripciones`) no valida claves extra; los clientes de otros módulos no se probaron.
- **Siguen abiertos A-2 y A-3** de la semana 11: la anulación idempotente y el nombre del alérgeno en el hallazgo. Se proponen para la revisión técnica (semana 13).
