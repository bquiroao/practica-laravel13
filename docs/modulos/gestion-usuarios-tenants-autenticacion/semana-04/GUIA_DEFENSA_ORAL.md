# Guía de defensa oral — Semana 4

## Resumen en 60 segundos

> «Continué el mismo Micro-HIS de la Semana 3. Organicé la entrada con MVC: un router que despacha por método y ruta, un controlador que solo traduce la petición a casos de uso y una vista JSON. Las interfaces Repository (`TenantRepository`, `UserRepository`, `SessionRepository`) viven en Aplicación porque son lo que los casos de uso necesitan. Implementé dos familias de adaptadores —PDO con SQL preparado e InMemory— y la misma suite de 26 comprobaciones pasa contra ambos, lo que demuestra que cumplen el mismo contrato. El controlador no tiene SQL ni reglas; hay una prueba estática que lo verifica. Para el repositorio compartido analicé las migraciones reales del HIS: la identidad queda en el hospital y solo se publicarían eventos mínimos de auditoría hacia la bandeja central, nunca contraseñas ni tokens. Esa parte es propuesta, no implementación.»

## Orden sugerido

1. Recordar el flujo: alta por tenant, autenticación segura, revocación.
2. Mostrar el diagrama de capas y ubicar el límite Repository en Aplicación.
3. Mostrar `Router`, `AuthMvcController` y `JsonView`; señalar la prueba estática sin SQL.
4. Ejecutar la suite y destacar que el mismo contrato pasa en PDO e InMemory.
5. Mostrar `failNext()` y el 503 sin éxito falso.
6. Explicar la propuesta de repositorio compartido y sus límites de privacidad.

## Preguntas probables

**¿Por qué las interfaces Repository están en Aplicación y no en Persistencia?**  
Porque expresan la necesidad del caso de uso. Persistencia las implementa; la dependencia apunta hacia adentro y el caso de uso no conoce PDO.

**¿Cómo demuestras que ambos adaptadores cumplen el mismo contrato?**  
La suite ejecuta exactamente las mismas comprobaciones contra PDO SQLite e InMemory: altas, login, unicidad por tenant, revocación y errores.

**¿El controlador tiene reglas de negocio?**  
No. Traduce entrada/salida. Las reglas (contraseña fuerte, correo normalizado, sesión activa) están en Dominio y Aplicación. Una prueba estática confirma que el controlador no contiene SQL ni PDO.

**¿Qué se compartiría con el repositorio central?**  
Como mucho, metadatos de eventos de auditoría con identificadores opacos, de forma asíncrona e idempotente. Nunca contraseñas, hashes ni tokens.

**¿Qué no está implementado?**  
Servidor HTTP real, JWT firmado, MFA, limitación de intentos y la conexión efectiva con la base central.
