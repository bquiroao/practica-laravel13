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
| 2 | Pacientes, médicos y citas | pacientes, medicos-especialidades, citas-medicas | **Lista** |
| 3 | Camas | catalogo-salas-camas, traslados-altas-camas, dashboard-ocupacion | **Lista** |
| 4 | Clínico | expediente-medico, notas-soap-diagnosticos, signos-vitales-alertas; prescripciones-alergias cuando existan los catálogos de medicamentos y alergias | Pendiente |
| 5 | Laboratorio, de la orden a la validación | ordenes-laboratorio-emr, recepcion-muestras, ingreso-resultados-laboratorio, validacion-resultados-bioquimico (sobre el catálogo de pruebas) | Pendiente |
| 6 | Reportes | reportes-operativos-exportables | Pendiente |
| 7 | Calidad, contratos y despliegue | qa-regresion-defectos, qa-e2e-ci-despliegue, contratos-api, verificacion-bd-postgresql | Pendiente |
| 8 | App móvil y de escritorio | investigacion-prototipo-nativephp | Pendiente |
| Disponibles | Se integran en ramas propias, siguiendo la sección 7 del `README.md` | catalogo-pruebas-laboratorio, admision-hospitalaria-asignacion-cama, reportes-analytics-basicos, alertas-criticas-notificaciones-internas, alergias-clinicas-alerta-visual-expediente, catalogo-medicamentos | Disponibles |

Son 30 módulos: 4 + 3 + 3 + 4 + 4 + 1 + 4 + 1 en las fases, más los 6 disponibles. La prueba `AccessControlTest::test_every_permission_belongs_to_a_documented_module` falla si un módulo de `docs/modulos/` queda sin permiso en el catálogo, o si un permiso apunta a un módulo que no existe.

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

## Fase 2: qué se entregó

**Una base clínica por hospital.** La base HOSPITAL no tiene columna de hospital: cada hospital es su propia base, como define el esquema federado.
- `HospitalDatabase` cambia la conexión «hospital» según el hospital de la petición (`metadata.database` en CENTRAL).
- `php artisan nido:hospitales` crea, migra y siembra cada base.
- Los pacientes de un hospital no aparecen en otro.

**Pacientes** (módulo pacientes):
- código por hospital (`HN-CEN-000123`);
- documento DPI (13 dígitos, departamento 01 a 22) o pasaporte (6 a 20 caracteres, país de 3 letras);
- el mismo documento bloquea el registro e indica la ficha existente (R-02);
- mismo nombre y nacimiento advierte y exige motivo para continuar (R-03);
- cambiar la identidad o el estado exige motivo (R-07);
- control de versión: si otra persona editó la ficha, se avisa en lugar de sobrescribir;
- búsqueda por código, documento o nombre de 3+ letras, sin importar tildes (R-10);
- el documento se muestra enmascarado a quien no puede editar.

**Médicos y especialidades** (módulo médicos-especialidades):
- perfil solo para cuentas con rol «Médico», con colegiado único;
- especialidades con vigencia y una sola principal;
- horario semanal en bloques de 30 minutos, sin cruces;
- franjas libres = horario − citas activas, hasta 14 días.

**Citas** (módulo citas médicas):
- horario futuro y dentro del horario del médico;
- especialidad vigente;
- sin cruces de médico ni de paciente; si otra persona toma el horario al mismo tiempo, la base lo impide y se pide elegir otro;
- transiciones programada → confirmada → atendida y cancelación con motivo;
- solo se marca «atendida» al terminar su hora.

**Para que sea fácil de usar:**
- una sola caja de búsqueda con ejemplos;
- formularios en secciones con indicaciones;
- un diálogo que ofrece abrir la ficha existente antes de duplicar;
- la cita se agenda en 4 pasos y solo muestra horarios libres;
- la agenda muestra en cada cita solo las acciones permitidas;
- el horario del médico se edita en la misma pantalla, con atajo «copiar lunes a martes–viernes»;
- mensajes de confirmación después de cada acción.

**Verificación:**
- `php artisan test`: 109 correctas y 2 incompletas (AU-01 episodios y AU-05 camas, de la fase 3).
- Se cerró **AU-02**: código y nombres de paciente en blanco.
- Recorrido en Chrome: 30/30, sin violaciones WCAG A/AA y sin desborde a 375 px.

## Fase 3: qué se entregó

**Catálogo de salas y camas** (módulo catalogo-salas-camas):
- salas con capacidad opcional;
- camas con código único dentro de su sala y tipo (general, UCI, aislamiento, pediátrica);
- la capacidad no se puede superar, ni con dos altas a la vez (la sala se bloquea al agregar una cama);
- cambios de estado con motivo, por transiciones permitidas;
- una cama ocupada no se cambia desde el catálogo.

**Traslados y altas** (módulo traslados-altas-camas):
- trabajan sobre la estancia abierta que crea la admisión;
- el traslado cierra la estancia y abre otra en una cama disponible, en una sola transacción, y puede exigir cama de UCI;
- el alta registra su tipo (médica, voluntaria, referencia o defunción) y un resumen, cierra el episodio y deja la cama en mantenimiento para limpieza;
- la defunción marca la ficha del paciente como fallecido.

**Ocupación** (módulo dashboard-ocupacion):
- porcentaje sobre las camas operativas;
- «ocupada» se cuenta por la estancia abierta, no por el estado guardado;
- avisa de las camas cuyo estado no coincide (relacionado con AU-05).

**Pantallas:**
- mapa de camas por sala con colores, filtro por estado y detalle con las acciones permitidas;
- tablero de ocupación que se actualiza cada minuto.

**Verificación:**
- 8 pruebas Feature nuevas (`BedTest`);
- recorrido en Chrome: 15/15, sin violaciones WCAG A/AA y sin desborde a 375 px.
- Las pruebas del esquema que revierten migraciones por pasos ahora cuentan los pasos solas, así una migración nueva no las rompe.
- Los internamientos de la demostración se cargan como datos de ejemplo; el flujo real de admisión lo integra su propio módulo.

## Pendientes conocidos

- **Diseño de Stitch:** la paleta y los componentes actuales son provisionales. Al llegar el diseño se ajustan `resources/css/app.css` y `resources/js/components/ui.tsx`, y el cambio se propaga a todas las pantallas.
- **Identidad protegida (R-11 del módulo pacientes):** pacientes confidenciales visibles solo con un permiso especial. Queda para una fase posterior, porque necesita permisos nuevos.
- **Esquema de Richard:** siguen pendientes las tareas 3.5 a 3.7 de `openspec/changes/postgresql-federated-schema-rebuild/tasks.md` (medicamentos, laboratorio, auditoría local). Se completan en las fases 4 y 5.
- **Dependencias:** `composer audit` reporta avisos de seguridad en paquetes del `composer.lock` heredado. Conviene actualizarlo en un PR aparte, con todas las pruebas.
