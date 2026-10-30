# Hospital Nido: plan de integración

**Hospital Nido** (*nido*: protección y cuidado) es el sistema hospitalario integrado que une los 30 módulos de `docs/modulos/`. Cada módulo se desarrolló como prototipo independiente (`micro-his-*`, PHP sin framework) con su análisis, reglas, pruebas y diseños. Este plan los lleva a una sola plataforma funcional, por fases, sin dejar ninguno fuera.

## Decisiones de arquitectura

| Tema | Decisión | Por qué |
|---|---|---|
| Backend | Laravel 12 como **API REST** con arquitectura **MVC** | Lo acordado por el equipo. |
| Frontend | **React 19 + TypeScript** como aplicación separada que consume la API (servida por Vite desde el mismo Laravel) | Lo acordado por el equipo. TypeScript detecta en compilación los errores de contrato con la API. |
| Base de datos | PostgreSQL federado de Richard: base **CENTRAL** (red, identidad, MPI, consentimientos, sincronización) y una base **HOSPITAL** por hospital | Se conserva tal cual; ya tiene integridad probada (`tests/Database`). |
| Tenant | El tenant es el **hospital** (`hospitals` en CENTRAL), enviado en `X-Tenant-ID` | Coincide con el esquema federado; no hacía falta una tabla `tenants`. |
| Sesión | JWT de 15 minutos **emitido para un hospital** (claim `hid`), renovado por la SPA | Corrige el defecto del módulo de usuarios que aceptaba el token de otro hospital. |
| Roles y permisos | RBAC propio sobre `hospital_memberships` + tablas `roles`, `permissions`, `role_permissions` | El esquema de Richard ya guarda el rol por hospital en `hospital_memberships`; `spatie/laravel-permission` duplicaba esa asignación en otras tablas. |
| Paquetes retirados | `spatie/laravel-permission` y `stancl/tenancy` | No encajaban con el esquema federado y no estaban en uso (no había configuración de tenancy). |

### Cómo se pasa un prototipo a MVC

| En el prototipo (`micro-his-*`) | En Hospital Nido |
|---|---|
| Entidades y objetos de valor del dominio | Modelos Eloquent (`app/Models/Central`, `app/Models/Hospital`) y casts |
| Validación de entrada | Form Requests (`app/Http/Requests`) |
| Reglas y casos de uso | Servicios (`app/Services`), con los mismos códigos de error del módulo (p. ej. `AUTOASIGNACION`) |
| Controladores HTTP propios | Controladores Laravel (`app/Http/Controllers/Api/V1`) + API Resources |
| Puerto de auditoría | `AuditTrail`: bitácora encadenada del módulo de gobernanza |
| Pruebas del prototipo | Pruebas Feature/Unit de PHPUnit contra PostgreSQL |
| Prototipo de pantallas | Páginas React (`resources/js/pages`) |

## Fases

Cada fase deja algo que se puede usar y probar. El menú ya muestra todas las pantallas; las que aún no se integran tienen la marca «pronto» y dicen de qué módulos salen.

| Fase | Contenido | Módulos | Estado |
|---|---|---|---|
| 1 | Plataforma: login por hospital, cambio de hospital, usuarios, roles y permisos, auditoría, estructura de la aplicación | gestion-usuarios-tenants-autenticacion, rbac, gobernanza-auditoria, dashboard-base | **Lista** |
| 2 | Pacientes, médicos y citas | pacientes, medicos-especialidades, citas-medicas | Pendiente |
| 3 | Camas | catalogo-salas-camas, admision-hospitalaria-asignacion-cama, traslados-altas-camas, dashboard-ocupacion | Pendiente |
| 4 | Clínico | expediente-medico, notas-soap-diagnosticos, signos-vitales-alertas, alergias-clinicas-alerta-visual-expediente, catalogo-medicamentos, prescripciones-alergias, alertas-criticas-notificaciones-internas | Pendiente |
| 5 | Laboratorio, de la orden a la validación | catalogo-pruebas-laboratorio, ordenes-laboratorio-emr, recepcion-muestras, ingreso-resultados-laboratorio, validacion-resultados-bioquimico | Pendiente |
| 6 | Reportes | reportes-analytics-basicos, reportes-operativos-exportables | Pendiente |
| 7 | Calidad, contratos y despliegue | qa-regresion-defectos, qa-e2e-ci-despliegue, contratos-api, verificacion-bd-postgresql | Pendiente |
| 8 | App móvil y de escritorio | investigacion-prototipo-nativephp | Pendiente |

