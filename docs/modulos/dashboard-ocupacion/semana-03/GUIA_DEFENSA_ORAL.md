# Guía de defensa oral — Semana 3

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

> «El controlador traduce a HTTP; el consolidador lee las camas y guarda un corte, y la consulta solo lee cortes; el dominio calcula la ocupación por sala, marca inconsistencias, avisa si el corte es viejo y filtra por estado; y el repositorio PDO hace una sola consulta preparada. Ningún puerto puede cambiar una cama.»

**¿Por qué la sala B dice «no aplica» y no 0 %?** Porque no tiene camas disponibles ni ocupadas: dividir entre cero no tiene sentido, y 0 % haría creer que hay lugar. La mutación que cuenta el mantenimiento como operativa hace fallar esa prueba y la del 50.0 %.

**¿Qué pasa si se cae la base al consolidar?** La lectura falla antes de guardar, se responde 503 E8 sin el SQLSTATE y la consulta sigue mostrando el corte anterior. **¿Qué error real encontraron las pruebas?** `json_encode(50.0)` guardaba `50`; con `JSON_PRESERVE_ZERO_FRACTION` el porcentaje vuelve como 50.0.
