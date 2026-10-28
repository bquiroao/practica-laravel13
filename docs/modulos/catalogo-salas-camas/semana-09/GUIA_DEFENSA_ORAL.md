# Guía de defensa oral — Semana 9

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-09-evaluacion-usabilidad.md](semana-09-evaluacion-usabilidad.md)

> «Evalué mis propios wireframes con las 10 heurísticas de Nielsen y WCAG 2.2 AA. Encontré 9 problemas, cada uno con la línea del wireframe o una medición de contraste, y los ordené por impacto: primero lo que puede duplicar códigos, quitar capacidad por error o impedir el uso con teclado.»

**¿Por qué el foco es el hallazgo más grave?** Porque sin un foco definido, quien usa teclado no puede abrir ni cerrar el diálogo de cambio de estado, y no completa la tarea (gravedad 4). **¿Cómo se mide el contraste?** Con la luminancia relativa de WCAG: (L1 + 0.05) ÷ (L2 + 0.05). El script se autoprueba con negro sobre blanco, que debe dar 21:1.

**¿Por qué no se usa el amarillo para «mantenimiento»?** Porque sobre blanco da 2.15:1, y el texto necesita 4.5:1; `#b45309` da 5.02:1. Además, cada estado lleva siempre su texto, para no depender solo del color (1.4.1).
