# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Tarea** | Diseñar y ejecutar pruebas trazadas a RF/RNF: unitarias de las reglas, caja blanca con ramas y condiciones del flujo, caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos |
| **Entregable** | Matriz RF/RNF → prueba, mínimo 3 unitarias, 2 de caja blanca y 4 de caja negra, comandos y resultados, capturas y defectos registrados |

## Entregables

| Pedido | Dónde |
|---|---|
| Matriz RF/RNF → prueba | [matriz-rf-prueba.md](matriz-rf-prueba.md): 14 RF y 8 RNF; 12 RF completos y 2 parciales, con su hallazgo |
| ≥ 3 unitarias | **4**: UNI-01..UNI-04 |
| ≥ 2 de caja blanca | **3**: CB-01 (ramas), CB-02 (MC/DC), CB-03 (caminos) |
| ≥ 4 de caja negra | **5**: CN-01 (equivalencia), CN-02 (límites), CN-03 (permisos), CN-04 (errores), CN-05 (consistencia) |
| Comandos y resultados | §4 y [evidencia/](evidencia/) |
| Capturas | [capturas/](capturas/): la terminal de cada corrida |
| Defectos registrados | [registro-defectos.md](registro-defectos.md): 8 del producto (5 cerrados) y 5 de las propias pruebas |

Las pruebas viven en el módulo, junto a las otras 100: [micro-his-qa/tests/Semana15/PruebasSemana15Prueba.php](../micro-his-qa/tests/Semana15/PruebasSemana15Prueba.php). Corren con `php tests/ejecutar.php`.

## 1. Estrategia

- **Prueba primero.** Las 12 pruebas se escribieron **antes** de corregir los hallazgos de la [revisión de la semana 13](../semana-13/registro-hallazgos.md). En rojo reproducen el defecto; en verde lo fijan.
- **Una mutación por corrección.** Cada corrección tiene una mutación que la deshace (M36–M40). Si la suite no la detecta, la prueba no protege nada.
- **Sin módulos ajenos.** El HIS central y el expediente no se prueban. El tenant y el usuario salen de los tokens de demostración del propio módulo.
- **Cada descripción nombra su RF y su regla**, para que la trazabilidad regla → prueba se pueda medir (M-3 del plan SQA).

## 2. Las 12 pruebas

### Unitarias: reglas del Dominio

| # | RF | Regla | Qué fija |
|---|---|---|---|
| UNI-01 | RF-10, RF-08 | R-09 | Un defecto sin clasificar cuenta con la severidad **propuesta**; reclasificarlo MENOR la baja |
| UNI-02 | RF-10 | R-09 | Sin ningún caso aplicable (todo NO_APLICA) la campaña cierra **NO_GO** con su propia causa |
| UNI-03 | RF-09 | R-08 | CORREGIDO exige un build `x.y.z(+commit)`; prueba 4 valores inválidos y 1 válido |
| UNI-04 | RF-10 | R-15 | Un MAYOR propuesto bloquea si el caso es crítico en la matriz, y no si no lo es |

### Caja blanca: ramas y condiciones

**CB-01 · Cobertura de ramas de `Campana::cerrar`.** El método tiene 7 decisiones. Cada una se toma por verdadero y por falso en alguna de las 9 filas:

| Rama | Condición | V en | F en |
|---|---|---|---|
| b1 | estado ≠ EN_CURSO | r1 | r2..r9 |
| b2 | hay casos pendientes | r2 | r3..r9 |
| b3 | ningún caso aplicable | r4 | r3, r5..r9 |
| b4 | críticos abiertos > 0 | r5, r8 | r3, r4, r6, r7, r9 |
| b5 | MAYOR en críticos > 0 | r6, r8 | r3, r4, r5, r7, r9 |
| b6 | hay aplicables y tasa < umbral | r7, r8 | r3, r4 (sin aplicables), r9 |
| b7 | sin causas → GO | r3, r9 | r4..r8 |

**CB-02 · MC/DC de `Defecto::bloqueaGo`** = (A ∧ B) ∨ (C ∧ B ∧ D):
- A: severidad efectiva CRÍTICA;
- B: no cerrado;
- C: severidad efectiva MAYOR;
- D: el caso es crítico en la matriz.

MC/DC pide que cada condición cambie el resultado por sí sola. Lo muestran estos pares de filas, que difieren en una única condición:

| Condición | Par | Resultado |
|---|---|---|
| A | c (CRÍTICA, abierto) / d (MENOR, abierto) | true / false |
| B en el primer término | c / b (CRÍTICA, cerrado) | true / false |
| C | e (MAYOR, abierto, crítico) / h (MENOR, abierto, crítico) | true / false |
| B en el segundo término | e / g (MAYOR, cerrado, crítico) | true / false |
| D | e / f (MAYOR, abierto, no crítico) | true / false |

Las filas a, i y j agregan la **severidad efectiva**: CRÍTICA o MAYOR propuestas y sin clasificar cuentan, y un CORREGIDO todavía bloquea.

**CB-03 · Caminos de la trazabilidad** (el código nuevo de RT-02). Recorre los tres caminos de la unión «defectos de esta campaña ∪ abiertos de sus casos»:
- un defecto de esta campaña;
- uno abierto de otra campaña, que se muestra con su `campana`;
- uno cerrado de otra campaña, que no se muestra.

Además verifica que un defecto que está en las dos listas aparezca una sola vez.

### Caja negra: por HTTP, desde el contrato

