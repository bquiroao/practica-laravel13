# Guía de defensa oral — Semana 3

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación por rol) · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

> «El controlador traduce a HTTP; tres servicios, uno por cliente, ordenan los casos de uso; el dominio decide si los metadatos son válidos, qué transiciones admite una versión y si el lector está en la audiencia; y el repositorio PDO guarda versiones y audiencias con sentencias preparadas. Cada servicio recibe solo su puerto, como pedía el ISP de la semana 2.»

**¿Por qué tres servicios y no uno?** Porque hay tres clientes con necesidades distintas: autor, responsable y lector. Así el doble de la prueba de base caída implementa 2 métodos, no 7, y un cambio en la revisión no toca la consulta.

**¿Por qué un rol ajeno recibe 404 y no 403?** Por R-07: un 403 confirmaría que el documento existe. La prueba compara la respuesta del rol ajeno con la de un documento inexistente y exige que sean idénticas. **¿Qué pasa si falla la base a mitad de un envío?** La versión y su audiencia se guardan en una transacción; si falla la audiencia, no queda nada a medias y se responde 503 E8.
