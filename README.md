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

**Requisitos:**
- PHP 8.2+ con las extensiones `pdo_pgsql` y `pgsql`;
- Composer 2;
- Node.js 20+;
- PostgreSQL 15+ (probado con 18).

Los comandos son para PowerShell en Windows. En Linux o macOS son los mismos, cambiando la ruta de `createdb`.

**1. Traer el proyecto e instalar dependencias:**
```powershell
git checkout develop
git pull origin develop
composer install
npm install
```

**2. Crear el archivo de configuración** (solo la primera vez):
```powershell
copy .env.example .env
php artisan key:generate
php artisan jwt:secret
```

**3. En `.env`, poner los datos de tu PostgreSQL** (puerto y contraseña del usuario `postgres`):
```
CENTRAL_DB_PORT=5432
CENTRAL_DB_PASSWORD=tu_contraseña
HOSPITAL_DB_PORT=5432
HOSPITAL_DB_PASSWORD=tu_contraseña
```
Para comprobar la conexión: `php artisan db:show --database=central`.

**4. Crear las dos bases principales.** Si dice que ya existen, sigue al paso 5.
```powershell
& "C:\Program Files\PostgreSQL\18\bin\createdb.exe" -U postgres -h 127.0.0.1 his_central
& "C:\Program Files\PostgreSQL\18\bin\createdb.exe" -U postgres -h 127.0.0.1 his_hospital
```

**5. Crear las tablas de CENTRAL:**
```powershell
php artisan migrate --database=central --path=database/migrations/central
php artisan migrate --database=central --path=database/migrations/framework
```

**6. Cargar roles, permisos, los 4 hospitales y los usuarios de demostración:**
```powershell
php artisan db:seed
```

**7. Crear la base de cada hospital con datos de ejemplo:**
```powershell
php artisan nido:hospitales --demo
```
Este comando:
- crea la base que falte (Central usa `his_hospital`; Norte, Oriente y Occidente usan `his_hospital_nor`, `his_hospital_ori` y `his_hospital_occ`);
- la migra y carga el catálogo de especialidades;
- con `--demo`, agrega médicos con horario, pacientes ficticios y citas;
- con `--fresh`, borra y recrea el esquema de cada base.

**8. Compilar las pantallas y encender:**
```powershell
npm run build
php artisan serve
```
Abre **http://localhost:8000**. Mientras programas, abre en otra terminal `npm run dev` para ver los cambios de React al instante.

**Cada vez que traigas cambios de `develop`:**
1. `git pull`;
2. `composer install` y `npm install`, si cambiaron dependencias;
3. `php artisan migrate --database=central --path=database/migrations/central`;
4. `php artisan nido:hospitales` (migra todas las bases de hospital);
5. `npm run build`.

**Problemas comunes:**

| Mensaje | Solución |
|---|---|
| `password authentication failed` | La contraseña de `.env` no es la de tu usuario `postgres`. |
| «Demasiados intentos» al iniciar sesión | `php artisan cache:clear` |
| La página sale en blanco | `npm run build` |
| `relation "..." does not exist` | Falta migrar: pasos 5 y 7. |

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

Los 30 módulos de `docs/modulos/` tienen su lugar en el menú. Los que aún no se integran aparecen con la marca «pronto» y muestran de qué módulos salen.

