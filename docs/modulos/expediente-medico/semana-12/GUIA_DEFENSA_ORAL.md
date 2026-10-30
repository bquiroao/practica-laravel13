# Guía de defensa oral — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documentos:** [presentación](semana-12-presentacion.md) y [recorrido, matriz y cambio](semana-12-defensa-prototipo.md)

> «Con el prototipo muestro el expediente en 10 pasos: la lista con datos mínimos, la consulta con atención activa, la historia externa con consentimiento, el rechazo sin atención y la apertura confirmada por admisión. El cambio solicitado fue el acceso de emergencia: sin consentimiento, el tratante justifica en 20 caracteres o más y ve la historia externa por 1 hora, con la hora de cierre a la vista y una revisión pendiente.»

**¿Por qué la emergencia es solo para la historia externa y no para saltarse la atención activa?** Porque así lo define R-07 desde la semana 1: la emergencia reemplaza el consentimiento del paciente, no la asignación clínica. Sin atención activa (R-05), la salida es que admisión registre la asignación.

**¿Por qué «Es una emergencia» no es el botón principal?** Porque es la excepción. Si fuera la opción por defecto, se usaría siempre, y la revisión del auditor perdería sentido (W4 de la semana 8: «Cancelar» es la opción por defecto).

**¿Por qué un mínimo de 20 caracteres?** Porque «urgente» no le dice nada al auditor. El contador y el ejemplo evitan adivinar, y el error dice cuántos faltan (B-05, WCAG 3.3.3).

**¿Por qué el error aparece dos veces?** La región de estado lo anuncia al lector de pantalla, y el texto junto al campo lo ve quien tiene el foco ahí. En móvil, el aviso de arriba puede quedar fuera de la pantalla.

**¿Qué corrigió la revisión?** La primera versión ponía la emergencia en la pantalla «sin atención activa», y eso contradecía R-07. Se rehízo sobre la pantalla sin consentimiento antes del commit.
