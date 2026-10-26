# Checklist de la revisión técnica formal — RBAC

**Proceso revisado:** «asignación de rol, autorización de una operación y denegación trazable» · **Paquete:** ver [plan §2](plan-revision-tecnica.md#2-alcance-y-paquete-de-revisión)

## Cómo se usa

- **Formulación de cada ítem.** Es una afirmación que se responde **Sí / No / N/A**. Un «No» genera un hallazgo con el [formato de hallazgos](formato-hallazgos.md).
- **Dónde mirar.** La columna **Dónde mirar** acota la lectura.
- **Verificación objetiva.** La columna **Cómo se verifica** da una comprobación objetiva: prueba automática, comando o búsqueda. El revisor **no** se limita a la prueba: lee el código buscando el caso que la prueba no cubre.
- **Perspectiva.** Cada ítem tiene un revisor responsable (plan §3), pero cualquiera puede registrar hallazgos en cualquier ítem.
- **Severidad por defecto.** Es la que se usa si el ítem falla. El moderador puede ajustarla en la reunión con el criterio del [formato](formato-hallazgos.md#2-severidad).

| Perspectiva | Revisor | Ítems |
|---|---|---|
| **D** · Dominio e integridad | R3 | RT-01..RT-05 |
| **C** · Contrato API | R2 | RT-06, RT-07, RT-12 |
| **S** · Seguridad: permisos, tenant e identidad | R1 | RT-08..RT-11, RT-15 |
| **A** · Arquitectura y dependencias | Moderador + R3 | RT-13, RT-14 |

## Checklist (15 ítems)

| ID | Persp. | Afirmación a verificar | Dónde mirar | Cómo se verifica | Base | Sev. si falla |
|---|:-:|---|---|---|---|:-:|
| RT-01 | D | Cada regla de dominio es **una clase con una sola condición y un solo código** de resultado. Las políticas las evalúan en el orden documentado y se detienen en la primera que falla. | `Dominio/Reglas/**`, `Dominio/Servicios/Politica*`, orden en `bootstrap/contenedor.php` | Leer cada regla. Pruebas DOM-01..04 (y el ejemplo OCP de la S2: `semana-02/ejemplo-ocp/verificar.php`). Comparar el orden de `contenedor.php` con el de la tabla de reglas de la S1. | R-01..R-08, RNF-05, RNF-06 | Menor |
| RT-02 | D | **Fail-closed.** Una excepción en una regla, una política sin reglas o la lectura de membresías caída producen DENEGADO o `false`. Ningún camino devuelve PERMITIDO por omisión. | `PoliticaAutorizacion`, `AutorizarOperacion`, `ConsultarPermisosEfectivos`, `Kernel::procesar` | DOM-03, PER-10, PEF-06, HTTP-08. Buscar `return true`, `?? true` y `catch` vacíos que dejen pasar. | R-05, RNF-01 | **Crítico** |
| RT-03 | D | **Una sola definición de vigencia.** «Vigente», «activa», «abierta» y «revocable» significan lo mismo en `Membresia`, en las reglas, en los repositorios (PHP y SQL) y en el índice único. Las diferencias son intencionales y están documentadas. | `Membresia::estaVigenteEn`, `ReglaMembresiaRevocable`, `ReglaSinMembresiaDuplicada`, `RepositorioMembresias*::{existeActiva,revocar}`, `schema.sql` | Tabla comparativa de las condiciones (`desde ≤ ahora < hasta`, `estado`, `hasta IS NULL`) en cada lugar. `herramientas/verificar-autorrevision.php` (AR-02). | R-02, R-03, RF-07 | Mayor |
| RT-04 | D | **Integridad de cambios.** Asignar o revocar y su evento (`ROL_ASIGNADO`, `ROL_REVOCADO`) ocurren en **una** transacción: si el evento falla, la membresía no cambia. | `RegistradorDeCambios`, `Transaccion`, `PdoTransaccion`, `CambioDeMembresia` | PER-04, PER-06, REV-05, INT-05 (con PostgreSQL). Revisar que no haya escrituras fuera de `ejecutar()`. | R-07 | **Crítico** |
| RT-05 | D | **Trazabilidad.** Todo intento denegado deja `ACCESO_DENEGADO` con actor, hospital, permiso, recurso, motivo, IP, fecha y `correlation_id`. La bitácora es append-only (puerto y trigger). Las consultas pasivas no auditan. Una falla de la bitácora **y** del respaldo es visible para alguien. | `AutorizarOperacion::denegar`, `EventoAuditoria`, `RegistroAuditoria`, `schema.sql` (trigger), `RegistroAuditoriaArchivo` | APP-03, PER-09 (respaldo), PEF-02, PEF-10. Leer los `catch` de `denegar()`. | R-06, RNF-02, RNF-03, RNF-14 | Mayor |
| RT-06 | C | **Contrato = código.** Las mismas operaciones (método + ruta), el mismo `x-permiso`, todos los códigos que el servidor devuelve documentados, y el lint es válido. | `api/openapi.yaml`, `Rutas.php` | API-01..03; `npx @redocly/cli@1.34.3 lint api/openapi.yaml` | RF-08, D-12 | Mayor |
| RT-07 | C | **Los errores no filtran información.** El 403 lleva solo un mensaje genérico y `correlation_id`. El 404 de auditoría es idéntico si el evento es de otro hospital. El 422 lista solo nombres de campos. Ninguna respuesta incluye rol, permiso faltante, motivo, traza ni SQL. | `ErrorVista`, `*Vista.php`, `Kernel::procesar` (catch → 500) | HTTP-03, HTTP-06, MVC-02, REV-06. Buscar `getMessage()` en las vistas. | R-08, RNF-04 | **Crítico** |
| RT-08 | S | **Rutas protegidas.** Toda ruta declara su permiso. Las únicas excepciones (`/decisiones`, `/permisos-efectivos`) están nombradas y justificadas. El permiso se evalúa **antes** del controlador. | `Rutas::definir`, `Kernel::procesar`, `MiddlewareAutorizacion` | ARQ-08, API-01. Leer el orden en `procesar()`. | RF-05, D-11 | **Crítico** |
| RT-09 | S | **Tenant.** Sin `X-Tenant-ID` → 400 antes de autenticar. El hospital del token debe coincidir con el de la solicitud en **toda** operación. Toda consulta filtra por hospital. | `Kernel`, `ReglaTenantCoincide`, `ConsultarEventoAuditoria`, repositorios (`WHERE hospital_id`) | HTTP-07, HTTP-06, PEF-05; paridad pasos 31 y 38 (S12) | R-01, RNF-08 | **Crítico** |
| RT-10 | S | **Identidad.** El usuario sale del token, nunca del cuerpo ni de un parámetro. Ningún endpoint responde por otra persona. La autenticación de demostración **no puede** quedar activa fuera de desarrollo. | `Formulario*::aComando`, `Peticion`, `Autenticador*`, `crearKernel`, `config.php` | Buscar `usuario_id` usado como actor. `herramientas/verificar-autorrevision.php` (AR-01). | R-04, OWASP API2 | **Crítico** |
| RT-11 | S | **Reglas administrativas.** No hay autoasignación ni autorrevocación; el rol debe estar en el catálogo, el usuario destino activo, el motivo tener 10 caracteres o más, y no se crean duplicados. | `Reglas/Asignacion/*`, `Reglas/Revocacion/*` | REV-01..06, DOM-*; paridad pasos 6–11 y 17–19 (S12) | R-03, R-04, RF-03, RF-14 | Mayor |
| RT-12 | C | **Validación en la frontera.** El formato (fechas ISO estrictas, patrón `modulo.accion`, longitudes, listas) se valida en `Formularios`; el dominio nunca recibe datos sin validar. | `Presentacion/Http/Formularios/*` | MVC-01..03, PEF-08; paridad pasos 12, 13 y 32 | RF-03, D-10 | Mayor |
| RT-13 | A | **Dependencias entre capas.** Presentación → Aplicación → Dominio ← Persistencia. El Dominio no usa PDO, HTTP ni el reloj del sistema. `Cliente` no conoce clases del servidor. | `src/**` | ARQ-01..07; `php bin/acoplamiento.php` (Ce y preocupaciones por clase) | D-07, D-08 | Mayor |
| RT-14 | A | **Solo dependencias estrictamente necesarias.** Sin Composer. Las extensiones PHP que el código usa están **declaradas**. Las herramientas de desarrollo (Node, Redocly, puppeteer, `tests/Dobles`) no son necesarias para ejecutar el flujo. Lo que se despliega no incluye código que el flujo no usa. | README (requisitos), `src/**` (búsqueda de `mb_*`, `pdo_*`, `json_*`), `public/index.php`, `src/Cliente` | Buscar con `grep -rn "mb_\|json_\|PDO"` en `src` y cruzar con el README. Revisar qué carga `public/index.php`. | D-13, RNF-06 | Menor |
| RT-15 | S | **Secretos y datos sensibles.** No hay credenciales en el repositorio. Los registros de acceso no incluyen tokens ni cuerpos. Los eventos no incluyen datos clínicos. | `config/config.php`, `Kernel::manejar` (registro), `EventoAuditoria` | OBS-01..03. Buscar `clave`, `password`, `Authorization` en registros. | RNF-04, D-15 | **Crítico** |

## Registro por revisor (durante la preparación)

Cada revisor copia esta tabla, marca **Sí / No / N/A** en sus ítems y anota los hallazgos preliminares en [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv):

| ID | Sí / No / N/A | Hallazgos (IDs) | Tiempo (min) |
|---|---|---|---|
| RT-01 … RT-15 | | | |
