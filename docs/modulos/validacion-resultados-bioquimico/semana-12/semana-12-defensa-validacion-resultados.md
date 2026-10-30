# Semana 12 — Segunda evaluación parcial: defensa de la validación de resultados por bioquímico

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Validación de resultados por bioquímico |
| **Proceso defendido** | Revisión, validación o rechazo de un resultado por bioquímico |
| **Autora** | Lopez Perez, Mercedes Azucena |
| **Fecha** | 27 de octubre de 2026 |

## 1. Alcance y qué es el prototipo

Se defienden las decisiones de componentes, UX, accesibilidad y movilidad del módulo, y se demuestra con el prototipo cómo se tratan roles, estados, errores y riesgos de resultados, revisión bioquímica, validación, rechazo, firma y trazabilidad.

El prototipo es el caso de uso PHP de la Semana 2 (`ReviewPendingLabResult`) con un adaptador en memoria. Se ejecuta por consola con un recorrido de 10 pasos (`recorrido/recorrido.php`). **No hay interfaz gráfica**: las decisiones de UX, accesibilidad y movilidad de la sección 4 son decisiones de diseño defendidas, no comportamiento demostrado. La firma no está implementada (ver REV13-03 de la Semana 13).

## 2. Presentación breve (5 diapositivas)

| # | Mensaje | Qué se muestra |
|---:|---|---|
| 1 | El problema: validar un resultado es una decisión clínica con consecuencias | Flujo de la Semana 1: validar, rechazar o no decidir |
| 2 | Componentes: el caso de uso decide, los contratos aíslan | Tres contratos (autorización, reloj, repositorio) y el caso de uso |
| 3 | Roles y estados | Pendiente → validado o rechazado; bioquímico autorizado frente a usuario sin permiso |
| 4 | Errores y riesgos | Nueve resultados del caso de uso y qué protege cada uno |
| 5 | Evidencia y cambio solicitado | Matriz decisión → RF/RNF → evidencia y la respuesta al cambio CC-01 |

## 3. Recorrido navegable

Se ejecuta desde la raíz del repositorio:

```bash
php docs/modulos/validacion-resultados-bioquimico/semana-12/recorrido/recorrido.php
```

La salida real está en [evidencia/recorrido-semana-12.txt](evidencia/recorrido-semana-12.txt). Cada paso se puede mostrar en orden:

| Paso | Escenario | Rol | Resultado | Qué demuestra |
|---:|---|---|---|---|
| 1 | Validar RES-1 | Bioquímico | VALIDATED | Decisión explícita con actor, hospital y momento; el valor no cambia |
| 2 | Rechazar RES-2 con motivo | Bioquímico | REJECTED | El rechazo conserva el resultado y el motivo |
| 3 | Rechazar RES-3 sin motivo | Bioquímico | INVALID_INPUT | Sin motivo no se decide |
| 4 | Validar RES-3 sin permiso | Usuario sin permiso | DENIED | La denegación no cambia estado |
| 5 | Resultado de otro hospital | Bioquímico | NOT_FOUND | No se revela un resultado ajeno |
| 6 | Contexto incompleto | Bioquímico | INVALID_CONTEXT | No se decide sin orden, examen y muestra |
| 7 | Decidir un resultado ya validado | Bioquímico | INVALID_STATE | La validación es definitiva en este flujo |
| 8 | Versión desactualizada | Bioquímico | CONFLICT | No se decide sobre una versión reemplazada |
| 9 | Falla de escritura | Bioquímico | PERSISTENCE_FAILURE | No se confirma éxito ni queda decisión parcial |
| 10 | Reintento tras la falla | Bioquímico | VALIDATED | La recuperación es posible sin duplicar |

Al final se comprueban 8 condiciones: estados finales, valor inalterado, motivo conservado y solo 3 decisiones registradas.

## 4. Decisiones defendidas

### Componentes

| Decisión | Razón | Riesgo que acepta |
|---|---|---|
| El caso de uso depende de tres contratos, no de clases concretas (DIP) | Cambiar la base de datos o el sistema de permisos sin tocar las reglas; pruebas sin infraestructura | Un adaptador real debe pasar las mismas pruebas |
| La escritura revalida versión y estado | Evita decidir sobre un resultado que cambió después de leerlo | Solo probado en memoria (REV13-07) |
| El caso de uso no codifica límites bioquímicos | La decisión clínica pertenece al bioquímico | Ninguno del lado del código; los límites deben venir del laboratorio |

### UX (decisiones de diseño, sin interfaz implementada)

- Una sola decisión por pantalla: validar o rechazar, con el contexto de orden, examen y muestra visible.
- Rechazar abre un campo de motivo obligatorio antes de confirmar; validar pide confirmación explícita porque es definitiva en este flujo.
- Los errores se muestran con acción: conflicto → «recargar»; falla → «reintentar»; sin permiso → mensaje genérico sin datos clínicos.
- Una acción no se presenta como exitosa hasta recibir la confirmación del servidor.

### Accesibilidad

- El estado se comunica con texto, no solo con color.
- Los mensajes de error se asocian al campo y se anuncian a lectores de pantalla.
- Toda acción se puede hacer con teclado; el orden de foco sigue el flujo (contexto → decisión → motivo → confirmar).
- Los valores y unidades se leen completos, sin abreviaturas ambiguas.

### Movilidad

- El caso principal es de escritorio por el volumen de datos del contexto; en móvil se prioriza consultar y decidir un resultado a la vez con confirmación.
- Con conexión limitada, una decisión pendiente no se muestra como confirmada y el reintento es seguro porque la versión esperada impide duplicar.

