# Contratos de entrada/salida de los componentes de Pacientes

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Flujo:** «registro, búsqueda y actualización segura de un paciente»

Este documento describe, para cada componente, lo que recibe, lo que devuelve y cómo falla. El backend está implementado en [`micro-his-pacientes/`](../micro-his-pacientes/README.md). El frontend es un diseño en Vue 3 + Pinia que se apoya en lo que ya existe en `resources/js`. Los dos lados se comunican **solo** por el contrato [`openapi.yaml`](../micro-his-pacientes/api/openapi.yaml), versión 1.1.0.

## 1. Backend (Micro-HIS PHP, implementado)

### 1.1 Presentación (MVC)

| Componente | Entrada | Salida | Errores |
|---|---|---|---|
| `Kernel::manejar()` | `Solicitud` (método, ruta, cabeceras, consulta, cuerpo) | `Respuesta` con `X-Request-ID`; escribe una línea en el registro de acceso (sin consulta, cuerpo ni token) | 400 `HOSPITAL_REQUERIDO` · 401 `NO_AUTENTICADO` · 403 `TENANT_NO_COINCIDE` · 404 `RUTA_NO_ENCONTRADA` · 405 · 500 `ERROR_INTERNO` |
| `PacientesControlador` | `Solicitud` + `Identidad` (actor, hospital) | `Respuesta` (por medio de la vista) | No decide nada: delega en el formulario, el caso de uso y la vista |
| `FormularioPaciente` (Presentación) | Cuerpo JSON y cabeceras (`If-Match`, `Idempotency-Key`) | Arreglo de datos con **formato** válido (tipos, booleanos, JSON) | `EntradaInvalida` → 400 `CUERPO` / `FORMATO` (con `campos`) / `VERSION_INVALIDA`; 428 `VERSION_REQUERIDA` |
| `PacienteVista` | `Resultado` | 201 con `Location` y `ETag`; 200 con `ETag`; 304 si `If-None-Match` coincide | Si el resultado es un error, delega en `ErrorVista` |
| `ErrorVista` | `Resultado` de error o `EntradaInvalida` | `{error, …datos}` con el código HTTP de **su categoría** (`invalido` 422, `conflicto` 409, `no_encontrado` 404, `prohibido` 403, `no_disponible` 503) | — |

### 1.2 Aplicación

Los cuatro casos de uso tienen la misma forma: reciben un comando y devuelven un `Resultado`. Nunca lanzan excepciones hacia la Presentación.

| Componente | Entrada | Salida (éxito) | Errores posibles (`Resultado::error`) |
|---|---|---|---|
| `RegistrarPaciente` | `ComandoRegistrar {actor, hospital, datos[], confirmaPeseACoincidencia, motivo?, claveIdempotencia?}` | `creado {codigo, version, identidad_provisional}` (+ `repetida` si es un reintento) | `ACCESO_DENEGADO` · `DATOS_INVALIDOS {campos}` · `MOTIVO_REQUERIDO` · `PACIENTE_EXISTENTE {codigo, identificador?}` · `POSIBLE_DUPLICADO {candidatos}` · `IDEMPOTENCY_KEY_REUTILIZADA` · `no_disponible` |
| `BuscarPacientes` | `ComandoBuscar {actor, hospital, criterios[]}` | `ok {resultados: ResumenPaciente[], total}` (máximo 20) | `ACCESO_DENEGADO` · `CRITERIO_INSUFICIENTE` · `no_disponible` |
| `ConsultarPaciente` | `ComandoConsultar {actor, hospital, codigo}` | `ok {paciente: DetallePaciente}` | `ACCESO_DENEGADO` · `PACIENTE_NO_ENCONTRADO` (el mismo si es de otro hospital) · `no_disponible` |
| `ActualizarPaciente` | `ComandoActualizar {actor, hospital, codigo, versionLeida, datos[], motivo?}` | `ok {codigo, version}` | `ACCESO_DENEGADO` · `PACIENTE_NO_ENCONTRADO` · `DATOS_INVALIDOS` · `REQUIERE_SUPERVISOR` · `MOTIVO_REQUERIDO` · `IDENTIFICADOR_EN_USO` · `VERSION_DESACTUALIZADA {version_vigente}` · `no_disponible` |
| `ControlAcceso::exigir()` | `actor, hospital, permiso` | — | `ErrorDominio PROHIBIDO / ACCESO_DENEGADO` |
| **`ControlAcceso::capacidades()`** *(semana 7)* | `actor, hospital` | `Capacidades` | — (sin permiso → `false`, fail-closed) |
| `FormularioPaciente::aDatos()` (Aplicación) | Arreglo plano | `DatosPaciente` (lo que falte llega vacío; `identidad_protegida` ausente = `null`) | — (lo rechaza el Dominio) |

