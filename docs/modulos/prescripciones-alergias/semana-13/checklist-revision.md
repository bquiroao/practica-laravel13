# Checklist de la revisión técnica formal — Prescripciones con validación de alergias

**Objeto:** Micro-HIS Prescripciones en el commit congelado por [`criterios-entrada.php`](herramientas/criterios-entrada.php), contrato 1.2.0 · **Plan:** [plan-revision-tecnica.md](plan-revision-tecnica.md)

## Cómo se usa

- **Cada ítem es una afirmación** que el revisor intenta **refutar**: si encuentra un caso que la contradice, es un hallazgo.
- **«Cómo se verifica»** dice qué prueba o comando ya la respalda. El revisor no se queda con que la prueba pase: busca el caso que la prueba **no** cubre. La columna sugiere uno.
- **Perspectiva** asignada a cada revisor (lectura basada en perspectivas):
  - **D** · dominio clínico (dosis, alergias, decisión);
  - **C** · contrato y frontera;
  - **S** · permisos y tenant;
  - **I** · integridad (inmutabilidad, idempotencia, traza, bloqueo seguro);
  - **A** · arquitectura y dependencias.
- **«Sev. si falla»:** la severidad por defecto, si se refuta. El moderador la puede ajustar con justificación.

## Checklist (15 ítems)

