# Guía de defensa oral — Semana 3

## Presentación breve

«Construí un Micro-HIS de recepción como aplicación PHP CLI sin framework. Separé la presentación, el caso de uso, las reglas del dominio y la persistencia PDO. El flujo asigna un identificador, decide si la muestra puede aceptarse o debe rechazarse con una razón y solo confirma después de guardar.»

## Preguntas para explicar el diseño

1. **¿Qué responsabilidad tiene cada capa?** Presentación procesa argumentos; aplicación coordina el caso de uso; dominio protege reglas e invariantes; infraestructura implementa persistencia y generación de identificadores.
2. **¿Cómo se evita SQL injection?** El adaptador usa `PDO::prepare()` y parámetros nombrados en `execute()`, nunca concatena los valores recibidos en la instrucción.
3. **¿Qué pasa cuando el guardado falla?** La excepción se propaga a la CLI, que informa el error y termina sin imprimir aceptación o rechazo como operación confirmada.
4. **¿Cómo se prueba sin base de datos?** Un repositorio en memoria implementa el puerto de persistencia y puede simular un fallo; se comprueban camino feliz, rechazo de dominio y error técnico.
5. **¿Qué no cubre el prototipo?** No consulta el EMR, verifica la orden contra un catálogo real, autentica usuarios ni imprime etiquetas. Esos contratos deben diseñarse antes de una integración clínica real.

## Evidencia

Ejecutar `php tests/run.php` desde `ejemplo/`. El runner reporta las aserciones con `PASS` y una línea final `OK`.
