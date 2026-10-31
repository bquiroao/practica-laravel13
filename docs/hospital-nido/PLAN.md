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
| 4 | Clínico | expediente-medico, notas-soap-diagnosticos, signos-vitales-alertas, alergias-clinicas-alerta-visual-expediente, alertas-criticas-notificaciones-internas | **Lista** |
| 5 | Laboratorio y prescripciones | ordenes-laboratorio-emr, recepcion-muestras, ingreso-resultados-laboratorio, validacion-resultados-bioquimico, prescripciones-alergias, catalogo-medicamentos, catalogo-pruebas-laboratorio | **Lista** |
| 6 | Reportes | reportes-operativos-exportables, reportes-analytics-basicos | **Lista** |
| 7 | Calidad, contratos y despliegue | qa-regresion-defectos, qa-e2e-ci-despliegue, contratos-api, verificacion-bd-postgresql | **Lista** |
| 8 | App móvil y de escritorio | investigacion-prototipo-nativephp | **Lista** (PWA) |

Son 30 módulos: 4 + 3 + 4 + 5 + 7 + 2 + 4 + 1, todos integrados. La prueba `AccessControlTest::test_every_permission_belongs_to_a_documented_module` falla si un módulo de `docs/modulos/` queda sin permiso en el catálogo, o si un permiso apunta a un módulo que no existe.

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

## Alergias clínicas y alerta visual en el expediente: qué se entregó

Se integró en su propia rama, siguiendo la sección 7.5 del `README.md`. Es el único módulo que escribe en `allergies`. Prescripciones las sigue leyendo por su cuenta.

**Registro** (CU-01, CU-02):
- **Alérgeno (RN-01):** se identifica por nombre o por código.
- **Tipo y severidad (RN-02):** de un catálogo cerrado.
- **Reacción (RN-04):** opcional, de hasta 500 caracteres.
- **Fecha de inicio (RN-05):** no puede ser futura.
- **Paciente (RN-06):** solo pacientes activos.
- **Verificación (RN-07):** la alergia queda verificada por quien la registra.
- **Sin duplicados (RN-03):**
  - mismo tipo y alérgeno, sin importar mayúsculas ni espacios;
  - también lo valida el índice de la base, por si dos personas registran a la vez.

**Nivel de alerta** (CU-04, RN-08):
- es el mayor que asigne cualquier regla a cualquier alergia activa;
- cada regla es una clase (OCP): `SeverityRule` y `AnaphylaxisRule`;
- la severidad desconocida cuenta como ALTA;
- una reacción anafiláctica siempre es CRÍTICA;
- `SIN_REGISTRO` se muestra en gris y aclara que no confirma que no tenga alergias (DEC-02).

**Reconocimiento** (CU-05, RN-09):
- una alerta ALTA o CRÍTICA pide «He leído la alerta» a cada usuario;
- vale solo para la huella que vio: si cambian las alergias activas o su severidad, se vuelve a pedir, y la huella vieja responde `ALERTA_CAMBIO`;
- se guarda en `allergy_alert_acknowledgements` (migración `2026_10_31_000300`), que no se puede modificar ni borrar.

**Inactivar o resolver** (CU-06, RN-10):
- una alergia no se borra: queda en el historial con fecha y motivo;
- deja de mostrarse en la alerta y de bloquear recetas.

**Defectos de la semana 15, corregidos:**
- **D-01:** una alergia con código y nombre, y otra solo con el mismo nombre, ya no quedan duplicadas.
- **D-02:** «anafiláctica» con tilde se detecta, y la alerta es CRÍTICA.
- **D-03:** un campo que no es texto se rechaza en lugar de descartarse.

**Pantalla:**
- `AllergyBanner` (`resources/js/modules/allergies/`), colocado debajo del nombre del paciente en la ficha y en el expediente;
- el color va por nivel, y el texto dice el nivel, no solo el color;
- tiene «Registrar alergia», «Inactivar o resolver» e historial;
- solo lo ven los roles clínicos.