Puertos que define la Aplicación: `Transaccion::ejecutar(Closure): mixed`, `AlmacenIdempotencia::{buscar, guardar}`, `PoliticaAcceso::permite(): bool` y `Reloj::hoy()`.

### 1.3 Dominio

| Componente | Entrada | Salida | Errores |
|---|---|---|---|
| **`RevisionDeFicha::paraAlta()`** *(semana 7)* | `hospital, DatosPaciente, hoy, Capacidades, confirmaCoincidencia, motivo` | `{paciente, confirmadas}` | En este orden: `DATOS_INVALIDOS` → `ACCESO_DENEGADO`/`MOTIVO_REQUERIDO` (proteger) → `PACIENTE_EXISTENTE` → `POSIBLE_DUPLICADO` → `ACCESO_DENEGADO`/`MOTIVO_REQUERIDO` (confirmar) |
| **`RevisionDeFicha::paraEdicion()`** *(semana 7)* | `Paciente actual, DatosPaciente, hoy, Capacidades, motivo` | `Paciente` nuevo, todavía sin guardar | En este orden: `ACCESO_DENEGADO` (no lo ve) → `DATOS_INVALIDOS` → `REQUIERE_SUPERVISOR` / `MOTIVO_REQUERIDO` → `ACCESO_DENEGADO`/`MOTIVO_REQUERIDO` (protección) → `IDENTIFICADOR_EN_USO` |
| **`Capacidades`** *(semana 7)* | `verProtegidos, proteger, confirmarDuplicado, editarRestringido` | Objeto de valor inmutable; `ninguna()` = todo `false` | — |
| `Paciente::desdeDatos()` | `hospital, DatosPaciente, hoy` | `Paciente` activo, sin código | `DatosInvalidos {campos}` (R-01) |
| `Paciente::resumen() / detalle()` | `puedeVerProtegidos` (por defecto `false`) | Datos mínimos o detalle; **reducidos** si es protegido y no hay permiso | — |
| `PoliticaEdicion`, `PoliticaDuplicados`, `PoliticaConfidencialidad` | Pacientes y booleanos de permiso | — (o la lista de coincidencias confirmadas) | `ErrorDominio` con su categoría y código |
| `CriterioBusqueda::desde()` | Criterios de texto | Criterio válido (código, documento completo o nombre ≥ 3 letras) | `CRITERIO_INSUFICIENTE` |
| **`EventoAuditoria::registrado() / actualizado()`** *(semana 7)* | Actor, paciente(s), coincidencias, motivo | Evento con el formato fijo (REF-03) | — |

Puertos Repository del Dominio: `ConsultaPacientes ⊂ RepositorioPacientes`, `IndiceDuplicados` y `RegistroAuditoria`.

### 1.4 Persistencia

| Componente | Entrada | Salida | Errores |
|---|---|---|---|
| `PdoRepositorioPacientes` / `RepositorioPacientesEnMemoria` | `Paciente`, versión esperada, criterio | `Paciente` con código y versión; listas ordenadas | `DocumentoDuplicado` · `ConflictoVersion` · `ErrorPersistencia` (→ 503) |
| `PdoIndiceDuplicados` / `IndiceDuplicadosEnMemoria` | Documento, nombre normalizado y fecha, códigos | Titular, coincidencias, protegidos | `ErrorPersistencia` |
| `ProteccionDocumentos` | Documento en claro | HMAC (buscar y unicidad) + cifrado libsodium | Claves ausentes → 503 `CONFIGURACION_INCOMPLETA` |

Los dos adaptadores de cada puerto cumplen la misma suite de contrato (CON-MEM-* y CON-PDO-*).

## 2. Frontend (Vue 3 + Pinia + axios, diseño)

Se apoya en lo que ya existe en el repositorio: `resources/js/plugins/axios.js` (Bearer y `X-Tenant-ID`), `resources/js/stores/auth.js` y `resources/js/router/guards.js`. Los componentes nuevos irían en `resources/js/modules/pacientes/`.

### 2.1 Acceso a datos

| Componente | Entrada | Salida | Errores |
|---|---|---|---|
| `pacientesApi.buscar(criterios)` | `{codigo?} \| {tipo_documento, numero_documento, pais_documento?} \| {nombre, nacimiento?}` | `{resultados: ResumenPaciente[], total}` | Lanza `ErrorPacientes {estado, codigo, campos?, datos?}` |
| `pacientesApi.consultar(codigo, version?)` | Código; versión conocida para `If-None-Match` | `{paciente, version}` o `{sinCambios: true}` (304) | `ErrorPacientes` (403/404/503) |
| `pacientesApi.registrar(datos, clave)` | `DatosPaciente` (+ `identidad_protegida?`, `confirmar_coincidencia?`, `motivo?`); `Idempotency-Key` | `{codigo, version, identidad_provisional, repetida}` | `ErrorPacientes` (400/403/409/422/503) |
| `pacientesApi.actualizar(codigo, version, datos)` | Código, versión leída (va en `If-Match`), datos (+ `motivo?`) | `{codigo, version}` | `ErrorPacientes` (400/403/404/409/422/428/503) |
| Interceptores de axios *(extensión del plugin existente)* | Toda petición | Agrega `X-Request-ID` (`crypto.randomUUID()`) | — |

