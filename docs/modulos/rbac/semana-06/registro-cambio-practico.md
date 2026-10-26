# Registro del cambio práctico defendido — «Revocar un rol»

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Cambio** | Permitir que un administrador **revoque** un rol, con motivo auditable, y que la decisión de autorización cambie de inmediato |
| **Método** | Pruebas primero (rojo → verde), sobre el MISMO Micro-HIS de las semanas 3 a 5 |
| **Resultado** | 67 pruebas: **65 pasan, 0 fallan**, 2 omitidas (requieren PostgreSQL) |

## 1. Solicitud

> «Hoy el sistema solo asigna roles. Si una enfermera se traslada de hospital, el administrador no tiene forma de quitarle el rol: sigue pudiendo registrar signos vitales. Necesitamos revocar roles, que la revocación quede justificada en la bitácora y que, desde ese momento, el sistema le deniegue las operaciones de ese rol.»

El cambio cubre los cinco aspectos que pide la tarea:

| Aspecto de la tarea | Cómo lo cubre el cambio |
|---|---|
| **Roles** | Una membresía activa pasa a `revoked`. |
| **Permisos** | Permiso nuevo `roles.revocar`, que se concede al rol `admin_hospital`. |
| **Rutas protegidas** | `POST /api/v1/roles/revocaciones`, protegida con `x-permiso: roles.revocar`. |
| **Decisiones de autorización** | Después de revocar, `AutorizarOperacion` responde DENEGADO (`MEMBRESIA_NO_VIGENTE`), **sin modificar su código**. |
| **Denegaciones auditables** | `ROL_REVOCADO` (con motivo) en la misma transacción; las denegaciones posteriores quedan como `ACCESO_DENEGADO` consultables por `correlation_id`. |

## 2. Requisitos nuevos

| ID | Requisito | Criterio de aceptación | Prueba |
|---|---|---|---|
| RF-11 | El administrador con `roles.revocar` puede revocar un rol activo y vigente de un usuario de su hospital, indicando un motivo. | Dado un admin con permiso y una membresía activa, cuando revoca con un motivo de 10 o más caracteres, entonces recibe 200 `ROL_REVOCADO`, la membresía queda `revoked` y existe `ROL_REVOCADO` con el motivo. | REV-01, REV-06 |
| RF-12 | Tras la revocación, las operaciones de ese rol se deniegan y se auditan. | Dado un usuario cuyo único rol fue revocado, cuando intenta una operación, entonces recibe 403 y el evento tiene el motivo `MEMBRESIA_NO_VIGENTE`. | REV-02 |
| RF-13 | Sin `roles.revocar`, la revocación se deniega y se audita. | Dado un usuario sin el permiso, cuando intenta revocar, entonces recibe 403 con `ACCESO_DENEGADO` (permiso `roles.revocar`) y la membresía no cambia. | REV-03 |
| RF-14 | Reglas de negocio de la revocación. | Autorrevocación → 422 `AUTORREVOCACION`; motivo < 10 caracteres → 422 `MOTIVO_INSUFICIENTE`; sin membresía activa y vigente → 404 `MEMBRESIA_NO_ENCONTRADA`. | REV-04 |
| RNF (R-07) | La revocación y su evidencia son atómicas. | Si la bitácora falla → 500 y la membresía sigue activa. | REV-05 |

**Por qué la autorrevocación está prohibida:** si el único administrador de un hospital se revocara a sí mismo, nadie podría volver a asignar roles en ese hospital.

## 3. Análisis de impacto (antes de programar)

