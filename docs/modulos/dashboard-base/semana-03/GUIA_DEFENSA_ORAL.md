# Guía de defensa oral — Semana 3

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

> «El controlador traduce a HTTP; el servicio tiene dos casos de uso, `inicio` y `tarjeta`; el dominio decide el estado de cada tarjeta, su texto y qué ve cada rol; y el repositorio PDO solo cuenta, con sentencias preparadas. Como cada tarjeta se pide por separado, si una falla las demás siguen.»

**¿Por qué «vacío» no es «error»?** Porque 0 camas disponibles es una respuesta correcta: se muestra una sugerencia. «Error» es que no pudimos leer el dato, y ahí se ofrece «Reintentar». La mutación que borra esa diferencia hace fallar la prueba de R-05.

**¿Qué pasa si se cae la tabla de camas?** Solo esa tarjeta responde 503 con «No pudimos cargar las camas disponibles», sin el SQLSTATE; pacientes y citas siguen en 200. **¿Dónde está «cargando»?** `inicio` devuelve el esqueleto con todas las tarjetas en «cargando», y el frontend lo muestra mientras pide cada una.
