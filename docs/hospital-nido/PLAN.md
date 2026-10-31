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
| 3 | Camas | catalogo-salas-camas, admision-hospitalaria-asignacion-cama, traslados-altas-camas, dashboard-ocupacion | **Lista** |
| 4 | Clínico | expediente-medico, notas-soap-diagnosticos, signos-vitales-alertas | **Lista** |
| 5 | Laboratorio y prescripciones | ordenes-laboratorio-emr, recepcion-muestras, ingreso-resultados-laboratorio, validacion-resultados-bioquimico, prescripciones-alergias | **Lista** |
| 6 | Reportes | reportes-operativos-exportables, reportes-analytics-basicos | **Lista** |
| 7 | Calidad, contratos y despliegue | qa-regresion-defectos, qa-e2e-ci-despliegue, contratos-api, verificacion-bd-postgresql | **Lista** |
| 8 | App móvil y de escritorio | investigacion-prototipo-nativephp | **Lista** (PWA) |
| Disponibles | Se integran en ramas propias, siguiendo la sección 7 del `README.md` | catalogo-pruebas-laboratorio, alertas-criticas-notificaciones-internas, alergias-clinicas-alerta-visual-expediente, catalogo-medicamentos | Disponibles |

Son 30 módulos: 4 + 3 + 4 + 3 + 5 + 2 + 4 + 1 en las fases, más los 4 disponibles. La prueba `AccessControlTest::test_every_permission_belongs_to_a_documented_module` falla si un módulo de `docs/modulos/` queda sin permiso en el catálogo, o si un permiso apunta a un módulo que no existe.

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

## Fase 4: qué se entregó

**Expediente** (módulo expediente-medico):
- un expediente por paciente (`EXP-<código>`);
- atenciones: consulta externa o internamiento. Si el paciente no tiene una atención abierta, al escribir se abre una consulta externa sola, para que nadie tenga que entender qué es un «episodio»;
- el encabezado dice en qué atención se está escribiendo y muestra los diagnósticos activos.

**Notas SOAP** (módulo notas-soap-diagnosticos):
- las cuatro secciones son obligatorias;
- diagnósticos con código CIE-10, con un solo principal;
- borrador que se completa y firma después;
- una nota firmada no cambia: se corrige con una versión nueva y un motivo de al menos 10 caracteres, y la versión anterior queda en el historial;
- solo un médico con perfil escribe notas, y queda asignado a la atención.

**Signos vitales** (módulo signos-vitales-alertas):
- los registran enfermería o el médico tratante;
- al menos una medición, con valores físicamente posibles;
- clasificación normal, anormal o crítica con rangos de adulto;
- si hay valores fuera de rango, se dispara `App\Events\VitalSignsOutOfRange` para el módulo de alertas críticas.

**Ajustes al esquema base** (migración `2026_10_30_000300`, reversible):
- la versión de las notas era única por expediente, lo que permitía **una sola nota por paciente**; ahora la versión es por cadena de corrección;
- los signos vitales solo los aceptaba del médico asignado; ahora también de enfermería.

**Verificación:**
- 8 pruebas Feature nuevas (`RecordTest`);
- recorrido en Chrome: 13/13, sin violaciones WCAG A/AA y sin desborde a 375 px.
- **Corregido en esta fase:** el color de los botones animaba al pasar de «deshabilitado» a normal y, en ese instante, no tenía contraste suficiente. El estado deshabilitado ahora es gris legible.

## Fase 5: qué se entregó

**Laboratorio** (un módulo por paso):
- **Órdenes desde el expediente:** al menos una prueba, sin repetidas, con indicación clínica y prioridad. Las urgentes se atienden primero.
- **Recepción:** código único de muestra para rotular el tubo, o rechazo con motivo.
- **Resultados:**
  - en la unidad de la prueba;
  - marcados como normal, bajo, alto o crítico;
  - se pueden corregir antes de validar, y la versión anterior se conserva.
- **Validación:**
  - la hace una persona distinta de quien ingresó el resultado; también lo exige un CHECK en la base;
  - un resultado rechazado vuelve a ingreso;
  - lo validado ya no cambia;
  - un valor crítico dispara `App\Events\LabResultCritical`.
- **Copia de datos:** cada ítem guarda los datos de la prueba al momento de pedirla.

**Prescripciones:**
- antes de emitir, se cruzan las alergias activas del paciente con el medicamento, por nombre y por familia;
- una alergia grave bloquea la receta (`BLOQUEO_ALERGIA`, o `BLOQUEO_ALERGIA_CRUZADA` si es de la misma familia);
- una alergia leve o moderada pide confirmación con motivo;
- anular una receta exige motivo.

**Catálogos provisionales:** pruebas y medicamentos salen de `config/nido_catalogos.php`, a través de las interfaces `LabTestDirectory` y `MedicationDirectory`. Los módulos de catálogo los reemplazan cambiando un `bind`.