| Artefacto | ¿Cambia? | Motivo |
|---|---|---|
| UML (semana 1) | Agrega CU-07 «Revocar rol» | Ya estaba previsto como cambio probable en la guía de defensa de la semana 1. |
| RF/RNF (semana 2) | Agrega RF-11 a RF-14 | Ver §2. |
| OCP (semana 2) | **Extensión**: `ReglaRevocacion` + `PoliticaRevocacion` + 3 reglas | Mismo patrón que la asignación; no se modifica ninguna regla existente. |
| Capas (semana 3) | Una pieza nueva por capa | Ver §4. |
| Repository (semana 4) | **El puerto cambia**: `revocar()` | Cambio de contrato: obliga a implementarlo en **ambos** adaptadores, y la suite de contrato lo verifica (CON-MEM-07). |
| Contrato API (semana 5) | Nueva operación; `info.version` pasa de 1.0.0 a **1.1.0** | Cambio compatible (operación nueva): no requiere `/api/v2`. |
| Esquema SQL | `CHECK` de `eventos_auditoria.tipo` admite `ROL_REVOCADO`; permiso nuevo en los datos demo | — |
| `AutorizarOperacion`, `PoliticaAutorizacion`, reglas de autorización, `Kernel`, middleware, transacciones, `Cliente` | **No cambian** | La decisión depende del **estado** de la membresía, no del código. Verificado con `git diff --quiet` (sin diferencias). |

## 4. Implementación

Archivos **nuevos** (13 de código y 1 de prueba):

| Capa | Archivo | Líneas | Rol |
|---|---|---:|---|
| Dominio | `Modelo/SolicitudRevocacion.php` | 19 | Datos de la revocación |
| Dominio | `Modelo/ErrorRevocacion.php` | 15 | Rechazo de una regla |
| Dominio | `Reglas/ReglaRevocacion.php` | 14 | Punto de extensión (OCP) |
| Dominio | `Reglas/Revocacion/ReglaNoAutorrevocacion.php` | 20 | Regla: 422 AUTORREVOCACION |
| Dominio | `Reglas/Revocacion/ReglaMotivoSuficiente.php` | 22 | Regla: 422 MOTIVO_INSUFICIENTE |
| Dominio | `Reglas/Revocacion/ReglaMembresiaRevocable.php` | 35 | Regla: 404 MEMBRESIA_NO_ENCONTRADA |
| Dominio | `Servicios/PoliticaRevocacion.php` | 40 | Aplica las reglas en orden |
| Aplicación | `Comandos/ComandoRevocarRol.php` | 20 | Entrada del caso de uso |
| Aplicación | `Resultados/ResultadoRevocacion.php` | 37 | Salida: 200/403/404/422/500 |
| Aplicación | `CasosDeUso/RevocarRol.php` | 106 | Autoriza `roles.revocar` → reglas → transacción (revocar + `ROL_REVOCADO`) |
| Presentación | `Formularios/FormularioRevocacion.php` | 43 | Formato del JSON (422 `DATOS_INVALIDOS`) |
| Presentación | `Vistas/RevocacionVista.php` | 22 | Formato de la respuesta |
| Presentación | `Controladores/RevocacionRolControlador.php` | 32 | Formulario → caso de uso → vista |
| Pruebas | `tests/Aplicacion/RevocacionPrueba.php` | 79 | REV-01..06 |

Archivos **modificados** (en total sobre el micro-HIS: 16 archivos, +227 / −37 líneas):

| Archivo | Cambio |
|---|---|
| `Dominio/Puertos/RepositorioMembresias.php` | + método `revocar()` |
| `Dominio/Modelo/EventoAuditoria.php` | + constante `ROL_REVOCADO` |
| `Persistencia/Pdo/PdoRepositorioMembresias.php` | `revocar()` con `UPDATE` preparado; devuelve `rowCount()` |
| `Persistencia/EnMemoria/RepositorioMembresiasEnMemoria.php` | `revocar()` con la misma semántica |
| `Presentacion/Http/Rutas.php` | + ruta `POST /api/v1/roles/revocaciones` con permiso `roles.revocar` |
| `bootstrap/contenedor.php` | + caso de uso `revocarRol` y controlador; `construirRutas()` (refactor previo) |
| `api/openapi.yaml` | + operación, esquemas `SolicitudRevocacion` y `RevocacionRealizada`, versión 1.1.0 |
| `database/schema.sql`, `datos-demo.sql`, `datos-demo.php` | `ROL_REVOCADO` en el `CHECK`; permiso `roles.revocar` para `admin_hospital` |
| `bin/demo.php` | Pasos 6–8: revocar → denegado → motivo en la bitácora |
| Pruebas existentes | `Escenario` (`caso()`, `rutas()`, `comandoRevocar()`), decorador con `revocar()`, CON-MEM-07, API-03 (4 escenarios nuevos), ARQ-08 usa `rutas()` |

## 5. Pruebas: rojo → verde