**Reportes:** el indicador «Alergias de alto riesgo» reemplaza el pendiente «Pacientes con alergia grave confirmada». Usa el mismo calculador de nivel del módulo.

**Corrección en reportes:** `Filters::range()` envía las fechas con microsegundos. Antes, lo registrado en el mismo segundo de la consulta quedaba fuera del período.

**Verificación:**
- 7 pruebas Feature nuevas (`AllergyTest`);
- en total, **159 correctas y ninguna incompleta**.

## Catálogo de medicamentos: qué se entregó

Se integró en su propia rama, siguiendo la sección 7.6 del `README.md`. Es el único módulo que escribe en `medications` (migración `2026_10_31_000400`). Prescripciones lo lee por `MedicationDirectory`.

**Tabla `medications`:**
- una fila por versión. El código `MED-NNNNNN` no cambia entre versiones (RN-06) y la vigencia es `[valid_from, valid_to)`: el día `valid_to` ya no está vigente;
- cada columna tiene los mismos límites que valida la API (RN-05), así un dato fuera de rango da 422, nunca un error de la base;
- la base repite las reglas del dominio (RNF-04):
  - dos versiones del mismo código no se cruzan (RN-01);
  - dos medicamentos con la misma clave de equivalencia no están vigentes a la vez (RN-04);
  - una versión no se edita, solo se cierra, y una versión cerrada no se reabre;
  - un código que aparece en una receta no se borra (RN-02).

**Alta** (CU-01):
- el código es el siguiente libre del hospital. Se calcula con un candado, así dos altas a la vez toman códigos distintos;
- **sin duplicados (RN-04):** principio activo, concentración, unidad, forma y vía. El principio activo se compara sin mayúsculas, tildes ni espacios repetidos, y «500», «500.0» y «500.000» son la misma concentración;
- un equivalente dado de baja no cuenta como duplicado si las vigencias no se cruzan (CA-05);
- las familias para alergias cruzadas se sugieren por el principio activo (amoxicilina → penicilinas), y quien registra las puede cambiar;
- el grupo terapéutico agrupa las recetas en reportes.

**Búsqueda** (CU-02, RF-04):
- solo lo vigente en la fecha consultada (hoy, si no se indica), por texto en el código, el código anterior, el nombre o el principio activo, y por forma farmacéutica;
- `%` y `_` se buscan como texto, no como comodines (RN-03), y la búsqueda no distingue tildes;
- máximo 50 resultados, por nombre y código, con el total.

**Versión nueva** (CU-03, RN-01):
- se envía la ficha completa; la vigente se cierra el día en que empieza la nueva;
- la nueva debe empezar después de la vigente (`SOLAPE_VIGENCIA`) y cambiar al menos un dato (`SIN_CAMBIOS`);
- si otra persona cambió el medicamento mientras se editaba, responde `CONFLICTO_CONCURRENTE`.

**Baja** (CU-04) y **eliminación** (CU-06):
- la baja necesita fecha (desde hoy) y motivo, y deja el registro y su historial;
- desde ese día el medicamento no aparece al buscar ni se puede recetar;
- solo se elimina un registro que nunca se recetó; si se recetó, responde `EN_USO` y sugiere la baja (RN-02).

**Historial** (CU-05): todas las versiones con quién las registró y quién las cerró. Lo puede consultar calidad (`auditoria.consultar`).

**Bitácora:** `MEDICAMENTO_REGISTRADO`, `MEDICAMENTO_NUEVA_VERSION` (con los campos que cambiaron), `MEDICAMENTO_DADO_DE_BAJA`, `MEDICAMENTO_ELIMINADO` y `MEDICAMENTO_ELIMINACION_RECHAZADA`.

**Catálogo inicial:**
- los 13 medicamentos del catálogo provisional, con su ficha completa y registros sanitarios ficticios;
- conservan su código anterior (`legacy_code`, p. ej. `AMOX500`), así las recetas anteriores siguen resolviendo y prescripciones acepta los dos códigos;
- se carga con `HospitalCatalogSeeder`, en cada hospital, al instalar.

