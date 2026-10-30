# Guía de defensa oral — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documentos:** [presentación](semana-06-diapositivas.md) y [matriz y registro del cambio](semana-06-defensa-arquitectura.md)

> «El catálogo tiene un hilo trazable: el administrador pide CU-02, RF-02 lo exige, `POST /salas/{sala}/camas` lo expone, y cada capa hace una parte. El cambio de capacidad lo demuestra: la regla entró en una clase nueva del dominio, el adaptador garantiza la consistencia en la base, y ni las rutas ni el controlador cambiaron. Las pruebas pasaron de 28 a 36, todas en verde.»

**¿Qué principio SOLID defiendes y dónde se ve?** SRP: las transiciones de estado viven solo en `EstadoCama`, y la capacidad solo en `CapacidadSala`. DIP: el servicio depende del puerto `RepositorioCatalogo`, no de PDO. Por eso el cambio no tocó el controlador.

**¿Por qué la regla de capacidad está en el dominio y también en el `INSERT`?** El dominio decide la regla y da el mensaje E9. El `INSERT … SELECT` vuelve a contar en la misma sentencia para que dos altas simultáneas no pasen juntas el tope. Es la misma idea que el E7 de la concurrencia optimista.

**¿Por qué las camas inactivas no cuentan?** Porque salieron de servicio (R-08) y no forman parte de la capacidad habilitada. Si contaran, una sala con camas retiradas quedaría bloqueada para siempre.

**¿Qué rompió el cambio y cómo lo detectaste?** La firma nueva de `guardarSala` rompía una llamada directa en las pruebas. Las pruebas fallaron, y lo resolví con un parámetro opcional, que además mantiene compatibles las salas existentes.

**¿Qué falta para producción?** Una migración de `wards.capacity` en el esquema compartido, `SELECT … FOR UPDATE` en PostgreSQL y documentar E9 en el OpenAPI de la semana 5 cuando se integre el PR #350.
