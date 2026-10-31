# Hospital Nido — Sistema Hospitalario Integrado

**Hospital Nido** (*nido*: protección y cuidado) es el sistema hospitalario integrado del Proyecto Final de Análisis de Sistemas II 2026. Une en una sola plataforma los 30 módulos analizados y prototipados en `docs/modulos/` para una red de varios hospitales:
- pacientes, citas y camas;
- expediente y laboratorio;
- reportes, calidad y administración.

> Requisito transversal: el sistema debe ser **fácil de usar, intuitivo y entenderse solo al usarlo**. Cada pantalla explica qué hacer, valida en el momento y confirma cada acción.

## Contenido

1. [Tecnologías y arquitectura](#1-tecnologías-y-arquitectura)
2. [Cómo levantar el sistema](#2-cómo-levantar-el-sistema)
3. [Usuarios de demostración](#3-usuarios-de-demostración)
4. [Estado de los módulos](#4-estado-de-los-módulos)
5. [Estructura del proyecto](#5-estructura-del-proyecto)
6. [Cómo continuar un módulo](#6-cómo-continuar-un-módulo)
7. [Guía de los módulos disponibles](#7-guía-de-los-módulos-disponibles)
8. [Pruebas](#8-pruebas)
9. [Flujo de trabajo con Git](#9-flujo-de-trabajo-con-git)
10. [API](#10-api)

---

## 1. Tecnologías y arquitectura

| Capa | Tecnología |
|---|---|
| Backend | **Laravel 12** (PHP 8.2+) como **API REST** con arquitectura **MVC** |
| Frontend | **React 19 + TypeScript**, Vite, React Router, TanStack Query, React Hook Form + Zod, Tailwind CSS 4 (componentes al estilo shadcn/ui) |
| Base de datos | **PostgreSQL 15+** federado: una base **CENTRAL** y una base **HOSPITAL por cada hospital** |
| Autenticación | JWT de 15 minutos emitido **para un hospital** (`tymon/jwt-auth`), renovado por la aplicación |
| Autorización | RBAC propio: roles por hospital (`hospital_memberships`) y permisos por módulo (`config/nido.php`) |
| Auditoría | Bitácora de solo inserción, sellada en cadena (SHA-256) por hospital |

**Cómo encaja MVC con React:**

| Capa | Dónde vive |
|---|---|
| Modelo | Modelos Eloquent (`app/Models/Central`, `app/Models/Hospital`) + servicios con las reglas de negocio (`app/Services`) |
| Controlador | Controladores de la API (`app/Http/Controllers/Api/V1`), con validación en Form Requests y permisos en el middleware `permiso:` |
| Vista | API Resources (`app/Http/Resources`), que dan forma al JSON, y páginas React (`resources/js/pages`), que lo muestran |

**Bases de datos:**
- **CENTRAL:** la red y sus hospitales, usuarios y credenciales, roles y permisos, índice maestro de pacientes, consentimientos, bitácora de auditoría, sincronización, y sesiones, caché y colas.
- **HOSPITAL, una por hospital:** todo lo clínico (pacientes, médicos, citas, camas, expediente, laboratorio…).
  - Las tablas no tienen columna de hospital: cada hospital es su propia base.
  - La conexión `hospital` apunta automáticamente a la base del hospital con el que se inició sesión (`App\Services\Hospital\HospitalDatabase`).
  - Entre las dos bases no hay llaves foráneas físicas, solo referencias por UUID.

El plan de integración, las decisiones de diseño y lo entregado en cada fase están en [`docs/hospital-nido/PLAN.md`](docs/hospital-nido/PLAN.md).

---

## 2. Cómo levantar el sistema

**Requisitos** (en Windows, Linux o macOS):
- PHP 8.2 o superior, con las extensiones `pdo_pgsql` y `pgsql` activadas en `php.ini`;
- Composer 2;
- Node.js 20 o superior;
- PostgreSQL 15 o superior (probado con 16 y 18).

Necesitas la contraseña de tu usuario `postgres`. Si no la recuerdas, en la sección «Problemas comunes» explicamos cómo poner una nueva.

### Primera vez (unos 5 minutos)

**1. Descargar el proyecto e instalar dependencias:**
```bash
git clone https://github.com/compilations-teams/sistema-hospitalario-integrado-SistenasII-2026-G1.git
cd sistema-hospitalario-integrado-SistenasII-2026-G1
git checkout develop
composer install
npm install
```

**2. Crear la configuración:**
```bash
cp .env.example .env          # en PowerShell: copy .env.example .env
php artisan key:generate
php artisan jwt:secret
```

**3. En `.env`, poner el puerto y la contraseña de tu PostgreSQL**, en las dos secciones:
```
CENTRAL_DB_PORT=5432
CENTRAL_DB_PASSWORD=tu_contraseña
HOSPITAL_DB_PORT=5432
HOSPITAL_DB_PASSWORD=tu_contraseña
```

**4. Instalar todo con un solo comando:**
```bash
php artisan nido:instalar
```
El comando:
- comprueba la conexión a PostgreSQL;
- crea las bases que falten: `his_central`, `his_hospital`, y una por cada hospital adicional (`his_hospital_nor`, `his_hospital_ori` y `his_hospital_occ`);
- crea las tablas;
- carga roles, permisos, los 4 hospitales, usuarios, especialidades y médicos;
- agrega datos de ejemplo: pacientes, citas, camas, internados y alergias.

Debe terminar con `Listo`. Con `--fresh` vuelve a empezar desde cero; **se pierde todo lo que haya**.

**5. Compilar las pantallas y encender:**
```bash
npm run build
php artisan serve
```
Abre **http://localhost:8000** y entra con un usuario de la [sección 3](#3-usuarios-de-demostración).

Para que las alertas críticas se escalen solas cuando vence su plazo, deja abierto el planificador en otra terminal:
```bash
php artisan schedule:work
```

### Las siguientes veces
```bash
php artisan serve
php artisan schedule:work      # en otra terminal: escalamiento de alertas
```

### Cuando traigas cambios de `develop`
```bash
git pull origin develop
composer install
npm install
php artisan nido:instalar      # aplica las migraciones nuevas sin borrar datos
npm run build
```

Mientras programas, deja `npm run dev` abierto en otra terminal para ver los cambios de React al instante.

### Problemas comunes

| Mensaje | Solución |
|---|---|
| `password authentication failed` | La contraseña de `.env` no es la de tu usuario `postgres`. |
| `could not find driver` | Activa `extension=pdo_pgsql` y `extension=pgsql` en `php.ini` y vuelve a abrir la terminal. |
| «Demasiados intentos» al iniciar sesión | `php artisan cache:clear` |
| La página sale en blanco | `npm run build` |
| `relation "..." does not exist` | `php artisan nido:instalar` |

**No recuerdo la contraseña de `postgres` (Windows):**
1. En una terminal **como administrador**, abre `C:\Program Files\PostgreSQL\18\data\pg_hba.conf`. En las líneas `host … 127.0.0.1/32` y `host … ::1/128`, cambia `scram-sha-256` por `trust` y guarda.
2. Ejecuta `Restart-Service postgresql-x64-18`.
3. Ejecuta `& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "ALTER USER postgres PASSWORD 'NuevaClave';"`.
4. Regresa esas líneas a `scram-sha-256`, guarda y reinicia el servicio otra vez.

---

## 3. Usuarios de demostración

Se crean con `php artisan db:seed`, solo en los entornos `local` y `testing`. **Contraseña de todos: `NidoDemo2026`.**

| Correo | Rol | Para probar |
|---|---|---|
| `admin@nido.test` | Administrador de red | Todo, en los 4 hospitales; edita la matriz de permisos |
| `admin.hospital@nido.test` | Administrador de hospital | Usuarios, roles, auditoría, médicos y horarios |
| `recepcion@nido.test` | Recepción y admisión | Pacientes y citas |
| `medico@nido.test` | Médico (Central y Norte) | Su agenda; cambio de hospital |
| `pediatria@nido.test`, `cardiologia@nido.test` | Médico | Otros especialistas |
| `enfermeria@nido.test` | Enfermería | Consulta de pacientes |
| `laboratorio@nido.test`, `bioquimico@nido.test` | Laboratorio | Módulos de laboratorio |
| `farmacia@nido.test` | Farmacia | Catálogo de medicamentos |
| `direccion@nido.test` | Dirección médica | Reportes y ocupación |
| `calidad@nido.test` | Calidad y QA | Auditoría y calidad |

---

## 4. Estado de los módulos

Los 30 módulos de `docs/modulos/` tienen su lugar en el sistema. **Los 30 están integrados** y funcionando. La sección 7 queda como guía para conectarse a los últimos que se integraron.

| Área | Módulo (`docs/modulos/…`) | Estado |
|---|---|---|
| Plataforma | `gestion-usuarios-tenants-autenticacion` | ✅ Integrado |
| | `rbac` | ✅ Integrado |
| | `gobernanza-auditoria` | ✅ Integrado |
| | `dashboard-base` | ✅ Integrado (inicio por rol) |
| | `contratos-api` | ✅ Integrado |
| | `verificacion-bd-postgresql` | ✅ Integrado |
| Pacientes y agenda | `pacientes` | ✅ Integrado |
| | `medicos-especialidades` | ✅ Integrado |
| | `citas-medicas` | ✅ Integrado |
| Camas | `catalogo-salas-camas` | ✅ Integrado |
| | `admision-hospitalaria-asignacion-cama` | ✅ Integrado (asistente en `/camas`) |
| | `traslados-altas-camas` | ✅ Integrado |
| | `dashboard-ocupacion` | ✅ Integrado |
| Clínico | `expediente-medico` | ✅ Integrado |
| | `notas-soap-diagnosticos` | ✅ Integrado |
| | `signos-vitales-alertas` | ✅ Integrado |
| | `alergias-clinicas-alerta-visual-expediente` | ✅ Integrado (banner en la ficha y el expediente) |
| | `catalogo-medicamentos` | ✅ Integrado (catálogo administrable en `/prescripciones`) |
| | `prescripciones-alergias` | ✅ Integrado (receta desde el catálogo de medicamentos) |
| | `alertas-criticas-notificaciones-internas` | ✅ Integrado (campana y `/alertas`, con escalamiento por tiempo) |
| Laboratorio | `catalogo-pruebas-laboratorio` | ✅ Integrado (catálogo administrable en `/laboratorio`) |
| | `ordenes-laboratorio-emr` | ✅ Integrado (ordena desde el catálogo de pruebas) |
| | `recepcion-muestras` | ✅ Integrado |
| | `ingreso-resultados-laboratorio` | ✅ Integrado |
| | `validacion-resultados-bioquimico` | ✅ Integrado |
| Gestión | `reportes-analytics-basicos` | ✅ Integrado (19 indicadores trazables) |
| | `reportes-operativos-exportables` | ✅ Integrado |
| Calidad | `qa-regresion-defectos` | ✅ Integrado |
| | `qa-e2e-ci-despliegue` | ✅ Integrado |
| Móvil | `investigacion-prototipo-nativephp` | ✅ Integrado (aplicación instalable, PWA) |

**Leyenda:**
- ✅ funciona de punta a punta, con pruebas;

---

## 5. Estructura del proyecto

```
app/
  Console/Commands/         nido:hospitales (prepara la base de cada hospital)
  Exceptions/               DomainRuleException: regla rechazada → {message, code, details}
  Http/Controllers/Api/V1/  controladores (Admin/, Clinical/, Auth, Hospital)
  Http/Middleware/          tenant (hospital), sesion (JWT), permiso:<código>
  Http/Requests/            validación de entrada (Form Requests)
  Http/Resources/           forma del JSON de respuesta
  Models/Central/           modelos de la base CENTRAL
  Models/Hospital/          modelos de la base del hospital
  Services/                 reglas de negocio por módulo (Patients, Doctors, Appointments, Audit…)
config/nido.php             catálogo de roles y permisos (cada permiso indica su módulo)
database/migrations/
  central/                  esquema CENTRAL
  hospital/                 esquema clínico (se aplica a la base de cada hospital)
  framework/                sesiones, caché y colas (en CENTRAL)
database/seeders/           catálogo RBAC, red de demostración, especialidades y datos clínicos
resources/js/
  components/ui.tsx         componentes base (Button, Field, Card, Dialog, Table…)
  lib/                      api.ts (cliente y errores), format.ts (fechas, horas, etiquetas)
  modules/catalog.ts        menú: cada pantalla, su permiso, sus módulos y si ya está lista
  pages/                    una carpeta por área (patients, doctors, appointments, admin…)
  routes.tsx                rutas de la aplicación
routes/api.php              rutas de la API (/api/v1)
tests/
  Database/                 integridad del esquema PostgreSQL
  Feature/Platform/         autenticación, RBAC y auditoría
  Feature/Clinical/         pacientes, médicos y citas
docs/
  hospital-nido/PLAN.md     plan de integración y decisiones
  modulos/                  análisis, diseño y prototipo de cada módulo (semanas 1–15)
```

---

## 6. Cómo continuar un módulo

Cada módulo de `docs/modulos/<modulo>/` trae su análisis, sus reglas de negocio y un prototipo en `micro-his-*/` (PHP sin framework, con pruebas). **Integrarlo** es llevar esas reglas y pruebas al proyecto Laravel + React con estos pasos.

El ejemplo completo a imitar es el módulo de pacientes:
- `app/Services/Patients/PatientService.php`;
- `app/Http/Controllers/Api/V1/Clinical/PatientController.php`;
- `app/Http/Requests/Clinical/PatientRequest.php` y `app/Http/Resources/PatientResource.php`;
- `resources/js/pages/patients/`;
- `tests/Feature/Clinical/PatientTest.php`.

**1. Lee el módulo.** El `README.md` del módulo y su `micro-his-*/src/Dominio` dicen las reglas, los códigos de error y los casos de prueba. Esas reglas son las que se respetan.

**2. Base de datos.**
- Revisa si las tablas ya existen en `database/migrations/hospital/` (por ejemplo, `allergies`, `vital_signs`, `care_episodes`, `bed_stays`, `wards`, `beds`).
- Si falta algo, crea una migración **nueva** en esa carpeta con la fecha de hoy. No edites las existentes.
- Usa SQL con `protected $connection = 'hospital'`, como las demás.
- Toda llave foránea va con `ON DELETE RESTRICT` y con un índice que empiece por sus columnas. `tests/Database/Verification/MigrationReversibilityTest` lo exige, y también exige que `down()` deje el esquema exactamente como estaba.
- Aplícala con `php artisan nido:hospitales`.

**3. Modelo.** En `app/Models/Hospital/`, extiende `HospitalModel` (conexión `hospital`, llaves UUID).

**4. Reglas de negocio.**
- Crea un servicio en `app/Services/<Área>/`.
- Cuando una regla se rechace, lanza `DomainRuleException('CODIGO', 'Mensaje claro para el usuario', 422|409|403, $detalles)`. Usa el mismo código del prototipo del módulo.
- Registra cada escritura en la bitácora: `$this->audit->record('<modulo>', 'ACCION', 'exito', $hospital->hospital_id, $user->user_id, 'recurso:id', [...], $ip)`.
- Si necesitas el usuario dentro de la base clínica (para firmar un registro), usa `UserRefSync::forUser($user, $hospital)`.

**5. Validación y controlador.**
- Form Request en `app/Http/Requests/`, con los nombres de los campos en español en `attributes()`.
- Controlador delgado en `app/Http/Controllers/Api/V1/Clinical/`: valida, llama al servicio y devuelve un Resource.
- **Busca los registros por id dentro del controlador** (`Model::query()->find($id)`), sin route model binding: el binding correría antes de elegir la base del hospital.

**6. Rutas y permisos.**
- Agrega las rutas en `routes/api.php`, dentro del grupo `sesion`, con `->middleware('permiso:<código>')`. Para aceptar cualquiera de dos permisos: `permiso:a|b`.
- Los permisos de cada módulo **ya existen** en `config/nido.php`.
- Si necesitas uno nuevo, agrégalo ahí (indicando su módulo), asígnalo a los roles que correspondan y corre `php artisan db:seed --class=RbacCatalogSeeder`.

**7. Pantalla.**
- Crea la página en `resources/js/pages/<área>/` y regístrala en `resources/js/routes.tsx` con carga diferida (`lazy`), como las demás.
- En `resources/js/modules/catalog.ts`, pon `ready: true` en la entrada de tu módulo.
- Usa los componentes de `components/ui.tsx`.
- Para mostrar errores de la API, usa `errorMessage`, `fieldErrors` y `ruleError`, de `lib/api.ts`.
- Para fechas y horas, usa `formatDate` y `formatTime`, de `lib/format.ts`: muestran la hora del hospital.

**8. Pruebas.**
- Crea pruebas Feature en `tests/Feature/Clinical/` que extiendan `ClinicalTestCase`. Esa clase deja una base limpia con especialidades, médicos con horario y 10 pacientes.
- `$this->as('recepcion@nido.test')` devuelve los encabezados de sesión de ese usuario.
- Prueba al menos:
  - el camino feliz;
  - cada regla del módulo, con su código de error;
  - que un usuario sin permiso reciba 403.

**9. Documenta.** Actualiza la [sección 4](#4-estado-de-los-módulos), la [sección 10](#10-api) y `docs/hospital-nido/PLAN.md`.

**Criterios de facilidad de uso** (obligatorios en toda pantalla):
- Título y una línea que diga para qué sirve la pantalla.
- Etiquetas en español claro.
- Debajo de cada campo con formato, una indicación con un ejemplo («13 dígitos», «Ej.: Neurología»).
- Errores junto al campo que los causa, diciendo qué hacer, no solo qué falló.
- Solo las acciones que el usuario puede hacer, según su permiso y el estado del registro.
- Un mensaje que confirme cada acción («Cita agendada», «Horario guardado»).
- Confirmación antes de las acciones que no se pueden deshacer.
- Estados vacíos con una salida («No hay citas este día · Agendar una cita»).
- Contraste AA (no usar `text-slate-400` para texto), todo usable con teclado y sin desborde horizontal a 375 px de ancho.

---

## 7. Guía de los módulos disponibles

Estos módulos están listos para tomarse (ya están todos integrados y quedan como guía para conectarse a ellos). En cada uno se indica qué existe, qué falta y con qué se conecta. Trabaja cada uno en su propia rama ([sección 9](#9-flujo-de-trabajo-con-git)).

### 7.1 Catálogo de pruebas de laboratorio — `catalogo-pruebas-laboratorio` ✅ Integrado
- **Qué hay:**
  - la tabla `lab_tests`: una fila por versión, con los requisitos de muestra, los valores de referencia y críticos, y la vigencia `[valid_from, valid_to]` (`valid_to` es el último día vigente). La base repite las reglas: dos versiones del mismo código no se cruzan, cada columna tiene los límites de la API y una versión no se edita ni se borra;
  - `App\Services\LabCatalog\LabTestCatalogService`: buscar lo vigente en una fecha, registrar y crear una versión nueva. Es el único que escribe en `lab_tests`. La regla de vigencia (RN-05) está aparte, en `ValidityPlan`;
  - el catálogo inicial: las 10 pruebas del catálogo provisional, con sus requisitos de muestra. **Conservan su código** (`HB`, `GLU`…), porque las órdenes, los resultados y el aviso de resultado crítico ya lo usan; las pruebas nuevas usan el formato `AAA-000`;
  - la pantalla `LabTestCatalog` (`resources/js/modules/labtests/`), en la pestaña «Pruebas disponibles» de `/laboratorio`;
  - el indicador «Pruebas por área del laboratorio» en reportes.
  - Detalle en `docs/hospital-nido/PLAN.md`.
- **Acuerdo de integración:**
  - órdenes, recepción, ingreso y validación leen el catálogo con `App\Services\LabCatalog\DatabaseLabTestDirectory`, registrado en `AppServiceProvider`. Solo se puede ordenar lo vigente hoy;
  - `LabTestDirectory` devuelve `code`, `name`, `specimen_type`, `unit`, `ref_low`, `ref_high`, `critical_low` y `critical_high`, y además `section`, `fasting_hours`, `preparation_instructions` y `turnaround_hours`;
  - las órdenes siguen guardando una copia de los datos de cada prueba al pedirla, así que una versión nueva no cambia la historia clínica;
  - la lista `pruebas_laboratorio` de `config/nido_catalogos.php` y `ConfigLabTestDirectory` ya no se usan.

### 7.2 Admisión hospitalaria y asignación de cama — `admision-hospitalaria-asignacion-cama` ✅ Integrado
- **Qué hay:**
  - `App\Services\Admissions\AdmissionService`: abre el internamiento (`care_episode` «inpatient») y su `bed_stay` en una cama disponible y compatible, todo en una transacción;
  - las reglas de elegibilidad de la cama, una clase por criterio en `app/Services/Admissions/Eligibility/`: cama operativa, sala, tipo de cama y aislamiento;
  - el asistente «Admitir paciente» en `/camas` (`resources/js/modules/admissions/`), y la trazabilidad de cada admisión en la cama ocupada;
  - el permiso `admisiones.gestionar` (médico y recepción).
  - Detalle en `docs/hospital-nido/PLAN.md`.
- **Defectos cerrados:**
  - **AU-01:** un episodio cerrado o anulado ahora exige fecha de fin;
  - **AU-05:** al abrir una estancia, la cama queda «occupied» (trigger de la base).
- **Acuerdo de integración:** la admisión crea un `care_episode` abierto con una `bed_stay` abierta, y traslados y altas (`BedMovementService`) trabajan sobre esa estancia. El origen (emergencia, consulta externa o referencia) queda en `care_episodes.admission_source`, y el indicador «Ingresos hospitalarios» se desglosa por él.

### 7.3 Reportes y analytics básicos — `reportes-analytics-basicos` ✅ Integrado
- **Qué hay:**
  - 15 indicadores trazables en la pestaña «Indicadores» de `/reportes`: indicador, desglose y registros de origen;
  - el permiso `reportes.ver` (dirección, administrador de hospital y calidad);
  - el permiso `reportes.citas_propias` (médico: solo el indicador de citas, con sus propias citas).
  - Detalle en `docs/hospital-nido/PLAN.md`.
- **Cómo lo usan los módulos disponibles:**
  - los indicadores leen las tablas en modo de solo lectura. Lo que registren admisión (`care_episodes`, `bed_stays`), alergias (`allergies`) y prescripciones aparece solo en ingresos, ocupación, estancia, alergias y recetas;
  - para **agregar un indicador** (por ejemplo, «alertas reconocidas a tiempo»):
    1. crea una clase en `app/Services/Analytics/Indicators/` que extienda `Indicator`. Debe devolver los casos del numerador (`peso`, `dims`, `fila`) y, si es tasa o promedio, su `denominator()`;
    2. agrégala en `IndicatorRegistry::INDICATORS` y quita su entrada de `IndicatorRegistry::PENDING`.

    La pantalla y las pruebas de contrato de `AnalyticsTest` la toman sin más cambios.

### 7.4 Alertas críticas y notificaciones internas — `alertas-criticas-notificaciones-internas` ✅ Integrado
- **Qué hay:**
  - las tablas `critical_alerts`, `critical_alert_notifications`, `critical_alert_events` (historial que solo crece) y `critical_alert_policies` (plazo de acuse por prioridad: crítica 15 min, alta 30 min, configurable de 5 a 60);
  - `App\Services\Alerts\CriticalAlertService`: crear la alerta sin duplicarla, enviarla, acusarla, cerrarla con nota y escalarla. Cada responsabilidad va en su clase: `RecipientResolver` (destinatarios por nivel), `NotificationDispatcher` (envío y hasta 3 intentos), `EscalationPolicy` (plazos y niveles) y `AlertHistory`;
  - los niveles: **0** enfermería y médico tratante, **1** médicos del hospital, **2** dirección médica;
  - el canal de notificación es un puerto, `App\Services\Alerts\Channels\AlertChannel`. Hoy el adaptador es la bandeja interna (`InternalInboxChannel`): **no se envían correos, SMS ni push**;
  - el comando `php artisan nido:alertas-escalar`, que el planificador corre cada minuto (`routes/console.php`);
  - los permisos nuevos `alertas.cerrar` (médico y dirección médica) y `alertas.configurar` (administrador de hospital y dirección médica);
  - la página `/alertas` y el número de la campana (`resources/js/modules/alerts/`);
  - el indicador «Alertas críticas reconocidas a tiempo» en reportes.
  - Detalle en `docs/hospital-nido/PLAN.md`.
- **Acuerdo de integración:**
  - las alertas nacen de **eventos de Laravel**, escuchados en `AppServiceProvider`: `App\Events\LabResultCritical` (resultado crítico validado) y `App\Events\VitalSignsOutOfRange` (toma con valores anormales o críticos). Los módulos que los disparan no cambiaron;
  - para que otro módulo cree alertas, dispara su propio evento y agrega un *listener* que llame a `CriticalAlertService::register()` con paciente, origen, id de origen, prioridad, título y descripción. El mismo origen e id de origen nunca crea dos alertas;
  - la alerta guarda solo el código del paciente, nunca su nombre.

### 7.5 Alergias clínicas y alerta visual en el expediente — `alergias-clinicas-alerta-visual-expediente` ✅ Integrado
- **Qué hay:**
  - `App\Services\Allergies\AllergyService`: registrar, inactivar o resolver (nunca borrar) y reconocer la alerta. Es el único que escribe en `allergies`;
  - el nivel de alerta: una regla por clase en `app/Services/Allergies/Rules/` (severidad y reacción anafiláctica). Para agregar una regla se crea una clase nueva;
  - la tabla `allergy_alert_acknowledgements`: quién reconoció qué alerta, por usuario y por huella;
  - el banner `AllergyBanner` (`resources/js/modules/allergies/`), colocado en la ficha del paciente y en el expediente.
  - Detalle en `docs/hospital-nido/PLAN.md`.
- **Acuerdo de integración:**
  - para mostrar las alergias en otra pantalla, coloca `<AllergyBanner patientId={id} />`. Solo se muestra a quien tiene `expediente.leer`, `alergias.gestionar` o `prescripciones.crear`;
  - prescripciones sigue **leyendo** la tabla por su cuenta: una alergia inactivada o resuelta deja de bloquear sin más cambios.

### 7.6 Catálogo de medicamentos — `catalogo-medicamentos` ✅ Integrado
- **Qué hay:**
  - la tabla `medications`: una fila por versión, con el código `MED-NNNNNN` que no cambia entre versiones y la vigencia `[valid_from, valid_to)`. La base repite las reglas: dos versiones del mismo código no se cruzan, dos medicamentos equivalentes no están vigentes a la vez, una versión no se edita (solo se cierra) y un código recetado no se borra;
  - `App\Services\Medications\MedicationCatalogService`: buscar lo vigente en una fecha, registrar, crear una versión nueva, dar de baja y eliminar un registro erróneo que nunca se usó. Es el único que escribe en `medications`;
  - el catálogo inicial: los 13 medicamentos del catálogo provisional, con su ficha completa. Conservan su código anterior (`legacy_code`, p. ej. `AMOX500`), así las recetas anteriores siguen resolviendo;
  - la pantalla `MedicationCatalog` (`resources/js/modules/medications/`), en la pestaña «Catálogo de medicamentos» de `/prescripciones`;
  - el indicador «Recetas por grupo terapéutico» en reportes.
  - Detalle en `docs/hospital-nido/PLAN.md`.
- **Acuerdo de integración:**
  - prescripciones receta desde el catálogo con `App\Services\Medications\DatabaseMedicationDirectory`, registrado en `AppServiceProvider`. Solo ve lo vigente hoy: un medicamento dado de baja deja de poder recetarse;
  - `MedicationDirectory::find()` acepta el código nuevo o el anterior. Devuelve `code`, `name`, `presentation`, `route` y `groups` (las familias para las alergias cruzadas; sus palabras siguen en `config/nido_catalogos.php`, en `familias_alergia`);
  - la lista `medicamentos` de `config/nido_catalogos.php` y `ConfigMedicationDirectory` ya no se usan. Se dejaron sin borrar para no tocar el archivo que comparte con el catálogo provisional de laboratorio.

**Para evitar conflictos al unir ramas:**
- `routes/api.php`, `config/nido.php`, `resources/js/routes.tsx` y `resources/js/modules/catalog.ts` los comparten todos los módulos.
- Agrega tus líneas en un bloque propio, con un comentario del módulo, y no reordenes lo que ya existe.
- Haz `git pull origin develop` antes de abrir tu PR.

---

## 8. Pruebas

Las pruebas usan dos bases **desechables**, distintas de las de desarrollo, porque se borran en cada prueba. `phpunit.xml` las espera en los puertos 55431 y 55433. Con tu PostgreSQL local, crea las bases y sobrescribe la conexión con variables de entorno.

**Una sola vez:**
```powershell
& "C:\Program Files\PostgreSQL\18\bin\createdb.exe" -U postgres -h 127.0.0.1 his_central_test
& "C:\Program Files\PostgreSQL\18\bin\createdb.exe" -U postgres -h 127.0.0.1 his_hospital_test
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -d his_hospital_test -c "CREATE EXTENSION IF NOT EXISTS btree_gist"
```

**Cada vez**, en la misma terminal:
```powershell
$env:CENTRAL_DB_PORT="5432"; $env:HOSPITAL_DB_PORT="5432"
$env:CENTRAL_DB_PASSWORD="tu_contraseña"; $env:HOSPITAL_DB_PASSWORD="tu_contraseña"
php artisan test                      # todo
php artisan test --group=clinical     # pacientes, médicos y citas
php artisan test --group=platform     # autenticación, RBAC y auditoría
```

**Antes de abrir un PR:**
```powershell
php artisan test
npx tsc --noEmit
npm run build
vendor/bin/pint --dirty
```

Hoy la suite da **189 correctas y ninguna incompleta**: el módulo de admisión cerró los defectos AU-01 y AU-05 (sección 7.2). Además, el **CI de GitHub** (`.github/workflows/ci.yml`) la corre en cada push y PR a `develop` y `main`, junto con la revisión de tipos y la compilación de las pantallas.

---

## 9. Flujo de trabajo con Git

- **Nunca trabajes sobre `main`.** Todo se integra en `develop`.
- Una rama por módulo o fase, creada desde `develop` actualizado:
  ```powershell
  git checkout develop
  git pull origin develop
  git checkout -b modulo-catalogo-medicamentos
  ```
- Commits en español, con prefijo: `feat(medicamentos): …`, `fix(…): …`, `docs: …`.
- Antes del PR: corre las validaciones de la sección 8 y vuelve a traer `develop` (`git pull origin develop`).
- El PR va hacia `develop`, con la plantilla del repositorio (`.github/pull_request_template.md`): resumen, alcance, validación ejecutada, contrato de la API y riesgos.
- No hagas `force push` sobre ramas compartidas. No mezcles en una rama cambios de módulos que no se relacionan.

---

## 10. API

Todas las rutas están bajo `/api/v1`.
- **Encabezados:** salvo `/hospitales`, todas requieren `X-Tenant-ID` con el id del hospital. Las protegidas requieren además `Authorization: Bearer <token>`, emitido para ese mismo hospital.
- **Errores de regla de negocio:** `{ "message", "code", "details"? }`, por ejemplo `POSIBLE_DUPLICADO` o `MEDICO_OCUPADO`.
- **Errores de validación:** el formato estándar de Laravel, con mensajes en español.

| Método | Ruta | Permiso |
|---|---|---|
| GET | `/hospitales` | Público (lista para el login) |
| POST | `/auth/login` | Hospital |
| GET | `/auth/me`, `/auth/hospitales` | Sesión |
| POST | `/auth/cambiar-hospital`, `/auth/refresh`, `/auth/logout` | Sesión |
| GET / POST | `/usuarios` | `usuarios.ver` / `usuarios.gestionar` |
| PATCH | `/usuarios/{id}/estado` | `usuarios.gestionar` |
| GET | `/roles` | `roles.ver` |
| PUT | `/roles/{rol}/permisos` | `roles.configurar` |
| POST | `/membresias` · `/membresias/{id}/revocar` | `roles.asignar` / `roles.revocar` |
| GET | `/auditoria`, `/auditoria/verificacion` | `auditoria.consultar` |
| GET / POST | `/pacientes` (`?q=` para buscar) | `pacientes.ver` / `pacientes.gestionar` |
| GET / PUT | `/pacientes/{id}` (PUT exige `version`) | `pacientes.ver` / `pacientes.gestionar` |
| GET / POST | `/especialidades` · PATCH `/especialidades/{id}` | `medicos.ver` o `citas.ver` / `medicos.gestionar` |
| GET / POST | `/medicos` · GET `/medicos/candidatos` | `medicos.ver` o `citas.ver` / `medicos.gestionar` |
| GET | `/medicos/{id}` | `medicos.ver` |
| POST / DELETE | `/medicos/{id}/especialidades[/{link}]` | `medicos.gestionar` |
| PUT / PATCH | `/medicos/{id}/horario`, `/medicos/{id}/estado` | `medicos.gestionar` |
| GET | `/disponibilidad?especialidad=&desde=[&medico=]` | `citas.ver` |
| GET / POST | `/citas` (`?desde=&hasta=&medico=&paciente=`) | `citas.ver` / `citas.gestionar` |
| POST | `/citas/{id}/estado` | `citas.gestionar` |
| GET | `/camas` (mapa: salas, camas y quién las ocupa) | `camas.ver` |
| POST | `/salas` · `/salas/{id}/camas` | `camas.gestionar` |
| PATCH | `/camas/{id}/estado` | `camas.gestionar` |
| POST | `/internamientos/{episodio}/traslado` · `/internamientos/{episodio}/alta` | `traslados.gestionar` |
| GET | `/ocupacion` | `ocupacion.ver` |
| GET | `/camas-disponibles?bed_type=&isolation=[&ward_id=]` (camas libres y compatibles) | `admisiones.gestionar` |
| POST | `/admisiones` (internamiento y cama en una transacción; `X-Request-Id` opcional, UUID) | `admisiones.gestionar` |
| GET | `/admisiones/{id}` · `/admisiones/{id}/eventos` (trazabilidad) | `admisiones.gestionar` o `camas.ver` / `admisiones.gestionar` o `auditoria.consultar` |
| GET | `/expediente/{paciente}` (notas, diagnósticos, signos vitales y atenciones) | `expediente.leer` |
| POST | `/expediente/{paciente}/notas` · PUT `/notas/{id}` (borrador; `sign: true` firma) | `notas.registrar` (+ `notas.firmar` para firmar) |
| POST | `/notas/{id}/correccion` | `notas.firmar` |
| POST | `/expediente/{paciente}/signos` | `signos_vitales.registrar` |
| GET | `/expediente/{paciente}/alergias` (nivel de alerta, alergias activas e historial) | `expediente.leer`, `alergias.gestionar` o `prescripciones.crear` |
| POST | `/expediente/{paciente}/alergias` · PATCH `/alergias/{id}/estado` (inactivar o resolver, con motivo) | `alergias.gestionar` |
| POST | `/expediente/{paciente}/alergias/reconocimiento` (con la huella que se vio) | `expediente.leer` o `alergias.gestionar` |
| POST | `/expediente/{paciente}/consulta` · `/episodios/{id}/terminar` | `notas.registrar` |
| GET | `/laboratorio/pruebas` · `/expediente/{paciente}/laboratorio` | permisos de laboratorio o `expediente.leer` |
| GET | `/catalogo/pruebas-laboratorio?q=&seccion=&muestra=&fecha=` · `/catalogo/pruebas-laboratorio/{código}?fecha=` (404 `PRUEBA_NO_VIGENTE` con su vigencia) · `/opciones` · `/{código}/versiones` | `pruebas_lab.ver`, `pruebas_lab.gestionar` u `ordenes_lab.crear` |
| POST | `/catalogo/pruebas-laboratorio` (alta) · `/catalogo/pruebas-laboratorio/{código}/versiones` | `pruebas_lab.gestionar` |
| POST | `/expediente/{paciente}/laboratorio` (orden) | `ordenes_lab.crear` |
| GET | `/laboratorio/trabajo?etapa=recepcion, resultados o validacion` | `muestras.recibir`, `resultados.registrar` o `resultados.validar` |
| POST | `/laboratorio/items/{id}/recibir` · `/laboratorio/items/{id}/rechazar` | `muestras.recibir` |
| POST | `/laboratorio/items/{id}/resultado` | `resultados.registrar` |
| POST | `/laboratorio/resultados/{id}/revision` | `resultados.validar` |
| GET | `/medicamentos` · `/recetas[?paciente=]` | `medicamentos.ver`, `prescripciones.crear` o `expediente.leer` |
| GET | `/catalogo/medicamentos?q=&forma=&fecha=` (vigentes en la fecha, máximo 50) · `/catalogo/medicamentos/{código}?fecha=` | `medicamentos.ver`, `prescripciones.crear` o `auditoria.consultar` |
| GET | `/catalogo/medicamentos/opciones` · `/catalogo/medicamentos/{código}/versiones` (historial) | `medicamentos.ver` o `auditoria.consultar` |
| POST | `/catalogo/medicamentos` (alta) · `/catalogo/medicamentos/{código}/versiones` · `/catalogo/medicamentos/{código}/baja` | `medicamentos.gestionar` |
| DELETE | `/catalogo/medicamentos/{código}` (solo si nunca se recetó) | `medicamentos.gestionar` |
| POST | `/expediente/{paciente}/recetas` · `/recetas/{id}/anular` | `prescripciones.crear` / `prescripciones.anular` |
| GET | `/reportes/operativos?tipo=&desde=&hasta=` (vista previa) | `reportes.ver` o `reportes.exportar` |
| GET | `/reportes/operativos/exportar?…` (CSV con huella SHA-256 en la bitácora) | `reportes.exportar` |
| GET | `/reportes/indicadores` · `/reportes/indicadores/resumen?desde=&hasta=` (catálogo y tarjetas) | `reportes.ver` o `reportes.citas_propias` |
| GET | `/reportes/{tipo}?desde=&hasta=[&granularidad=dia\|semana\|mes]` (indicador, comparación, tendencia y ficha) | `reportes.ver`; con `reportes.citas_propias`, solo `citas` y sus propias citas |
| GET | `/reportes/{tipo}/desglose?…[&dimension=]` · `/reportes/{tipo}/registros?…&parte=[&pagina=]` | Igual que `/reportes/{tipo}` |
| GET | `/alertas?vista=mias, abiertas o recientes` · `/alertas/resumen` (la campana) · `/alertas/{id}` (con historial) | `alertas.ver` |
| POST | `/alertas/{id}/acuse` (solo quien la recibió) · `/alertas/{id}/cierre` (con nota) | `alertas.ver` / `alertas.cerrar` |
| GET / PUT | `/alertas/politicas` (plazo de acuse por prioridad, de 5 a 60 min) | `alertas.ver` o `alertas.configurar` / `alertas.configurar` |
| GET / POST | `/calidad/defectos` · POST `/calidad/defectos/{id}/estado` | `calidad.gestionar` |
| GET / POST | `/calidad/ejecuciones` · GET `/calidad/gate/{versión}` | `versiones.gestionar` o `calidad.gestionar` |
| POST | `/calidad/ci/ejecuciones` (desde el CI, con `Authorization: Bearer NIDO_CI_TOKEN`) | Token de CI |
| GET | `/sistema/base-datos` (verificación en vivo) | `base_datos.verificar` |
| GET | `/sistema/openapi[?completo=1]` (contrato OpenAPI 3 generado de las rutas) | Sesión (`contratos_api.ver` para el contrato completo) |