| Área | Módulo (`docs/modulos/…`) | Estado |
|---|---|---|
| Plataforma | `gestion-usuarios-tenants-autenticacion` | ✅ Integrado |
| | `rbac` | ✅ Integrado |
| | `gobernanza-auditoria` | ✅ Integrado |
| | `dashboard-base` | ✅ Integrado (inicio por rol) |
| | `contratos-api` | 🔜 En el plan |
| | `verificacion-bd-postgresql` | 🔜 En el plan |
| Pacientes y agenda | `pacientes` | ✅ Integrado |
| | `medicos-especialidades` | ✅ Integrado |
| | `citas-medicas` | ✅ Integrado |
| Camas | `catalogo-salas-camas` | ✅ Integrado |
| | `admision-hospitalaria-asignacion-cama` | 🟦 **Disponible** (sección 7.2) |
| | `traslados-altas-camas` | ✅ Integrado |
| | `dashboard-ocupacion` | ✅ Integrado |
| Clínico | `expediente-medico` | ✅ Integrado |
| | `notas-soap-diagnosticos` | ✅ Integrado |
| | `signos-vitales-alertas` | ✅ Integrado |
| | `alergias-clinicas-alerta-visual-expediente` | 🟦 **Disponible** (sección 7.5) |
| | `catalogo-medicamentos` | 🟦 **Disponible** (sección 7.6) |
| | `prescripciones-alergias` | ✅ Integrado (con catálogo provisional) |
| | `alertas-criticas-notificaciones-internas` | 🟦 **Disponible** (sección 7.4) |
| Laboratorio | `catalogo-pruebas-laboratorio` | 🟦 **Disponible** (sección 7.1) |
| | `ordenes-laboratorio-emr` | ✅ Integrado (con catálogo provisional) |
| | `recepcion-muestras` | ✅ Integrado (con catálogo provisional) |
| | `ingreso-resultados-laboratorio` | ✅ Integrado (con catálogo provisional) |
| | `validacion-resultados-bioquimico` | ✅ Integrado (con catálogo provisional) |
| Gestión | `reportes-analytics-basicos` | 🟦 **Disponible** (sección 7.3) |
| | `reportes-operativos-exportables` | 🔜 En el plan |
| Calidad | `qa-regresion-defectos` | 🔜 En el plan |
| | `qa-e2e-ci-despliegue` | 🔜 En el plan |
| Móvil | `investigacion-prototipo-nativephp` | 🔜 En el plan |

**Leyenda:**
- ✅ funciona de punta a punta, con pruebas;
- 🟦 listo para tomarse (la base, los permisos y la entrada del menú ya existen);
- 🔜 programado en las siguientes fases;
- ⏳ depende de otro módulo.

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

