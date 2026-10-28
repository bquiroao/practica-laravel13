# Autorrevisión del autor (criterio de entrada E-6)

**Autor:** Boris Alexander Quiroa Orellana · **Fecha:** 27/10/2026 · **Commit:** el de [`criterios-entrada-2-despues.txt`](evidencia/criterios-entrada-2-despues.txt)

## Qué es y qué no es

**Qué es:**
- Una pasada del autor por los 15 ítems del [checklist](checklist-revision.md) **antes** de la reunión.
- Su propósito: que la reunión no gaste tiempo en lo que el propio autor podía encontrar.
- Cada hallazgo se **reproduce** contra el kernel real con [`herramientas/verificar-autorrevision.php`](herramientas/verificar-autorrevision.php) ([salida](evidencia/autorrevision-verificaciones.txt)).

**Qué no es:** **no reemplaza** a los revisores. El autor tiene puntos ciegos sobre sus propias decisiones (ver el final).

## Resultado por ítem

| Ítem | Resultado | Nota |
|---|---|---|
| RT-01 Prescripción completa y dosis | ⚠️ AR-04 | Las reglas funcionan (DOM-01, DOM-02, M5); el tipo «entero» es más estricto que el del contrato |
| RT-02 Coincidencia directa | ⚠️ **AR-03** | Por código, correcta. Por nombre, la coincidencia es exacta tras normalizar: una alergia **grave** escrita «Amoxicilina trihidrato» no bloquea |
| RT-03 Cruzada y advertencia | ✅ (riesgo en AR-03) | DOM-04, DOM-09, APP-06/07, JUS-01..03. Un alérgeno sin grupo conocido no advierte («Penicilina G»), dentro de AR-03 |
| RT-04 Sin registros ≠ sin alergias | ✅ | APP-08, M6. Un paciente con solo alergias resueltas cuenta como evaluado (P-005): es lo correcto, porque alguien las registró |
| RT-05 Bloqueo seguro | ✅ | APP-13, ANU-06, INT-06, CLI-05, M1, M2, M20, M21 |
| RT-06 Reverificación al emitir | ✅ | APP-10; el reintento no reverifica a propósito (APP-17), pero ver AR-02 |
| RT-07 Contrato = código | ⚠️ AR-04, AR-06 | API-01..03 y JUS-04 en verde; lint válido |
| RT-08 Errores sin fuga | ⚠️ **AR-02** | El 404 es idéntico; pero un reintento con una clave **ajena** devuelve la prescripción de otro médico |
| RT-09 Formato en la frontera | ✅ | MVC-01, MVC-04, HTTP-05, ANU-07 |
| RT-10 Permiso en el caso de uso | ⚠️ **AR-02** | Cada caso de uso pregunta antes de leer; pero el reintento responde **antes** de evaluar el override del que reintenta |
| RT-11 Tenant | ✅ | HTTP-04, APP-11, CON-MEM-02..04, M9, M11, M12, M16 |
| RT-12 Identidad del actor | ❌ **AR-01** | El actor sale del token (✅), pero los tokens demo están siempre activos |
| RT-13 Inmutabilidad y anulación | ⚠️ AR-05 | Correcta (INT-02, INT-10, M14, M32); el reintento de una anulación responde 409 sin contexto |
| RT-14 Idempotencia y traza | ⚠️ **AR-02** | Todo o nada correcto (APP-14, INT-05); la clave es del hospital, no del prescriptor |
| RT-15 Capas y dependencias | ✅ · observación AR-07 | ARQ-01..10, CLI-06; sin Composer; el Expediente detrás de un puerto con tiempo límite y circuito. El `tipo` de la alergia se lee y no se usa |

## Hallazgos

### AR-01 · La autenticación de demostración no se puede desactivar · Crítico · Riesgo

