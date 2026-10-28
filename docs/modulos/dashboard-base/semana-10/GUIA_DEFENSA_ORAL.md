# Guía de defensa oral — Semana 10

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-10-diseno-movil.md](semana-10-diseno-movil.md)

> «En el teléfono el dashboard es una columna de tarjetas, ordenadas según el rol, con un solo número cada una. Cada tarjeta carga y falla por su cuenta, y sin conexión cada una muestra su último valor con la hora, nunca un número sin fecha.»

**¿Por qué dos columnas solo desde 376 px?** Por aritmética: dos tarjetas legibles necesitan al menos 170 px cada una, más tres márgenes de 12 px, que suman 376. Por debajo, una tarjeta comprimida cortaría el número o el texto.

**¿Por qué «Sin datos guardados» y no 0?** Porque 0 es un valor real: «no hay citas». Si una tarjeta nunca cargó, mostrar 0 confundiría vacío con error, justo lo que R-05 prohíbe.
