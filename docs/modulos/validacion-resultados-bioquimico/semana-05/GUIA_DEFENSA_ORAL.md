# Guía de defensa oral - Semana 5 (Validación de resultados)

1. **¿Qué es real y qué es propuesta?** Real: el caso de uso, los puertos y las mediciones. Propuesta: API HTTP, idempotencia, sesión, PDO y outbox.
2. **¿Por qué no un microservicio?** Un equipo, sin carga medida, y la decisión cambia estado y registro de forma atómica sobre un dato que nace en Ingreso.
3. **¿Cuál es el hallazgo principal?** El estado del resultado es un dato compartido entre Ingreso y Validación; separar los servicios lo volvería eventual o distribuido.
4. **¿Para qué `If-Match`?** Concurrencia optimista: si cambió la versión, 409 `version-conflict`.
5. **¿Qué límite se demostró?** La versión no cambia al decidir; el 409 del reintento lo da el estado. Se propone incrementarla.
6. **¿Por qué 404 y no 403 entre hospitales?** Para no revelar que el resultado existe.
7. **¿Por qué el 503 no se guarda como idempotente?** Para que el reintento sí pueda completarse.
8. **¿Cómo se verifica?** `php verificacion/frontera.php` y `php verificacion/contrato.php`.
