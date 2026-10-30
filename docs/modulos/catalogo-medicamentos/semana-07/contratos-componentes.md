# Contratos de entrada y salida de los componentes

Componentes **propios** del Catálogo de medicamentos. Los nombres de Laravel/Vue son la propuesta
para el HIS; los de dominio y aplicación ya existen en el Micro-HIS (`../micro-his/src/`).

## 1. Backend

| Componente | Entrada | Salida | Errores que produce | Depende de |
|---|---|---|---|---|
| `RequestId` (middleware) | Encabezado `X-Request-Id` opcional | Mismo id (o uno nuevo) en la petición y en la respuesta | — | — |
| `TenantDesdeToken` (middleware) | Token autenticado | `Identidad(usuario, rol, hospitalId)` | 401 `NO_AUTENTICADO` | Autenticación del HIS |
| `CatalogoPolicy` | `Identidad`, acción (`buscar`, `registrar`, …) | permitido / denegado | 403 `PROHIBIDO` | Matriz de `semana-05/contrato-api.md` §2 |
| Solicitudes de formato (FormRequest) | Cuerpo JSON | Arreglo con tipos correctos | 400 `CUERPO_INVALIDO`, 415 | — (no valida reglas de negocio) |
| `CatalogoMedicamentosController` | Petición + `Identidad` | `MedicamentoResource` o lista | Traduce `ErrorDominio` a 404/409/422/503 | Casos de uso |
| Casos de uso (`RegistrarMedicamento`, `BuscarMedicamentosVigentes`, `RegistrarNuevaVersion`, `DarDeBajaMedicamento`, `ConsultarHistorial`, `EliminarRegistroSinUso`, `ConsultarVigentePorCodigo`) | `hospitalId`, datos crudos, usuario | `Medicamento` o lista | `ErrorValidacion`, `ErrorReglaNegocio`, `ErrorNoEncontrado`, `ErrorPersistencia` | Interfaces de rol del dominio |
| Modelo (`Medicamento`, `FichaMedicamento`, `Vigencia`, `ClaveEquivalencia`, `CriterioBusqueda`, `Limites`) | Datos crudos o valores | Objetos inmutables | `ErrorValidacion`, `ErrorReglaNegocio` | Nada |
| `RepositorioMedicamentosPdo` | Objetos del dominio | Objetos del dominio | `ErrorReglaNegocio` (restricciones), `ErrorPersistencia` | PDO pgsql, esquema `catalogo` |
| `UsoPrescripcionesAdaptador` | `hospitalId`, `CodigoMedicamento` | `bool` | `ErrorPersistencia` | `prescripciones-alergias` (**pendiente de acordar**) |
| `MedicamentoResource` | `Medicamento` | JSON de `contrato-api.md` §4 | — | `MedicamentoVista` |

## 2. Frontend

| Componente | Props / entrada | Eventos / salida | Estados que maneja |
|---|---|---|---|
| `CatalogoBuscarView` | Query `q`, `forma`, `fecha` de la URL | Navega a detalle o a alta | vacío, cargando, resultados, sin resultados, error |
| `BuscadorMedicamentos` | `criterio` inicial, lista de formas | `buscar(criterio)` | validación local: `q` 2–60 caracteres o forma |
| `TablaMedicamentos` | `medicamentos[]`, `fecha` | `seleccionar(codigo)` | lista vacía con acción «Registrar» |
| `FormularioMedicamento` | `valores`, `errores{campo: mensaje}`, `modo` (alta o versión), opciones | `enviar(datos)`, `cancelar()` | errores por campo, enviando (botón deshabilitado) |
| `HistorialVersiones` | `versiones[]` | — | versión vigente resaltada |
| `DialogoConfirmacion` | `titulo`, `mensaje`, `accionPeligrosa` | `confirmar()`, `cancelar()` | foco atrapado; Escape cancela |
| `MensajeEstado` | `tipo` (info, éxito, error), `texto`, `accion?` | `accion()` | `role="status"` o `role="alert"` |
| `useCatalogoApi` | Token, `X-Request-Id` por llamada | Promesas con `datos` o `ErrorApi{estado, codigo, mensaje, detalles, requestId}` | 401 → iniciar sesión, 503 → reintento sugerido |
| `useBusquedaVigentes` | Criterio | `resultados`, `meta`, `cargando`, `error` | cancela la búsqueda anterior si llega una nueva |
| `useFormularioMedicamento` | Valores iniciales | `errores`, `enviar()` | 422 → errores por campo; 409 → mensaje con enlace al código existente |
| `usePermisos` | Rol de la sesión | `puede(accion)` | Solo oculta acciones; el servidor decide |

## 3. Reglas de los contratos

1. **Una regla, un lugar:** los límites de RN-05 los define `Limites` y la UI los recibe por la API (`/api/v1/opciones` y los mensajes 422). La validación local es solo ayuda.
2. **El tenant nunca viaja como dato:** ningún componente del frontend envía `hospital_id`.
3. **Errores uniformes:** todos los componentes consumen `ErrorApi`, con el `request_id` visible para soporte.
4. **Sin ampliar el módulo:** no se agregan existencias, precios ni prescripciones; esos datos pertenecen a otros módulos.
