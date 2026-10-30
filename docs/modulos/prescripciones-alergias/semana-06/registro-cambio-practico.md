# Registro del cambio práctico defendido — «Anular una prescripción emitida, con motivo»

| | |
|---|---|
| **Módulo** | Prescripciones electrónicas con validación de alergias |
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Caso de uso** | CU-07 (modelado en la semana 1; pendiente desde la semana 3) |
| **Contrato** | 1.0.0 → **1.1.0**, compatible |
| **Fecha** | 27/10/2026 |

## 1. Solicitud

> «El médico emitió cefalexina a una paciente alérgica a la penicilina, con advertencia justificada. Horas después, el Expediente registra que la paciente reaccionó a una cefalosporina. Farmacia todavía no dispensó. El médico necesita **dejar sin efecto** la prescripción. Al mismo tiempo, no se puede editar: está firmada, y farmacia ya pudo haberla leído.»

El cambio toca los seis temas del módulo:

| Tema | Cómo lo toca |
|---|---|
| **Prescripciones** | La prescripción emitida **no se edita** (R-10): la anulación es un hecho nuevo y el estado ANULADA se **deriva** |
| **Medicamentos** | La prescripción anulada conserva sus medicamentos tal como se emitieron: farmacia ve qué se anuló |
| **Dosis** | Tampoco cambian: no hay «corregir la dosis»; se anula y se emite otra |
| **Alergias** | El motivo típico es una alergia descubierta después de emitir. La verificación guardada queda como evidencia de qué se sabía al firmar |
| **Validación previa** | Anular no vuelve a verificar: no hay nada que verificar en dejar sin efecto |
| **Bloqueo seguro** | **Asimétrico.** Emitir falla cerrado si el Expediente no responde; anular **no** depende del Expediente, porque reduce el riesgo |

## 2. Requisitos nuevos

| ID | Requisito | Regla (S1) |
|---|---|---|
| RF-15 | El médico con `prescripcion.anular` puede anular una prescripción de su hospital con un motivo de al menos 10 caracteres (sin contar los espacios de los extremos); si no, 422 `MOTIVO_REQUERIDO` | R-10, D-19 |
| RF-16 | Solo se anula una prescripción EMITIDA; una ya anulada responde 409 `ESTADO_NO_PERMITE_ANULAR` | R-10, D-18 |
| RF-17 | La anulación y su traza `PRESCRIPCION_ANULADA` se guardan juntas (todo o nada) | R-12 |
| RF-18 | Anular no consulta el Expediente: funciona aunque esté caído | R-07, asimetría |
| RNF-16 | Compatibilidad: contrato 1.1.0 compatible con 1.0.0 (la clave `anulacion` solo aparece si existe) y caracterización de la semana 7 intacta | — |

Tenant y permisos se heredan: otro hospital responde 404, igual que uno inexistente (R-08); sin el permiso, 403 (R-09).

## 3. Análisis de impacto (antes de programar)

| Capa | Cambia | No cambia |
|---|---|---|
| Presentación | Una ruta, `Controlador::anular()`, `Formulario::anulacion()`, `Vista::anulacion()` | Kernel, autenticación, tenant, observabilidad, las otras 3 acciones |
| Aplicación | `AnularPrescripcion` (nuevo) | Emitir, Previa, Consultar, `EvaluacionClinica`, los puertos de la Aplicación |
| Dominio | `Anulacion` y `PoliticaAnulacion` (nuevos); `Prescripcion` deriva el estado y suma `conAnulacion()`; `RepositorioPrescripciones::anular()` | `PoliticaEmision`, `VerificadorAlergias`, `ValidadorPrescripcion` |
| Persistencia | `anular()` en PDO e InMemory; tabla `prescription_voids` con trigger | Fuente de alergias, catálogo, pacientes, transacción, bandeja |
| Contrato | Una operación nueva y dos códigos de error; `estado` admite ANULADA | Las 3 operaciones existentes |

**La decisión central: ¿columna `status` o tabla aparte?**
- Cambiar `prescriptions.status` a ANULADA sería un `UPDATE`, que el trigger de inmutabilidad (R-10) rechaza, con razón.
- Por eso la anulación va en `prescription_voids`, otra tabla también inmutable y con la prescripción como clave primaria. Esa clave hace que la base impida sola una segunda anulación.
- El estado de la prescripción se **deriva**: ANULADA si existe su anulación.

## 4. Implementación

| Archivo | Qué hace |
|---|---|
| `src/Dominio/Modelo/Anulacion.php` | El hecho: motivo, quién y cuándo |
| `src/Dominio/Reglas/PoliticaAnulacion.php` | Estado → motivo, en ese orden (D-18 → D-19 de la semana 1); no consulta alergias |
| `src/Dominio/Modelo/Prescripcion.php` | `estado` derivado de `anulacion`; `conAnulacion()` devuelve **otra** instancia |
| `src/Aplicacion/CasosDeUso/AnularPrescripcion.php` | Permiso → existe en el hospital → política → anulación y traza en una transacción; la carrera (`YaAnulada`) responde 409 |
| `src/Persistencia/{Pdo,EnMemoria}/…Repositorio…` | `anular()`; `porId` y `porClave` devuelven la prescripción con su anulación |
| `database/schema.sql` | `prescription_voids` (PK = prescripción, motivo ≥ 10, trigger inmutable); la bandeja acepta `PRESCRIPCION_ANULADA` |
| `database/integracion-hospital.sql` | Lo mismo en la propuesta H5 sobre la base real (el validador sigue en 13/13) |
| `api/openapi.yaml` | 1.1.0: `POST /api/v1/prescripciones/{id}/anulacion` |

