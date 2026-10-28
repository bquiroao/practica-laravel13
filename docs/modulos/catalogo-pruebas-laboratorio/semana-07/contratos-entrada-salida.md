# Contratos de entrada y salida por componente

## Backend

| Componente | Entrada | Salida | Errores que produce |
|---|---|---|---|
| `LabTestApiController` | Petición HTTP (token, query o cuerpo JSON) | Respuesta HTTP con `LabTestResource` | Traduce excepciones a 401/403/404/409/422/500 |
| `RegistrarPruebaRequest` | Cuerpo JSON | Arreglo con tipos correctos (enteros, número, fechas) | 400 si no es JSON; 422 si falta un campo obligatorio |
| `RegistrarPrueba` | Datos validados, tenant, usuario | `LabTest` (versión creada) | `DatosInvalidos`, `VigenciaSuperpuesta`, `ErrorPersistencia` |
| `ConsultarPruebasVigentes` | Tenant y filtros `{texto, seccion, tipo_muestra, fecha}` | `LabTest[]` (puede ser vacío) | `DatosInvalidos` si la fecha no es válida |
| `VerDetallePrueba` | Tenant, código y fecha | `LabTest` | `PruebaNoVigente` (con su vigencia si existe) |
| `LabTestRepository` | Ver interfaz (semana 4) | Entidades de dominio, nunca filas | `ErrorPersistencia` |
| `LabTestResource` | `LabTest` | JSON del contrato v1 (semana 5, sección 3) | — |

## Frontend

| Componente | Entrada (props / estado) | Salida (eventos / acciones) |
|---|---|---|
| `CatalogoPruebasView` | Ruta `/laboratorio/pruebas?fecha=` | Llama `store.cargarVigentes(filtros)` |
| `FiltrosCatalogo` | `filtros: {texto, seccion, tipoMuestra, fecha}` | Evento `buscar(filtros)` |
| `TablaPruebas` | `pruebas: Prueba[]`, `cargando: boolean` | Evento `abrir(codigo)` |
| `DetallePruebaView` | Ruta `/laboratorio/pruebas/:codigo?fecha=` | Llama `store.cargarDetalle(codigo, fecha)` |
| `AvisoVigencia` | `vigencia: {desde, hasta}`, `fecha` | Solo muestra; no calcula reglas |
| `AltaPruebaView` | Permiso `lab.catalogo.gestionar` | Llama `store.registrar(datos)`; navega al detalle si 201 |
| `FormularioRequisitos` | `valores`, `errores: {campo: mensaje}`, `opciones` | Evento `guardar(valores)` |
| `useCatalogoStore` | Acciones anteriores | Estado `{pruebas, detalle, errores, cargando, estado: vacío/cargando/éxito/error}` |
| `catalogoApi` | Llamadas del store | Promesas con `data` o el objeto `error` del contrato |

Regla común: **ningún componente de frontend decide si una prueba está vigente ni valida reglas de negocio**; muestra lo que responde la API y los errores por campo que esta devuelve.