| # | Técnica | Clases o valores | Resultado esperado |
|---|---|---|---|
| CN-01 | Equivalencia del resultado | PASA válida · FALLA válida · FALLA sin evidencia · NO_APLICA sin motivo · valor fuera del enum · tipo equivocado | 201 · 201 · 422 · 422 · 422 · 400 |
| CN-02 | Valores límite | Tasa 0,5 con umbral 0,5 y 0,5001 · evidencia de 5 MB y de 5 MB + 1 byte | GO / NO_GO · 201 / 413 |
| CN-03 | Permisos | Tester, desarrollador, analista y producto en operaciones ajenas · producto consulta · otro hospital con su tenant y con el ajeno · Líder QA cierra | 403 · 200 · 404 / 403 · 200 |
| CN-04 | Errores | Campaña inexistente · JSON roto · almacén caído · campaña cerrada | 404 · 400 · 503 sin intento · 409 |
| CN-05 | Consistencia entre operaciones | CRÍTICA propuesta sin clasificar: la trazabilidad la marca con `bloquea_go` y el cierre la cuenta | 1 bloqueante, NO_GO por «1 defecto crítico abierto» |

## 3. Correcciones (de la revisión de la semana 13)

| Defecto | Hallazgo | Cambio en el código |
|---|---|---|
| DEF-01 · **crítico** | RT-01 | `Defecto::severidadEfectiva()` (la clasificada, o la propuesta); `criticoAbierto()` y el SQL de los conteos la usan (`COALESCE`) |
| DEF-02 | RT-02 | Puerto `RepositorioDefectos::abiertosDe()` en PDO, memoria y el doble de prueba. La trazabilidad muestra los abiertos de otras campañas con su `campana`; contrato **1.2.0**, aditivo |
| DEF-03 | RT-05 | `Campana::cerrar`: sin aplicables → NO_GO con causa y tasa 0 |
| DEF-04 | RT-06 | `Campana::FORMATO_BUILD` compartido por iniciar y corregir |
| DEF-05 | RT-07 | README con requisitos y un control de `mbstring` al arrancar |
| — | CP-02 del plan SQA | **Una sola regla de bloqueo:** `Defecto::bloqueaGo()`, que usa la trazabilidad, con la misma semántica que los conteos del cierre. CN-05 lo verifica de punta a punta |

Además, en las pruebas existentes:
- CON-06 afirmaba el defecto RT-01 como regla y se reescribió (DP-05).
- Las descripciones de APP-01, APP-09 y CAM-01 ahora nombran R-14, R-13 y R-15 (M-3).
- Las mutaciones M32 y M33 se reubicaron porque su código cambió.

## 4. Comandos y resultados

```bash
cd docs/modulos/qa-regresion-defectos/micro-his-qa
export QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa"
php tests/ejecutar.php --solo=UNI,CB,CN    # antes de corregir: 4 pasan, 8 fallan
php tests/ejecutar.php                     # después: 112/112
php tests/mutaciones.php                   # 40/40 detectadas
php ../semana-13/herramientas/sonda-revision.php   # 2 de 6 (RT-03 y RT-04, agendados)
php ../semana-14/herramientas/medir-sqa.php        # tablero SQA
```

| Corrida | Resultado | Evidencia | Captura |
|---|---|---|---|
| 0 · primera corrida de las pruebas nuevas | 10 fallan, **4 por errores de las pruebas** (DP-01..DP-04) | [pruebas-0-errores-en-las-pruebas.txt](evidencia/pruebas-0-errores-en-las-pruebas.txt) | [png](capturas/pruebas-0-errores-en-las-pruebas.png) |
| 1 · rojo, antes de corregir | **8 fallan por el defecto que buscan**; las 4 CN de regresión (CN-01..CN-04) ya pasan | [pruebas-1-antes-rojo.txt](evidencia/pruebas-1-antes-rojo.txt) | [png](capturas/pruebas-1-antes-rojo.png) |
| 2 · verde, con PostgreSQL | **112/112** | [pruebas-2-despues-verde.txt](evidencia/pruebas-2-despues-verde.txt) | [png](capturas/pruebas-2-despues-verde.png) |
| Mutaciones | **40/40** (M36–M40 nuevas) | [mutaciones.txt](evidencia/mutaciones.txt) | [png](capturas/mutaciones.png) |
| Sonda de la revisión | 6 → **2** (RT-03, RT-04) | [sonda-2-despues.txt](evidencia/sonda-2-despues.txt) | [png](capturas/sonda-2-despues.png) |
| Tablero SQA | **6 de 6 en umbral** (la línea base tenía 4 de 6) | [tablero-2-despues.txt](evidencia/tablero-2-despues.txt) | [png](capturas/tablero-2-despues.png) |

**Las 4 CN que pasaron en rojo** (equivalencia, límites, permisos y errores) no buscaban un hallazgo: fijan comportamiento que ya era correcto. Es la mitad del trabajo de una prueba de regresión, y por eso tampoco se esperaba verlas fallar.

## 5. Limitaciones

- **RF-02 y RF-11 siguen parciales** (DEF-06..DEF-08), registrados y agendados.
- **La caja negra entra por el kernel en proceso**, no por un socket. HTTP-01 y CLI-* ya cubren el transporte real (semana 5).
- **No hay medición de cobertura de líneas:** PHP sin Xdebug ni PCOV. La cobertura de ramas de CB-01 se documenta a mano, rama por rama, y las mutaciones hacen de control.