Son 30 módulos: 4 + 3 + 4 + 7 + 5 + 2 + 4 + 1. La prueba `AccessControlTest::test_every_permission_belongs_to_a_documented_module` falla si un módulo de `docs/modulos/` queda sin permiso en el catálogo, o si un permiso apunta a un módulo que no existe.

## Fase 1: qué se entregó

**Backend**
- Migración CENTRAL `2026_10_30_000000_create_nido_access_and_audit_tables`: `user_credentials`, `roles`, `permissions`, `role_permissions` y `audit_logs`. La tabla `audit_logs` viene del diseño `integracion-central.sql` del módulo de gobernanza: es de solo inserción (un trigger rechaza UPDATE, DELETE y TRUNCATE) y cada registro se sella con el anterior de su hospital.
- Catálogo en `config/nido.php`: 43 permisos (cada uno con su módulo de origen) y 10 roles. `RbacCatalogSeeder` lo copia a la base y se puede correr en cada despliegue.
- Reglas traídas de los módulos:
  - **Usuarios:** contraseña de 12 o más caracteres con letra y dígito; mensaje genérico ante credenciales malas; límite de 5 intentos con 429.
  - **RBAC:** no autoasignación ni autorrevocación; rol en catálogo; sin duplicados; vigencia válida; motivo de al menos 10 caracteres; el rol revocado corta el acceso en la petición siguiente.
  - **Gobernanza:** sello encadenado y verificación de la cadena.
- Reglas nuevas para evitar escalar privilegios: un administrador de hospital no asigna ni revoca el rol de administrador de red, y no desactiva a alguien que trabaja en otro hospital.
- Se cerraron tres defectos que la verificación de BD dejó abiertos en `ApiBoundaryTest`:
  - **E-01:** no existía el modelo `Tenant`;
  - **E-06:** la clase `JwtAuth` chocaba con el alias `JWTAuth`;
  - **E-07:** la ruta comodín de la SPA atrapaba `/api`.

**Frontend**
- Login con selección de hospital.
- Estructura de la aplicación: menú lateral según permisos, selector de hospital y cierre de sesión.
- Inicio por rol.
- Pantallas de usuarios, roles y permisos, y auditoría.
- Pantallas «pronto» para el resto de los módulos.

**Verificación**
- `php artisan test`: 90 correctas y 3 incompletas, que son los defectos AU-01, AU-02 y AU-05 del esquema clínico; se corrigen en las fases 2 y 3.
- Recorrido en Chrome real: 25/25 comprobaciones, axe-core sin violaciones WCAG A/AA en todas las pantallas y sin desborde horizontal a 375 px.

## Pendientes conocidos

- **Diseño de Stitch:** la paleta y los componentes actuales son provisionales. Al llegar el diseño se ajustan `resources/css/app.css` y `resources/js/components/ui.tsx`, y el cambio se propaga a todas las pantallas.
- **Esquema de Richard:** siguen pendientes las tareas 3.5 a 3.7 de `openspec/changes/postgresql-federated-schema-rebuild/tasks.md` (medicamentos, laboratorio, auditoría local). Se completan en las fases 4 y 5.
- **Una base HOSPITAL para todos:** por ahora los cuatro hospitales de demostración comparten la base HOSPITAL configurada. Una base por hospital necesita una conexión dinámica según el hospital del token, y queda para cuando haya datos clínicos (fase 2).
- **Dependencias:** `composer audit` reporta avisos de seguridad en paquetes del `composer.lock` heredado. Conviene actualizarlo en un PR aparte, con todas las pruebas.
