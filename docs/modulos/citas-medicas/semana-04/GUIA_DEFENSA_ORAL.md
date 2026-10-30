# Guía de defensa oral — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-04-mvc-repository.md](semana-04-mvc-repository.md)

> «La petición entra por un front controller; las rutas eligen la acción y sacan el id de la URL; el controlador llama al modelo, que no cambió desde la Semana 3, y le pasa el resultado a la vista, que es la única que conoce los códigos HTTP. El modelo depende de `RepositorioCitas` y de `Reloj`, y los dos adaptadores del Repository pasan el mismo guion de pruebas.»

**¿Qué tiene que respetar el adaptador en memoria para ser equivalente a PDO?** El intervalo semiabierto `[inicio, fin)`, informar primero el cruce del médico y la concurrencia optimista. La mutación que toma el fin como incluido hace fallar la prueba de contrato.

**¿Cómo se integra Citas en un repositorio de datos compartido?** Escribe solo `appointments` y sus cancelaciones, y lee las especialidades y los pacientes, que tienen otros dueños. Ante dos solicitudes al mismo tiempo, la consulta de cruces es la primera barrera y la exclusión `gist` de PostgreSQL es la última.
