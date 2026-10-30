# Guía de defensa oral — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-11-prototipo.md](semana-11-prototipo.md)

> «En el prototipo, cada tarjeta del dashboard carga y falla por su cuenta. Si “Citas de hoy” falla, las otras siguen con su resumen, y “Reintentar” recarga solo esa tarjeta. El menú y las tarjetas dependen del rol, y una ruta sin permiso avisa dentro del layout.»

**¿Cómo se simulan la falla y la lentitud?** El panel cambia la respuesta de «Citas de hoy»: normal, vacía, falla (E5) o lenta. Lenta nunca responde y la corta el límite de 10 s (R-04). **¿Por qué el foco va al título de la tarjeta después de reintentar?** Porque mientras carga el botón deja de servir, y quien usa teclado no debe quedarse sin saber dónde está (semana 9, B-05).
