# Semana 12 — Presentación, recorrido navegable, matriz y cambio solicitado

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana (@bquiroao) |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final |
| **Presentación** | [PDF](presentacion/semana-12-qa-e2e.pdf) · [PPTX](presentacion/semana-12-qa-e2e.pptx) (5 diapositivas) |
| **Recorrido navegable** | Prototipo de la [semana 11](../semana-11/prototipo/index.html) con el cambio · [capturas](capturas/) · [recorrido-navegable.txt](evidencia/recorrido-navegable.txt) |

## 1. Recorrido navegable

Se recorre el prototipo sobre la API real, ya con el cambio:

| Recorrido | Pasos |
|---|---|
| Camino feliz | Release ve la **vista previa** «Resultado esperado: ✓ Aprobado» (6 reglas, calculadas por el servidor) → registra → «Coincide con la vista previa» → Operaciones despliega |
| Error crítico | La aprobación se reemplaza mientras se instala → 409 `GATE_NO_APROBADO` |

**Resultado: 88/88 comprobaciones y 0 violaciones de axe-core**, en escritorio (1280 px) y en móvil (390 px).

| Paso | Escritorio | Móvil |
|---|---|---|
| Vista previa y confirmación | [a03](capturas/escritorio/a03-confirmar-gate.png) | [a03](capturas/movil/a03-confirmar-gate.png) |
| Registrado, coincide | [a04](capturas/escritorio/a04-gate-aprobado.png) | [a04](capturas/movil/a04-gate-aprobado.png) |
| 409 al desplegar | [b03](capturas/escritorio/b03-gate-no-aprobado.png) | [b03](capturas/movil/b03-gate-no-aprobado.png) |

## 2. Matriz decisión → RF/RNF → evidencia

| # | Decisión de diseño | RF / RNF | Evidencia que la sostiene |
|---|---|---|---|
| D-1 | Solo datos `SINT-…` en escenarios | RF-01, RNF-06 | DOM-01; mutación M1 |
| D-2 | El plan incluye todos los críticos y se congela | RF-03 | DOM-02, DOM-03; M2, M3 |
| D-3 | Una ejecución vale para un digest | RF-04, RNF-07 | DOM-04, DOM-10; M4 |
| D-4 | Reintento que pasa = INESTABLE | RF-06 | DOM-05; M5 |
| D-5 | Reportar es idempotente; la misma clave en otra ejecución es conflicto | RF-13 | APP-03, DOM-11, INT-05; M11, M32 |
| D-6 | La evidencia se confirma antes de registrar (fail-closed) y las reglas antes del almacén | RF-05, RNF-04 | APP-04, APP-05, PER-05; M10, M12, M31 |
| D-7 | El gate es una función pura del dominio (DIP) | RF-09, RNF-01, RNF-02 | ARQ-01…06; DOM-08; ejemplo DIP de la semana 2 |
| D-8 | Una evaluación no se edita; cuenta la **última** | RF-10, RF-12 | CAM-02, INT-10; M26 |
| D-9 | Se despliega solo con el último gate aprobado, vigente y el mismo digest | RF-12, RNF-07 | CAM-01…06, INT-10; M27–M30 |
| D-10 | El pipeline no evalúa ni despliega | RF-14, RNF-05 | APP-06, CAM-06; config de permisos |
| D-11 | Contrato OpenAPI verificado contra el código | RF-01…14 | API-01…04 (API-01 se puso en rojo con este cambio) |
| D-12 | **Vista previa del gate sin efectos (cambio de esta semana)** | RF-09, RNF-02 | APP-11; M34; recorrido A3–A4 |
| D-13 | La consola no se deshabilita: lleva el foco al error | — (UX, WCAG 2.1.1 y 2.4.11) | R-F1 (semana 10); recorrido A6 |
| D-14 | La consola usa la API real, sin simulación | RNF-02 | Hallazgo H-1 de la semana 11 (5 reglas en la UI contra 6 en el servidor) |

## 3. Cambio solicitado: vista previa del gate (B-01)

**Pedido.** El hallazgo H-09 de la semana 9 (severidad 3) decía: «el botón nombra una decisión que el servidor recién toma al registrar». El responsable de release necesita saber **antes** qué decidirá el gate, sin que la consola copie la política.

| | Antes | Después |
|---|---|---|
| Contrato | 1.1.0: solo `POST /versiones/{v}/gate` (evalúa **y guarda**) | **1.2.0**, aditivo: `POST /versiones/{v}/gate/vista-previa` → `GATE_PREVISTO`, sin guardar |
| Aplicación | `EvaluarGate::ejecutar` | Se agrega `previsualizar`; las dos usan el mismo `calcular()` (la función pura `QualityGate::evaluar`) |
| Consola | «Las reglas se calculan al registrar» | «Resultado esperado: …» con las 6 reglas; al registrar, «Coincide» o «La decisión cambió» |
| Permiso | `e2e.evaluar` | El mismo |

**Cómo se hizo**, con la prueba primero:

| Paso | Qué pasó | Evidencia |
|---|---|---|
| 1 | APP-11 falla: `previsualizar` no existe | — |
| 2 | Implementación | APP-11 en verde |
| 3 | Se agrega la ruta: **API-01 se pone en rojo**, porque el contrato no conoce la operación | [cambio-1-api01-rojo.txt](evidencia/cambio-1-api01-rojo.txt) |
| 4 | Se documenta (1.2.0) | 89/89 contra PostgreSQL ([cambio-2](evidencia/cambio-2-suite-postgresql.txt)) |
| 5 | Mutación M34 («la vista previa registra la evaluación») | La detecta APP-11 ([mutaciones.txt](evidencia/mutaciones.txt)) |

**Riesgo residual.** La vista previa y el registro no pueden divergir por código, pero sí por tiempo:
- la ejecución puede cruzar las 24 h;
- la política puede cambiar.

Ese caso no se oculta: la consola dice «La decisión cambió al registrar».