**Defectos de la semana 15, corregidos:**
- **D-01:** cada campo se valida por separado; una concentración «0» sin unidad informa los dos errores a la vez.
- **D-02:** toda respuesta lleva `X-Request-Id`, también los errores. Si la base no responde, sale un 503 `PERSISTENCIA` con `Retry-After: 5` y sin detalles internos.
- **D-03:** dos altas a la vez toman códigos distintos, en lugar de que la segunda falle.

**Pantalla:**
- `MedicationCatalog` (`resources/js/modules/medications/`), en la pestaña «Catálogo de medicamentos» de `/prescripciones`;
- búsqueda por texto, forma y fecha; la ficha con su estado en texto (vigente, programada, reemplazada o dado de baja) y el historial;
- farmacia y el administrador del hospital registran, versionan, dan de baja y eliminan; un duplicado ofrece abrir el medicamento que ya existe;
- eliminar pide confirmación (hallazgo H-03 de la semana 9).

**Reportes:** el indicador «Recetas por grupo terapéutico» reemplaza el pendiente «Recetas por familia de medicamento». El grupo sale de la ficha del catálogo.

**Verificación:**
- 9 pruebas Feature nuevas (`MedicationCatalogTest`), con los criterios CA-01 a CA-13;
- en total, **168 correctas y ninguna incompleta**.

## Catálogo de pruebas de laboratorio: qué se entregó

Se integró en su propia rama, siguiendo la sección 7.1 del `README.md`. Es el único módulo que escribe en `lab_tests` (migración `2026_10_31_000500`). Órdenes, recepción, ingreso y validación lo leen por `LabTestDirectory`.

**Tabla `lab_tests`:**
- una fila por versión, con los datos de la prueba, los requisitos de muestra y la vigencia `[valid_from, valid_to]`: `valid_to` es el último día vigente (RN-04);
- incluye la unidad y los valores de referencia y críticos. La semana 1 los dejó «por confirmar»; quedan aquí porque ingreso y validación de resultados ya los usan para marcar valores bajos, altos y críticos;
- la base repite las reglas: dos versiones del mismo código no se cruzan (RN-05), cada columna tiene los límites de la API (RN-02, RN-03) y una versión no se edita ni se borra; solo se adelanta su cierre (RN-08).

**Alta** (CU-01 a CU-03):
- código `AAA-000`, único en el hospital (`CODIGO_EXISTENTE` si ya está); nombre, sección y tiempo de respuesta;
- requisitos de muestra: tipo, contenedor, volumen mínimo, ayuno, preparación, conservación y estabilidad;
- vigencia desde hoy o después, con fecha hasta opcional; queda registrado quién la creó (RN-08).

**Versión nueva** (RN-05):
- se envía la ficha completa; si la vigente se cruza, se cierra el día previo, y si empieza el mismo día o antes responde `VIGENCIA_SUPERPUESTA`;
- la decisión vive en su propia clase, `ValidityPlan`, separada del guardado (SRP, semana 2);
- todo se guarda en una transacción: si la base falla, responde 500 `ERROR_PERSISTENCIA` y la versión anterior conserva su vigencia (RN-07, CA-10);
- si otra persona registró una versión mientras se editaba, responde `CONFLICTO_CONCURRENTE`.

**Consulta** (CU-04, CU-05):
- solo lo vigente en la fecha (hoy, si no se indica), por texto, sección y tipo de muestra;
- el detalle de una prueba que no está vigente en esa fecha responde 404 `PRUEBA_NO_VIGENTE` con su vigencia (RN-06).

**Catálogo inicial:** las 10 pruebas del catálogo provisional, con sus requisitos de muestra. Conservan su código (`HB`, `GLU`…) porque las órdenes, los resultados y el aviso de resultado crítico ya lo usan. Se carga con `HospitalCatalogSeeder` al instalar.

**Defectos de la semana 15, corregidos:**
- **D-02:** un volumen de 10000 mL o más da 422, no 500.
- **D-03:** los textos más largos que su columna (nombre, contenedor, preparación) dan 422, no 500.
- **D-04:** `%` y `_` se buscan como texto, no como comodines.

