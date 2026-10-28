# Plan de aseguramiento de la calidad (SQA) — Notas SOAP y diagnósticos

**Módulo:** ASII-11 Notas SOAP y diagnósticos · **Responsable del módulo:** Joshua Eduardo García Reyes · Estructura inspirada en IEEE 730.

## 1. Propósito y alcance

Asegurar, **de forma medible y continua**, cuatro atributos del Micro-HIS (`micro-his-notas-soap/`) y su contrato:

| Atributo | Qué significa aquí | Métricas |
|---|---|---|
| Calidad del flujo | Registrar, firmar, corregir y consultar hacen lo que dicen R-01..R-08 y RF-01..RF-16 | M1, M2 |
| Confiabilidad | Ante una falla nunca queda una nota sin diagnóstico, una firma a medias ni un duplicado por reintento | M4, M7 |
| Seguridad | Cada hospital ve solo lo suyo; el contrato no promete ni oculta comportamiento | M3, M5, M6 |
| Trazabilidad | Cada escritura deja su evento, y cada hallazgo de revisión se sigue hasta verificarse | M4, M6 |

**Fuera de alcance:** el prototipo de interfaz (tiene su recorrido automático, S11) y el resto del HIS.

## 2. Responsables

| Rol | Persona | Responsabilidades SQA |
|---|---|---|
| **Responsable del módulo** (RM) | Joshua Eduardo García Reyes | Código, pruebas y contrato; corre la medición semanal; ejecuta las acciones correctivas. Dueño de M1–M5 y M7. |
| **Responsable SQA** (RSQA) | Compañero/a del grupo *(por confirmar)* | **Independiente** del autor: revisa el tablero, abre y cierra no conformidades y decide si un ROJO bloquea. Dueño de M6. |
| **Revisor de seguridad** (RS) | R1 de la revisión S13 *(por confirmar)* | Revisa cambios en `Kernel`, `Rutas`, autenticación y aislamiento por hospital |
| **Moderador** (MR) | El de la revisión S13 *(por confirmar)* | Conduce las revisiones formales (CP-03) y verifica el retrabajo |
| **Integrador** | Docente / responsable de `develop` | Aprueba el merge cuando no hay ROJO bloqueante |

**Regla de independencia:** quien corrige un hallazgo (RM) no lo da por verificado (RSQA o MR).

## 3. Métricas (7)

[herramientas/medir-sqa.php](herramientas/medir-sqa.php) las mide sobre el código real. **VERDE** cumple el umbral · **ÁMBAR** cumple con una condición pendiente · **ROJO** incumple y abre una no conformidad (§6).

| ID | Métrica | Fórmula | Fuente | Umbral | Resp. | Frecuencia | Acción si incumple | Línea base S14 |
|---|---|---|---|---|---|---|---|---|
| M1 | Pruebas en verde | pasan ÷ (total − omitidas) | `php tests/ejecutar.php` | 100 %; omitidas ≤ 1 | RM | Cada PR + semanal | Bloquear el merge y corregir primero | **VERDE** 100 % (123/123) |
| M2 | Códigos de error afirmados | códigos del enum del contrato que alguna prueba afirma ÷ 18 | `openapi.yaml` × `tests/**` | 100 % | RM | Semanal + PR que toque errores | Agregar la prueba que afirme el código | **ROJO** 66.7 % (12/18) |
| M3 | Contrato ↔ código | operaciones con mismo método, ruta y `x-permiso` ÷ operaciones | `Rutas` + `openapi.yaml` (+ API-01..05, lint) | 100 % | RM | PR que toque `Rutas` o `api/` | Bloquear; actualizar contrato y versión en el mismo PR | **VERDE** 100 % (5/5) |
| M4 | Integridad ante fallas (R-06) | escenarios con falla inyectada sin efectos a medias ÷ 5 | Dobles de falla del Micro-HIS | 100 % | RM | Semanal + PR en `Aplicacion` o `Persistencia` | Hallazgo Crítico: bloquear | **VERDE** 100 % (5/5) |
| M5 | Aislamiento por hospital (R-01) | intentos de un médico de otro hospital rechazados ÷ 5 (leer, listar, firmar, corregir, registrar) | Kernel real con un token de otro hospital | 100 % | RM, RS | Semanal | Hallazgo Crítico: no se libera | **ROJO** 60 % (3/5) |
| M6 | Hallazgos Crítico/Mayor sin verificar | filas con esa severidad y estado ≠ Verificado/Rechazado | `semana-13/plantillas/registro-hallazgos.csv` | 0 Crítico siempre; 0 Mayor para liberar | RSQA | Semanal | Crítico: detener la liberación; Mayor: plan con fecha | **ROJO** 2 (Crítico 1 · Mayor 1) |
| M7 | Escrituras con reintento seguro | escrituras cuyo reintento idéntico no duplica, como el contrato permite llamarlas ÷ 4 | Kernel real | 100 % | RM | PR que toque escrituras | Exigir la clave o detectar el duplicado | **ROJO** 75 % (3/4) |

**Línea base: 3 VERDE · 0 ÁMBAR · 4 ROJO.** Los cuatro ROJO tienen acción en §7.

## 4. Controles preventivos

