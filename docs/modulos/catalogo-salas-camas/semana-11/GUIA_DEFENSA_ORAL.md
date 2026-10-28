# Guía de defensa oral — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-11-prototipo.md](semana-11-prototipo.md)

> «El prototipo es un solo HTML que se abre sin servidor. La misma página es tabla en escritorio y tarjetas en móvil. Recorre el cambio de estado de una cama hasta guardarlo, y también el error crítico: que admisión ocupe la cama mientras otra persona la edita.»

**¿Cómo se sabe que funciona en 320 px?** No se confió en la vista: `capturar.mjs` fija el ancho exacto con el protocolo DevTools y compara `scrollWidth` con `clientWidth` en cada pantalla. Para probar que la medición detecta fallos, se inyectó un bloque de 600 px y marcó «SÍ».

**¿Por qué el conflicto es el error crítico?** Porque guardar ese cambio pondría en mantenimiento una cama con un paciente, y la capacidad de la sala sería falsa. El prototipo no guarda el cambio, conserva el motivo y deja la cama sin acción (R-06).
