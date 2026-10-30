# Guía de defensa oral — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documentos:** [presentación](semana-12-presentacion.md) y [recorrido, matriz y cambio](semana-12-defensa-prototipo.md)

> «Con el prototipo muestro el flujo completo en 8 pasos: los roles limitan los destinos, los estados siempre se leen en texto, los errores aparecen junto al campo y el conflicto E7 conserva el motivo. En móvil y escritorio no hay scroll horizontal en ningún ancho medido. El cambio solicitado fue confirmar antes de inactivar, porque esa acción baja la capacidad de la sala: ahora pide una casilla explícita, y si falta, el error y el foco van a ella.»

**¿Por qué una confirmación y no un diálogo modal?** Porque la casilla queda en el mismo formulario, se lee con lector de pantalla en orden y no atrapa el foco. Un modal agrega un paso y suele romper la navegación por teclado.

**¿Por qué solo al inactivar?** Porque es la acción que saca la cama de la capacidad hasta que alguien la reactive. Pedir confirmación en todo enseña a confirmar sin leer.

**¿El cambio reemplaza una regla del backend?** No. Es prevención de errores en la interfaz (heurística 5 de Nielsen). Quién puede inactivar (R-05) lo sigue decidiendo el backend, con E6.

**¿Cómo demostraste que no se rompió lo demás?** Con el mismo `capturar.mjs` de la semana 11: la ruta de mantenimiento sigue guardando sin confirmación, y las 12 mediciones nuevas no tienen scroll horizontal.

**¿Qué decisión de movilidad defiendes?** Las tarjetas en lugar de la tabla en 360 px, con objetivos de 44 px: la tabla de 12 camas no cabe sin scroll en un teléfono.
