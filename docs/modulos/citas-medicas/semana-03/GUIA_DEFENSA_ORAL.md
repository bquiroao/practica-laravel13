# Guía de defensa oral — Semana 3

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

> «El controlador traduce a HTTP, el servicio ordena los casos de uso, el dominio decide si el horario es válido y qué transiciones admite una cita, y el repositorio PDO busca cruces y guarda con sentencias preparadas. La base y la hora se inyectan por puertos, así que las pruebas fijan "ahora" y usan una base SQLite de prueba o un doble que simula la base caída.»

**¿Por qué [2000, 3000) y [3000, 4000) no se cruzan?** El intervalo es semiabierto: el fin no pertenece a la cita. La consulta usa `starts_at < fin AND ends_at > inicio`, igual que el rango `[)` de la exclusión `gist` real.

**¿Qué pasa si se cae la base?** `RepositorioPdo` convierte la `PDOException` en `ErrorPersistencia`, y el controlador responde 503 E8 sin mostrar el SQLSTATE. **¿Cómo se prueba R-01 sin depender de la hora real?** El puerto `Reloj` se inyecta con «ahora» = 1000; la mutación que quita la regla hace fallar la prueba de «cita en el pasado».
