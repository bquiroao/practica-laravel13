# Guía de defensa oral

## Resumen de 60 segundos
Construí un Micro-HIS vanilla en PHP 8.2 para que un bioquímico revise, valide o rechace un resultado. Adapté el diseño de Semana 2 en una implementación independiente con carpetas Presentation, Application, Domain y Persistence. El controlador traduce resultados del caso de uso a códigos HTTP, mientras el adaptador PDO usa consultas preparadas y una transacción para actualizar el resultado y registrar la auditoría atómicamente. Las pruebas combinan dobles para reglas y fallas controladas con SQLite en memoria para comprobar persistencia real.

## Orden de presentación
1. Flujo y requisitos.
2. Diagramas C4/UML y separación de capas.
3. Caso de uso y puertos.
4. Adaptador PDO y transacción.
5. Evidencia de pruebas y límites.

## Preguntas probables
- **¿Por qué capas?** Reduce acoplamiento y permite probar reglas sin base de datos.
- **¿Dónde vive el puerto repository?** En Domain de esta entrega; PDO e InMemory son adaptadores.
- **¿Cómo se garantiza atomicidad?** Transacción, relectura de versión/estado, UPDATE condicional e INSERT de auditoría antes de commit; cualquier PDOException hace rollback.
- **¿Qué prueban dobles y PDO real?** Los dobles prueban reglas, autorización y errores deterministas; SQLite prueba SQL, constraints y persistencia.
- **¿Cuáles son los límites?** SQLite es demostrativo, no hay servidor HTTP, autenticación real ni rangos clínicos.