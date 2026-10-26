# Guía de defensa oral — Semana 1

**Estudiante según la captura:** Mercedes Azucena Lopez Perez · **Módulo:** Gestión de usuarios, tenants y endurecimiento de autenticación · **Documento:** [semana-01-uml-usuarios-tenants-autenticacion.md](semana-01-uml-usuarios-tenants-autenticacion.md)

## 1. Resumen en 60 segundos

> «Modelé tres pasos relacionados: el alta de una identidad con una membresía en el tenant autorizado, el inicio de sesión y el cierre de la sesión actual. El tenant se valida antes de operar; el alta no concede acceso fuera de la membresía explícita. Para iniciar sesión se verifican credenciales, estado de la identidad y vigencia de la membresía antes de emitir un JWT con sujeto, tenant, `jti` y expiración. Al cerrar sesión, el identificador del token se registra como revocado, y las rutas protegidas deben comprobarlo. El repositorio todavía no define dónde se almacenan las credenciales y sus rutas de autenticación están rotas; por eso el proveedor de credenciales aparece como un límite pendiente y no afirmo que esto ya esté implementado.»

## 2. Orden sugerido para exponer

1. Explicar el límite entre identidad global (`central_users`) y acceso por hospital (`hospital_memberships`).
2. Mostrar CU-01: administrador autorizado, alta o vínculo de identidad, membresía y tenant correcto.
3. Mostrar CU-02: selección del tenant, validación de credenciales, estado y membresía antes del JWT.
4. Mostrar CU-03: invalidación del `jti` y comprobación posterior en las rutas protegidas.
5. Recorrer actividad con una excepción por flujo y distinguir 401/autenticación de un error de alta.
6. Seguir una regla de la matriz, como R-07 (revocación), a través de su actividad y secuencia.
7. Cerrar identificando las decisiones abiertas de credenciales, tenant/hospital y blacklist.

## 3. Decisiones clave

| Decisión | Justificación |
|---|---|
| Identidad global y membresía por hospital son entidades distintas | La existencia de una persona no concede acceso automático a todos los hospitales. |
| El tenant se comprueba antes de autenticar o crear membresías | Un encabezado del cliente solo solicita un contexto; no demuestra que el usuario tenga acceso a él. |
| La membresía vigente es necesaria además de credenciales correctas | Una contraseña correcta no debe conceder acceso a un hospital sin una relación vigente. |
| Los fallos de autenticación usan una respuesta genérica | No revelar si existe la cuenta, su estado o a qué tenant pertenece. |
| El JWT identifica sujeto, contexto, `jti` y expiración | Permite aplicar el token solo en el ámbito validado y referenciarlo al revocar. |
| Logout persiste revocación antes de confirmar | Sin comprobación de blacklist en las rutas, un token JWT seguiría siendo utilizable hasta expirar. |
| Las credenciales son una dependencia explícita | `central_users` no almacena contraseñas y el diseño no decide aquí su ubicación o proveedor. |
| Sin autenticación offline | El diseño federado excluye esta capacidad; una referencia local de usuario no es una credencial. |

## 4. Preguntas probables

**¿Por qué separar usuario y membresía?**  
Una persona puede tener una identidad única y acceso a varios hospitales con membresías independientes. Revocar una membresía no requiere borrar la identidad ni el acceso a otros contextos.

**¿El valor de `X-Tenant-ID` demuestra que el usuario pertenece al tenant?**  
No. Solo identifica el contexto solicitado. El servidor valida que exista y esté activo, y después comprueba la membresía vigente del usuario.

**¿Dónde se guardan las contraseñas?**  
El repositorio deja esa decisión pendiente. `central_users` no tiene contraseña; por eso el modelo usa un verificador de credenciales como límite abstracto y no inventa un lugar de almacenamiento.

**¿Qué hace seguro el cierre de sesión si el JWT es autocontenido?**  
La API debe guardar su `jti` en el mecanismo de revocación y consultar ese mecanismo en las rutas protegidas. Si la revocación no se persiste, no se puede confirmar el cierre como exitoso.

**¿El sistema actual ya permite registrarse o iniciar sesión?**  
No. El README del proyecto informa que las rutas `/api/v1/auth` producen HTTP 500 porque usan modelos heredados que ya no corresponden al esquema federado.

**¿Por qué no se modela un inicio de sesión sin conexión?**  
El diseño de PostgreSQL federado lo declara sin resolver y fuera de alcance. `local_user_ref` no almacena credenciales ni habilita autenticación offline.

**¿Qué diferencia hay entre autenticación y autorización?**  
Autenticación identifica al usuario. Autorización decide qué operaciones permite su membresía y rol. Esta tarea modela la primera y crea el contexto para que RBAC haga la segunda.

**¿Qué muestran los tres diagramas?**  
Casos de uso delimita actores y objetivos; actividad describe ramas y errores; secuencia presenta las llamadas entre interfaz, API y servicios. La matriz conecta las reglas con los tres.

## 5. Recorrido de demostración

1. Abrir [el documento principal](semana-01-uml-usuarios-tenants-autenticacion.md) y explicar supuestos y decisiones pendientes.
2. Mostrar [casos de uso](diagramas/semana-01-casos-de-uso.puml) y sus tres flujos.
3. Recorrer los diagramas de [alta](diagramas/semana-01-actividad.puml), [autenticación](diagramas/semana-01-actividad-autenticacion.puml) y [revocación](diagramas/semana-01-actividad-revocacion.puml), señalando las decisiones y errores de cada uno.
4. En [secuencia](diagramas/semana-01-secuencia.puml), mostrar que la emisión de JWT ocurre después de comprobar tenant, credenciales y membresía.
5. Seguir R-07 en la matriz hasta CU-03, la actividad de revocación A-24–A-25 y la secuencia S-41–S-44.

## 6. Cambios que deben permanecer sincronizados

| Cambio solicitado | Partes a revisar |
|---|---|
| El tenant no equivale a un hospital | Supuestos, actores, CU-01/CU-02, reglas R-01–R-03 y diagramas. |
| Se aprueba un proveedor de credenciales | Supuestos, CU-01/CU-02, R-04/R-05, actividad, secuencia y matriz. |
| Cambia el almacén de revocación | CU-03, R-07/R-08, excepciones, actividad, secuencia y matriz. |
| Se añaden MFA o recuperación de cuenta | Ampliar el alcance y añadir flujos, actores, decisiones, amenazas y trazabilidad. |
| Se permite revocación administrativa de todas las sesiones | Definir actor, autorización, alcance temporal, auditoría y caso de uso nuevo; no ampliar silenciosamente el logout actual. |

Para regenerar imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/gestion-usuarios-tenants-autenticacion/semana-01/diagramas/*.puml
```
