# Registro del cambio solicitado — «Si se corta la red al corregir, el médico no sabe qué pasó» (B-3)

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Semana:** 12 (segunda evaluación parcial)

## 1. Solicitud

> «Cuando se pierde la respuesta de una corrección, el reintento responde 409 `nota_ya_corregida` y el médico cree que falló. Haga que reintentar una corrección sea tan seguro como reintentar un registro, **sin permitir dos correcciones de la misma versión**.»

La brecha estaba anotada desde la semana 8 (B-3) y se midió en la semana 10: el registro con `Idempotency-Key` devuelve la misma nota, pero la corrección no aceptaba clave. La interfaz tenía que adivinar (regla R-M6: abrir la corrección y comparar antes de decir «sí llegó»).

## 2. Requisito nuevo y restricciones

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| **RF-16** | La corrección acepta `Idempotency-Key`: repetir la misma corrección con la misma clave devuelve la versión ya creada, sin crear otra. | **Dado** una corrección enviada con la clave K, **cuando** llega otra vez igual con K, **entonces** responde 201 con la misma `clinical_note_id` y `Idempotent-Replayed: true`, y hay una sola versión nueva y un solo `NOTA_CORREGIDA`. (CA-18) |

| Regla que no se puede romper | Prueba |
|---|---|
| RF-12: cada versión se corrige una sola vez (sin clave sigue el 409) | CID-03, COR-03 |
| La misma clave con otro contenido no es un reintento (422) | CID-02 |
| La clave de un registro no sirve para una corrección | CID-04 |
| Solo el autor corrige; la clave no deja ver correcciones ajenas | CID-05, COR-04 |
| R-06: nota, diagnóstico, evento y clave en la misma transacción | REF-04, COR-07 |

## 3. Análisis de impacto (antes de programar)

| Opción | Por qué no / por qué sí |
|---|---|
| A. Dejar que la interfaz interprete el 409 (R-M6) | ✗ Cada cliente debe repetir la lógica y no distingue «mi corrección llegó» de «otro equipo corrigió primero». |
| B. Devolver la corrección existente si el contenido es igual, sin clave | ✗ El servidor no sabe si es el mismo intento; además revelaría correcciones de otros equipos del mismo médico. |
| **C. Reutilizar el puerto `RegistroIdempotencia` del registro, con una huella que incluye el tipo de petición y la nota** | ✓ **Elegida.** Mismo mecanismo ya probado (HTTP-11, HTTP-12, INT-06); no toca Dominio, puertos, adaptadores ni esquema SQL. |

| Pieza | Cambio |
|---|---|
| `CorregirNotaComando` | + `claveIdempotencia` opcional y `huella()` (empieza por `correccion` y la nota) |
| `CorregirNota` | + puertos `RegistroIdempotencia` y `RepositorioDiagnosticos`; busca la clave después de verificar acceso y la guarda en la misma transacción |
| `FormularioRegistroNota` / `FormularioCorreccionNota` | La lectura de la cabecera pasa a un método compartido (`claveIdempotencia()`) |
| `bootstrap/contenedor.php` | Pasa los dos puertos nuevos |
| `api/openapi.yaml` | 1.1.0 → **1.2.0**, compatible: solo agrega el parámetro opcional y la cabecera `Idempotent-Replayed` |

## 4. Pruebas: rojo → guarda → verde

Las pruebas CID-01..05 y la guarda API-05 se escribieron **antes** de tocar el código.

| Momento | Resultado (`php tests/ejecutar.php`) |
|---|---|
| 1. Pruebas escritas, sin implementación | **124 · 119 pasan · 4 fallan · 1 omitida.** CID-01 obtiene `[201, 409]` en vez de `[201, 201]`; CID-05 obtiene 201 con una clave inválida. Pasan CID-03 (compatibilidad) y API-05. |
| 2. Implementación, contrato sin tocar | **123 pasan · 1 falla: API-05.** «`POST …/correcciones`: código lee la clave / contrato la declara: esperado true, obtenido false». La guarda detectó que el código cambió y el contrato no. |
| 3. Contrato 1.2.0 | **124 · 123 pasan · 0 fallan · 1 omitida** (INT-06, PostgreSQL). `redocly lint`: válido. |

| Prueba nueva | Qué comprueba |
|---|---|
| CID-01 | Reintento con la misma clave: 201 + `Idempotent-Replayed`, misma versión, una sola corrección y un solo evento |
| CID-02 | Misma clave, otro contenido: 422 `idempotency_key_reutilizada`, nada guardado |
| CID-03 | Sin clave, el segundo envío sigue en 409 `nota_ya_corregida` |
| CID-04 | La clave de un registro no sirve para corregir: 422 |
| CID-05 | Clave inválida: 400; otro médico con la misma clave: 403 |
| API-05 | Las operaciones que leen `Idempotency-Key` la declaran en el contrato, y solo ellas |

## 5. Medición antes / después

| Medición | Antes | Después |
|---|---|---|
| Reintento de una corrección con la misma clave | 201 → **409** `nota_ya_corregida` | 201 → **201** misma versión |
| Correcciones guardadas tras el reintento | 1 | 1 |
| Reintento sin clave | 409 | 409 (sin cambio) |
| Lógica que necesita la interfaz | R-M6: consultar y comparar | Ninguna: misma respuesta que el registro |

**Costo** (`php bin/acoplamiento.php`): Ce de `CorregirNota` 16 → 19 y 76 → 99 líneas, con las mismas 4 preocupaciones; Ce total 244 → 248. Cero cambios en Dominio, puertos, adaptadores PDO/memoria y `database/schema.sql`. B-3 queda **cerrada**; R-M6 se mantiene solo para clientes que no manden la clave.
