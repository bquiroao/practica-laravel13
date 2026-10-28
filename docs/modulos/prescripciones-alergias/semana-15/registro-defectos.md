# Registro de defectos — pruebas de la semana 15

**Estudiante:** Boris Alexander Quiroa Orellana · **Formato:** el de la [revisión técnica](../semana-13/formato-hallazgos.md) · **Fecha:** 27/10/2026

**Cómo se registra un defecto aquí:**
- Cada defecto abierto tiene una prueba en [`tests/Defectos/DefectosPrueba.php`](../micro-his-prescripciones/tests/Defectos/DefectosPrueba.php). La prueba describe el comportamiento **correcto**, y por eso **falla** ([evidencia](evidencia/4-defectos-abiertos.txt)).
- No corre en la suite por defecto, para que la suite principal siga en verde. Se ejecuta con `php tests/ejecutar.php --defectos --solo=DEF`.
- **Al corregirse**, la prueba pasa y se mueve a su grupo, como hizo DEF-02 (hoy CB-01 C4 y CB-02).

## Resumen

| ID | Origen | Severidad | Estado | Prueba | Detectado por |
|---|---|---|---|---|---|
| **DEF-02** | AR-02 (S13) | Mayor | ✅ **Cerrado** | CB-01 (C4) + CB-02 + M36 | Autorrevisión; lo reproduce la caja blanca |
| DEF-01 | AR-01 (S13) | **Crítico** | Abierto · plazo 30/10 | DEF-01 | Autorrevisión |
| DEF-03 | AR-03 (S13) | Mayor | Abierto · decisión de farmacia 03/11 | DEF-03 | Autorrevisión; SQA M3 |
| DEF-04 | AR-04 (S13) | Menor | Abierto | DEF-04 | Autorrevisión |
| DEF-05 | AR-05 (S13) | Menor | Abierto | DEF-05 | Medición de la semana 10 |
| DEF-06 | AR-06 (S13) | Menor | Abierto | DEF-06 | Prototipo de la semana 11 |
| DEF-07 | SQA M6 (S14) | Menor | Abierto | DEF-07 | Medición del plan SQA |
| **DEF-08** | **Nuevo** | **Mayor** | Abierto | DEF-08 | **Caja negra, valores límite (CN-02), esta semana** |

## DEF-02 · Otro prescriptor con la misma clave recibía «ya emitida» una prescripción ajena · Mayor · **Cerrado**

- **Qué pasaba:** el reintento se reconocía solo por la huella del contenido, que no incluye al usuario. La Dra. Ruiz, sin override, enviaba lo mismo con la clave del Dr. López y recibía `200 YA_EMITIDA` con la prescripción de él, sin que nada quedara a su nombre.
- **Rojo:** CB-01 (C4) y CB-02 (la condición F del MC/DC) fallan antes de la corrección; los otros 13 caminos pasan ([antes](evidencia/def-02-1-antes.txt)).
- **Corrección:** `EmitirPrescripcion::reintento` exige además el mismo prescriptor; si no lo es, 409 `CLAVE_REUTILIZADA` (`src/Aplicacion/CasosDeUso/EmitirPrescripcion.php`, una condición).
  - **Descartado:** incluir al usuario en la huella. Cambiaría el `content_hash` de lo ya guardado.
- **Verde:** CB-01 y CB-02 pasan ([después](evidencia/def-02-2-despues.txt)); la suite completa sigue en verde.
- **Vigilancia:** la mutación **M36** quita la condición, y la detectan CB-01 y CB-02 ([mutaciones](evidencia/mutaciones.txt)).
- **En el plan SQA:** M4 pasa de 11/12 a 12/12 ([medición después](evidencia/6-medir-sqa-despues.txt)). AR-02 queda `Cerrado` en el registro de la semana 13.

## DEF-08 · La dosis máxima diaria se aplica por ítem, no por medicamento · Mayor · **nuevo**

- **Cómo apareció:** al diseñar los valores límite de CN-02 **desde el requisito** («la dosis diaria no supera la máxima»), la pregunta fue: ¿y si el mismo medicamento aparece dos veces?
- **Reproducción:** dos ítems de Paracetamol 1000 mg cada 6 h (4000 mg/día cada uno, **8000 mg/día en total**, el doble de la máxima) → `200 SIN_HALLAZGOS`. Lo mismo pasa con dos vías distintas (oral y rectal).
- **Regla afectada:** RF-03, R-02. `ValidadorPrescripcion` compara cada ítem por separado (`src/Dominio/Reglas/ValidadorPrescripcion.php:54-56`).
- **Por qué es Mayor:** una sobredosis de Paracetamol es un daño clínico real, y la regla que la debía impedir no se dispara.
- **Corrección propuesta:** sumar la dosis diaria por medicamento (o por principio activo) antes de compararla, y señalar todos los ítems que la componen. Pide decidir con farmacia si se suma por **principio activo**: dos presentaciones del mismo fármaco.
- **Por qué no se corrigió esta semana:** cambia una regla clínica y lo que el médico ve. Necesita el criterio de farmacia (suma por medicamento o por principio activo) y una inspección (CP-4 del plan SQA).

