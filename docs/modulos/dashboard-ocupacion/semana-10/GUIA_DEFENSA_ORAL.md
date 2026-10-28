# Guía de defensa oral — Semana 10

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-10-diseno-movil.md](semana-10-diseno-movil.md)

> «En el teléfono, lo primero es la hora y la fuente del corte. Después, una tarjeta por sala, con las que tienen más camas libres primero, porque la jefatura la abre para decidir dónde cabe un ingreso. El desglose por estado va en el detalle de cada sala.»

**¿Por qué aquí sí es seguro reintentar y en otros módulos no?** Porque generar un corte no cambia ninguna cama (R-09): uno de más solo agrega una lectura. En cambio, reenviar un cambio de estado o una cita podría chocar con lo que otra persona hizo mientras tanto.

**¿Por qué no se esconden las cifras cuando son viejas?** Porque siguen siendo una referencia útil. Se muestran con su hora y una franja fija que avisa que pueden estar desactualizadas (R-06), y el teléfono nunca recalcula la ocupación por su cuenta.
