# Guía de defensa oral — Semana 5

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-05-cliente-servidor-microservicios.md](semana-05-cliente-servidor-microservicios.md) · **Contrato:** [openapi.yaml](../micro-his-rbac/api/openapi.yaml)

## 1. Resumen en 60 segundos

> «Llevé el mismo Micro-HIS a un diseño cliente-servidor. Tiene un contrato OpenAPI 3.1 validado con Redocly, y una prueba compara ese contrato con las rutas reales: si alguien cambia una sin la otra, falla. Agregué un endpoint de decisión para que los demás módulos le pregunten a RBAC si un usuario puede hacer algo, y un cliente que, si RBAC no responde, deniega en lugar de permitir.
>
> Para decidir si RBAC debía ser un microservicio, medí: dentro del proceso una decisión cuesta 0,006 ms y por red local entre 33 y 62 ms, miles de veces más. Ninguno de los cinco criterios medibles que definí para separarlo se cumple hoy. Mi decisión es mantenerlo como módulo con una frontera clara y dejar la migración planificada por fases, con compuertas medibles. El plan de issue, worktree, rama y PR está listo para la semana 16.»

## 2. Orden sugerido de la exposición

1. **Decisión y dato clave** (§1 y §5.2): la tabla de latencias.
2. **Contrato:** abrir `openapi.yaml`, mostrar `x-permiso` y el endpoint de decisión; mostrar que pasa el lint.
3. **Cliente-servidor:** diagrama y el patrón PDP/PEP.
4. **Criterios C1–C5** y por qué hoy no se cumplen.
5. **Resiliencia:** diagrama de secuencia; circuit breaker y fail-closed.
6. **Seguridad, observabilidad y consistencia:** una tabla de cada una.
7. **Migración por fases** y **plan de issue/rama/worktree/PR**.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| **No dividir** RBAC hoy | La tarea pide no dividir sin una justificación medible. Medí y la red cuesta miles de veces más que la lógica. Ningún criterio C1–C5 se cumple. |
| Frontera lógica sí, física no | Ya tengo lo valioso de un microservicio (contrato, datos propios, interfaz estrecha) sin pagar la red. Extraerlo después es cambiar un adaptador. |
| Endpoint de decisión con **200** también al denegar | La consulta tuvo éxito; la respuesta es «no». Si respondiera 403, el cliente no distinguiría «no tienes permiso» de «no puedes usar este endpoint». |
| El PDP **no acepta `usuario_id`** | Solo decide sobre el dueño del token. Así un módulo no puede preguntar por los permisos de otra persona (diputado confundido). |
| El PDP no tiene permiso propio | Protegerlo con un permiso sería circular. ARQ-08 garantiza que es la única ruta así. |
| **Fail-closed** en el cliente | En un hospital, permitir algo indebido es peor que bloquear un momento algo legítimo. |
| **No** reintentar ante 4xx | Un 401 o 422 es una respuesta válida: reintentar no la cambia y solo agrega carga. |
| **Sin caché** de decisiones | Una caché de `PERMITIDO` retrasaría una revocación: riesgo clínico y legal. |
| `X-Request-ID` validado con lista blanca | Se escribe en los logs: si se reflejara cualquier texto, habría inyección en logs (OBS-01). |
| Plan con la convención **oficial** (`feature/asii-02-…` + worktree) | Es la que pide el README y valida el CI. Las entregas semanales usan la convención del grupo. |

## 4. Preguntas probables y respuestas

**¿Qué es cliente-servidor y dónde está en tu diseño?**
El cliente pide y el servidor decide y responde, por un contrato de red.
- **Clientes:** la SPA Vue y los módulos clínicos (con `ClienteRbac`).
- **Servidor:** RBAC.
- **Contrato:** `openapi.yaml`.

**¿Qué es un microservicio y por qué no lo hiciste?**
Un servicio que se despliega por separado y es dueño de sus datos. No lo hice porque cuesta caro (red, fallos parciales, consistencia eventual) y hoy no da ningún beneficio medible: no hay otro consumidor, ni carga alta, ni un equipo aparte.

**¿Cómo mediste?**
Con `bin/medir.php`:
- **A:** 5000 decisiones dentro del proceso.
- **B:** 5000 a través del Kernel HTTP en proceso.
- **C:** 300 por red real contra `php -S`.
- **C0:** 300 peticiones vacías (404) como control.

