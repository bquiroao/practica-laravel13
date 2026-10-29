# Semana 15 — Pruebas unitarias, caja blanca y caja negra

Módulo: **Alergias clínicas y alerta visual en expediente** · Flujo: «registro de alergia y emisión de alerta al consultar el expediente»

**Archivo de pruebas:** [`../micro-his/pruebas/semana15.php`](../micro-his/pruebas/semana15.php). Tiene 15 pruebas: **5 unitarias, 3 de caja blanca y 7 de caja negra**. Solo usa código del módulo y la base de prueba propia `micro_alergias_test`: **no prueba módulos ajenos**.

Las pruebas marcadas `[DEFECTO D-NN]` describen el comportamiento **correcto**. Fallan mientras el defecto siga abierto y el ejecutor las cuenta aparte («con defecto registrado»), no como fallas.

## 1. Matriz RF/RNF → prueba

| Requerimiento | Pruebas de esta semana | Pruebas anteriores que también lo cubren |
|---|---|---|
| RF-01 Registrar | CN-01, CN-02, CN-05, CN-07 (D-03), CB-01 | T-01, T-10, T-39 |
| RF-02 Paciente existente y activo | CN-04 (404) | T-04, T-08 |
| RF-03 Sin duplicados activos | U-01, CN-04 (409), CN-06 (D-01) | T-02, T-11, T-41, C-3 |
| RF-04 Nivel y orden | U-02, CB-02, U-05 (D-02) | T-05, T-09, T-35 |
| RF-05 «Sin registro» no es «sin alergias» | CB-02 (lista vacía) | T-06, T-19 |
| RF-06 Reconocimiento ALTA/CRÍTICA | U-04 | T-15, T-18 |
| RF-07 Huella invalida reconocimientos | CB-02 (huella) | T-16, T-42 |
| RF-08 Permisos por rol | CN-03 | T-37, T-38 |
| RF-09 Inactivar o resolver | — (CU-06 fuera del Micro-HIS) | T-14 (la base impide `DELETE`) |
| RNF-01 Tenant del token | CB-03 | T-38 |
| RNF-02 p95 < 300 ms | — (se mide con `scripts/medir-frontera.php`, M-04) | — |
| RNF-03 Accesibilidad | — (semana 9: `contraste.py` y axe) | T-19 |
| RNF-04 Observabilidad y formato de error | CN-04 | T-36, T-43 |
| RNF-05 Sin datos a medias | CN-04 (503 con 0 filas) | T-12, T-13, T-44 |
| RNF-06 Privacidad | — | T-43 |
| RNF-07 OCP | — | `semana-02/ejemplo-ocp/verificar.php` |

## 2. Diseño de las pruebas

### Unitarias (reglas del Dominio, sin base)

| ID | Regla | Técnica | Datos |
|---|---|---|---|
| U-01 | RN-01, RN-03 | Valores representativos | `"  PENICILINA "`, código + nombre, ambos vacíos |
| U-02 | RN-08 | Tabla de decisión completa | Las 5 severidades |
| U-03 | RN-04 | Valores límite | 500 y 501 caracteres multibyte (`á`), solo espacios y tabulador |
| U-04 | RN-09 | Tabla de decisión completa | Los 5 niveles |
| U-05 | RN-08 | Error supuesto | «Reacción anafiláctica» → **D-02** |

### Caja blanca

| ID | Unidad | Criterio de cobertura | Caminos o condiciones |
|---|---|---|---|
| CB-01 | `RegistrarAlergia::fecha()` | Caminos | (1) `null`, (2) `''`, (3) válida, (4) `createFromFormat` desbordada (`2019-02-30` → 03-02, detectada por la comparación), (5) `createFromFormat` = `false` (`02/03/2019`) |
| CB-02 | `CalculadorAlerta::calcular()` | Ramas | Ciclo que no se ejecuta (lista vacía); regla que devuelve `null`; regla que sube el nivel; desempate del `usort` por clave; huella independiente del orden |
| CB-03 | `KernelApi::resolver()`, condición `A ‖ (B ∧ C)` del tenant | MC/DC | A = hospital del token distinto; B = hay encabezado; C = encabezado distinto. Casos: (A=1) → 403; (A=0, B=1, C=1) → 403; (A=0, B=1, C=0) → 200; (A=0, B=0) → 200 |