**Observabilidad (RNF-06):** cada respuesta lleva `X-Request-Id`, y cada petición deja en el registro su identificador, la ruta y el estado, sin datos clínicos.

**Pantalla:**
- `LabTestCatalog` (`resources/js/modules/labtests/`), en la pestaña «Pruebas disponibles» de `/laboratorio`;
- búsqueda por texto, sección, muestra y fecha; la ficha con los requisitos de muestra, los rangos y el historial;
- el jefe de laboratorio (bioquímico) y el administrador del hospital registran pruebas y versiones nuevas;
- **cambio de la semana 12:** una prueba que vence en 7 días o menos muestra «Vence el … (en N días)», con texto y no solo color, y la ficha dice qué versión rige después.

**Reportes:** el indicador «Pruebas por área del laboratorio» reemplaza el último pendiente de este módulo. La sección sale del catálogo.

**Verificación:**
- 10 pruebas Feature nuevas (`LabTestCatalogTest`), con los criterios CA-01 a CA-10;
- en total, **178 correctas y ninguna incompleta**.

## Alertas críticas y notificaciones internas: qué se entregó

Se integró en su propia rama, siguiendo la sección 7.4 del `README.md`. Es el único módulo que escribe en las tablas `critical_alert*` (migración `2026_10_31_000600`). Nace de los eventos que ya disparaban laboratorio y signos vitales; esos módulos no cambiaron.

**Ciclo:** `CREADA → ENVIADA → (ESCALADA) → ACUSADA → CERRADA`. Una alerta escalada todavía se puede acusar.

**Registro** (CU-01):
- un resultado crítico validado crea una alerta **crítica**; una toma de signos vitales, **crítica** si algún valor es crítico y **alta** si solo hay valores anormales;
- **RN-01:** exige paciente, origen, id de origen, prioridad y descripción;
- **RN-02:** el mismo origen e id de origen (el resultado validado o la toma) devuelve la alerta existente; también lo impide la base;
- **RNF-02:** guarda solo el código del paciente, nunca su nombre.

**Envío y escalamiento** (CU-02, CU-03, CU-05, CU-09):
- niveles: **0** enfermería y médico tratante (el que pidió el laboratorio o el asignado al episodio), **1** médicos del hospital, **2** dirección médica. No existe el rol «jefe de servicio», así que el nivel 1 son los médicos;
- **RN-03:** un nivel sin personal pasa al siguiente;
- **RN-04 y RN-05:** si nadie acusa en el plazo (crítica 15 min, alta 30 min), sube un nivel; en el nivel 2 se registra «escalamiento agotado» una sola vez. A las 10:15 ya escala una alerta de las 10:00; a las 10:14, no;
- **RN-08:** hasta 3 intentos por destinatario; si alguno se agota, se escala de inmediato y la alerta no se pierde (RNF-05);
- el comando `nido:alertas-escalar` lo corre el planificador cada minuto (`php artisan schedule:work`);
- **RNF-06:** el canal es un puerto (`AlertChannel`); hoy es la bandeja interna, sin correo, SMS ni push.

**Acuse y cierre** (CU-04, CU-06):
- **RN-07:** solo acusa alguien a quien se le notificó; los demás reciben `ALERTA_NO_NOTIFICADA`;
- **RN-06:** acusar dos veces no agrega nada, y una alerta acusada ya no se escala;
- **RN-09:** se cierra solo si está acusada y con nota, y solo con el permiso nuevo `alertas.cerrar` (médico y dirección médica).

**Historial** (CU-07, RN-10): cada paso guarda evento, nivel, actor y momento; la base impide editarlo o borrarlo.

**Plazos** (CU-08, RN-11): de 5 a 60 minutos por prioridad, con el permiso nuevo `alertas.configurar` (administrador de hospital y dirección médica). Aplican a los plazos que empiezan desde ese momento.

