# Guía de defensa oral — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-15-pruebas.md](semana-15-pruebas.md)

> «Diseñé 13 pruebas trazadas a RF/RNF: 5 unitarias, 2 de caja blanca y 6 de caja negra por HTTP. Contra `develop` pasan 9 de 12; U-05, de capacidad, espera la semana 6 y con ese código pasa. Las 3 que fallan son defectos reales y quedan registrados: códigos con espacios duplicados, sin control de permisos y un error de PHP expuesto cuando cae la base.»

**¿Por qué entregas pruebas que fallan?** Porque la prueba está para encontrar defectos. Si la ajustara para que pase, escondería el problema. El resultado (exit 1) es la evidencia, y cada falla tiene su defecto con causa y propuesta.

**¿Cómo sabes que las pruebas detectan errores?** Corrí dos mutaciones de una línea en una copia. Permitir inactiva → mantenimiento la detecta U-01, e ignorar la concurrencia la detecta CB-01.

**¿Por qué V(G) = 5 y 6 caminos?** `cambiarEstado` tiene 4 decisiones propias (cama inexistente, motivo, estado desconocido y la carrera con otro usuario): 4 + 1 = 5. La excepción del dominio agrega una salida más, y por eso son 6 caminos.

**¿Por qué no probaste el RBAC?** Es otro módulo. CN-04 solo comprueba que el catálogo rechace a quien no tiene permiso, y no lo hace (DEF-02).

**¿Por qué DEF-03 es grave si es un caso raro?** Porque expone el SQLSTATE y la ruta del servidor a cualquier cliente, y además responde 200: un cliente creería que la sala se creó.
