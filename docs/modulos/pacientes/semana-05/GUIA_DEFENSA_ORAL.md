# Guía de defensa oral — Semana 5

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-05-cliente-servidor-microservicios.md](semana-05-cliente-servidor-microservicios.md) · **Contrato:** [openapi.yaml](../micro-his-pacientes/api/openapi.yaml)

## 1. Resumen en 60 segundos

> «Llevé el Micro-HIS Pacientes a un diseño cliente-servidor. Tiene un contrato OpenAPI validado y pruebas que fallan si el contrato y el código no coinciden. Completé el protocolo HTTP de la actualización segura: ETag con If-Match para no pisar cambios, If-None-Match para revalidar, e Idempotency-Key para que un registro se pueda reintentar sin efectos dobles. Agregué X-Request-ID y un registro de acceso que no guarda datos personales. Para los módulos que consumen pacientes hice un cliente con timeout, reintento seguro, circuit breaker y una caché que, si el servidor cae, muestra la última copia avisando que puede estar vieja. Después medí: consultar en proceso cuesta 0,003 ms y por red 18 ms; y separar la base rompería 4 llaves foráneas de las que dependen 9 tablas clínicas. No se cumple ninguno de los cinco criterios para extraer el servicio, así que la decisión es no dividir. La frontera física que sí tiene sentido ya existe: el índice maestro en CENTRAL, al que aviso por eventos. Dejé el issue, el worktree y el PR listos para integrarlo al HIS.»

## 2. Orden sugerido de la exposición

1. **Decisión y datos** (§1): la tabla con 0,003 ms frente a 18 ms y las 4 FK.
2. **Contrato:** abrir `openapi.yaml` y mostrar API-01..03.
3. **Idempotencia:** IDEM-03 (el problema real) e IDEM-01 (la solución).
4. **Resiliencia:** el diagrama de secuencia y por qué el PUT no se reintenta.
5. **Frontera:** el diagrama de opciones A y B y los criterios P1–P5.
6. **Plan:** el issue, el comando del worktree y el flujo del PR.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| No dividir | Separar la base rompe 4 FK `ON DELETE RESTRICT` y convierte cada lectura de admisión en ≈ 18 ms de red. Hoy no hay consumidor externo, ni carga, ni equipo propio (P1–P5 = 0). |
| La frontera física es el MPI | La identidad **entre hospitales** ya vive en otra base (CENTRAL). Ahí sí conviene la separación, y se comunica por eventos. |
| `Idempotency-Key` en el POST | Un timeout no dice si el registro se hizo. Con la clave, el reintento devuelve el mismo 201; sin ella, un 409 engañoso. |
| Clave guardada en la misma transacción | No puede quedar un paciente sin su clave (que se duplicaría al reintentar) ni una clave sin paciente. |
| El PUT no se reintenta | Si la primera edición se aplicó, el reintento con la versión vieja daría 409 y el usuario creería que falló. Lo correcto es recargar: `RESULTADO_INCIERTO`. |
| Caché degradada solo para mostrar | Una copia vieja puede no saber que el paciente falleció. Se muestra con aviso, pero nunca se usa para registrar ni para crear episodios. |
| El registro de acceso no guarda la query | La búsqueda lleva nombres de pacientes en la URL. Registrarlos sería una fuga de datos personales (OBS-02, mutación M17). |
| 304 no se vende como optimización | Lo medí: D ≈ C. El 304 sigue siendo un viaje de red. Su valor es revalidar y tener copia ante caídas. |

## 4. Preguntas probables y respuestas

**¿Qué es la arquitectura cliente-servidor aquí?**
El servidor (módulo Pacientes) es la única autoridad sobre las reglas y los datos. Los clientes (la SPA y los otros módulos) piden por un contrato HTTP. El servidor no guarda estado de sesión: cada petición trae token, hospital y versión.

**¿Por qué no microservicios, si es «más moderno»?**
Porque la tarea pide justificarlo con datos y los datos dicen lo contrario: costo de red, integridad referencial perdida y ningún criterio cumplido. Los microservicios resuelven problemas de equipos, escala y despliegue que hoy no tenemos.

**¿Qué es la idempotencia?**
Que repetir la operación tenga el mismo efecto que hacerla una vez. GET y PUT lo son por definición en HTTP; POST no. Por eso el POST usa `Idempotency-Key`.

**Si PUT es idempotente, ¿por qué no lo reintentas?**
Con `If-Match` deja de serlo en la práctica: la primera vez cambia la versión de 7 a 8, y el reintento con "7" ya no coincide y devuelve 409. El cliente no puede distinguir «falló» de «ya se aplicó», así que recargar es lo seguro.

**¿Qué es un circuit breaker?**
Después de 3 fallas seguidas, el cliente deja de llamar al servidor durante 30 s y responde enseguida «no disponible». Eso protege al servidor caído de una avalancha de reintentos y al usuario de esperas largas. Luego prueba una vez (semiabierto) y, si responde, vuelve a la normalidad.

**¿Cómo se mantiene la consistencia con el MPI si son bases distintas?**
Con el patrón outbox. El evento se guarda en la base del hospital en la misma transacción que el cambio. Un proceso aparte lo entrega a la base central, que lo procesa de forma idempotente por `event_id`. Es consistencia eventual, con el retraso visible en `sync_checkpoints.lag_seconds`.

**¿Por qué la rama del PR no es `semana-5-pacientes`?**
Las entregas semanales usan la convención del grupo. El plan de integración al HIS sigue la regla oficial del README: `feature/asii-03-pacientes-bquiroao` desde un worktree, que es lo que valida el check «ASII PR Guard».

## 5. Recorrido de demostración

1. `php tests/ejecutar.php` → 70 pasan, 2 omitidas.
2. `npx @redocly/cli lint api/openapi.yaml` → válido.
3. `php bin/medir.php 2000 100` → la tabla de §5.2 (los números varían, el orden de magnitud no).
4. Abrir [`ClientePacientes.php`](../micro-his-pacientes/src/Cliente/ClientePacientes.php) y mostrar la política por operación en el comentario de la clase.
5. Mostrar [`plan/issue-asii-03-pacientes.md`](plan/issue-asii-03-pacientes.md) y el comando del worktree.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| Demostrar que contrato y código no pueden divergir | En `openapi.yaml`, cambiar `x-permiso: pacientes.buscar` por `pacientes.leer` y ejecutar: falla API-01. |
| Hacer que el cliente reintente 2 veces | `new ClientePacientes(..., reintentos: 2)`. Ajustar CLI-02, que espera 2 intentos. |
| Cambiar el umbral del circuito | `new CircuitoInterruptor(5, 60.0)`. CLI-04 usa 3 y 30 s. |
| Agregar una operación (por ejemplo, inactivar) | Ruta en `Rutas`, operación en `openapi.yaml` con su `x-permiso` y respuestas, y caso de uso con `ControlAcceso::…`. API-01..03 obligan a mantener las tres cosas sincronizadas. |
| Crear el issue | `sed '1,/^---$/d' docs/modulos/pacientes/semana-05/plan/issue-asii-03-pacientes.md \| gh issue create --title "ASII-03 — Pacientes: registro, edición, búsqueda y detalle" --label asii --label module --body-file -` |