## DEF-01 · Los tokens de demostración funcionan en cualquier entorno · Crítico

- **Reproducción:** con `PRESCRIPCIONES_ENTORNO=produccion`, `crearKernel()` + «Bearer token-dr-lopez» → **200**. Debería ser 401.
- **Corrección propuesta:** el autenticador se elige por configuración; fuera de desarrollo, el autenticador del HIS (JWT) y sin tokens demo.
- **Por qué no se corrigió:** la corrección necesita el autenticador real del HIS, que es otro módulo, y la tarea pide no probar módulos ajenos. El plan SQA le da plazo al 30/10.

## DEF-03 · Un alérgeno escrito de otra forma no coincide · Mayor

- **Reproducción:** una alergia grave «Amoxicilina trihidrato» → la previa de Amoxicilina responde `SIN_HALLAZGOS`. SQA M3 da 9/12.
- **Por qué no se corrigió:** es una decisión clínica (codificar los alérgenos o advertir ante un nombre que contiene el principio activo). Farmacia decide el 03/11.

## DEF-04 · «dias» y «frecuencia_horas» rechazan 7.0 aunque el contrato los declara enteros · Menor

- **Reproducción:** `"dias": 7.0` → 422. En JSON Schema, `7.0` es un `integer` válido.
- **Corrección propuesta:** aceptar decimales sin parte fraccionaria, o documentar que se envíen sin decimales.

## DEF-05 · El reintento de una anulación responde 409 sin decir quién anuló · Menor

- **Reproducción:** anular, y repetir la misma petición → 409 con solo `{error}`.
- **Corrección propuesta:** `Idempotency-Key` opcional en la anulación, o `anulada_por` y `anulada_en` en el 409.

## DEF-06 · El hallazgo trae el código del alérgeno sin su nombre · Menor

- **Reproducción:** la previa de P-001 con Cefalexina → `"alergeno": "J01CA04"`, sin `alergeno_nombre`.
- **Corrección propuesta:** agregar `alergeno_nombre` (cambio compatible del contrato).

## DEF-07 · El contrato documenta un código que nadie emite · Menor

- **Reproducción:** `SERVICIO_NO_DISPONIBLE` está en el enum de errores del contrato y no aparece en ningún archivo de `src/`.
- **Corrección propuesta:** retirarlo del contrato (1.2.1), o documentar quién lo emite (por ejemplo, el proxy del HIS).

## Observación (no es un defecto)

- **AR-07:** una intolerancia leve bloquea igual que una alergia. Bloquear de más es lo seguro; la decisión es de farmacia (plan SQA, RK-10).

## Lo que esto significa para el plan SQA

Medición después de la semana 15 ([evidencia](evidencia/6-medir-sqa-despues.txt)):

| Métrica | Antes (S14) | Después (S15) | Por qué |
|---|---|---|---|
| M1 Pruebas | 111/111 | **129/129** | 18 pruebas nuevas (UNI, CB, CN) |
| M2 Mutaciones | 35/35 | **36/36** | M36 vigila DEF-02 |
| M3 Sensibilidad | 9/12 | 9/12 | DEF-03 sigue abierto |
| M4 Accesos bloqueados | 11/12 | **12/12** | DEF-02 cerrado |
| M5 Bloqueo seguro | 4/4 | 4/4 | — |
| M6 Contrato | 20/23 códigos | 20/23 | **No cambió, aunque esperaba 22/23.** CN-06 sí afirma `RUTA_NO_ENCONTRADA` y `METODO_NO_PERMITIDO`, pero dentro del texto «404 RUTA_NO_ENCONTRADA», y M6 busca el código como literal entre comillas. Es una limitación de la medición, anotada para la revisión mensual; `SERVICIO_NO_DISPONIBLE` sí está sin emisor (DEF-07) |
| M7 Hallazgos | 1 crítico · 2 mayores | 1 crítico · **1 mayor** | AR-02 cerrado |

**DEF-08 no lo veía ninguna métrica del plan SQA.** Se agrega al corpus de M3 como un caso de dosis, o como una métrica propia, en la revisión mensual del 17/11.