## 5. Pruebas: rojo → verde

| Momento | Resultado | Evidencia |
|---|---|---|
| Antes de implementar: ANU-01..07 | **7 de 7 fallan** | [cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt) |
| Después: ANU-01..07 | **7 de 7 pasan** | [cambio-2-despues-verde.txt](evidencia/cambio-2-despues-verde.txt) |
| Suite completa con PostgreSQL 18 | **107/107** (+ CON-MEM-08, CON-PDO-08, INT-10) | [cambio-3-postgres.txt](evidencia/cambio-3-postgres.txt) |
| Mutaciones | **33/33** (M31 motivo, M32 doble anulación en InMemory, M33 sin traza) | [mutaciones.txt](evidencia/mutaciones.txt) |
| Contrato | Lint sin errores (1.1.0) | [lint-openapi.txt](evidencia/lint-openapi.txt) |

**Durante el cambio, las pruebas de guarda hicieron su trabajo:**
- **API-01 falló** en cuanto se agregó la ruta y antes de actualizar el YAML: el contrato no documentaba la operación nueva. Se actualizó el contrato, no la prueba.
- **DOM-08 falló:** la prescripción tenía un método público nuevo. Se revisó que `conAnulacion()` no modifica nada (devuelve otra instancia) y se registró en la prueba.
- **CAR-01 nunca falló:** los 32 escenarios grabados en la semana 7 dan exactamente lo mismo. Eso prueba la compatibilidad.

| Prueba | Verifica |
|---|---|
| ANU-01 | Anular → 200 ANULADA; la consulta muestra la anulación; huella, ítems y verificación intactos |
| ANU-02 | Motivo de menos de 10 caracteres sin contar espacios → 422 y nada anulado |
| ANU-03 | Ya anulada → 409; una sola traza de anulación |
| ANU-04 | Otro hospital = inexistente (404); farmacia sin permiso → 403 |
| ANU-05 | Anulación y traza juntas; si la traza falla, sigue EMITIDA (503) |
| ANU-06 | Con el Expediente caído: emitir 503, **anular 200** |
| ANU-07 | Sin motivo o no texto → 400; id no numérico → 404 |
| CON-*-08 | Los dos adaptadores: `anular` no cambia lo emitido, una segunda lanza `YaAnulada` y se revierte con la transacción |
| INT-10 | En PostgreSQL: la anulación es inmutable (trigger), única por prescripción, y la fila de la prescripción no cambia |

## 6. Demostración de punta a punta

`php bin/demo-anulacion.php` ([salida](evidencia/demo-anulacion.txt)), sin base:

| Paso | Respuesta |
|---|---|
| Emitir cefalexina con advertencia justificada | 201 |
| Anular con «reacción» (8 caracteres) | 422 `MOTIVO_REQUERIDO` |
| Farmacia intenta anular | 403 |
| Otro hospital intenta anular | 404 `NO_ENCONTRADO` |
| El médico anula con motivo | 200 `ANULADA` |
| Farmacia consulta | `estado: ANULADA` con la anulación; la verificación original sigue a la vista |
| Segunda anulación | 409 |
| Se cae el Expediente: emitir | 503 `VERIFICACION_NO_DISPONIBLE` |
| … y anular | **200**: no depende del Expediente |

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa | Por qué |
|---|---|---|
| Tabla aparte y estado derivado | `UPDATE status` | El trigger de R-10 lo prohíbe, y con razón: lo firmado no cambia |
| Anular no consulta el Expediente | Verificar también al anular | Anular **reduce** el riesgo; bloquearlo por una falla ajena sería *fail-closed* en la dirección equivocada |
| 200 y no 201 | 201 con `Location` | La anulación no es un recurso que se consulte por separado; se ve dentro de la prescripción |
| `anulacion` solo si existe | Siempre, en `null` | Contrato compatible: las respuestas 1.0.0 siguen siendo byte a byte las mismas (CAR-01) |
| Doble defensa ante la doble anulación | Solo la política | La política responde 409 en el caso normal; la clave primaria (y `YaAnulada`) cubre la carrera entre dos peticiones |

## 8. Riesgos y pendientes

- **«Dispensada» no existe todavía:** cuando farmacia registre la dispensación, la política deberá rechazar la anulación de una prescripción ya dispensada (D-18 de la semana 1). Es una línea en `PoliticaAnulacion` y una prueba.
- La anulación debería **notificar** a farmacia y enfermería (ASII-21, alertas): queda en el plan de integración.
- El motivo es texto libre: un catálogo de motivos ayudaría a medir por qué se anula (semana 14, SQA).
