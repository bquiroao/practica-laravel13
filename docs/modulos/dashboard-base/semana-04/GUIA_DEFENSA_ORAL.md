# Guía de defensa oral — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-04-mvc-repository.md](semana-04-mvc-repository.md)

> «La petición entra por un front controller; las rutas distinguen el inicio de una tarjeta; el controlador llama al modelo y le pasa el resultado a la vista, que decide el código HTTP, incluido el 503 de una tarjeta en error. El modelo depende de `FuenteConteos`, un repository de solo lectura, y la implementan un adaptador PDO y uno en memoria que pasan el mismo guion.»

**¿Por qué la vista y no el controlador decide el 503?** Porque convertir el estado «error» en un código HTTP es presentación. Así el controlador solo elige el caso de uso y no tiene ni una regla.

**¿Qué papel tiene el dashboard en un repositorio de datos compartido?** Es un lector puro: cuenta en tablas que escriben Pacientes, Citas y Catálogo, y solo por una interfaz que devuelve conteos. Si una tabla falla, solo su tarjeta queda en error. **¿Qué encontró la prueba HTTP?** Que con una cita la tarjeta decía «1 citas hoy»; ahora cada tarjeta tiene singular y plural.
