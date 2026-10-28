# Guía de defensa oral - Semana 3 (Usuarios, tenants y autenticación)

1. **¿Cuáles son las capas y quién depende de quién?** Presentation, Application, Domain, Persistence. Todo apunta al núcleo; Persistence implementa los puertos de Application.
2. **¿Cómo se prueba que la aplicación no conoce PDO?** Sección F de `run.php` busca PDO y SQL en esas capas.
3. **¿Por qué el token se guarda como hash?** Si se filtra la tabla, los hashes no sirven como sesión.
4. **¿Por qué el mismo 401 para todo fallo de login?** Para no revelar si el correo existe.
5. **¿Cómo se aísla un tenant?** `UNIQUE (tenant_id, email)` y todas las consultas filtran por tenant.
6. **¿Qué pasa si la base falla?** `PdoBase` traduce a `PersistenceFailure`; el controlador responde 503 sin filtrar SQL.
7. **¿Cómo se prueba la expiración?** Con un reloj falso, sin esperar 8 horas.
8. **¿Qué falta?** Servidor HTTP real, límite de intentos, MySQL/PostgreSQL y que la carrera de duplicado dé 409.
