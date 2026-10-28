# Semana 12 — Segunda evaluación parcial: defensa de recepción de muestras y códigos identificadores

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Módulo** | Recepción de muestras y códigos identificadores |
| **Flujo** | «Recepción, identificación inequívoca y rechazo controlado de muestra» |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | 27 de octubre de 2026 |

## 1. Qué se entrega

1. Presentación breve (sección 2).
2. Recorrido navegable (sección 3): un recorrido ejecutable de 10 pasos y 12 comprobaciones sobre el prototipo real de la Semana 3.
3. Matriz decisión → RF/RNF → evidencia (sección 4).
4. Respuesta documentada a un cambio solicitado (sección 5).

**Aclaración sobre «recorrido navegable»:** el módulo no tiene interfaz. El recorrido es un script que recorre el caso de uso real y se complementa con los seis wireframes de la Semana 8, que son el diseño de la navegación. Lo que se demuestra con código es la lógica; lo que se demuestra con wireframes es la experiencia, y no se mezclan.

## 2. Presentación breve

**Diapositiva 1 — Problema.** Una muestra mal identificada o aceptada sin criterio contamina el resultado de un paciente. Hay que identificar cada recipiente de forma inequívoca y rechazar con motivo y trazabilidad.

**Diapositiva 2 — Decisiones de componentes.** Dominio (`SpecimenAcceptancePolicy`), caso de uso (`ReceiveSpecimen`), puertos (`SpecimenRepository`, `SpecimenIdentifierGenerator`), adaptadores (PDO y generador aleatorio). Las reglas no conocen la base de datos.

**Diapositiva 3 — UX y accesibilidad.** Una muestra por recepción, rechazo con motivo obligatorio, estados con texto y no solo color, foco al primer error (Semanas 8 y 9).

**Diapositiva 4 — Movilidad.** Se propone uso en tableta o móvil con lector de código; es una propuesta, no se implementó ni se probó.

**Diapositiva 5 — Roles, estados, errores y riesgos.** Ver las secciones 3 y 6.

## 3. Recorrido ejecutable

Comando, desde la raíz del repositorio:

```bash
php docs/modulos/recepcion-muestras/semana-12/recorrido/recorrido.php
```

Salida guardada en [evidencia/recorrido-semana-12.txt](evidencia/recorrido-semana-12.txt): **12 comprobaciones, 0 fallidas**.

| Paso | Qué demuestra | Tema del enunciado |
|---:|---|---|
| 1 | Un rol sin permiso no crea recepciones | Roles |
| 2 | Sangre íntegra queda aceptada con identificador `SP-...` | Estados, códigos únicos |
| 3 | Sello roto en sangre se rechaza con motivo | Rechazo |
| 4 | Sello roto en orina no es causa de rechazo | Reglas por tipo de muestra |
| 5 | Sin etiqueta y recipiente dañado: se acumulan los dos motivos | Errores |
| 6 | Dos recipientes de la misma orden tienen identificadores distintos | Códigos únicos, orden |
| 7 | Una falla de persistencia lanza error y no se informa éxito | Errores |
| 8 | **Límite:** el motivo libre se guarda sin catálogo | Riesgo |
| 9 | **Límite:** una orden inexistente se acepta | Riesgo, orden de laboratorio |
| 10 | Quedan 3 rechazos guardados | Estados |

**Honestidad sobre el rol:** la guarda de rol del paso 1 está escrita en el propio script. El prototipo de la Semana 3 **no implementa autorización**. Lo que el paso muestra es el comportamiento esperado, no una función existente.

## 4. Matriz decisión → RF/RNF → evidencia

