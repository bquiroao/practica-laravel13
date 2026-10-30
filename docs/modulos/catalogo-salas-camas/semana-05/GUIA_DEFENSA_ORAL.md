# Guía de defensa oral — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-05-cliente-servidor.md](semana-05-cliente-servidor.md)

> «El catálogo es un servidor HTTP con tres operaciones, descritas en un OpenAPI que pasa el lint. Una prueba de contrato hace pasar 9 escenarios reales por las rutas y el controlador, y falla si una respuesta no está documentada. Evalué extraerlo como microservicio con cuatro criterios medibles; ninguno lo pide hoy, así que sigue como módulo, con un plan de migración por si un criterio se cumple.»

**¿Por qué no hacerlo microservicio si la frontera es clara?** Porque una frontera clara no justifica pagar red, eventos y una segunda base. El enunciado pide una justificación medible: hoy no hay despliegues bloqueados, carga propia ni un segundo equipo.

**¿Qué protege la prueba de contrato?** Que el documento y el código no se separen. Si alguien quita una ruta o un código del OpenAPI, o el controlador empieza a responder algo no documentado, la prueba falla. La mutación lo mostró con 4 escenarios fuera del contrato.

**¿Por qué outbox y no publicar el evento después de guardar?** Porque si el proceso cae entre el `UPDATE` y la publicación, ocupación nunca se entera. Con outbox, el evento se guarda en la misma transacción que el cambio, y se publica después.
