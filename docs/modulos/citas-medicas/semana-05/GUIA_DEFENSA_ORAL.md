# Guía de defensa oral — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-05-cliente-servidor.md](semana-05-cliente-servidor.md)

> «La agenda es un servidor HTTP con tres operaciones, descritas en un OpenAPI que pasa el lint. Una prueba de contrato hace pasar 10 escenarios reales por las rutas y el controlador. Evalué extraerla como servicio de agenda con cuatro criterios medibles: es el mejor candidato del HIS, pero hoy ningún criterio lo pide, así que sigue como módulo, con un plan de migración por si aparece el portal del paciente.»

**¿Por qué la agenda y no otro módulo sería el primero en separarse?** Porque es el único con clientes externos previsibles (el portal del paciente) y con contención propia: dos personas que piden el mismo horario. Las dos cosas se miden: clientes en producción y tasa de 409.

**¿Por qué cerrar E6 antes de migrar?** Porque hoy una carrera responde 503 en vez de 409 (hallazgo H-02). Si se migra así, el error cambia de lugar, pero sigue confundiendo al usuario. Con la barrera de la base y su 409, el comportamiento es el mismo dentro y fuera del monolito, y la prueba de contrato lo confirma.

**¿Por qué una clave de idempotencia en `POST /citas`?** Porque si el timeout corta la respuesta y el cliente reintenta, sin la clave se crearían dos citas. Con la clave, el reintento devuelve la misma cita.
