# Contratos de entrada/salida de los componentes de Gobernanza y auditoría

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas»

**Cómo leer este documento:**
- Cada componente del [diagrama](diagramas/semana-07-componentes.png) aparece con lo que recibe, lo que entrega y lo que **no** puede hacer.
- El backend está implementado; las rutas son relativas a [`micro-his-auditoria/`](../micro-his-auditoria/README.md).
- El frontend es diseño, con la convención del HIS: `resources/js/modules/<módulo>/`, Vue 3, Pinia y el `axios` compartido.

## 1. Backend (Micro-HIS PHP, implementado)

### 1.1 Presentación (MVC)

| Componente | Entrada | Salida | Prohibido |
|---|---|---|---|
| `Kernel` + `Rutas` | `Solicitud` (método, ruta, cabeceras, consulta, cuerpo) | `Respuesta` con `X-Request-ID`; 401 sin token; 403 si el `X-Tenant-ID` no es del token; 405 en `PUT`/`PATCH`/`DELETE` | Reglas de negocio |
| `FormularioMovimientos` | `Solicitud` + `Identidad` | `ComandoRegistrar` · `ComandoConsultar` · `ComandoDetalle` · `ComandoVerificar` · `ComandoAncla`, o `EntradaInvalida` (400) | Validar reglas de dominio; tomar el actor o el hospital del cuerpo |
| `AuditoriaControlador` | `Solicitud` + `Identidad` | `Respuesta` que arma una Vista | SQL, `new`, códigos HTTP (ARQ-07) |
| `MovimientosVista` · `ErrorVista` | `Resultado` | JSON + estado HTTP (201 con `Location`, 200, 400–503) | Decidir el resultado |

### 1.2 Aplicación

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| `RegistrarMovimiento` | `ComandoRegistrar(emisor, hospitalToken, datos)` | `Resultado`: `CREADO {id, secuencia, sello}` · `OK YA_REGISTRADO` · `INVALIDO` · `CONFLICTO` (`EVENTO_DUPLICADO`, `YA_CORREGIDO`) · `PROHIBIDO` · `NO_DISPONIBLE` | 9 reglas, idempotencia y corrección, dentro de la transacción |
| `ConsultarMovimientos` | `ComandoConsultar(actor, tenant, parametros)`: `desde`, `hasta` (obligatorios), `usuario`, `modulo`, `accion`, `resultado`, `cursor` | `OK {movimientos: [resumen + corregido_por] (≤ 50), cursor_siguiente}` · `INVALIDO` (`DATOS_INVALIDOS`, `FILTRO_NO_SOPORTADO`) · `PROHIBIDO` · `NO_DISPONIBLE` | **Semana 7:** solo orquesta |
| `VerDetalleMovimiento` | `ComandoDetalle(actor, tenant, id)` | `OK {movimiento: completo + corregido_por}` · `NO_ENCONTRADO` (igual si es de otro hospital) · `PROHIBIDO` | **Semana 7:** ya no depende de `ConsultarMovimientos` |
| `VerificarIntegridad` · `ObtenerAncla` | `ComandoVerificar(actor, hospital, ?ancla)` · `ComandoAncla(actor, ?hospital)` | `INTEGRA` / `ALTERADA` / `TRUNCADA` · `{secuencia, sello}` | Permiso `auditoria.verificar` en la red |
| `AutorizacionLectura` (nuevo) | `actor`, `Alcance` | `bool` | Único lugar que sabe qué permiso exige leer: `consultar` o `consultar_red` |
| `CursorPaginacion` (nuevo) | `Movimiento` / `string` | cursor base64url de `ocurrido_en UTC\|id` / `[fecha, id]` o `null` | Formato estable (REF-03); defecto DEF-07-01 |
| `ProyeccionMovimientos` (nuevo) | `list<Movimiento>` / `Movimiento` | filas `resumen + corregido_por` / `completo + corregido_por` | Una sola lectura de correcciones por página |
| `CadenaSellada` | `Movimiento` / lectura a auditar | movimiento sellado y guardado | Bloquea la cadena antes de leer el último sello |

### 1.3 Dominio

| Componente | Entrada | Salida | Nota |
|---|---|---|---|
| `Alcance` (nuevo) | tenant (`*` o hospital) | `hospital` (`null` = red), `esRed()`, `tenant()`, `etiqueta()`, `incluye(Movimiento)` | Único dueño de la decisión de tenant en las lecturas |
| `CatalogoFiltros` (nuevo) | parámetros sin el cursor | filtros activos, o `ConsultaInvalida(codigo, campos)` | Orden: no soportado → inválido; el rango es obligatorio |
| `FiltroMovimiento` (5) | parámetros | `validar` → errores; `coincide(Movimiento)`; `sql()` → fragmento + parámetros | OCP de la semana 2 |
| `ReglaMovimiento` (9) | `SolicitudRegistro` | `?Rechazo` | OCP de la semana 2; `ReglaCorreccion` en la semana 6 |
| `Movimiento` · `Sello` | datos ya validados | movimiento inmutable; `sha256(anterior ‖ secuencia ‖ contenido)` | Ningún método lo modifica |
| `ConsultaMovimientos` | `Alcance` + filtros + límite + cursor (`en()`) | consulta para la `Bitacora` | El alcance no es un filtro intercambiable |
| «puerto» `Bitacora` | — | `agregar`, `bloquearCadena`, `porEvento`, `porId`, `ultimo`, `buscar`, `cadena`, `correccionDe`, `correccionesDe` | **Sin** actualizar ni borrar (ARQ-05) |

