# Guía de defensa oral — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-13-revision-tecnica.md](semana-13-revision-tecnica.md)

> «Es una inspección del Micro-HIS del expediente de la semana 3, con seis fases, roles separados y un checklist de 15 ítems sobre integridad, acceso autorizado, consentimiento, alcance y auditoría. Ese código está en el PR #191, aún sin integrar, así que el plan declara que el criterio de entrada E-01 no se cumple y la reunión espera. La autorrevisión ya encontró cuatro mayores y un menor.»

**¿Por qué no convocar ya la reunión?** Porque se revisaría un código que todavía puede cambiar. Lo que se revisa tiene que estar congelado en `develop`. Si no, los hallazgos podrían referirse a líneas que ya no existen.

**¿Cuál es el hallazgo más grave?** H-05: si la base CENTRAL falla, el intento de ver la historia de otros hospitales no queda en la auditoría. RNF-04 dice que la auditoría no se omite «en ningún camino», y un fallo técnico también es un camino.

**¿Por qué una auditoría modificable es mayor?** Porque R-08 pide que no se pueda cambiar. Con una tabla común, quien tenga acceso a la base convierte un rechazo en «ok» y borra el rastro. El script lo hace con un solo `UPDATE`.