**Verificación:**
- 6 pruebas Feature nuevas;
- recorrido en Chrome: 18/18, con médico, laboratorio, bioquímico y vista de celular;
- se corrigió el contraste de los botones de acción peligrosa: el coral no llegaba a AA y ahora son rojo oscuro.

## Fases 6, 7 y 8: qué se entregó

**Reportes operativos:**
- cinco reportes: citas, pacientes, internamientos, laboratorio y recetas;
- filtros validados, con un rango de hasta 92 días;
- vista previa en pantalla;
- CSV con BOM, para que Excel muestre bien las tildes;
- PDF desde el navegador, con una hoja de impresión limpia;
- cada exportación queda en la bitácora con sus filtros, la fecha de corte y la huella SHA-256 del archivo.

**Calidad:**
- **Defectos:** pasan por abierto, en corrección, corregido y cerrado; también se pueden reabrir. Para cerrar uno hay que describir la re-ejecución que lo comprobó.
- **Ejecuciones por versión:** se registran a mano o desde el CI, con su token.
- **Quality gate:** una versión queda bloqueada si su última ejecución de alguna suite tiene fallas, o si quedan defectos críticos o altos sin cerrar. Las pruebas inestables se marcan como aviso.
- **CI en GitHub Actions:** en cada push y PR corre PHPUnit contra PostgreSQL, revisa los tipos, compila las pantallas y revisa el formato.

**Contratos API:**
- OpenAPI 3 generado de las rutas reales, así que nunca se desactualiza;
- cada usuario ve solo las operaciones que su rol puede usar;
- con el permiso `contratos_api.ver`, se puede ver y descargar el contrato completo.

**Verificación de la base de datos:**
- conexión y versión de las dos bases;
- migraciones pendientes;
- extensión `btree_gist`;
- integridad de la bitácora;
- coherencia de las camas;
- cantidad de registros por tabla.

**App móvil:**
- la investigación evaluó NativePHP;
- se entrega como **aplicación web instalable (PWA)**, con manifiesto, íconos y service worker;
- en el dispositivo solo se guarda la estructura de la aplicación, nunca datos clínicos.

**Instalación:** `php artisan nido:instalar` deja el sistema listo en un solo paso.

**Verificación:**
- 6 pruebas Feature nuevas; en total, 137 correctas;
- recorrido en Chrome de la fase: 19/19;
- todos los recorridos juntos: 120/120 comprobaciones, sin violaciones WCAG A/AA y sin desborde a 375 px;
- se corrigieron el contraste del texto «Cargando…» y del estado vacío sobre el fondo arena, y el ancho de la barra superior en el celular.

## Reportes y analytics básicos: qué se entregó

