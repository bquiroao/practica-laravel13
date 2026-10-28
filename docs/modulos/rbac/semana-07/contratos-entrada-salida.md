# Contratos de entrada/salida de los componentes RBAC

**Estudiante:** Boris Alexander Quiroa Orellana · **Semana 7** · Complemento de [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

Cada componente se describe por lo que **recibe**, lo que **devuelve**, cómo **falla** y de qué **depende**. El contrato entre el frontend y el backend es el mismo `api/openapi.yaml` (v1.1.0) de las semanas 5 y 6: el frontend no conoce clases del servidor.

## 1. Backend (Micro-HIS PHP, implementado)

### 1.1 Presentación (MVC)

| Componente | Entrada | Salida | Fallas / errores | Depende de |
|---|---|---|---|---|
| `Kernel::manejar` | `Solicitud` (método, ruta, cabeceras, cuerpo, ip) | `Respuesta` (estado, cuerpo, cabeceras con `X-Request-ID`) + 1 registro de acceso | 400 sin tenant · 401 sin identidad · 403 permiso de la ruta · 404/405 · 500 genérico | `Rutas`, `Autenticador`, `MiddlewareAutorizacion` |
| `MiddlewareAutorizacion::verificar` | `Solicitud`, `Identidad`, `permiso` | `ResultadoAutorizacion` | — (fail-closed dentro de `AutorizarOperacion`) | `AutorizarOperacion` |
| `FormularioAsignacion::aComando` | `Peticion` con `{usuario_id, rol, valido_desde, valido_hasta?}` | `ComandoAsignarRol` | `DatosInvalidos(campos)` → 422 | — |
| `FormularioRevocacion::aComando` | `Peticion` con `{usuario_id, rol, motivo}` | `ComandoRevocarRol` | `DatosInvalidos(campos)` → 422 | — |
| `FormularioDecision::aComando` | `Peticion` con `{permiso, recurso}` | `ComandoAutorizar` (el actor sale del token) | `DatosInvalidos` → 422 | — |
| `AsignacionRolControlador::crear` / `RevocacionRolControlador::crear` | `Peticion` | `Respuesta` | Solo traduce: formulario → caso de uso → vista | Su caso de uso (ARQ-05) |
| `AutorizacionControlador::decidir` | `Peticion` | `Respuesta` 200 `{decision, correlation_id?}` | 422 | `AutorizarOperacion` |
| `*Vista` | `Resultado*` del caso de uso | `Respuesta` (única que llama a `Respuesta::json`, ARQ-07) | El 403 nunca lleva el motivo | `ErrorVista` |

### 1.2 Aplicación

| Componente | Entrada | Salida | Fallas | Depende de |
|---|---|---|---|---|
| `AutorizarOperacion::ejecutar` | `ComandoAutorizar` (usuario, tenantToken, tenantSolicitud, permiso, recurso, ip) | `ResultadoAutorizacion` (permitido, 200/403, correlationId) **sin motivo** | Lectura fallida → DENEGADO `ERROR_EVALUACION`; bitácora caída → respaldo; nunca lanza | `PoliticaAutorizacion`, `RepositorioMembresias`, `RegistroAuditoria` ×2 |
| `AutorizarOperacion::exigir` *(semana 7)* | usuario, tenantToken, hospital, permiso, ip | `ResultadoAutorizacion` | Igual que `ejecutar` | — (atajo de `ejecutar`) |
| `AsignarRol::ejecutar` | `ComandoAsignarRol` | `ResultadoAsignacion`: 201 · 403 · 404 · 409 · 422 · 500 | Nunca lanza | `AutorizarOperacion`, `PoliticaAsignacion`, `RegistradorDeCambios` |
| `RevocarRol::ejecutar` | `ComandoRevocarRol` | `ResultadoRevocacion`: 200 · 403 · 404 · 422 · 500 | Nunca lanza | `AutorizarOperacion`, `PoliticaRevocacion`, `RegistradorDeCambios` |
| `RegistradorDeCambios::aplicar` *(semana 7)* | `CambioDeMembresia` | `Desenlace`: `APLICADO` · `SIN_EFECTO` · `DUPLICADO` · `FALLA` | Nunca lanza; si falla, se revierte todo (R-07) | `Transaccion`, `RepositorioMembresias`, `RegistroAuditoria` |
| `ConsultarEventoAuditoria::ejecutar` | correlationId, hospital | `array` del evento o `null` (otro hospital = `null`) | `ErrorPersistencia` → 500 en el Kernel | `RegistroAuditoria` |

### 1.3 Dominio

| Componente | Entrada | Salida | Fallas | Depende de |
|---|---|---|---|---|
| `PoliticaAutorizacion::evaluar` | `ContextoAutorizacion` | `null` (permite) o código de motivo | Sin reglas / regla que lanza → motivo (fail-closed) | `ReglaAutorizacion[]` |
| `PoliticaAsignacion::evaluar` / `PoliticaRevocacion::evaluar` | `SolicitudAsignacion` / `SolicitudRevocacion` | `null` o `ErrorAsignacion` / `ErrorRevocacion` (código + HTTP) | *(semana 7)* Una regla que no puede leer → `ERROR_PERSISTENCIA` 500 (fail-closed) | `ReglaAsignacion[]` / `ReglaRevocacion[]` |
| `CambioDeMembresia::aplicarEn` *(semana 7)* | `RepositorioMembresias` | `int` afectadas | `ErrorPersistencia` / `MembresiaDuplicada` | El puerto |
| `CambioDeMembresia::evento` *(semana 7)* | `int` afectadas | `EventoAuditoria` | — | `EventoAuditoria` |
| `EventoAuditoria::accesoDenegado / rolAsignado / rolRevocado` *(semana 7)* | Solicitud de dominio + correlationId (+ ip) | `EventoAuditoria` con el formato fijo de cada tipo (REF-03) | — | — |
| Puertos `RepositorioMembresias`, `CatalogoPermisos`, `DirectorioUsuarios`, `RegistroAuditoria` | Ver semana 4, §5 | — | Solo `ErrorPersistencia` / `MembresiaDuplicada` | — |

### 1.4 Persistencia

| Componente | Entrada | Salida | Fallas |
|---|---|---|---|
| Adaptadores `Pdo*` | Llamadas a los puertos | Objetos de dominio | `PDOException` → `ErrorPersistencia`; SQLSTATE 23505 → `MembresiaDuplicada` |
| Adaptadores `*EnMemoria` | Llamadas a los puertos | Objetos de dominio | Misma semántica (suite de contrato CON-MEM-01..07) |

## 2. Frontend (Vue 3 + Pinia + axios, diseño)

Se apoya en lo que ya existe en el repositorio: `resources/js/plugins/axios.js` (Bearer y `X-Tenant-ID`), `resources/js/stores/auth.js` y `resources/js/router/guards.js`. Los componentes nuevos irían en `resources/js/modules/rbac/`.

### 2.1 Acceso a datos

| Componente | Entrada | Salida | Errores |
|---|---|---|---|
| `rbacApi.asignarRol(datos)` | `{usuario_id, rol, valido_desde, valido_hasta?}` | `{codigo: 'ROL_ASIGNADO', correlation_id}` | Lanza `ErrorRbac {estado, codigo?, campos?, correlationId?}` |
| `rbacApi.revocarRol(datos)` | `{usuario_id, rol, motivo}` | `{codigo: 'ROL_REVOCADO', correlation_id}` | `ErrorRbac` |
| `rbacApi.decidir(permiso, recurso)` | Texto, texto | `{decision: 'PERMITIDO' \| 'DENEGADO', correlation_id?}` | `ErrorRbac` (401/422/500) |
| `rbacApi.consultarEvento(correlationId)` | Texto | Evento de auditoría | `ErrorRbac` (403/404) |
| Interceptores de axios *(extensión del plugin existente)* | Toda petición | Agrega `X-Request-ID` (`crypto.randomUUID()`); ante un 403 con `correlation_id` emite el evento `rbac:acceso-denegado` | — |

### 2.2 Aplicación del cliente

| Componente | Entrada | Salida | Notas |
|---|---|---|---|
| `usePermisosStore` (Pinia) | `puede(permiso, recurso = 'ui')` | `Promise<boolean>`; guarda la decisión 30 s | Solo para **mostrar u ocultar**; el backend vuelve a decidir. `invalidar()` al cerrar sesión o cambiar de hospital. Sin `PERMITIDO` en caché persistente (semana 5, §9). |
| `usePermiso(permiso)` | Texto | `{ permitido: Ref<boolean>, cargando: Ref<boolean> }` | Ante un error → `permitido = false` (fail-closed también en la UI). |
| `permisoGuard` (vue-router) | `to.meta.permiso` | Continúa o redirige a `acceso-denegado` con `?ref=correlation_id` | Se encadena después del `authGuard` existente. |

### 2.3 UI

| Componente | Props | Emits / slots | Muestra |
|---|---|---|---|
| `AdministracionRolesPage` | — (ruta `/rbac/roles`, `meta.permiso: 'roles.asignar'`) | — | Formulario de asignación y lista con botón de revocar |
| `FormularioAsignacionRol` | — | `asignado(correlationId)` · `error(ErrorRbac)` | Campos y errores por campo (`campos` del 422) |
| `RevocacionRolDialog` | `usuarioId`, `rol` | `revocado(correlationId)` · `cancelado()` | Motivo obligatorio (contador ≥ 10 caracteres como ayuda; la regla real está en el dominio) |
| `SiPermiso` | `permiso` | slot `default` · slot `denegado` | Renderiza el contenido solo si `usePermiso` lo permite |
| `AccesoDenegadoAviso` | `correlationId` | `cerrar()` | «Acceso denegado. Referencia: …» + copiar la referencia; **nunca** el motivo |
| `EventoAuditoriaDetalle` | `correlationId` | — | Evento completo para el auditor (`auditoria.consultar`) |

## 3. Reglas que cruzan ambos lados

| Regla | Backend | Frontend |
|---|---|---|
| ¿Quién decide si se permite? | **Siempre** `AutorizarOperacion` (ruta + caso de uso) | Nunca: pregunta al PDP y solo oculta o muestra |
| Ante un error | Fail-closed (DENEGADO / 500) | `permitido = false` |
| Motivo de la denegación | Solo en la bitácora | Nunca se muestra; solo el `correlation_id` |
| Trazabilidad | `X-Request-ID` en todas las respuestas | Lo genera y lo envía en cada petición |
