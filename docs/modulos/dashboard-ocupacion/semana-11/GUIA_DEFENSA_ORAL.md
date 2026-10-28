# Guía de defensa oral — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-11-prototipo.md](semana-11-prototipo.md)

> «El prototipo muestra la ocupación por sala con la hora y la fuente del corte, y ordena las salas por camas libres. El error crítico es un corte de más de 15 minutos: las cifras siguen visibles, pero una franja avisa que pueden estar desactualizadas.»

**¿Por qué la falla al generar un corte no borra las cifras?** Porque el corte anterior sigue siendo válido para su hora (R-09): se conserva y se dice cuál es. **¿Por qué la barra del aviso cambia de color?** Un fallo con la barra verde de éxito confunde; la alerta usa `#b91c1c`, medido a 6.47:1 en la semana 9, y además el texto dice qué pasó.
