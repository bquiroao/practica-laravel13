# Guía de defensa oral — Semana 10

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-10-diseno-movil.md](semana-10-diseno-movil.md)

> «En móvil la agenda muestra primero la hora y el estado de cada cita. Agendar se hace en tres pasos, eligiendo solo entre horarios libres. Confirmar y cancelar están dentro del detalle de la cita, no en la lista, para no tocar la cita equivocada.»

**¿Qué pasa si se corta la conexión al agendar?** No se sabe si la cita llegó, así que «Comprobar» consulta la agenda del paciente. Reenviar no puede duplicarla, porque R-03 impide dos citas activas del paciente en el mismo horario. La cita no se guarda para después: otra persona podría tomar el horario mientras tanto.

**¿Por qué las canceladas van plegadas al final?** Porque ya no ocupan horario (R-08) y en una pantalla de 320 px cada línea cuenta. Siguen disponibles con «Mostrar», sin estorbar a las activas.