**¿No son injustas las cifras por usar `php -S`?**
Exageran el costo absoluto, sí, y lo digo en el documento. Pero el control C0 muestra que la mayor parte del costo es el salto de red mismo, que en producción baja pero no desaparece. Por eso la fase 3 exige medir de nuevo en condiciones reales antes de decidir.

**¿Qué pasa si RBAC se cae?**
- **En el monolito:** cae junto con el HIS (mismo proceso).
- **Si fuera un servicio:** el cliente deniega (fail-closed); tras 3 fallas el circuito se abre 30 s para no saturar; en la fase 4 se autorizaría con la réplica local.

**¿Qué es un circuit breaker?**
Un interruptor que, después de varias fallas seguidas, deja de llamar al servicio por un tiempo y responde de inmediato (aquí: DENEGADO). Pasado ese tiempo prueba una vez: si funciona, se cierra. Lo prueba CLI-04.

**¿Quién es dueño de cada dato?**
- RBAC escribe `hospital_memberships`, el catálogo y su bitácora.
- Usuarios escribe `central_users`.
- Sincronización escribe la réplica.
- Nadie más escribe en las tablas de RBAC.

**¿Qué consistencia tienes?**
- **Fuerte:** dentro de RBAC (transacción + índice único).
- **Eventual:** hacia las réplicas de cada hospital (outbox), con un límite de 60 s para revocaciones. Los permisos críticos se consultan en la fuente.

**¿Cómo trazas una petición entre módulos?**
Con `X-Request-ID`: el cliente lo envía, el servidor lo devuelve y lo escribe en el registro de acceso. El `correlation_id` enlaza con el evento de auditoría. Hay un registro real en `evidencia/salida-registro-acceso.txt`.

**¿Cómo sabes que el contrato y el código coinciden?**
- **API-01:** compara operaciones y permisos entre el YAML y `Rutas::definir()`.
- **API-03:** ejecuta 15 escenarios reales y verifica que cada código devuelto esté documentado.

Lo comprobé quitando el 409 del YAML: la prueba falló.

**¿Qué es el plan de issue, rama, worktree y PR?**
- Un issue ASII-02 con 5 tareas.
- Cada tarea en su propia rama `feature/asii-02-…`, creada en un worktree desde `origin/develop`.
- Un PR hacia `develop` con la plantilla, la evidencia y `Closes #issue`.
- 1 aprobación, el CI en verde, y luego se borran el worktree y la rama.

El issue y el PR están listos en `plan/`.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/micro-his-rbac

# 1. Pruebas (mostrar API-*, CLI-*, OBS-* y ARQ-08)
php tests/ejecutar.php

# 2. Contrato válido
npx @redocly/cli@1.34.3 lint api/openapi.yaml

# 3. Medición (tarda ~1 minuto)
php bin/medir.php 2000 100

# 4. Servidor real + consulta al PDP (PowerShell)
$env:MICROHIS_PERSISTENCIA = "memoria"; php -S 127.0.0.1:8080 -t public
# en otra terminal:
curl -i -X POST http://127.0.0.1:8080/api/v1/autorizaciones/decisiones `
  -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-ana" `
  -H "X-Request-ID: demo-defensa-001" -H "Content-Type: application/json" `
  -d '{\"permiso\":\"prescripciones.crear\",\"recurso\":\"demo\"}'
```

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| «Agrega una operación al contrato» | Agregar la ruta en `Rutas::definir()` **y** el bloque en `openapi.yaml` (con `x-permiso` y las respuestas 400/401/403/500). API-01/02 lo verifican. Si falta uno de los dos, la prueba falla. |
| «Cambia el umbral del circuit breaker» | `new CircuitoInterruptor(umbralFallas: 5, segundosAbierto: 60)` donde se crea el cliente. Ajustar CLI-04 si se usa el valor por defecto. |
| «Que el cliente no reintente» | `new ClienteRbac(..., reintentos: 0)`. |
| «Agrega un campo al registro de acceso» | En `Kernel::manejar()`, dentro del arreglo que se pasa a `registrarAcceso`. OBS-02 verifica que no se filtren tokens. |
| «¿Y si quisieras dividirlo ya?» | Mostrar §5.3: haría falta cumplir algún C1–C5 y medir p95 ≤ 50 ms. Mostrar la fase 4 de la migración: cambiar el adaptador en proceso por `ClienteRbac`. |
| «Muestra que el cliente no conoce el servidor» | ARQ-01: agregar `use Rbac\Presentacion\Http\Kernel;` en `ClienteRbac.php` → la prueba falla. Quitarlo. |
