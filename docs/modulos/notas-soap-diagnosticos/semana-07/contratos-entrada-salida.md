# Contratos de entrada/salida de los componentes de Notas SOAP

**Estudiante:** Joshua Eduardo García Reyes · **Semana 7** · Complemento de [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

Cada componente se describe por lo que **recibe**, lo que **devuelve**, cómo **falla** y de qué **depende**. El contrato entre el frontend y el backend es el mismo [`api/openapi.yaml`](../micro-his-notas-soap/api/openapi.yaml) (v1.1.0) de las semanas 5 y 6: el frontend no conoce clases del servidor. Lo marcado *(semana 7)* es lo que agregó el refactor.

## 1. Backend (micro-HIS PHP, implementado)

### 1.1 Presentación (MVC)

| Componente | Entrada | Salida | Fallas / errores | Depende de |
|---|---|---|---|---|
| `Kernel::manejar` | `Solicitud` (método, ruta, cabeceras, cuerpo) | `Respuesta` con `X-Request-ID` + 1 registro de acceso sin texto clínico | 404/405 ruta · 400 `tenant_requerido` · 401 `token_invalido` · 403 permiso de la ruta · traduce cada excepción del dominio a su código (ver §3) · 500 genérico | `Rutas`, `Autenticador`, `NotasSoapController` |
| `Rutas::definir` | — | 5 rutas, cada una con su permiso (`notas_soap.registrar`, `.consultar`, `.firmar`, `.corregir`) | — | — |
| `FormularioRegistroNota::comando` | `Solicitud` con `medical_record_id`, `care_episode_id`, secciones `subjetivo/objetivo/analisis/plan`, `diagnostico {code, description, type}`; cabecera opcional `Idempotency-Key` | `RegistrarNotaComando` (el autor y el hospital salen del token, nunca del cuerpo) | `CuerpoInvalido` → 400 | — |
| `FormularioCorreccionNota::comando` | `Solicitud` con las mismas secciones y diagnóstico + `correction_reason` | `CorregirNotaComando` | `CuerpoInvalido` → 400 | — |
| `FiltroNotasExpediente::comando` | `Solicitud` + `expedienteId` | `ListarNotasExpedienteComando` | `CuerpoInvalido` → 400 | — |
| `NotasSoapController` | `Solicitud`, `Identidad`, hospital y el id de la ruta | `Respuesta` 201 (registrar, corregir) o 200 (firmar, consultar, listar) | Solo traduce: formulario → caso de uso → vista | Formularios, casos de uso, `SelectorVista` |
| `VistaJson` / `VistaHtml` | `NotaResultado` o lista de `ResumenNotaResultado` | `Respuesta`; si fue un reintento, cabecera `Idempotent-Replayed: true` | El 500 nunca lleva SQL ni nombres de tablas (RNF-03) | — |

### 1.2 Aplicación

| Componente | Entrada | Salida | Fallas | Depende de |
|---|---|---|---|---|
| `RegistrarNotaConDiagnostico::ejecutar` | `RegistrarNotaComando` | `NotaResultado` (nota en borrador + diagnóstico); si la clave ya se usó con el mismo contenido, la misma nota marcada como repetida | `AccesoDenegado` · `ClaveIdempotenciaReutilizada` · `ExpedienteNoDisponible` · `NotaIncompleta` · `DiagnosticoInvalido` · `ErrorPersistencia` | `ReglasDeEscritura`, `RegistradorClinico`, `RepositorioNotas`, `RepositorioDiagnosticos`, `GeneradorId`, `Reloj`, `RegistroIdempotencia` |
| `CorregirNota::ejecutar` | `CorregirNotaComando` | `NotaResultado` de la versión nueva (`version_no` + 1, `supersedes_id`, `correction_reason`) | `AccesoDenegado` · `NotaNoEncontrada` · `NotaYaCorregida` (también si dos llegan a la vez y el `UNIQUE` rechaza una) · `ExpedienteNoDisponible` · `NotaNoFirmada` · `MotivoCorreccionInsuficiente` · `NotaIncompleta` · `DiagnosticoInvalido` · `ErrorPersistencia` | `ReglasDeEscritura`, `RegistradorClinico`, `RepositorioNotas`, `GeneradorId`, `Reloj` |
| `FirmarNota::ejecutar` | `FirmarNotaComando` | `NotaResultado` con `signed_at` | `AccesoDenegado` · `NotaNoEncontrada` · `NotaFirmada` · `ErrorPersistencia` | `RepositorioNotas`, `RepositorioDiagnosticos`, `RegistradorClinico`, `Reloj` |
| `ConsultarNota::ejecutar` / `ListarNotasExpediente::ejecutar` | Comando con id y hospital | `NotaResultado` / lista de `ResumenNotaResultado` | `AccesoDenegado` · `NotaNoEncontrada` | Repositorios |
| `RegistradorClinico::guardar` *(semana 7)* | Un `CambioClinico` y, opcional, un trabajo más para la misma transacción (la clave de idempotencia) | Nada: el cambio y su evento quedan guardados juntos | Cualquier falla revierte todo (R-06) y sube como `ErrorPersistencia`; no deja evento ni clave (REF-04) | `Transaccion`, `RepositorioNotas`, `RepositorioDiagnosticos`, `RegistroAuditoria` (solo interfaces, ARQ-15) |

### 1.3 Dominio

| Componente | Entrada | Salida | Fallas | Depende de |
|---|---|---|---|---|
| `NotaSoap::registrar` / `::corregir` / `->firmar` | Datos de la nota; la anterior y el motivo al corregir | `NotaSoap` nueva (inmutable) | `NotaIncompleta` · `NotaNoFirmada` · `MotivoCorreccionInsuficiente` · `NotaFirmada` | `SeccionesSoap` |
| `Diagnostico::registrar` | id, nota, código CIE-10, descripción, tipo | `Diagnostico` ligado a la nota, el episodio y el autor | `DiagnosticoInvalido` | `NotaSoap`, `TipoDiagnostico` |
| `ReglasDeEscritura::exigirMismoHospital` *(semana 7)* | `UsuarioClinico`, hospital de la solicitud | Nada si cumple (R-01) | `AccesoDenegado` | — |
| `ReglasDeEscritura::exigirPuedeEscribir` *(semana 7)* | Usuario, hospital, episodio y el mensaje que elige el caso de uso | Nada si cumple (R-01 y R-02) | `AccesoDenegado`; un error del verificador sube tal cual y termina en 500 `error_interno` (REF-03) | `VerificadorAcceso` |
| `ReglasDeEscritura::exigirExpedienteDisponible` *(semana 7)* | Expediente, episodio y mensaje | Nada si cumple (R-03) | `ExpedienteNoDisponible` | `RepositorioExpedientes` |
| `CambioClinico::aplicarEn` *(semana 7)* — `NotaConDiagnostico`, `FirmaDeNota` | `RepositorioNotas`, `RepositorioDiagnosticos` | Guarda la nota y su diagnóstico, o registra la firma | `ErrorPersistencia` del adaptador | Los puertos |
| `CambioClinico::entrada` *(semana 7)* | — | `EntradaBitacora` del cambio (registrada o corregida según `supersedes_id`; firmada) | — | `EntradaBitacora` |
| `EntradaBitacora::notaRegistrada / notaFirmada / notaCorregida` *(semana 7)* | `NotaSoap` | Evento con nombre y campos fijos, solo identificadores (RNF-03, REF-01) | `LogicException` si se pide `NOTA_CORREGIDA` de una nota que no corrige a otra | — |
| Puertos `RepositorioNotas`, `RepositorioDiagnosticos`, `RepositorioExpedientes`, `VerificadorAcceso`, `RegistroAuditoria` | Ver semana 4 | Objetos del dominio | Solo `ErrorPersistencia` | — |

### 1.4 Persistencia

| Componente | Entrada | Salida | Fallas |
|---|---|---|---|
| Adaptadores `Pdo*` (PostgreSQL) | Llamadas a los puertos, siempre con sentencias preparadas | Objetos del dominio | `PDOException` → `ErrorPersistencia` con su SQLSTATE (23xxx = restricción, como el `UNIQUE (supersedes_id)`) |
| Adaptadores `*EnMemoria` | Llamadas a los puertos | Objetos del dominio | Misma semántica (las pruebas REP corren contra los dos) |

## 2. Frontend (Vue 3 + Pinia, diseño)

Va en `resources/js/modules/notas-soap/` del repositorio del grupo y reutiliza `plugins/axios.js`, `stores/auth.js` y `router/guards.js`.

### 2.1 Acceso a datos

| Componente | Entrada | Salida | Fallas | Depende de |
|---|---|---|---|---|
| `notasSoapApi.registrar(datos, clave)` | Formulario + `Idempotency-Key` | Nota (201) e indicador `repetida` si llega `Idempotent-Replayed` | Rechaza con `{estado, code, message, requestId}` tal como lo manda el servidor | `plugins/axios.js` |
| `notasSoapApi.corregir(id, datos)` / `.firmar(id)` | id + formulario con `correction_reason` / solo id | Nota (201 / 200) | Igual | `plugins/axios.js` |
| `notasSoapApi.consultar(id)` / `.listar(expedienteId)` | id | Nota / lista de resúmenes | Igual | `plugins/axios.js` |
| `plugins/axios.js` (ya existe) + interceptores del módulo (diseño) | Cada petición | El existente agrega `Authorization: Bearer` y `X-Tenant-ID` desde `localStorage`; el del módulo agrega un `X-Request-ID` nuevo | Diseño: ante 401, pedir a `stores/auth.js` que cierre la sesión (hoy el plugin no maneja respuestas) | `localStorage`, `stores/auth.js` |

### 2.2 Aplicación cliente

| Componente | Entrada | Salida | Fallas | Depende de |
|---|---|---|---|---|
| `useEnvioSeguro(operacion)` | La llamada a `notasSoapApi` y su tipo | El resultado de la llamada | Reintenta **una vez** solo lecturas y registro (con la misma clave) ante falla de red o 5xx; firma y corrección no se reintentan; ningún 4xx se reintenta | `notasSoapApi` |
| `useNotasSoapStore` (Pinia) | Acciones `cargarExpediente`, `registrar`, `corregir`, `firmar` | Estado: `notas`, `seleccionada`, `borrador`, `cargando`, `error` | Guarda el error para la pantalla; **no limpia el borrador** hasta recibir 201 (RNF-06) | `useEnvioSeguro` |
| `usePermisosNotas` | Roles de `stores/auth.js` y la nota | `puedeRegistrar`, `puedeFirmar`, `puedeCorregir` (solo para mostrar u ocultar) | — (si se equivoca, el servidor responde 403/409) | `stores/auth.js` |

### 2.3 UI

| Componente | Entrada (props) | Salida (eventos) | Qué muestra ante falla |
|---|---|---|---|
| `NotasExpedientePage` | `expedienteId`, `episodioId` (de la ruta) | — | Carga la lista desde el store |
| `LineaTiempoNotas` | Lista de resúmenes | `seleccionar(id)` | Cada versión con su número; las corregidas, marcadas como reemplazadas |
| `NotaSoapDetalle` | Nota | `firmar`, `corregir` | Secciones, diagnóstico, autor, firma y, si es corrección, el motivo |
| `NotaSoapFormulario` | `modo` (`registro` o `correccion`), borrador | `guardar(datos)` | Errores de validación (422) junto al campo; conserva lo escrito |
| `CorreccionNotaDialog` | Nota firmada | `confirmar(datos)` | Abre el formulario en modo corrección con las secciones de la nota anterior |
| `FirmarNotaBoton` | Nota en borrador | `firmar` | Pide confirmación: la firma no se puede deshacer |
| `ErrorNotaAviso` | `{estado, code, message, requestId}` | — | El mensaje del servidor y el `requestId` para soporte |

## 3. Reglas que cruzan los dos lados

| Regla | Dónde se cumple |
|---|---|
| **El frontend no decide nada clínico.** No autoriza, no valida el CIE-10 como regla final y no elige el autor. | Backend: `ReglasDeEscritura`, `Diagnostico`, el autor sale del token. El frontend solo oculta botones. |
| **Mismos códigos de error en los dos lados.** | 400 `cuerpo_invalido` · 403 `acceso_denegado` · 404 `nota_no_encontrada` · 409 `expediente_no_disponible`, `nota_firmada`, `nota_no_firmada`, `nota_ya_corregida` · 422 `nota_incompleta`, `diagnostico_invalido`, `motivo_insuficiente`, `idempotency_key_reutilizada` · 500 `error_persistencia`, `error_interno`. Todos están en el `enum` del contrato (API-04). |
| **El 403 no explica de más.** | El mensaje dice que no tiene permiso, no qué asignación falta ni de qué hospital es el expediente. |
| **Reintentar no duplica.** | Registro con `Idempotency-Key` (semana 5); corrección protegida por el `UNIQUE (supersedes_id)` (semana 6); firma sin reintento automático. |
| **No se pierde lo escrito (RNF-06).** | Backend: un 422 no guarda nada. Frontend: el store conserva el borrador hasta el 201. |
| **Trazabilidad.** | Cada petición lleva `X-Request-ID`; el servidor lo devuelve y lo anota en el registro de acceso; `ErrorNotaAviso` lo muestra. |
