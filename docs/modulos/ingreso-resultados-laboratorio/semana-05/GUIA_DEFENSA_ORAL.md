# Guía de defensa oral - Semana 5 (Ingreso de resultados)

1. **¿Qué es real y qué es propuesta?** Real: caso de uso, dominio, repositorios y las mediciones. Propuesta: API HTTP, idempotencia, sesión, outbox.
2. **¿Por qué no un microservicio?** Un equipo, sin carga medida y transacción local de versión + clave. La frontera limpia es necesaria, no suficiente.
3. **¿Qué es la idempotencia aquí?** Misma `Idempotency-Key` y contenido repite la respuesta; otro contenido da 409. Evita duplicados por reintento.
4. **¿Para qué `expectedVersionId`?** Concurrencia optimista: si la versión cambió, 409 y se recarga.
5. **¿Por qué 404 y no 403 entre hospitales?** Para no revelar que el ítem existe.
6. **¿Qué límite del prototipo se demostró?** Repetir una captura da CONFLICT y `REJECTED` mezcla dos causas.
7. **¿Cómo se verifica?** `php verificacion/frontera.php` y `php verificacion/contrato.php`.
