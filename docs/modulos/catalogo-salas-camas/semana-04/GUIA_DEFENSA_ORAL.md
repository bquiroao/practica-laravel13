# Guía de defensa oral — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-04-mvc-repository.md](semana-04-mvc-repository.md)

> «La petición entra por un front controller; las rutas eligen la acción; el controlador llama al modelo, que no cambió desde la Semana 3, y le pasa el resultado a la vista, que es la única que conoce los códigos HTTP. El modelo depende de la interfaz `RepositorioCatalogo`, y la implementan un adaptador PDO y uno en memoria que pasan el mismo guion de pruebas.»

**¿Por qué la interfaz Repository está en Aplicación?** Porque es el límite: el caso de uso define qué necesita y la persistencia lo cumple. Si estuviera junto a PDO, el modelo dependería de la base. **¿Cómo sabes que los dos adaptadores son equivalentes?** El mismo guion corre contra los dos (8 y 8 pruebas), incluida la regla de contrato E7; la mutación que quita esa regla del adaptador en memoria hace fallar la prueba.

**¿Qué aporta el repositorio de datos compartido?** Catálogo, Admisión y Ocupación trabajan sobre la misma base HOSPITAL sin llamarse entre sí. Para que no se pisen, cada columna tiene un solo dueño y todos cambian `beds.status` con concurrencia optimista.