**Defectos de la semana 15:**
- **D-03 (mayor), corregido:** enfermería ya no puede cerrar; el permiso `alertas.cerrar` lo valida la ruta.
- **D-04, no aplica:** era del arranque del Micro-HIS; aquí toda la API responde JSON también con la base caída.
- **D-05, corregido:** el canal registra la misma hora del sistema que el historial.

**Pantalla:**
- la campana muestra cuántas alertas esperan el acuse de cada usuario y se revisa cada 30 segundos;
- **cambio de la semana 12:** el título de la pestaña dice «(2) …» aunque no esté activa;
- `/alertas` con «Para mí», «Abiertas», «Recientes» y «Plazos de acuse»; el detalle tiene acusar, cerrar con nota, historial y a quién se notificó.

**Reportes:** el indicador «Alertas críticas reconocidas a tiempo» (acusadas en el nivel 0 ÷ alertas del período) reemplaza su pendiente.

**Verificación:**
- 11 pruebas Feature nuevas (`CriticalAlertTest`), con los criterios CA-01 a CA-16 y el reloj controlado (`travelTo`, RNF-04);
- en total, **189 correctas y ninguna incompleta**.

## Admisión: orden de ingreso y tiempo de espera de cama (mejora)

Mejora del módulo de admisión, en su propia rama. Antes, la admisión creaba el internamiento y asignaba la cama en el mismo paso, así que no había espera que medir y el indicador «Tiempo de espera de cama» seguía como «Próximamente».

**Flujo nuevo:**
- el médico (o recepción) **ordena el ingreso**: paciente, tipo de cama, aislamiento, origen, prioridad (urgente o programada) y motivo;
- el paciente queda en la lista **«Esperando cama»** de `/camas`, con las urgentes primero y cuánto lleva esperando;
- al **asignarle una cama** compatible se crea el internamiento como en una admisión directa (misma transacción y mismas reglas) y la orden queda «asignada»;
- la orden también se puede **cancelar** con motivo;
- si alguien admite directamente a un paciente que tenía una orden pendiente, la admisión cumple esa orden.

**Por qué una tabla aparte (`admission_orders`, migración `2026_11_01_000100`):** un internamiento abierto sin cama habría afectado a ocupación, traslados, altas, la regla de un internamiento abierto por paciente y signos vitales. Con la tabla aparte, nada de eso cambia.

**Reglas:**
- una sola orden pendiente por paciente, y no para quien ya está internado (también lo impide la base);
- cada estado lleva sus datos: asignada = internamiento y hora; cancelada = motivo y hora;
- la bitácora guarda `ORDEN_INGRESO_CREADA`, `ORDEN_INGRESO_CANCELADA`, y en `ADMISION_CREADA` la orden y los minutos de espera.

**Indicador «Tiempo de espera de cama»:** minutos entre la orden y la cama asignada ÷ órdenes con cama asignada en el período, con desglose por prioridad y tipo de cama. Se muestra como «2 h 15 min». Las admisiones directas, sin orden, no cuentan. Con él ya no queda ningún indicador «Próximamente».

**Verificación:**
- 6 pruebas Feature nuevas (`AdmissionOrderTest`), con el reloj controlado;
- en total, **195 correctas y ninguna incompleta**.

## Pendientes conocidos

- **Diseño de Stitch:** la paleta y los componentes actuales son provisionales. Al llegar el diseño se ajustan `resources/css/app.css` y `resources/js/components/ui.tsx`, y el cambio se propaga a todas las pantallas.
- **Identidad protegida (R-11 del módulo pacientes):** pacientes confidenciales visibles solo con un permiso especial. Queda para una fase posterior, porque necesita permisos nuevos.
- **Esquema de Richard:** sigue pendiente la tarea 3.7 de `openspec/changes/postgresql-federated-schema-rebuild/tasks.md` (auditoría local). La 3.5 (medicamentos) y la 3.6 (laboratorio) las cubrieron los catálogos de medicamentos y de pruebas.
- **Dependencias:** `composer audit` reporta avisos de seguridad en paquetes del `composer.lock` heredado. Conviene actualizarlo en un PR aparte, con todas las pruebas.
