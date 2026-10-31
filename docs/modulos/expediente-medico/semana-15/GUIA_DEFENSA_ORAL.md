# Guía de defensa oral — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-15-pruebas.md](semana-15-pruebas.md)

> «Diseñé 17 pruebas trazadas a RF/RNF: 6 unitarias, 2 de caja blanca y 9 de caja negra por HTTP. Contra `develop` pasan 14 de 17. Las 3 que fallan son defectos reales y quedan registrados: cualquiera abre un expediente sin sesión ni relación de atención, la auditoría se puede modificar, y con la base caída se ve el error de PHP.»

**¿Por qué entregas pruebas que fallan?** Porque la prueba está para encontrar defectos. Si la ajustara para que pase, escondería el problema. El exit 1 es la evidencia, y cada falla tiene su defecto con causa y propuesta.

**¿Cómo sabes que las pruebas detectan errores?** Corrí dos mutaciones de una línea en una copia. Aceptar el instante exacto en que vence el consentimiento lo detectan cuatro pruebas, y saltarse el vínculo con el MPI lo detectan U-04 y CB-01.

**¿Por qué V(G) = 7?** `consultar` tiene 6 decisiones: asignación, paciente, expediente, si es longitudinal, si hay vínculo MPI y si el consentimiento está vigente. 6 + 1 = 7, y cada camino deja exactamente un evento de auditoría.

**¿Por qué DEF-01 es crítica?** Porque son datos clínicos: un médico sin relación con el paciente abre su expediente con 201. Es justo el acceso que el módulo existe para impedir.

**¿Por qué no probaste el acceso de emergencia?** Porque el Micro-HIS no lo implementa. Lo dejo en la matriz como RF-04 sin prueba, en vez de inventar una prueba de algo que no existe.

**¿Cómo pruebas que la auditoría no se pierde?** U-05 y CB-01 cuentan un evento por operación, también en los rechazos, y CN-08 tumba CENTRAL: responde 503 E8 y aun así queda el evento E8.
