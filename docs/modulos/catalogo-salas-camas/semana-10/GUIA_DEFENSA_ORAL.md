# Guía de defensa oral — Semana 10

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-10-diseno-movil.md](semana-10-diseno-movil.md)

> «En móvil, lo primero es la capacidad de cada sala; después, el estado de cada cama, y al final crear. Hay una tarea por pantalla, sin modales, sin tablas anchas, y cada error de conexión dice si el cambio se guardó o no.»

**¿Por qué no se guardan los cambios para enviarlos después?** Porque una cama puede ser ocupada por admisión mientras el teléfono está sin conexión. Enviar luego un «mantenimiento» atrasado chocaría con ese estado (E7) y rompería la consistencia del catálogo. Por eso, sin conexión, el cambio no se envía, y cuando la respuesta es incierta, «Comprobar» consulta el estado real.

**¿Por qué 44 px si WCAG pide 24?** El 2.5.8 exige 24 px como mínimo, pero en el piso se trabaja de pie, con prisa y a veces con guantes: 44 px reduce los toques equivocados. **¿Qué pasa a partir de 431 px?** Vuelve la tabla de seis columnas de la semana 8.