- **Dónde:** `bootstrap/contenedor.php:61-65` · `config/config.php:20-25`
- **Qué pasa:** `crearKernel()`, el punto de entrada real, construye **siempre** `AutenticadorTokenDemo` con 4 tokens fijos que están en el repositorio. Uno de ellos (`token-dr-lopez`) puede emitir, autorizar advertencias y anular.
- **Evidencia:** `crearKernel(config)` + «Bearer token-dr-lopez» → **HTTP 201 EMITIDA**. No hay ninguna variable de entorno que lo impida.
- **Por qué es crítico:** cualquiera que lea el repositorio puede emitir y anular prescripciones a nombre de un médico si el servicio se despliega tal cual.
- **Propuesta (para después de la reunión):** el autenticador se elige por configuración. Sin `PRESCRIPCIONES_ENTORNO=desarrollo`, el servicio arranca con el autenticador del HIS (JWT) y **sin** tokens demo (fail-closed).
- **Nota:** Auditoría tiene el mismo hallazgo (su AR-01): el patrón se copió entre módulos.

### AR-02 · La clave de idempotencia es del hospital, no del prescriptor · Mayor · Defecto

- **Dónde:** `src/Aplicacion/CasosDeUso/EmitirPrescripcion.php:60-63` y `124-128` · `database/schema.sql` (`UNIQUE (hospital, idempotency_key)`)
- **Qué pasa:** el reintento se busca por `(hospital, clave)` y se compara la huella, que **no incluye al usuario**. Si otro médico del mismo hospital envía el mismo contenido con la misma clave, recibe `200 YA_EMITIDA` con la prescripción del primero. Además, el reintento responde **antes** de evaluar el permiso override del que reintenta.
- **Evidencia:**

  | Petición | Respuesta |
  |---|---|
  | dr-lopez emite con advertencia justificada y la clave «clave-compartida» | 201 EMITIDA #1 |
  | dr-ruiz (**sin** override) envía lo mismo con la misma clave | **200 YA_EMITIDA #1** |
  | dr-ruiz con una clave propia | 409 JUSTIFICACION_REQUERIDA (lo correcto) |

  El prescriptor de #1 es dr-lopez, y no queda ninguna traza a nombre de dr-ruiz.
- **Consecuencia:** la Dra. Ruiz cree que emitió, pero no se creó nada a su nombre y nada lo registra. Las claves son UUID del cliente, así que la colisión es improbable por azar. Puede ocurrir con un cliente defectuoso (una clave fija) o con una clave copiada a propósito.
- **Propuesta:** que la huella incluya al usuario y que un reintento de otro usuario responda 409 `CLAVE_REUTILIZADA`, o bien que la unicidad sea `(hospital, prescriptor, clave)`.

### AR-03 · La coincidencia por nombre es exacta: un alérgeno escrito de otra forma no bloquea · Mayor · Riesgo

- **Dónde:** `src/Dominio/Reglas/VerificadorAlergias.php:40-55` · `CatalogoEnMemoria::grupoDelAlergeno` y `PdoCatalogoMedicamentos::grupoDelAlergeno`
- **Qué pasa:** si la alergia tiene código, la comparación es por código y funciona. Si solo tiene **nombre** (texto libre del Expediente), se compara el nombre normalizado completo con el principio activo. Una sal, un apellido del fármaco o un sinónimo no coinciden, y tampoco se encuentra su grupo.
- **Evidencia** (P-005, alergia **grave** activa, previa de Amoxicilina 500 mg):

  | Alergia registrada como | Resultado |
  |---|---|
  | «Amoxicilina» (control) | BLOQUEO |
  | «Amoxicilina trihidrato» | **SIN_HALLAZGOS** |
  | «Amoxicilina (sal sódica)» | **SIN_HALLAZGOS** |
  | «Penicilina G» | **SIN_HALLAZGOS** (ni siquiera la advertencia cruzada) |

- **Consecuencia:** es un **falso negativo**: la regla que existe para impedir el daño no se dispara. La tabla real `allergies` del HIS permite registrar solo el nombre (P-002, P-006 y P-007 de los datos demo lo hacen).
- **Propuesta:** no se resuelve con más normalización. Hay dos caminos: pedir al Expediente que codifique los alérgenos (ATC o un catálogo de sinónimos), o tratar como **advertencia** un nombre que **contenga** el principio activo, para que alguien lo mire. Es una decisión clínica: la toma farmacia.