### 1.4 Persistencia

| Componente | Cumple | Garantía |
|---|---|---|
| `PdoBitacora` · `PdoTransaccion` | `Bitacora` · `Transaccion` | Sentencias preparadas; candado `pg_advisory_xact_lock`; el trigger rechaza `UPDATE`/`DELETE`/`TRUNCATE` |
| `BitacoraEnMemoria` · `TransaccionEnMemoria` | lo mismo | Pasan la **misma** suite de contrato (CON-MEM/PDO-01..09) |

## 2. Frontend (Vue 3 + Pinia + axios, diseño)

Ubicación propuesta: `resources/js/modules/auditoria/`. Usa el `api` de `@/plugins/axios`, que ya agrega `Authorization: Bearer` y `X-Tenant-ID`.

### 2.1 Acceso a datos — `api/auditoriaApi.js`

| Función | Llama a | Devuelve | Errores |
|---|---|---|---|
| `consultar(parametros)` | `GET /auditoria/movimientos` | `{movimientos, cursorSiguiente}` | Normaliza a `{tipo, codigo, campos, requestId}`. Tipos: `validacion` (422/400), `permiso` (403), `sesion` (401), `noDisponible` (503), `red` (sin respuesta) |
| `detalle(id)` | `GET /auditoria/movimientos/{id}` | `movimiento` | Igual; el 404 se presenta como «no existe o no es de su hospital» |
| `verificar(hospital, ancla?)` | `POST /auditoria/verificaciones` | `{estado, …}` | Igual |
| `ancla(hospital?)` | `GET /auditoria/anclas` | `{secuencia, sello}` | Igual |

**No existe** `registrar` en el frontend: los movimientos los escriben los módulos emisores, servidor a servidor. **Tampoco existen** editar ni borrar.

### 2.2 Dominio del cliente — `domain/filtrosBitacora.js`

| Función | Entrada | Salida | Nota |
|---|---|---|---|
| `validarRango(desde, hasta)` | fechas `AAAA-MM-DD` | `{desde?: mensaje}` | Mismo mensaje que `FiltroRangoFechas`. Solo es ayuda para la UX: el servidor decide |
| `aParametros(filtros)` | objeto del formulario | parámetros sin vacíos y solo con los 6 nombres conocidos | Evita `FILTRO_NO_SOPORTADO` |

### 2.3 Aplicación del cliente — Pinia y router

| Componente | Estado / entrada | Acciones / salida | Regla |
|---|---|---|---|
| `useBitacoraStore` | `filtros`, `alcance` (`hospital` \| `red`), `movimientos`, `cursorSiguiente`, `estado` (`inicial` · `cargando` · `vacio` · `listo` · `error`), `error` | `buscar()` reinicia las páginas; `siguiente()` agrega la página del cursor; `verDetalle(id)`; `limpiar()` al cambiar de tenant | Con alcance de red envía `X-Tenant-ID: *`; nunca guarda movimientos en `localStorage` (datos sensibles) |
| `useVerificacionStore` | `hospital`, `ancla?` | `verificar()` → `estado` | Solo con `auditoria.verificar` |
| `permisoGuard` | `meta.permiso` de la ruta | permite o redirige | Es UX; el servidor vuelve a exigir el permiso |

### 2.4 UI

| Componente | Props | Emite | Reglas de interacción |
|---|---|---|---|
| `BitacoraPage` | — | — | Ruta `/auditoria/movimientos`, `meta.permiso = 'auditoria.consultar'` |
| `FiltrosBitacora` | `modelValue` (filtros), `errores` | `update:modelValue`, `buscar` | El rango es obligatorio; el botón Buscar queda deshabilitado sin rango; errores bajo cada campo |
| `SelectorAlcance` | `puedeRed: boolean` | `update:alcance` | Solo se muestra con `consultar_red` |
| `TablaMovimientos` | `movimientos` | `ver(id)` | Sin acciones de editar ni borrar; insignias «corrige a #n» y «corregido por #n» |
| `PaginadorCursor` | `hayMas`, `cargando` | `siguiente` | «Cargar más»: el cursor no permite saltar a una página |
| `DetalleMovimiento` | `movimiento` | `cerrar`, `ver(id)` | Muestra secuencia, sello, motivo de corrección y enlaces al original y a su corrección |
| `EstadoConsulta` | `estado`, `error` | `reintentar` | Vacío con ayuda; error recuperable con el `request_id` para soporte |
| `VerificacionPage` | — | — | `meta.permiso = 'auditoria.verificar'`; INTEGRA, ALTERADA y TRUNCADA en lenguaje claro |

## 3. Reglas que cruzan ambos lados

| Regla | Backend | Frontend |
|---|---|---|
| R-02 inmutable | La API no tiene `PUT`/`DELETE` (405) | No hay botones de editar ni borrar |
| R-05 tenant | `Alcance` + `AutorizacionLectura`; un 404 es igual para otro hospital y para un id inexistente | `X-Tenant-ID` lo pone el interceptor; al cambiar de tenant se limpia el store |
| R-06 rango obligatorio | `CatalogoFiltros` (`DATOS_INVALIDOS`) | `validarRango` antes de enviar |
| R-08 meta-auditoría | Cada consulta y cada detalle quedan registrados | Aviso en la página: «Su consulta queda registrada» |
| R-09 paginación | 50 por página con cursor opaco | «Cargar más», sin números de página |