Se integró en su propia rama (PR #560), siguiendo la sección 7.3 del `README.md`. Comparte `/reportes` con los reportes operativos: pestaña «Indicadores».

**Indicadores trazables** (módulo reportes-analytics-basicos):
- 15 indicadores de solo lectura sobre las tablas que ya llenan los demás módulos:
  - **hospitalización:** ocupación de camas, estancia promedio, ingresos, egresos y mortalidad;
  - **consulta externa:** citas, cancelación de citas y consultas atendidas;
  - **pacientes y clínico:** pacientes nuevos, diagnósticos CIE-10, signos vitales fuera de rango y alergias;
  - **laboratorio y farmacia:** pruebas solicitadas, resultados críticos y recetas emitidas.
- Tres niveles con los mismos filtros y permisos (RN-08): el **indicador**, su **desglose** por una dimensión y los **registros** que forman cada parte.
- La suma del desglose es el numerador, y la de los registros es su parte (RN-07): las tres salen de los mismos casos. Por eso un promedio (estancia) se desglosa en días y egresos, nunca promediando promedios.
- Ficha de trazabilidad con fórmula, período, filtros, fuente y fecha del cálculo (RN-05).
- Comparación con el período anterior del mismo largo, y tendencia por día, semana o mes, con los períodos parciales marcados (RN-03).
- «Sin datos» en lugar de un cero (RN-06).
- Los grupos con menos de 3 casos se juntan en «Otros» sin cambiar el total (RN-04).
- El paciente aparece solo con su código (RN-09).
- Período de hasta 366 días.
- El valor se calcula completo y se redondea solo en la pantalla (`resources/js/modules/analytics/format.ts`).
- **Médico:** con el permiso nuevo `reportes.citas_propias`, solo ve el indicador de citas y solo con sus propias citas. Pedir las de otro médico responde 403 (CA-07).
- Se corrigieron dos defectos de las pruebas de la semana 15:
  - **D-01:** una parte que no existe en el desglose responde 422;
  - **D-02:** el período tiene límite.
- Cada respuesta lleva `X-Correlation-Id` y su duración queda en el log (RNF-01). Si la base no responde, sale un 503 sin detalles técnicos.
- **Un indicador nuevo es una clase nueva** en `app/Services/Analytics/Indicators/`, registrada en `IndicatorRegistry` (RNF-05).
- Cinco indicadores esperan datos de los módulos disponibles y se muestran como «Próximamente»:
  - alertas reconocidas a tiempo;
  - tiempo de espera de cama;
  - recetas por familia de medicamento;
  - pruebas por área del laboratorio;
  - pacientes con alergia grave confirmada.
- Ingresos, estancia, alergias, recetas y resultados críticos ya leen las tablas que esos módulos van a llenar.

**Pantalla:**
- tarjetas por área con su comparación;
- el detalle tiene la ficha, la tendencia y el desglose en gráficas (Recharts), cada una con su tabla para lectores de pantalla;
- registros paginados, de 10 en 10;
- las gráficas se descargan aparte, solo al abrir la pestaña.

**Verificación:**
- 7 pruebas Feature nuevas (`AnalyticsTest`), entre ellas una prueba de contrato que exige RN-07 a los 15 indicadores en todas sus dimensiones;
- en total, 144 correctas y 2 incompletas (AU-01 y AU-05).

## Admisión hospitalaria y asignación de cama: qué se entregó

Se integró en su propia rama, siguiendo la sección 7.2 del `README.md`. Completa el ciclo de las camas: admisión → traslados → alta.

**Admisión** (módulo admision-hospitalaria-asignacion-cama):
- Abre el internamiento (`care_episode` «inpatient») y su estancia en la cama, en una sola transacción: o queda todo, o nada.
- Registra el origen: emergencia, consulta externa o referencia.
- **Cama compatible (RN-03, RN-04):**
  - una clase por criterio en `app/Services/Admissions/Eligibility/`: cama operativa, sala pedida, tipo de cama y aislamiento;
  - una cama de aislamiento se reserva para quien lo requiere;
  - si la cama no sirve, se dice por qué (`CAMA_NO_ELEGIBLE` con sus motivos).
- **Concurrencia:**
  - **RN-01:** una cama tiene una sola estancia abierta; lo garantiza la exclusión de `bed_stays`. Si otra persona la tomó, responde `CAMA_YA_ASIGNADA` y el asistente vuelve a la lista actualizada (CU-05);
  - **RN-02:** un paciente tiene un solo internamiento abierto. Se valida con la fila del paciente bloqueada, así dos admisiones simultáneas no lo duplican;
  - si la cama está bloqueada más de 2 s, responde `CONFLICTO_CONCURRENCIA`, que se puede reintentar.
- **Trazabilidad (RN-05):** `ADMISION_CREADA` y `CAMA_ASIGNADA` van a la bitácora encadenada, con el rol y el `X-Request-Id` de la solicitud. La consulta de eventos junta también los traslados y el alta.
- **Privacidad:** la respuesta identifica al paciente solo con su código. El hospital sale de la sesión: si viene en el cuerpo, se rechaza.

**Migración `2026_10_31_000200`** (reversible):
- columna `care_episodes.admission_source`;
- cierre de **AU-01**: un episodio cerrado o anulado exige fecha de fin;
- cierre de **AU-05**: al abrir una estancia, la cama queda «occupied».

**Pantalla:**
- botón «Admitir paciente» en `/camas`, arriba y en cada cama disponible;
- el asistente tiene tres pasos: buscar paciente, qué necesita (origen, aislamiento, tipo de cama, sala y motivo) y elegir la cama;
- muestra «Quedan N camas» y avisa cuando quedan 2 o menos (cambio preparado de la semana 12);
- la confirmación muestra un resumen;
- en la cama ocupada, «Ver admisión y trazabilidad».

**Reportes:** el indicador «Ingresos hospitalarios» se desglosa ahora por origen de la admisión.

**Verificación:**
- 6 pruebas Feature nuevas (`AdmissionTest`);
- en total, **152 correctas y ninguna incompleta**: AU-01 y AU-05 pasan.

## Pendientes conocidos

- **Diseño de Stitch:** la paleta y los componentes actuales son provisionales. Al llegar el diseño se ajustan `resources/css/app.css` y `resources/js/components/ui.tsx`, y el cambio se propaga a todas las pantallas.
- **Identidad protegida (R-11 del módulo pacientes):** pacientes confidenciales visibles solo con un permiso especial. Queda para una fase posterior, porque necesita permisos nuevos.
- **Esquema de Richard:** siguen pendientes las tareas 3.5 a 3.7 de `openspec/changes/postgresql-federated-schema-rebuild/tasks.md` (medicamentos, laboratorio, auditoría local). Se completan en las fases 4 y 5.
- **Dependencias:** `composer audit` reporta avisos de seguridad en paquetes del `composer.lock` heredado. Conviene actualizarlo en un PR aparte, con todas las pruebas.
