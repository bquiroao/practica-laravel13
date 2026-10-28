# Guía de defensa oral — Semana 7

## Explicación breve

El inicio de sesión concentraba en `AuthController` la validación HTTP, el tenant, la consulta del usuario, la verificación de contraseña, la emisión de JWT y la respuesta. Propuse que el controlador sea un adaptador HTTP y que `AuthenticateTenantUser` coordine dos puertos: autenticación de credenciales dentro del tenant y emisión de token. El tenant procede del middleware, no del cuerpo enviado por el usuario, y el caso de uso comprueba que la identidad devuelta pertenezca a ese tenant antes de emitir una sesión.

La interfaz existente es Vue 3 con `LoginPage`, Pinia, Axios y un guard de navegación. El guard mejora la experiencia, pero el backend continúa siendo la autoridad. El prototipo del refactor se verifica con dobles; no está conectado a Laravel.

## Preguntas posibles

### ¿Qué componente se refactorizó y por qué?

`AuthController::login()`: mezcla HTTP, contexto tenant, persistencia, verificación de credenciales, token y serialización. El boceto reduce de 27 a 13 líneas locales y elimina las llamadas directas a Eloquent, `Hash` y JWT en el controlador.

### ¿La aplicación ya usa este caso de uso?

No. El código de Laravel existente fue inspeccionado y el refactor se presenta como propuesta con prototipo aislado. No se conectó al controlador, porque el worktree no incluye `vendor/` ni los modelos de autenticación necesarios para ejecutar una prueba de integración.

### ¿Cómo se evita que el cliente cambie de tenant?

La cabecera solo selecciona contexto. `TenantMiddleware` resuelve el tenant y la aplicación usa ese contexto; el caso de uso rechaza una identidad cuyo tenant no coincide. La autorización real permanece en el backend.

### ¿Qué falta antes de integrar?

Resolver la discrepancia entre el API runtime de `develop` y el OpenAPI de Semana 5, implementar adaptadores concretos, agregar pruebas HTTP/tenant y verificar el frontend con el entorno completo.