Estos seis módulos están listos para tomarse. En cada uno se indica qué existe, qué falta y con qué se conecta. Trabaja cada uno en su propia rama ([sección 9](#9-flujo-de-trabajo-con-git)).

### 7.1 Catálogo de pruebas de laboratorio — `catalogo-pruebas-laboratorio`
- **Ya existe:**
  - los permisos `pruebas_lab.ver` y `pruebas_lab.gestionar` (laboratorio, bioquímico y administrador de hospital; el médico solo ve);
  - la entrada del menú `/laboratorio`, compartida con los demás módulos de laboratorio;
  - el prototipo en `docs/modulos/catalogo-pruebas-laboratorio/micro-his/` (`LabTest`, `RequisitoMuestra`, `Vigencia`, `VigenciaSuperpuesta`).
- **Falta:**
  - las tablas del laboratorio: el esquema base las dejó pendientes (tarea 3.6 de `openspec/changes/postgresql-federated-schema-rebuild/tasks.md`);
  - el registro de pruebas con vigencias que no se crucen;
  - la pantalla del catálogo.
- **Se conecta con:** órdenes, recepción, ingreso y validación de resultados, que **ya están integrados** con un catálogo provisional (`config/nido_catalogos.php`). Para conectar tu catálogo:
  1. implementa `App\Services\Catalogs\LabTestDirectory` sobre tu tabla. Devuelve `code`, `name`, `specimen_type`, `unit`, `ref_low`, `ref_high`, `critical_low` y `critical_high`, y respeta la vigencia;
  2. en `AppServiceProvider`, cambia el `bind` de `LabTestDirectory` por tu clase.

  Las órdenes guardan una copia de los datos de cada prueba al pedirla, así que editar el catálogo no cambia la historia clínica.
- **Pantalla:** reemplaza la pestaña «Pruebas disponibles» de `/laboratorio` (`resources/js/pages/lab/LabPage.tsx`) por tu catálogo administrable.

### 7.2 Admisión hospitalaria y asignación de cama — `admision-hospitalaria-asignacion-cama`
- **Ya existe:**
  - las tablas `care_episodes` (episodio de atención), `bed_stays` (estancia en una cama; la base impide dos estancias a la vez en la misma cama), `wards` y `beds`;
  - el permiso `admisiones.gestionar` (médico y recepción);
  - el prototipo, con las reglas de aislamiento, cama operativa y concurrencia.
- **Falta:**
  - el servicio de admisión: abrir un episodio `inpatient` y asignar una cama disponible, todo en una transacción;
  - la pantalla dentro de `/camas`.
- **Defectos abiertos que le tocan** (`tests/Database/Verification/ConstraintBoundaryTest`):
  - **AU-01:** un episodio cerrado sin fecha de fin se guarda;
  - **AU-05:** una cama con estancia abierta sigue figurando como disponible.
- **Se conecta con:**
  - el catálogo de salas y camas (integrado), que da de alta salas y camas en `/camas`;
  - traslados y altas (integrado, `BedMovementService`), que cierran o mueven la estancia;
  - la pantalla `/camas` (`resources/js/pages/beds/BedsPage.tsx`): agrega ahí el botón «Admitir paciente» en las camas disponibles.
- **Acuerdo de integración:** la admisión crea un `care_episode` abierto con una `bed_stay` abierta, y traslados y altas trabajan sobre esa estancia.

### 7.3 Reportes y analytics básicos — `reportes-analytics-basicos`
- **Ya existe:**
  - el permiso `reportes.ver` (dirección, administrador de hospital y calidad);
  - la entrada del menú `/reportes`, compartida con reportes operativos exportables;
  - el prototipo (`ReportRegistry`, `ReporteOcupacion`, `Indicador`, `Filtros`).
- **Falta:**
  - los indicadores, calculados con las tablas existentes (pacientes, citas y estancias) y filtros por fecha;
  - la pantalla con tarjetas de indicadores y gráficas (se recomienda Recharts).
- **Se conecta con:** reportes operativos exportables, que también vive en `/reportes`. Usen pestañas: «Indicadores» para este módulo y «Exportar» para el otro.

### 7.4 Alertas críticas y notificaciones internas — `alertas-criticas-notificaciones-internas`
- **Ya existe:**
  - los permisos `alertas.ver` y `alertas.gestionar`;
  - la entrada del menú `/alertas`;
  - la campana de la barra superior (`resources/js/layout/AppShell.tsx`), lista para mostrar el número de alertas;
  - el prototipo (`Alerta`, `Prioridad`, `PoliticaEscalamiento`, `EstadoAlerta`).
- **Falta:**
  - la tabla de alertas;
  - el reconocimiento y el escalamiento;
  - la pantalla y el contador de la campana.
- **Se conecta con:** signos vitales fuera de rango y resultados críticos de laboratorio, ambos ya integrados. Al validar un resultado crítico se dispara `App\Events\LabResultCritical` (paciente, prueba, valor, unidad y marca). Para no acoplar los módulos, se usan **eventos de Laravel**:
  - al guardar una toma con valores anormales o críticos, `VitalSignService` dispara `App\Events\VitalSignsOutOfRange`;
  - el evento trae hospital, paciente, código del paciente, episodio, toma y la lista de hallazgos (`signo`, `etiqueta`, `valor`, `nivel`: `anormal` o `critica`), y `isCritical()`;
  - para crear la alerta, registra un *listener* en `AppServiceProvider` con `Event::listen(VitalSignsOutOfRange::class, …)`.

### 7.5 Alergias clínicas y alerta visual en el expediente — `alergias-clinicas-alerta-visual-expediente`
- **Ya existe:**
  - la tabla `allergies`: alérgeno, tipo, severidad y estado, con una regla que impide duplicar una alergia activa;
  - el permiso `alergias.gestionar` (médico y enfermería);
  - el prototipo (`CalculadorAlerta`, `NivelAlerta`, `ReglaNivelAlerta`).
- **Falta:**
  - el registro y la confirmación de alergias;
  - el cálculo del nivel de alerta;
  - **el banner visual**, siempre visible en la ficha del paciente y en el expediente.
- **Se conecta con:**
  - la ficha del paciente (`resources/js/pages/patients/PatientDetailPage.tsx`);
  - el expediente (`resources/js/pages/records/RecordPage.tsx`), que ya tiene marcado con un comentario el lugar del banner, debajo del nombre del paciente;
  - prescripciones (ya integrado), que **lee** las alergias activas de la tabla `allergies` para bloquear o advertir. Lo que registres aparece ahí sin más cambios. En la demostración se cargan dos alergias de ejemplo.
- **Acuerdo de integración:** exporta un componente `AllergyBanner` en `resources/js/modules/allergies/` que reciba el id del paciente, para que las demás pantallas solo tengan que colocarlo.

### 7.6 Catálogo de medicamentos — `catalogo-medicamentos`
- **Ya existe:**
  - los permisos `medicamentos.ver` y `medicamentos.gestionar` (farmacia y administrador de hospital; médico y enfermería solo ven);
  - la entrada del menú `/prescripciones`, compartida con prescripciones;
  - el prototipo (`CodigoMedicamento`, `Concentracion`, `ClaveEquivalencia`, `BusquedaCatalogo`).
- **Falta:**
  - las tablas de medicamentos: el esquema base las dejó pendientes (tarea 3.5 del mismo `tasks.md`);
  - el registro y la búsqueda de medicamentos;
  - la pantalla.
- **Se conecta con:** prescripciones, que **ya está integrado** con un catálogo provisional. Para conectar tu catálogo:
  1. implementa `App\Services\Catalogs\MedicationDirectory` sobre tu tabla. Devuelve `code`, `name`, `presentation`, `route` y `groups`. `groups` son las familias para las alergias cruzadas, por ejemplo `['penicilinas']`; las palabras de cada familia están en `config/nido_catalogos.php`, en `familias_alergia`;
  2. en `AppServiceProvider`, cambia el `bind` de `MedicationDirectory` por tu clase.
- **Pantalla:** reemplaza la pestaña «Catálogo de medicamentos» de `/prescripciones` (`resources/js/pages/prescriptions/PrescriptionsPage.tsx`) por tu catálogo administrable.

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

Hoy la suite da **131 correctas y 2 incompletas**. Las 2 incompletas son los defectos AU-01 y AU-05 de admisión (sección 7.2).

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
| GET | `/expediente/{paciente}` (notas, diagnósticos, signos vitales y atenciones) | `expediente.leer` |
| POST | `/expediente/{paciente}/notas` · PUT `/notas/{id}` (borrador; `sign: true` firma) | `notas.registrar` (+ `notas.firmar` para firmar) |
| POST | `/notas/{id}/correccion` | `notas.firmar` |
| POST | `/expediente/{paciente}/signos` | `signos_vitales.registrar` |
| POST | `/expediente/{paciente}/consulta` · `/episodios/{id}/terminar` | `notas.registrar` |
| GET | `/laboratorio/pruebas` · `/expediente/{paciente}/laboratorio` | permisos de laboratorio o `expediente.leer` |
| POST | `/expediente/{paciente}/laboratorio` (orden) | `ordenes_lab.crear` |
| GET | `/laboratorio/trabajo?etapa=recepcion, resultados o validacion` | `muestras.recibir`, `resultados.registrar` o `resultados.validar` |
| POST | `/laboratorio/items/{id}/recibir` · `/laboratorio/items/{id}/rechazar` | `muestras.recibir` |
| POST | `/laboratorio/items/{id}/resultado` | `resultados.registrar` |
| POST | `/laboratorio/resultados/{id}/revision` | `resultados.validar` |
| GET | `/medicamentos` · `/recetas[?paciente=]` | `medicamentos.ver`, `prescripciones.crear` o `expediente.leer` |
| POST | `/expediente/{paciente}/recetas` · `/recetas/{id}/anular` | `prescripciones.crear` / `prescripciones.anular` |