### 2.2 Aplicación del cliente

| Componente | Entrada | Salida | Notas |
|---|---|---|---|
| `useBusquedaPacientes()` | `buscar(criterios)` | `{estado: 'inicial' \| 'cargando' \| 'resultados' \| 'vacio' \| 'error', resultados, total, error}` | Los criterios **no** van a la URL del navegador: un nombre en el historial sería una fuga de datos (R-04). |
| `useFichaPaciente(codigo)` | Código | `{paciente, version, cargando, error, recargar()}` | Guarda la versión para el `If-Match`. No guarda la ficha en `localStorage`. |
| `useGuardarPaciente()` | `registrar(datos)` · `actualizar(codigo, version, datos)` | `{guardando, resultado, error}` | Genera **una** `Idempotency-Key` por intento de registro y la reutiliza al reintentar (semana 5). El `PUT` no se reintenta solo: ante un error de red pide recargar. |
| `permisoGuard` (vue-router) | `to.meta.permiso` | Continúa o redirige a «acceso denegado» | Solo controla la navegación; el backend vuelve a verificar cada operación. |

### 2.3 UI

| Componente | Props | Emits / slots | Muestra |
|---|---|---|---|
| `BusquedaPacientesPage` | — (ruta `/pacientes`, `meta.permiso: 'pacientes.buscar'`) | — | Buscador y resultados |
| `ResultadosPacientes` | `resultados`, `estado` | `seleccionar(codigo)` | Filas con nombre, nacimiento y documento enmascarado. Si `identidad_protegida` y `nombre` es «Identidad protegida», muestra la fila reducida con una etiqueta, sin inventar datos. |
| `PacienteDetallePage` | — (ruta `/pacientes/:codigo`, `meta.permiso: 'pacientes.leer'`) | — | `FichaPaciente` y el botón «Editar» |
| `FichaPaciente` | `paciente` | `editar()` | Los campos que vengan en `null` se muestran como «Reservado», nunca vacíos ni con un valor anterior |
| `PacienteFormularioPage` | `modo: 'registrar' \| 'editar'`, `codigo?` | — | Formulario, avisos y diálogos |
| `FormularioPaciente` | `valorInicial`, `modo` | `enviar(datos)` · `cancelar()` | Campos y errores por campo (`campos` del 400 o del 422) |
| `AvisoDuplicado` | `error` (`PACIENTE_EXISTENTE` / `POSIBLE_DUPLICADO`) | `abrir(codigo)` · `confirmar(motivo)` · `cancelar()` | El código del paciente existente (o «Reservado»). Para una coincidencia probable, la opción de confirmar solo si el usuario puede, y con motivo. |
| `MotivoDialog` | `titulo`, `ayuda` | `aceptar(motivo)` · `cancelar()` | Motivo obligatorio para un cambio de identidad (R-07), una protección (R-11) o una confirmación (R-03) |
| `ConflictoVersionDialog` | `versionVigente` | `recargar()` · `cancelar()` | «Otra persona modificó esta ficha»; nunca sobrescribe |

## 3. Reglas que cruzan ambos lados

| Regla | Backend | Frontend |
|---|---|---|
| ¿Quién decide qué se oculta de un protegido? | **Siempre** el Dominio (`Paciente::resumen/detalle`, `RevisionDeFicha`) | Nunca: muestra lo que llega; `null` = «Reservado» |
| ¿Quién decide si hay un duplicado? | `PoliticaDuplicados` a través de `RevisionDeFicha` | Solo traduce el 409 y, si corresponde, ofrece confirmar con motivo |
| Versión | `UPDATE … WHERE version` (R-06) | Guarda la versión leída y la envía en `If-Match`; ante el 409, recarga |
| Reintentos | El `POST` es idempotente con `Idempotency-Key` | Reintenta el `POST` con la **misma** clave; no reintenta el `PUT` |
| Datos personales | Nunca en el registro de acceso ni en el 409 de un protegido | Nunca en la URL, en `localStorage` ni en mensajes de error genéricos |
| Trazabilidad | `X-Request-ID` en todas las respuestas | Lo genera y lo envía en cada petición |
