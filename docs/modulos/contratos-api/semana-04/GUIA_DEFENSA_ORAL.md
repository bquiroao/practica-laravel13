# Guía de defensa oral — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación por rol) · **Documento:** [semana-04-mvc-repository.md](semana-04-mvc-repository.md)

> «La petición entra por un front controller; las rutas eligen la acción; el controlador llama a uno de los tres servicios y le pasa el resultado a la vista, que es la única que conoce los códigos HTTP. La interfaz Repository es `RepositorioDocumentos`, la unión de los tres puertos de la semana 2, y la implementan un adaptador PDO y uno en memoria que pasan el mismo guion.»

**¿Una sola interfaz Repository no rompe el ISP?** No: los adaptadores implementan la interfaz completa, pero cada servicio recibe solo su puerto. Por eso el doble de la prueba de base caída sigue implementando 2 métodos.

**¿Por qué el repositorio compartido sería CENTRAL?** Porque la documentación es la misma para todos los hospitales y ahí ya están los `role_code`. Los demás módulos publican su OpenAPI por el caso de uso `enviar`, nunca con SQL directo, así que también pasan por la validación y la revisión.