### AR-04 · El contrato dice «integer» y el código rechaza 7.0 · Menor · Contrato

- **Dónde:** `src/Dominio/Reglas/ValidadorPrescripcion.php:44` y `50-52` · `api/openapi.yaml:275` y `277`
- **Qué pasa:** el contrato declara `dias` y `frecuencia_horas` como `integer`, y en JSON Schema `7.0` es un entero válido. El código exige el tipo entero de PHP: `json_decode` convierte `7.0` en un número decimal, y el validador lo rechaza.
- **Evidencia:** `"dias": 7` → 200 · `"dias": 7.0` → **422** `items[0].dias` · `"frecuencia_horas": 8.0` → **422**.
- **Consecuencia:** un cliente que serialice los números como decimales (frecuente en algunos lenguajes) recibe un 422 que el contrato no anticipa.
- **Propuesta:** aceptar un decimal sin parte fraccionaria, o documentar en el contrato que deben enviarse sin decimales.

### AR-05 · Anular no es idempotente · Menor · Contrato

- **Dónde:** `src/Aplicacion/CasosDeUso/AnularPrescripcion.php:56-57` · `src/Dominio/Reglas/PoliticaAnulacion.php:22`
- **Qué pasa:** el reintento de una anulación que sí llegó responde 409 `ESTADO_NO_PERMITE_ANULAR` con solo `{error}`. El cliente necesita otra petición para saber si la anulación fue suya.
- **Evidencia:** 1.ª → 200 ANULADA; el reintento idéntico → 409, con un cuerpo de solo la clave `[error]`.
- **Ya conocido:** medido en la semana 10; la interfaz lo reconcilia (D-35).
- **Propuesta:** `Idempotency-Key` opcional en la anulación, o `anulada_por` y `anulada_en` en el 409.

### AR-06 · El hallazgo trae el código del alérgeno sin su nombre · Menor · Contrato

- **Dónde:** `src/Dominio/Modelo/Hallazgo.php:31-41` · `src/Dominio/Modelo/Prescripcion.php:54`
- **Qué pasa:** `alergeno` es el código si la alergia lo tiene. Farmacia, en la consulta, ve `alergenos_evaluados: ["J01CA04"]` y no tiene a qué Expediente preguntar el nombre.
- **Evidencia:** previa de P-001 con Cefalexina → hallazgo `"alergeno":"J01CA04"`; la consulta de farmacia → `["J01CA04"]`.
- **Ya conocido:** encontrado al construir el prototipo de la semana 11 (A-3).
- **Propuesta:** `alergeno_nombre` en el hallazgo y en la consulta (cambio compatible).

### AR-07 · El tipo de la alergia no participa en ninguna regla · Observación · Riesgo

- **Dónde:** `src/Dominio/Modelo/Alergia.php:18` (se guarda) · `src/Dominio/Reglas/*` (no se lee)
- **Qué pasa:** una **intolerancia** leve (náuseas) a la Amoxicilina bloquea igual que una alergia: las dos dan BLOQUEO. En todo `src/` hay **0** lecturas de `Alergia::tipo`.
- **Por qué es solo una observación:** bloquear de más es lo seguro, y R-04 no distingue el tipo. Pero un bloqueo absoluto por una intolerancia puede llevar a que los médicos ignoren el sistema.
- **Propuesta:** que farmacia decida si una intolerancia se trata como advertencia. Si se decide, es un cambio en `VerificadorAlergias` con su prueba.

## Lo que la autorrevisión no puede cubrir

- **AR-02 y AR-03 nacen de decisiones del propio autor:** la huella sin el usuario (semana 4) y la comparación por nombre (semana 1). Que el autor los encontrara ahora no garantiza que no haya otros del mismo tipo.
- **El autor conoce las pruebas** y tiende a buscar dentro de lo que ellas cubren. Los revisores R1 y R3 deben buscar **fuera**.
- **No hubo farmacéutico:** AR-03 y AR-07 son decisiones clínicas que el autor no puede tomar.
