# Guía de defensa oral — Semana 1

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-01-uml-citas.md](semana-01-uml-citas.md)

## 1. Resumen en 60 segundos

> «La recepcionista pide una cita para un paciente con un médico, una especialidad y un horario. El sistema revisa que el médico tenga perfil y especialidad vigentes y que ni él ni el paciente tengan otra cita activa a esa hora. Si todo está libre, la cita queda programada. Después se confirma o se cancela con un motivo, y cancelar libera el horario. Una cita completada o cancelada ya no cambia y nunca se borra.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Paciente no es actor | No usa el sistema (no hay portal); la recepcionista actúa por él. |
| D7 además de D5 y D6 | Dos recepcionistas pueden validar a la vez; la exclusión de la base es la red de seguridad (E6). |
| Confirmar no revalida | La cita programada ya ocupa su horario: la exclusión cubre `scheduled` y `confirmed`. |
| Reprogramar = cancelar y solicitar otra | Las citas finalizadas no cambian (R-07). |

## 3. Preguntas probables

**¿Qué significa «disponible» si no hay horario de atención?** Es un supuesto: sin cita activa cruzada y con perfil y especialidad vigentes. Hoy se podría agendar a las 3:00 a. m.; el horario de atención queda como limitación y como requisito para la Semana 2.

**¿Por qué R-05 la aplica el servicio y R-04 la base?** El trigger `appointments_specialty_trigger` revisa la especialidad, pero no el estado de `doctor_profiles`.
