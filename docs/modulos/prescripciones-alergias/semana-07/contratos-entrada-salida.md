# Contratos de entrada/salida de los componentes de Prescripciones

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «creación de prescripción con verificación previa de alergias»

**Cómo leer este documento:**
- Cada componente del [diagrama](diagramas/semana-07-componentes.png) aparece con lo que recibe, lo que entrega y lo que **no** puede hacer.
- El backend está implementado; las rutas son relativas a [`micro-his-prescripciones/`](../micro-his-prescripciones/README.md).
- El frontend es diseño, con la convención del HIS: `resources/js/modules/<módulo>/`, Vue 3, Pinia, `vue-router` con sus guardas y el `axios` compartido (`resources/js/plugins/axios.js`).

## 1. Backend (Micro-HIS PHP, implementado)

### 1.1 Presentación (MVC)

| Componente | Entrada | Salida | Prohibido |
|---|---|---|---|
| `Kernel` + `Rutas` | `Solicitud` (método, ruta, cabeceras, cuerpo) | `Respuesta` con `X-Request-ID`; 401 sin token; 400/403 por `X-Tenant-ID`; 405 en `PUT`/`PATCH`/`DELETE`; una línea en el registro de acceso | Reglas de negocio |
| `FormularioPrescripcion` | `Solicitud` + `Identidad` | `SolicitudEmision` · `EntradaVerificacion` · `id`, o `EntradaInvalida` (400: `IDEMPOTENCY_KEY_REQUERIDA`, `JSON_INVALIDO`, `CUERPO_INVALIDO`) | Validar reglas clínicas; tomar el usuario o el hospital del cuerpo |
| `PrescripcionesControlador` | `Solicitud` + `Identidad` | `Respuesta` que arma una Vista | SQL, `new`, códigos de negocio o HTTP (ARQ-07) |
| `PrescripcionVista` · `ErrorVista` | `Resultado` / `EntradaInvalida` | JSON + estado HTTP (201 con `Location`, 200, 400–503) | Decidir el resultado |

### 1.2 Aplicación

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| `VerificarAlergiasPrevia` | `usuario`, `hospital`, `paciente`, `items` | `OK` con `SIN_HALLAZGOS` · `ADVERTENCIA` · `BLOQUEO` · `CONFIRMACION_REQUERIDA` · `VERIFICACION_NO_DISPONIBLE` y `hallazgos`; `RECHAZADO` (datos, dosis) · `NO_ENCONTRADO` · `DENEGADO` · `NO_DISPONIBLE` | **Semana 7:** el código lo decide `PoliticaEmision::resultadoPrevio()` |
| `EmitirPrescripcion` | `SolicitudEmision(usuario, hospital, paciente, clave, items, justificacion, confirmaSinAlergias)` | `CREADO {id, verificacion}` · `OK YA_EMITIDA` · `RECHAZADO` (`DATOS_INVALIDOS`, `DOSIS_EXCEDE_MAXIMO`, `CONFIRMACION_REQUERIDA`, `BLOQUEO_ALERGIA[_CRUZADA]` + hallazgos) · `CONFLICTO` (`JUSTIFICACION_REQUERIDA` + hallazgos, `CLAVE_REUTILIZADA`) · `NO_ENCONTRADO` · `DENEGADO` · `NO_DISPONIBLE` (`VERIFICACION_NO_DISPONIBLE`, `ERROR_PERSISTENCIA`) | **Semana 7:** solo orquesta; la decisión clínica es de `PoliticaEmision` |
| `ConsultarPrescripcion` | `usuario`, `hospital`, `id` | `OK` con la prescripción y su verificación · `NO_ENCONTRADO` (igual si es de otro hospital) · `DENEGADO` · `NO_DISPONIBLE` | — |
| `EvaluacionClinica` | `paciente`, `hospital`, `items` | `[ExpedienteAlergias, ResultadoVerificacion]`, un `Resultado` de rechazo (datos, dosis), o `FuenteAlergiasNoDisponible` | Común a la previa y a la emisión |
| Puertos (`FuenteAlergias`, `CatalogoMedicamentos`, `Pacientes`, `PoliticaAcceso`, `RegistroAuditoria`, `Transaccion`) | Ver semana 2 | Ver semana 2 | `FuenteAlergias`: nunca vacío ante una falla |

