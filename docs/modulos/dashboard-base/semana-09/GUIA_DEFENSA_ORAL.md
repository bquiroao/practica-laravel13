# Guía de defensa oral — Semana 9

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-09-evaluacion-usabilidad.md](semana-09-evaluacion-usabilidad.md)

> «Evalué mis wireframes del dashboard y el código real del layout con las 10 heurísticas de Nielsen y WCAG 2.2 AA. Encontré 10 problemas con evidencia y les di prioridad a los transversales, porque el layout envuelve las pantallas de todos los módulos.»

**¿Cuál es el hallazgo más curioso?** `lang="en"`: el HTML toma el idioma de `APP_LOCALE=en`, así que un lector de pantalla pronuncia en inglés toda la interfaz en español (3.1.1). Se corrige con una línea de configuración, pero afecta a todo el sistema.

**¿Por qué importa el foco al navegar?** En una SPA la página no se recarga: si el router no mueve el foco, quien usa lector de pantalla no sabe que cambió de página (2.4.3). **¿Por qué no se corrigió aquí?** Porque esos archivos son compartidos (`resources/`, `config/`) y no pertenecen a la carpeta del módulo; se proponen para un PR del frontend coordinado con el equipo.
