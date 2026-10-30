# Guía de defensa oral — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-15-pruebas.md](semana-15-pruebas.md)

> «Diseñé 16 pruebas trazadas a RF/RNF: 6 unitarias, 2 de caja blanca y 8 de caja negra por HTTP. Contra `develop` pasan 13 de 16. Las 3 que fallan son defectos reales y quedan registrados: el tablero no pide permiso ni hospital, un paciente en una sala inactiva desaparece del corte, y con la base caída se ve el error de PHP.»

**¿Por qué entregas pruebas que fallan?** Porque la prueba está para encontrar defectos. Si la ajustara para que pase, escondería el problema. El exit 1 es la evidencia, y cada falla tiene su defecto con causa y propuesta.

**¿Cómo sabes que las pruebas detectan errores?** Corrí dos mutaciones de una línea en una copia. Contar el mantenimiento como cama operativa lo detectan U-01 y U-02, y cambiar `>` por `>=` en los 15 minutos lo detecta U-04, que prueba justo 900 y 901 segundos.

**¿Por qué V(G) = 4?** La consulta tiene 3 decisiones: si hay corte, si hay filtro y si el estado existe. 3 + 1 = 4 caminos: vacío, sin filtro, E3 y filtrado.

**¿Por qué el límite de 15 minutos no se prueba exacto por HTTP?** Porque el servidor lee su propio reloj y puede avanzar un segundo entre que guardo el corte y lo consulto. La prueba exacta está en U-04, y por HTTP uso 880 y 920 segundos para no tener una prueba que falle al azar.

**¿Cómo pruebas que se conserva el corte anterior?** En CN-06 hago que la lectura de salas falle: consolidar responde 503 E8 sin detalles y la consulta sigue mostrando el corte de antes, con la misma hora.

**¿Por qué DEF-02 importa si es «menor»?** Porque el tablero existe para ver dónde hay pacientes. Si una sala se inactiva con alguien dentro, ese paciente deja de verse sin ningún aviso.