| Momento | Resultado | Evidencia |
|---|---|---|
| 0. Refactor previo (`construirRutas()`, sin cambio de comportamiento) | 60 pruebas: 58 pasan, 0 fallan | — |
| 1. Pruebas nuevas escritas, **sin implementación** | 67 pruebas: **57 pasan, 8 fallan** (REV-01..06, CON-MEM-07, API-03) | [`evidencia/cambio-practico-1-antes-rojo.txt`](evidencia/cambio-practico-1-antes-rojo.txt) |
| 2. Implementación lista, **contrato aún sin actualizar** | 67 pruebas: 63 pasan, **2 fallan (API-01, API-03)**: la red de seguridad del contrato detectó la ruta sin documentar | [`evidencia/cambio-practico-2-contrato-desactualizado.txt`](evidencia/cambio-practico-2-contrato-desactualizado.txt) |
| 3. Contrato actualizado (v1.1.0) | 67 pruebas: **65 pasan, 0 fallan**; Redocly: válido | [`evidencia/cambio-practico-3-despues-verde.txt`](evidencia/cambio-practico-3-despues-verde.txt), [`evidencia/salida-lint-openapi.txt`](evidencia/salida-lint-openapi.txt) |

Las pruebas de arquitectura (ARQ-01..08) siguieron en verde durante todo el cambio. Eso confirma que las piezas nuevas respetan:

- la regla de dependencias;
- el controlador sin SQL ni reglas de negocio;
- un adaptador PDO y uno InMemory por puerto;
- que solo el PDP queda sin permiso propio.

## 6. Demostración de punta a punta

`php bin/demo.php`, pasos 6 a 8. Salida en [`evidencia/salida-demo-revocacion.txt`](evidencia/salida-demo-revocacion.txt):

```
▶ 6. El administrador revoca el rol «enfermeria» de la enfermera, con motivo
  ← 200 {"codigo":"ROL_REVOCADO","correlation_id":"142371fa-…"}
▶ 7. La enfermera intenta registrar signos vitales otra vez (ahora denegado)
  ← 403 {"mensaje":"Acceso denegado.","correlation_id":"0c56479a-…"}
▶ 8. El administrador ve el motivo de esa denegación en la bitácora
  ← 200 {…"tipo":"ACCESO_DENEGADO",…"motivo":"MEMBRESIA_NO_VIGENTE"…}
```

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa descartada | Motivo |
|---|---|---|
| `POST /roles/revocaciones` (la revocación es un recurso) | `DELETE /roles/asignaciones/{id}` | La membresía no se borra (se conserva como `revoked` para la auditoría), la revocación lleva un cuerpo (el motivo) y no hace falta exponer ids internos de membresía. |
| Estado `revoked` (sin borrar) | `DELETE FROM membresias` | La historia de quién tuvo qué rol es evidencia; el esquema ya tenía ese estado. |
| Motivo obligatorio como **regla de dominio** (≥ 10 caracteres) | Validarlo solo en el formulario | Que exista un motivo es formato; que sea suficiente es una política del hospital. Así funciona igual desde cualquier canal. |
| Revocar también membresías activas **con** fecha de fin futura | Solo las abiertas (`valido_hasta` nulo) | Una membresía con fin futuro también da permisos hoy. |
| Si otra solicitud revocó primero (0 filas) → 404 sin evento | Registrar un `ROL_REVOCADO` vacío | No se audita algo que no ocurrió. |
| Versión 1.1.0 del contrato | `/api/v2` | Solo se agrega una operación: es compatible con los clientes actuales. |

## 8. Riesgos y pendientes

- **Réplicas locales:** al integrar con el HIS, la revocación debe propagarse a `local_user_refs` mediante el evento `rbac.membresia.revocada` (semana 5, §7). El límite de 60 s exige que los permisos críticos se consulten en la fuente.
- **Base real:** CON-PDO-07 (`UPDATE` real) está escrito, pero se omite sin credenciales de PostgreSQL.
- **Último administrador:** la regla de autorrevocación evita el caso más común. Que un admin revoque al **último otro** admin del hospital todavía es posible. Se resolvería con una regla nueva `ReglaNoUltimoAdministrador`, que también sería una clase más (OCP).