## 5. Matriz decisión → RF/RNF → evidencia

| # | Decisión | RF/RNF | Evidencia | Estado |
|---:|---|---|---|---|
| D1 | Solo un bioquímico autorizado decide | RF-01, RNF-03 | Paso 4 del recorrido; CA-04 | Demostrado (autorización simulada) |
| D2 | El hospital limita el acceso | RF-02, RNF-03 | Paso 5; CA-11 | Demostrado; el tenant no sale de la sesión (REV13-01) |
| D3 | Solo se decide la versión pendiente esperada | RF-03 | Pasos 7 y 8; CA-07, CA-08 | Demostrado |
| D4 | Validar no altera el valor | RF-04, RNF-05 | Paso 1 y comprobación final; CA-01 | Demostrado |
| D5 | Rechazar exige motivo y conserva el resultado | RF-05 | Pasos 2 y 3; CA-02, CA-03 | Demostrado |
| D6 | La decisión conserva actor, hospital y momento | RF-06, RNF-04 | Pasos 1 y 2; CA-01, CA-02 | Demostrado; sin firma (REV13-03) |
| D7 | Estado y registro se confirman juntos | RF-07, RNF-02 | Pasos 9 y 10; CA-09, CA-10 | Demostrado en memoria (REV13-07) |
| D8 | Cada fallo tiene una respuesta distinta | RF-08 | Los 10 pasos | Demostrado en el caso de uso; sin contrato API (REV13-05) |
| D9 | Contexto incompleto bloquea la decisión | RF-02 | Paso 6; CA-05 | Demostrado |
| D10 | Dependencias por contrato | RNF-01, RNF-06 | CA-13 | Demostrado |
| D11 | Interfaz accesible y móvil | — | Sección 4 | **Solo diseño; no demostrado** |
| D12 | Firma de la decisión | — | — | **No implementado** |

## 6. Roles, estados y riesgos

| Rol | Puede | No puede |
|---|---|---|
| Bioquímico autorizado en el hospital | Validar o rechazar un resultado pendiente de su hospital | Decidir en otro hospital; decidir un resultado ya decidido |
| Usuario sin permiso | — | Decidir; recibe denegación sin información clínica |
| Personal de laboratorio | Dar seguimiento a un rechazo (fuera de este flujo) | Validar |

Estados: **pendiente → validado** o **pendiente → rechazado**. Un resultado decidido no vuelve a pendiente en este flujo; una rectificación requiere un flujo separado (R-08).

| Riesgo | Control | Evidencia |
|---|---|---|
| Decidir sobre un valor que cambió | Versión esperada | Paso 8 |
| Decisión sin responsable | Actor y momento en cada decisión | Pasos 1 y 2 |
| Rechazo sin explicación | Motivo obligatorio | Paso 3 |
| Fuga de resultados ajenos | Misma respuesta que «no disponible» | Paso 5 |
| Falso éxito ante una falla | Resultado distinto y sin cambios | Paso 9 |
| Decisión sin firma ni ligada al valor | **Sin control** | REV13-03 |
| Autovalidación por quien capturó | **Sin control** | REV13-02 |

## 7. Respuesta documentada a un cambio solicitado

> **Aviso:** la tarea pide documentar la respuesta a «un cambio solicitado», pero no se recibió una solicitud concreta. El cambio CC-01 es **hipotético**, elegido porque corresponde al hallazgo REV13-02. Si el docente o el cliente plantea otro cambio, se reemplaza por él con el mismo formato.

**CC-01 — «Quien capturó un resultado no puede validarlo».**

| Elemento | Respuesta |
|---|---|
| Decisión | **Aceptado con condiciones**: la regla debe confirmarla el responsable de laboratorio, porque en un servicio pequeño puede haber un solo bioquímico |
| Requisitos afectados | RF-01 (autorización), RF-03 (decisión sobre la versión), RF-08 (resultados distintos); RNF-03 |
| Cambio de diseño | El resultado guarda el autor de la captura; el caso de uso compara el actor con ese autor antes de decidir |
| Respuesta al rechazo | Se propone un resultado nuevo e independiente de la denegación general para poder explicar el motivo sin revelar datos |
| Código afectado | `LabResult` (un campo nuevo), `ReviewPendingLabResult`, `ReviewOutcomeStatus`, el adaptador en memoria y los datos de prueba |
| Pruebas nuevas | Capturador intenta validar (se deniega); otro bioquímico valida (pasa); capturador rechaza (se decide con el responsable si también se prohíbe) |
| Dependencia | El módulo de ingreso de resultados debe entregar el autor de la captura |
| Riesgos | Bloqueo operativo si hay un único bioquímico; la excepción debe ser explícita, auditada y autorizada |
| Estimación | Cambio pequeño en código (un campo y una condición) y varias pruebas; el mayor costo es la decisión de negocio y la integración con ingreso de resultados |
| Estado | **No implementado.** Queda documentado y pendiente de decisión |

## 8. Preguntas de defensa y límites

Preguntas frecuentes en [GUIA_DEFENSA_ORAL.md](GUIA_DEFENSA_ORAL.md).

Límites:

- El recorrido es una ejecución en consola con un adaptador en memoria; no hay interfaz ni base de datos.
- UX, accesibilidad y movilidad son decisiones de diseño sin pruebas con usuarios ni mediciones.
- Firma y separación de funciones no están implementadas; son los hallazgos REV13-03 y REV13-02.
- El cambio CC-01 es hipotético y no se implementó.
- Los datos son sintéticos y no se evalúa criterio clínico.