| ID | Control | Resp. | Umbral | Fuente | Acción si se incumple | Frecuencia | Riesgos |
|---|---|---|---|---|---|---|---|
| CP-01 | **Pruebas primero** en todo cambio de reglas, rutas o casos de uso, con rojo → verde en el PR (como en S6 y S12) | RM | 100 % de esos PR | Descripción del PR | RSQA devuelve el PR | Cada PR | RN-01, RN-05 |
| CP-02 | **Guardas** de capas y contrato dentro de la suite (ARQ, API-01..05) | RM | 20/20 en verde | Suite | Bloquear el merge | Cada PR | RN-01, RN-06 |
| CP-03 | **Revisión técnica formal** (S13) antes de liberar cambios de dominio, contrato o seguridad | MR | E-1..E-8 y acta con S-1..S-5 | `semana-13/herramientas/criterios-entrada.php` | Sin acta no se libera | Por liberación | RN-02, RN-03 |
| CP-04 | **Hospital en cada dato**: toda lectura filtra por el hospital del expediente, no solo por la cabecera | RM | M5 = 100 % | Prueba nueva + M5 | Hallazgo Crítico | Cada PR de lectura | RN-02 · **pendiente (S15)** |
| CP-05 | **Configuración por entorno**: sin credenciales en el código y tokens de demostración solo en desarrollo | RM | 0 tokens fijos fuera de desarrollo | Revisión RT-15 | Bloquear el despliegue | Por liberación | RN-03 · **pendiente (S15)** |
| CP-06 | **Contrato primero**: cambio de API = `openapi.yaml` y versión en el mismo PR | RM | Lint válido, M3 = 100 % | `npx @redocly/cli@1.34.3 lint` | Bloquear el merge | PR de `api/` o `Rutas` | RN-06 |

## 5. Controles detectivos

| ID | Control | Resp. | Umbral | Fuente | Acción si se incumple | Frecuencia | Riesgos |
|---|---|---|---|---|---|---|---|
| CD-01 | **Suite completa** | RM | M1 = 100 % | `php tests/ejecutar.php` | Bloquear; issue si es intermitente | Cada PR | RN-01, RN-06, RN-08 |
| CD-02 | **Medición SQA** y tablero | RM corre · RSQA revisa | 0 ROJO para liberar | `php herramientas/medir-sqa.php` | No conformidad por cada ROJO | Semanal (lunes) | Todos |
| CD-03 | **Lint del contrato** | RM | Válido | Redocly | Bloquear el merge | PR de contrato | RN-06 |
| CD-04 | **Inyección de defectos** (mutantes 1 y 2, [§2 del documento](semana-14-sqa-notas-soap.md#2-los-controles-detectan-defectos-comprobado)) | RSQA | Cada mutante pone ≥ 2 métricas en ROJO | `SQA_MICROHIS=<copia> php herramientas/medir-sqa.php` | Si sobrevive, falta una prueba | Por iteración | RN-01, RN-04 |
| CD-05 | **Recorrido automático** del prototipo | RM | 28/28, axe 0 | `semana-11/herramientas/recorrido.cjs` | Bloquear el merge del prototipo | PR del prototipo | RN-09 |
| CD-06 | **Revisión de la bitácora**: lecturas entre hospitales y notas sin `NOTA_REGISTRADA` | RS | 0 casos | Consulta sobre `audit_events` (requiere PostgreSQL) | Incidente de seguridad | Semanal desde S15 | RN-02, RN-04 · **pendiente** |

## 6. No conformidades

| Situación | Quién la abre | Plazo | Escalamiento |
|---|---|---|---|
| Métrica en ROJO | RSQA | Acción con fecha en ≤ 2 días hábiles | Si sigue en ROJO la semana siguiente: al Integrador y se congela la liberación |
| Hallazgo Crítico | Quien lo detecte | Corrección inmediata | Al Integrador el mismo día |
| Hallazgo Mayor | MR o RSQA | Plan con fecha, verificado antes de liberar | Vencido el plazo, pasa a bloqueante |

Se registran en el [formato de la S13](../semana-13/formato-hallazgos.md) con prefijo `NC-`.

## 7. Plan de acción de la línea base

| ROJO | Acción | Resp. | Plazo | Verificación |
|---|---|---|---|---|
| M5 60 % (AR-01) | Guardar el hospital del expediente y filtrar por él al leer y listar | RM | S15 | M5 = 100 % y prueba nueva |
| M6 (AR-01, AR-04) | Corregir AR-01 (arriba) y AR-04 (autenticador por entorno) | RM; verifica MR | S15 | Estado «Verificado» en el registro |
| M2 66.7 % | Afirmar los 6 códigos que faltan: `tenant_requerido`, `token_invalido`, `ruta_no_encontrada`, `metodo_no_permitido`, `nota_firmada`, `servicio_no_disponible` | RM | S15 | M2 = 100 % |
| M7 75 % (B-6) | Exigir `Idempotency-Key` al registrar (contrato 2.0 o aviso de deprecación) | RM | S16 | M7 = 100 % |

## 8. Calendario de seguimiento

| Cadencia | Qué se hace | Quién |
|---|---|---|
| Cada PR | CD-01, CD-03 y CP-01, CP-02, CP-06 | RM; RSQA revisa |
| Semanal (lunes) | CD-02: medición y tablero; no conformidades nuevas | RM corre; RSQA revisa |
| Por iteración | CD-04 (mutantes) y revisión de la matriz de riesgos | RSQA |
| Por liberación | CP-03 (acta de revisión) y CP-05 | MR, Integrador |

| Hito | Meta |
|---|---|
| S14 (hoy) | Línea base medida: 3 VERDE · 4 ROJO; mutantes detectados |
| S15 | Pruebas y retrabajo: M2, M5 y M6 en VERDE; CP-04 y CP-05 implementados |
| S16 | Integración: M7 en VERDE y 0 ROJO para liberar |
