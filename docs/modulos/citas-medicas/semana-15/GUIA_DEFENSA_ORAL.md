# Guía de defensa oral — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-15-pruebas.md](semana-15-pruebas.md)

> «Diseñé 15 pruebas trazadas a RF/RNF: 6 unitarias, 2 de caja blanca y 7 de caja negra por HTTP. Contra `develop` pasan 10 de 14; U-05, la carrera al guardar, espera la semana 6 y con ese código pasa. Las 4 que fallan son defectos reales y quedan registrados: una cita sin paciente, sin control de permisos, la confirmación de una cita vencida y un error de PHP expuesto cuando cae la base.»

**¿Por qué el intervalo es [inicio, fin)?** Para que dos citas contiguas no choquen: la que termina a las 10:00 y la que empieza a las 10:00 conviven. U-03 y CN-02 lo prueban justo en el límite, y con 1 segundo de cruce ya es E4.

**¿Cómo distingues las dos E7 en caja blanca?** Por las escrituras. La E7 del dominio (la cita ya está completada) no intenta escribir; la de la carrera (otro usuario la cambió) intenta y el repositorio devuelve `false`.

**¿Por qué CN-06 usa una cita sembrada en la base?** Porque por la API no se puede crear una cita en el pasado (E2). La precondición es una agenda que ya tiene una cita vencida, que es lo que pasa en la realidad cuando un día termina.

**¿Cómo sabes que las pruebas detectan errores?** Corrí dos mutaciones de una línea. Permitir confirmada → completada la detecta U-01, y aceptar inicio = ahora la detectan U-02 y CB-01.

**¿Por qué no probaste el RBAC?** Es otro módulo. CN-04 solo comprueba que la agenda rechace lo no autorizado, y no lo hace (DEF-02).