### 1.3 Dominio

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| `PoliticaEmision` (**nuevo**) | `ResultadoVerificacion`, `ExpedienteAlergias`, `SolicitudEmision`, `Closure(): bool` (¿tiene override?) | `DecisionEmision`: `PERMITIDA` (con la justificación normalizada y si la confirmación quedó registrada) · `BLOQUEADA(código)` · `REQUIERE_JUSTIFICACION` · `REQUIERE_CONFIRMACION` | Orden: confirmación → bloqueo → advertencia. Consulta el override **solo** si hace falta (DOM-09). Dueña de `MIN_JUSTIFICACION` |
| `PoliticaEmision::resultadoPrevio` (**nuevo**) | `ResultadoVerificacion`, `ExpedienteAlergias` | `string` | La previa y la emisión ya no pueden divergir |
| `DecisionEmision` (**nuevo**) | — | `tipo`, `codigoBloqueo`, `justificacion`, `confirmacionRegistrada`, `permiteEmitir()` | Inmutable; constructores con nombre |
| `Prescripcion::emitir` (**nuevo**) | `SolicitudEmision`, `ExpedienteAlergias`, `ResultadoVerificacion`, `DecisionEmision`, hora | `Prescripcion` (sin id) | Lanza `LogicException` si la decisión no permite emitir |
| `ValidadorPrescripcion` | `items`, catálogo resuelto | `{campos, dosis}` | R-01, R-02 |
| `VerificadorAlergias` | medicamentos, expediente, grupos | `ResultadoVerificacion` | R-04, R-05 |
| `RepositorioPrescripciones` | ver semana 4 | ver semana 4 | Sin actualizar ni eliminar |

### 1.4 Persistencia y Cliente

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| Adaptadores `Pdo/*` y `EnMemoria/*` | Los de cada puerto | Los de cada puerto | Misma suite de contrato (semana 4) |
| `HttpFuenteAlergias` | URL base, circuito, límite | `ExpedienteAlergias` o `FuenteAlergiasNoDisponible` | Semana 5 |
| `ClientePrescripciones` | paciente, items, justificación, confirmación, `?clave` | `ResultadoCliente` CONFIRMADO / RECHAZADO / INCIERTO (+ clave) | Programa contra `api/openapi.yaml` |

## 2. Frontend (Vue 3, diseño) — `resources/js/modules/prescripciones/`

### 2.1 Interfaz

| Componente | Props (entrada) | Eventos (salida) | Prohibido |
|---|---|---|---|
| `pages/PrescribirPage.vue` | `pacienteId` (de la ruta del expediente) | — | Decidir bloqueos; emitir sin pasar por el store |
| `components/FormularioItems.vue` | `items`, `catalogo` (lista para el selector) | `update:items` | Calcular la dosis máxima (la muestra el panel, la decide el servidor) |
| `components/PanelVerificacion.vue` | `resultado` (`codigo` + `hallazgos`), `cargando` | `verificar` | Ocultar un hallazgo; mostrar un bloqueo como advertencia |
| `components/DialogoJustificacion.vue` | `hallazgos`, `tieneOverride` | `confirmar(justificacion)`, `cancelar` | Habilitar «Emitir» con menos de 20 caracteres (ayuda; el servidor vuelve a validar) |
| `components/ConfirmacionSinAlergias.vue` | `visible` (si la previa respondió `CONFIRMACION_REQUERIDA`) | `update:confirmado` | Marcarla por defecto |
| `components/ResultadoEmision.vue` | `estado` (CONFIRMADO / RECHAZADO / INCIERTO), `cuerpo` | `reintentar`, `verDetalle` | Decir «emitida» si el estado es INCIERTO |
| `pages/PrescripcionDetallePage.vue` + `components/DetallePrescripcion.vue` | `id` | — | Ofrecer editar o borrar (R-10) |

### 2.2 Aplicación (estado)

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| `stores/usePrescripcionStore.js` (Pinia) | acciones `verificar()`, `emitir()`, `reintentar()`, `consultar(id)` | `borrador`, `verificacion`, `estadoEmision`, `clavePendiente` | Una `Idempotency-Key` por intención; **se guarda en `sessionStorage` mientras el estado sea INCIERTO** y se reutiliza en «Reintentar» |
| `router` + `guards.js` | `meta.permiso: 'prescripcion.emitir'` | navegación permitida o redirección | Solo oculta lo que el servidor igual negaría |

### 2.3 Dominio (ayuda)

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| `domain/validacionLocal.js` | `items` | errores de formato por campo (vacíos, frecuencia, días 1–90) | **Ayuda** de usabilidad; el servidor decide todo (R-03) |

### 2.4 Acceso a datos

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| `api/prescripcionesApi.js` | `verificar(paciente, items)`, `emitir(cuerpo, clave)`, `consultar(id)` | `{ estado, cuerpo }` normalizado; `X-Request-ID` en cada llamada | Sobre el `axios` compartido (token y `X-Tenant-ID` del store de auth); reintenta solo sin respuesta o 5xx, con la **misma** clave |

**Lo que el frontend no tiene, a propósito:**
- un botón «emitir sin verificar» (R-03);
- editar o borrar una prescripción (R-10);
- una cola de «pendientes de enviar» (semana 5).