| # | Decisión | Requisito | Evidencia | Estado |
|---|---|---|---|---|
| D1 | Reglas de aceptación aisladas en `SpecimenAcceptancePolicy` | RF de aceptación/rechazo | Pasos 2 a 5 | Demostrado |
| D2 | Identificador interno aleatorio por recipiente | RF de identificación inequívoca | Pasos 2 y 6 | Demostrado |
| D3 | Rechazo exige motivo y los acumula | RF de rechazo controlado | Pasos 3 y 5; dominio impide rechazo sin motivo | Demostrado |
| D4 | Repositorio detrás de un puerto | RNF de mantenibilidad | Pasos 2 y 7; pruebas de la Semana 3 | Demostrado |
| D5 | El error de guardado no se oculta | RNF de integridad | Paso 7 | Demostrado a nivel de excepción |
| D6 | Estados con texto además de color | RNF de accesibilidad | Wireframes de la Semana 8 y checklist de la Semana 9 | Diseño, no probado |
| D7 | Una muestra por recepción, foco al primer error | RNF de usabilidad | Semana 8 | Diseño, no probado |
| D8 | Autorización por rol y hospital | RNF de seguridad | Solo el paso 1, en el script | **No implementado** |
| D9 | Verificar la orden contra el sistema de órdenes | RF de vínculo con la orden | Paso 9 muestra la falta | **No implementado** |
| D10 | Cadena de custodia append-only | RF de trazabilidad | Semana 6 (propuesta) | **No implementado** |
| D11 | Uso móvil con lector de código | RNF de movilidad | Diapositiva 4 | Propuesta |

Los códigos RF/RNF de la tabla se nombran por tema porque la captura no trae una numeración oficial; la numeración exacta está en los documentos de las Semanas 2 y 6.

## 5. Respuesta a un cambio solicitado

**La captura pide una respuesta documentada a un cambio solicitado, pero no trae el texto del cambio.** Para no dejar el punto vacío se responde a un cambio **hipotético**, elegido porque el recorrido lo evidencia (paso 8) y porque coincide con el hallazgo 4 de la Semana 9. Si la docente entrega otro cambio, se reemplaza esta sección.

### CC-REC-01 (hipotético): el motivo de rechazo debe salir de un catálogo, no de texto libre

| Campo | Contenido |
|---|---|
| **Cambio** | El rechazo debe registrar un motivo de un catálogo del laboratorio, con una observación libre separada |
| **Situación actual** | El paso 8 demuestra que `rejectionReason` acepta cualquier texto |
| **Decisión** | Aceptar el cambio |
| **Componentes que cambian** | Dominio: nuevo enumerado o catálogo y validación en `ReceptionRequest`. Persistencia: columna del código del motivo. Presentación: lista desplegable |
| **Componentes que no cambian** | `SpecimenAcceptancePolicy` (sus reglas automáticas ya generan motivos propios), puertos y generador de identificadores |
| **Riesgo** | El catálogo real lo define el laboratorio; este diseño **no inventa códigos de rechazo** |
| **Migración** | Los rechazos anteriores con texto libre se conservan en la observación |
| **Pruebas que se agregan** | Un motivo fuera del catálogo se rechaza; el rechazo exige código; la observación libre es opcional |
| **Estado** | Propuesta, sin código |

## 6. Riesgos

| Riesgo | Estado | Mitigación propuesta |
|---|---|---|
| Aceptar una orden inexistente | Demostrado (paso 9) | Verificar contra el sistema de órdenes |
| Motivo de rechazo libre | Demostrado (paso 8) | CC-REC-01 |
| Sin autorización real | Declarado | Integrar con el módulo de usuarios |
| Sin custodia ni idempotencia | Declarado en la Semana 6 | Implementar según CHG-REC-06-01 |
| Reglas por examen no definidas | Pendiente del laboratorio | No inventarlas |

## 7. Limitaciones

- No hay interfaz; el recorrido es de lógica.
- El rol, la verificación de la orden y la custodia no existen en el prototipo.
- El cambio CC-REC-01 es hipotético.
- Los datos son sintéticos y el almacenamiento es SQLite en memoria.
