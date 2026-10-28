# Guía de defensa oral — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-13-revision-tecnica.md](semana-13-revision-tecnica.md)

> «Es una inspección del Micro-HIS del dashboard, congelado en un commit de `develop`, con seis fases, roles separados y un checklist de 15 ítems que cubre sesión, permisos, menú, tarjetas y sus cuatro estados. La autorrevisión encontró un hallazgo mayor (el cliente envía su hospital y sus permisos), tres menores y una observación.»

**¿Por qué una tarjeta inexistente con 403 es un error?** Porque R-07 dice que un 403 oculta la tarjeta. Si alguien escribe mal un id, el cliente la esconde en silencio como si fuera falta de permiso, y nadie se entera del error. Con 404 se distingue «no existe» de «no puedes verla».

**¿Por qué la zona horaria es un hallazgo y no un detalle?** Porque «citas de hoy» es el primer número que ve recepción. Una cita de las 20:00 en Guatemala guardada en UTC cae al día siguiente, y la tarjeta dice «No hay citas hoy». El script lo reproduce con una cita concreta.

**¿Por qué C-11 es «no aplica» y no «sí»?** Porque el límite de 10 s vive en el cliente de la semana 2, que no es parte del objeto congelado. Marcar «sí» sin haberlo revisado sería inventar el resultado.
