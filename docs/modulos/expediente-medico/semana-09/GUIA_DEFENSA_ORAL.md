# Guía de defensa oral — Semana 9

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-09-evaluacion-usabilidad.md](semana-09-evaluacion-usabilidad.md)

> «Evalué mis wireframes del expediente con las 10 heurísticas de Nielsen y WCAG 2.2 AA. Encontré 8 problemas con evidencia, y los primeros del backlog protegen la integridad y el acceso autorizado: crear el expediente del paciente correcto y no cortar una emergencia sin aviso.»

**¿Por qué el botón fuera de la fila es P1?** Porque el expediente no se puede borrar (R-04). Si dos pacientes se llaman parecido y el botón no está junto a su fila, se puede crear el expediente equivocado sin remedio. Por eso el botón va en la fila, y el diálogo repite nombre, fecha de nacimiento y documento.

**¿Cómo se cumple 2.2.1 si R-07 limita la emergencia a 1 hora?** WCAG pide avisar y permitir extender el tiempo, pero R-07 no permite alargar el acceso. Se avisa 5 minutos antes, y para seguir hace falta un nuevo acceso con su propia justificación, que también queda auditado.
