# Guía de defensa oral — Semana 7

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

> «El centro de documentación tiene tres componentes de frontend y doce de backend, cada uno con su contrato. La medición pone arriba al controlador, con Ce 6, pero es un acoplamiento sano: es concreto y nadie depende de él. El acoplamiento caro está en el versionado: el prototipo decide por su cuenta qué versión es vigente y cuál obsoleta, y filtra la audiencia en el cliente. Para enfermería eso contradice al backend. El refactor deja una sola fuente, la consulta, y la UI muestra lo que recibe.»

**¿Por qué no refactorizar el controlador, si es el de mayor Ce?** Porque su Ce alto viene de los puertos por cliente de la semana 2: el controlador concreto absorbe la variedad para que los servicios queden chicos. Partirlo movería líneas sin bajar el riesgo de un cambio.

**¿Dónde se contradicen la UI y el backend?** En la vigente. El backend la calcula dentro de la audiencia del lector, y para enfermería la vigente es la 1.0.0. La UI tiene una sola vigente por documento, la 1.1.0, y le mostraría a enfermería la 1.0.0 como obsoleta.

**¿Por qué filtrar la audiencia en el cliente no sirve?** Porque el cliente ya recibió el documento: el filtro es cosmético. R-07 exige que lo ajeno ni siquiera llegue y que responda 404 como lo inexistente.

**¿El refactor necesita cambiar el contrato?** No. La respuesta de CU-03 ya trae `obsoleta` y `vigente`, y la prueba de contrato de la semana 5 fija esa forma.

**¿Qué pruebas protegen el cambio?** «Enfermería ve la 1.0.0 de su audiencia», «la obsoleta se consulta con aviso» y «rol fuera de la audiencia = 404 E6», con los dos adaptadores.
