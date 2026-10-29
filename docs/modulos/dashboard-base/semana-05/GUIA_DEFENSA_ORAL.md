# Guía de defensa oral — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-05-cliente-servidor.md](semana-05-cliente-servidor.md)

> «El dashboard es un servidor HTTP con dos operaciones: el layout y una tarjeta. El cliente pide cada tarjeta por separado, así que una caída solo apaga esa tarjeta. Una prueba de contrato hace pasar 8 escenarios reales por las rutas y el controlador. Evalué extraerlo, pero como BFF y no como servicio de datos, porque no es dueño de nada; ninguno de los cuatro criterios lo pide hoy.»

**¿Por qué un BFF y no un microservicio normal?** Porque el dashboard no tiene datos propios: solo cuenta lo que escriben otros. Separarlo sin que se separen sus fuentes sería una llamada de red más para leer la misma base. Tiene sentido cuando las fuentes ya son servicios o cuando hay un segundo cliente, como una app móvil.

**¿Qué garantiza que la SPA no cambie si se extrae?** El contrato. Las dos rutas y sus estados (`resumen`, `vacio`, `error`) son los mismos con `COUNT` en la base o con llamadas a servicios. La prueba de contrato lo vigila.
