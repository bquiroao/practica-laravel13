# Guía de defensa oral — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-15-pruebas.md](semana-15-pruebas.md)

> «Diseñé 15 pruebas trazadas a RF/RNF: 6 unitarias, 2 de caja blanca y 7 de caja negra por HTTP. Contra `develop` pasan 11 de 14; U-05, de navegación, espera la semana 6 y con ese código pasa. Las 3 que fallan son defectos reales y quedan registrados: se entra con un hospital ajeno, una tarjeta inexistente se confunde con falta de permiso, y con la base caída se ve el error de PHP.»

**¿Por qué entregas pruebas que fallan?** Porque la prueba está para encontrar defectos. Si la ajustara para que pase, escondería el problema. El exit 1 es la evidencia, y cada falla tiene su defecto con causa y propuesta.

**¿Cómo sabes que las pruebas detectan errores?** Corrí dos mutaciones de una línea en una copia. Que nunca haya «vacío» lo detectan U-01, U-04 y CB-01, y que la tarjeta ignore el permiso lo detecta CB-01.

**¿Por qué V(G) = 5?** El flujo de `tarjeta` tiene 4 decisiones: hospital vacío, tarjeta permitida, fuente que falla y conteo cero. 4 + 1 = 5, y cada camino termina en un estado distinto: E1, E4, error, vacío y resumen.

**¿Cómo pruebas que una tarjeta falla sola?** En CN-06 cambio la tabla de camas del SQLite temporal por una vista que falla al leerse. Camas responde 503 E5 con un texto amable y pacientes sigue en 200: eso es R-03 visto desde fuera.

**¿Por qué no probaste el RBAC ni los otros módulos?** Son de otros equipos. CN-04 solo comprueba que el dashboard rechace un hospital ajeno, y no lo hace (DEF-01).

**¿Por qué DEF-02 importa si es «menor»?** Porque el cliente oculta las tarjetas con 403 (R-07). Si una tarjeta no existe por un error de configuración, desaparece sin aviso y nadie se entera.
