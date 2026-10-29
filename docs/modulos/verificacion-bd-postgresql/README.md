# Módulo Verificación de base de datos PostgreSQL

Revisión del esquema PostgreSQL federado del SHI (bases CENTRAL y HOSPITAL): modelo de clases de las entidades centrales vinculado a las tablas reales, correspondencias verificadas por script y hallazgos.

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73-svg`) · asignado en el tablero del docente

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | Diagrama de clases UML de paciente, profesional, cita, cama y expediente con sus clases de unión; tabla de correspondencia clase → tabla, relaciones → llaves foráneas, script que la verifica contra las migraciones y 5 hallazgos | [semana-01-clases-his-postgresql.md](semana-01/semana-01-clases-his-postgresql.md) | — | [§5 Defensa](semana-01/semana-01-clases-his-postgresql.md#5-hallazgos-de-la-revisión) |
| 2 | 26 requisitos de datos (RD-01..RD-26) con su mecanismo en PostgreSQL y un criterio ejecutable; script estático y de ejecución contra PostgreSQL: 23 cumplidos y 3 brechas | [semana-02-requisitos-de-datos.md](semana-02/semana-02-requisitos-de-datos.md) | — | [§4 Brechas](semana-02/semana-02-requisitos-de-datos.md#4-brechas-y-propuesta) |
| 3 | Vista UML de despliegue y datos de la arquitectura federada: qué guarda CENTRAL y qué guarda cada base HOSPITAL, y sincronización propuesta | [semana-03-arquitectura-datos-federada.md](semana-03/semana-03-arquitectura-datos-federada.md) | — | [Decisiones y límites](semana-03/semana-03-arquitectura-datos-federada.md#decisiones-y-límites) |
| 9 | Calidad de los datos de prueba (seeders): lista de completitud, consistencia y realismo, seeder propuesto de 16 tablas y verificación en PostgreSQL | [semana-09-calidad-datos-prueba.md](semana-09/semana-09-calidad-datos-prueba.md) | — | [§5 Evidencia](semana-09/semana-09-calidad-datos-prueba.md#5-evidencia) |
