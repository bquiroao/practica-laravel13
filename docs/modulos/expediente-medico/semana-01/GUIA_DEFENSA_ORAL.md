# Guía de defensa oral — Semana 1

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-01-uml-expediente.md](semana-01-uml-expediente.md)

## 1. Resumen en 60 segundos

> «Cada paciente tiene un solo expediente por hospital, que nunca se borra. Solo lo consulta el profesional que tiene una asignación de atención activa con ese paciente, y solo ve el alcance autorizado. Para ver su historia en otros hospitales, el paciente debe estar vinculado al índice maestro y haber dado consentimiento a este hospital. En una emergencia sin consentimiento se puede entrar con una justificación escrita, por una hora como máximo, y queda una revisión pendiente. Todo acceso, aceptado o rechazado, queda en una auditoría que no se puede modificar.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Base CENTRAL como actor secundario | Es otra base federada: el hospital le pregunta y ella decide; RBAC y la auditoría local son internos. |
| Relación de atención y no solo rol (R-05) | Ser médico no basta: hay que estar atendiendo a ese paciente. |
| `<<extend>>` en cadena (CU-03, CU-04) | La historia externa y la emergencia ocurren solo bajo una condición. |
| Auditar también los rechazos (R-08) | Un intento negado puede ser un abuso: también es evidencia. |

## 3. Preguntas probables

**¿Qué pasa si un médico sin asignación activa intenta abrir el expediente?** D4 lo detiene: 403 (E1), y el rechazo queda auditado (S3).

**¿Cómo se evita que el acceso de emergencia se use siempre?** Exige justificación, dura como máximo 1 hora (lo impone el esquema) y crea una revisión pendiente que hará un auditor (R-07).

**¿Por qué no se puede abrir un segundo expediente?** La base lo impide con `medical_records_local_patient_id_unique`; el sistema responde 409 y abre el que ya existe (D3, E3).