| ID | Persp. | Afirmación a verificar | Dónde mirar | Cómo se verifica (y qué buscar además) | Base | Sev. si falla |
|---|:-:|---|---|---|---|:-:|
| RT-01 | D | **Prescripción completa y dosis máxima (R-01, R-02).** Cada ítem tiene un medicamento activo del catálogo, dosis > 0, la unidad del catálogo, una frecuencia permitida, una vía del medicamento y de 1 a 90 días. La dosis diaria (dosis × 24 / frecuencia) no supera la máxima, con el límite inclusivo. | `Dominio/Reglas/ValidadorPrescripcion`, `schema.sql` (CHECK) | DOM-01, DOM-02, APP-09, INT-07, M5. Buscar: tipos límite (`7.0`, `"500"`), ítems repetidos, dosis decimal | R-01, R-02, RF-02, RF-03 | Mayor |
| RT-02 | D | **Coincidencia directa = bloqueo absoluto (R-04).** Una alergia activa al mismo principio activo bloquea, por código o por nombre normalizado, y ninguna justificación ni permiso la levanta. | `Dominio/Reglas/VerificadorAlergias` | DOM-03, APP-05, INT-03, M3. Buscar: **cómo se escribe** el alérgeno en el Expediente (sales, sinónimos, mayúsculas, espacios) | R-04, RF-05 | **Crítico** |
| RT-03 | D | **Reactividad cruzada y advertencia (R-05).** El mismo grupo farmacológico advierte; con severidad potencialmente mortal o desconocida, bloquea. La advertencia exige una justificación de 20 caracteres o más **y** el permiso override, que se consulta solo si hace falta. La previa informa `puede_justificar` con la misma regla. | `Alergia::impideJustificar`, `PoliticaEmision`, catálogo de grupos | DOM-04, DOM-09, APP-06, APP-07, JUS-01..03, M4, M7, M28, M29, M34, M35. Buscar: un alérgeno sin grupo conocido | R-05, RF-06, RF-19 | **Crítico** |
| RT-04 | D | **«Sin registros» no es «sin alergias» (R-06).** Si el Expediente no tiene ningún registro, no se emite sin la confirmación explícita, que queda guardada. | `ExpedienteAlergias::evaluado`, `PoliticaEmision` | APP-08, M6. Buscar: un paciente con solo alergias resueltas (¿cuenta como evaluado?) | R-06, RF-07 | **Crítico** |
| RT-05 | I | **Bloqueo seguro (R-07).** Si la fuente de alergias falla, **nunca** se lee como lista vacía: la previa dice «no disponible» y la emisión responde 503 sin emitir. Anular sí funciona con el Expediente caído. | `FuenteAlergias` (puerto), `PdoFuenteAlergias`, `HttpFuenteAlergias`, `EvaluacionClinica` | APP-13, ANU-06, INT-06, CLI-05, M1, M2, M20, M21, M22. Buscar: un estado HTTP 200 con cuerpo vacío o mal formado | R-07, RF-08, RNF-03 | **Crítico** |
| RT-06 | D | **Reverificación al emitir (R-03).** La emisión vuelve a consultar el Expediente en la misma operación; el resultado de la verificación previa **nunca** se acepta del cliente. | `EmitirPrescripcion`, `SolicitudEmision` | APP-10; paridad de la semana 11, paso 14. Buscar: ¿un reintento idempotente reverifica? (APP-17 dice que no, a propósito) | R-03, RF-04, RF-14 | **Crítico** |
| RT-07 | C | **Contrato = código.** Las 4 operaciones, cada `x-permiso`, cada código y estado que devuelve el servidor, y `puede_justificar` (1.2.0) están documentados; el lint es válido. | `api/openapi.yaml`, `Rutas`, `Vistas` | API-01..03, JUS-04, M27; `npx @redocly/cli@1.34.3 lint`. Buscar: tipos del contrato frente a lo que el código acepta (JSON Schema trata `7.0` como entero) | RNF-17, D-16 | Mayor |
| RT-08 | C | **Los errores no filtran información.** Una prescripción o un paciente de otro hospital responden igual que uno inexistente. Un 409 no revela datos de otra persona. 500 y 503 son genéricos. | `Kernel`, `ErrorVista`, `PrescripcionVista`, casos de uso | APP-03, APP-11, HTTP-07, HTTP-09, ANU-04. Buscar: ¿qué devuelve un reintento con una clave **ajena**? | R-08, RF-09 | **Crítico** |
| RT-09 | C | **Formato en la frontera.** JSON objeto, tipos correctos e `Idempotency-Key` con su patrón; el formato da 400 y la regla da 422. El usuario y el hospital salen de la identidad, nunca del cuerpo. | `FormularioPrescripcion`, `Solicitud` | MVC-01, MVC-04, HTTP-05, ANU-07 | RF-09 | Mayor |
| RT-10 | S | **Permiso por operación, en el caso de uso.** Verificar y emitir exigen `prescripcion.emitir`; consultar, `prescripcion.consultar`; anular, `prescripcion.anular`; todos **antes** de leer datos, aunque se llame sin HTTP. | `CasosDeUso/*`, `PoliticaAcceso` | APP-11, ANU-04, HTTP-02, JUS-02. Buscar: algún camino que devuelva datos **antes** del permiso | R-09, CU-01..09 | **Crítico** |
| RT-11 | S | **Tenant.** Sin `X-Tenant-ID`, 400; si no coincide con el token, 403. El paciente, la prescripción y la clave de idempotencia se buscan **dentro** del hospital del token. | `Kernel`, `Pacientes`, repositorios (InMemory y PDO) | HTTP-04, APP-11, CON-MEM-02..04, M9, M11, M12, M16 | R-08, RF-09 | **Crítico** |
| RT-12 | S | **Identidad del actor.** El prescriptor sale del token. La autenticación de demostración **no puede** quedar activa fuera de desarrollo. | `Autenticacion/*`, `crearKernel`, `config.php` | Leer `crearKernel`; [AR-01](autorrevision-autor.md) | R-09, OWASP API2 | **Crítico** |
| RT-13 | I | **Inmutabilidad y anulación (R-10).** No hay método, ruta ni SQL que modifique o borre una prescripción o sus ítems; el trigger lo rechaza. Anular es un hecho nuevo, único por prescripción, con motivo de 10 caracteres o más. | `Prescripcion` (readonly), `Anulacion`, `schema.sql` | DOM-08, INT-02, INT-10, ANU-01..05, CON-MEM-08, M14, M31, M32. Buscar: ¿el reintento de una anulación que sí llegó? | R-10, RF-11, RF-15..17 | **Crítico** |
| RT-14 | I | **Idempotencia y traza (R-11, R-12).** El mismo contenido con la misma clave devuelve la **misma** prescripción sin duplicar; otro contenido da 409. La prescripción, sus ítems y su traza se guardan todos o ninguno. | `EmitirPrescripcion::reintento`, `SolicitudEmision::huella`, `schema.sql` (UNIQUE), `Pdo*` | APP-12, APP-14, APP-15, APP-17, INT-04, INT-05, M8, M10, M13, M17, M33. Buscar: la misma clave enviada por **otro usuario** del mismo hospital | R-11, R-12, RF-12, RF-13 | **Crítico** |
| RT-15 | A | **Capas y dependencias estrictamente necesarias.** Presentación → Aplicación → Dominio ← Persistencia; sin Composer ni librerías de terceros. La única dependencia de otro módulo es el Expediente, detrás de un puerto, con tiempo límite y circuito. | `src/**`, `HttpFuenteAlergias`, `CircuitoInterruptor` | ARQ-01..10, CLI-06, M22; `php bin/acoplamiento.php`. Buscar: lo que la regla de alergias **no** usa del Expediente (por ejemplo, el tipo de la alergia) | RNF-01, RNF-02, D-12, D-18 | Mayor |

## Cobertura del pedido de la tarea

| Tema pedido | Ítems |
|---|---|
| Reglas de dominio (prescripciones, medicamentos, dosis, alergias) | RT-01..04, RT-06 |
| Contrato API | RT-07..09 |
| Permisos / tenant | RT-10..12 |
| Integridad: validación previa y bloqueo seguro | RT-05, RT-06, RT-13, RT-14 |
| Dependencias estrictamente necesarias | RT-15 |

## Registro por revisor (durante la preparación)

Cada revisor anota en [`plantillas/registro-preparacion.csv`](plantillas/registro-preparacion.csv):
- los minutos de preparación;
- las líneas revisadas;
- los hallazgos preliminares.

Y registra los hallazgos en [`plantillas/registro-hallazgos.csv`](plantillas/registro-hallazgos.csv) con el [formato](formato-hallazgos.md).
