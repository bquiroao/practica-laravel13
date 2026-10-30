# Guía de defensa oral — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-04-mvc-repository.md](semana-04-mvc-repository.md)

> «La petición entra por un front controller; las rutas distinguen consolidar de consultar; el controlador llama al servicio y le pasa el resultado a la vista, que decide el código HTTP. La interfaz Repository es `RepositorioOcupacion`, la unión de los tres puertos de la semana 2, y la implementan un adaptador PDO y uno en memoria que pasan el mismo guion.»

**¿Qué tiene que imitar el adaptador en memoria?** Lo mismo que devuelve el SQL: solo salas activas, ordenadas, y una fila con cama `null` para una sala sin camas. La mutación que incluye las salas inactivas hace fallar 3 pruebas.

**¿Cómo encaja Ocupación en un repositorio de datos compartido?** Lee las camas y estancias que escriben Catálogo y Admisión, sin poder cambiarlas, y es dueña solo de sus cortes. Si la lectura falla, la consulta sigue mostrando el último corte, con su hora y el aviso si tiene más de 15 minutos.
