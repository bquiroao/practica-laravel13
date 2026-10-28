# Guía de defensa oral — Semana 3

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

> «El controlador traduce a HTTP; el servicio abre y consulta el expediente y audita cada salida; el dominio decide quién puede tener expediente y si el consentimiento está vigente; y dos adaptadores PDO, uno para HOSPITAL y otro para CENTRAL, leen y escriben con sentencias preparadas.»

**¿Qué es el expediente longitudinal aquí?** Además del expediente local, el profesional ve en qué otros hospitales tiene expediente el paciente, pero solo si está vinculado al MPI y hay un consentimiento vigente para este hospital. Si falta el consentimiento o venció, responde 403.

**¿Por qué dos adaptadores?** Porque son dos bases distintas: si CENTRAL cae, la consulta longitudinal responde 503 sin el SQLSTATE, pero la consulta local sigue en 200. **¿Cómo se garantiza la auditoría?** Todo caso de uso pasa por `auditado()`, que registra el resultado o el código del rechazo; la prueba cuenta las 11 operaciones.