### Caja negra (API v1)

| ID | Técnica | Clases y valores |
|---|---|---|
| CN-01 | Partición de equivalencia | Válidas: 5 severidades → 201. Inválidas: `grave` (español), `SEVERE` (mayúsculas), `''` → 422 |
| CN-02 | Valores límite | Fecha = hoy (America/Guatemala) → 201; hoy + 1 → `FECHA_INICIO_FUTURA`; `2026-02-29` → `FECHA_INVALIDA` |
| CN-03 | Tabla de decisión rol × operación | 3 roles × 3 operaciones (9 combinaciones) |
| CN-04 | Conjetura de errores | 401, 400, 404, 409 y 503 (con 0 filas nuevas), todos con el formato `{codigo, mensaje, request_id}` |
| CN-05 | Valores límite | Reacción de 500 → 201; de 501 → 422 |
| CN-06 | Equivalencia (dos formas de nombrar la misma sustancia) | Código + nombre, luego solo nombre → **D-01** |
| CN-07 | Conjetura de errores (tipos JSON) | `"reaccion": 123` → **D-03** |

## 3. Comandos y resultados

Desde `docs/modulos/alergias-clinicas-alerta-visual-expediente/micro-his`, con Docker `healthy`:

```powershell
php scripts/crear-esquema.php
php pruebas/ejecutar.php --solo=semana15 --evidencia=../semana-15/evidencia/salida-pruebas-semana15.txt
php pruebas/ejecutar.php --evidencia=../semana-15/evidencia/salida-pruebas-todas.txt
```

| Comando | Resultado esperado (corrida de preparación) | Resultado en tu equipo |
|---|---|---|
| `--solo=semana15` | `RESULTADO: 15 pruebas, 12 correctas, 0 fallidas, 0 omitidas, 3 con defecto registrado` | `<pega aquí>` |
| Todas | `RESULTADO: 59 pruebas, 56 correctas, 0 fallidas, 0 omitidas, 3 con defecto registrado` | `<pega aquí>` |

**Capturas:** tómalas de tu terminal con las dos corridas y guárdalas en `evidencia/salida-pruebas-semana15.png` y `evidencia/defectos.png`.

## 4. Defectos registrados

| ID | Severidad | Prueba | Pasos | Esperado | Obtenido | Causa | Corrección propuesta | Estado |
|---|---|---|---|---|---|---|---|---|
| D-01 | Menor | CN-06 | POST `{codigo: J01CA04, nombre: Penicilina}` y luego POST `{nombre: Penicilina}` | 409 `ALERGIA_DUPLICADA` | 201: dos alergias activas de Penicilina | La clave es «código si existe, si no el nombre», igual que el índice real `allergies_active_equivalent_unique` (RT-02) | Proponer al líder: comparar también por nombre cuando ambos registros lo traen, en la Aplicación y en el índice (migración revisada) | Abierto |
| D-02 | **Mayor** | U-05 | Registrar Ibuprofeno `moderate` con la reacción «Reacción anafiláctica» | Alerta CRÍTICA | MODERADA: la regla busca «anafila» y el texto dice «anafilá» | `str_contains` sin normalizar tildes (RT-01) | Normalizar con `Normalizer::normalize(..., FORM_D)` + quitar las marcas diacríticas, o buscar con `/anafil[aá]/iu`. Agregar variantes («shock anafiláctico», «ANAFILAXIA») a U-05 | Abierto |
| D-03 | Menor | CN-07 | POST con `"reaccion": 123` | 400 `FORMATO_INVALIDO` | 201: se guarda la alergia **sin** reacción | `KernelApi::registrar` descarta los valores que no son texto en lugar de rechazarlos (RT-03) | Validar el tipo de cada campo presente: si viene y no es texto → 400 | Abierto |

D-02 es el único **Mayor**: puede rebajar una alergia con antecedente de anafilaxia. Cumple el criterio de salida de la semana 13 porque tiene una acción decidida (una prueba que lo documenta y una corrección propuesta), y M-03 de la semana 14 lo cuenta como abierto.
