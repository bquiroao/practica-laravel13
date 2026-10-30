# Guía de defensa oral — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica · **Documentos:** [presentación](semana-06-diapositivas.md) y [matriz y registro del cambio](semana-06-defensa-arquitectura.md)

> «El centro de documentación tiene un hilo trazable: el responsable de integración aprueba y marca obsoleta, RF-03, RF-06 y R-10 lo exigen, el contrato lo expone, y cada capa hace una parte. El cambio práctico fue la auditoría del versionado: marcar obsoleta ya no borra a quien aprobó. Entró solo en la persistencia, sin cambiar la firma de ningún puerto, y cerró el hallazgo H-03 de la semana 13.»

**¿Qué principio SOLID defiendes y dónde se ve?** ISP: hay tres puertos, uno por cliente. `ServicioPublicacion` solo conoce `EscritorVersiones`, y el doble de «base caída» implementa dos métodos. Por eso el cambio de auditoría tocó solo la implementación de `RevisionPublicaciones`, sin romper a los demás clientes.

**¿Por qué dos `UPDATE` y no uno con el nombre de la columna en una variable?** Porque meter texto en el SQL, aunque sea un nombre fijo, abre la puerta a la inyección y la métrica M-04 lo mediría como concatenación. Dos sentencias preparadas fijas son igual de claras y seguras.

**¿Por qué la columna nueva y no sobrescribir con más cuidado?** Porque aprobar y marcar obsoleta son dos hechos distintos con dos responsables (R-10). Cada uno necesita su propio lugar para que la auditoría no dependa del orden de los cambios.

**¿Qué demuestra la métrica M-02?** Que R-10 ahora tiene una prueba que la cita. Con ella, 8 de 10 reglas tienen prueba y la métrica cumple su umbral del 80 %.

**¿Qué queda pendiente del versionado?** Enlazar la versión obsoleta con la que la reemplaza (R-08, hallazgo H-05), el lint obligatorio del OpenAPI (R-04, H-02) y tomar usuario y roles del token (H-01, semana 15).
