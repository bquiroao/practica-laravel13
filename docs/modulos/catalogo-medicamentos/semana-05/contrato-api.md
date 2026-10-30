# Contrato API v1 — Catálogo de medicamentos

Base: `/api/v1` · JSON UTF-8 · Implementación: `micro-his/src/Presentation/Api/`.
Las fechas son `AAAA-MM-DD`; las vigencias son `[vigente_desde, vigente_hasta)`.

## 1. Encabezados

| Encabezado | Dirección | Uso |
|---|---|---|
| `Authorization: Bearer <token>` | petición | Obligatorio. El token define **usuario, rol y hospital** (tenant). |
| `Content-Type: application/json` | petición | Obligatorio en `POST` con cuerpo; si no, 415. |
| `X-Request-Id` | ambos | Si el cliente lo envía (8–64 caracteres `A-Z a-z 0-9 -`), se reutiliza; si no, se genera. Siempre vuelve en la respuesta. |
| `Location` | respuesta | En `201`, ruta del recurso creado. |
| `Retry-After: 5` | respuesta | En `503`: el cliente puede reintentar después de 5 s. |
| `Allow` | respuesta | En `405`: métodos aceptados por esa ruta. |

## 2. Endpoints y permisos

| Método | Ruta | Permiso / rol | Descripción | Éxito |
|---|---|---|---|---|
| GET | `/medicamentos?q=&forma=&fecha=` | QF_CATALOGO, CONSULTA_CLINICA, AUDITOR_CALIDAD, SISTEMA_PRESCRIPCIONES | CU-02: vigentes a `fecha` (hoy si falta). `q` 2–60 caracteres o `forma`. Máx. 50. | 200 |
| GET | `/medicamentos/{codigo}?fecha=` | los cuatro roles | RF-09: versión vigente del código a esa fecha. | 200 |
| GET | `/medicamentos/{codigo}/versiones` | QF_CATALOGO, AUDITOR_CALIDAD | CU-05: historial completo. | 200 |
| POST | `/medicamentos` | QF_CATALOGO | CU-01: alta (versión 1). | 201 + `Location` |
| POST | `/medicamentos/{codigo}/versiones` | QF_CATALOGO | CU-03: nueva versión; cierra la anterior. | 201 + `Location` |
| POST | `/medicamentos/{codigo}/baja` | QF_CATALOGO | CU-04: cierra la vigencia (no borra). | 200 |
| DELETE | `/medicamentos/{codigo}` | QF_CATALOGO | CU-06: elimina solo si nunca se usó en prescripciones. | 204 |
| GET | `/opciones` | QF_CATALOGO, CONSULTA_CLINICA, AUDITOR_CALIDAD | Listas cerradas de formas, vías y unidades. | 200 |

`SISTEMA_PRESCRIPCIONES` es el rol propuesto para que `prescripciones-alergias` lea el catálogo
(**pendiente de acordar** con su dueño). Los tokens del Micro-HIS (`tok-qf-norte-0001`, etc.) son
**ficticios** y están en `config.php`.

## 3. Cuerpos

### Alta y nueva versión (`POST /medicamentos`, `POST /medicamentos/{codigo}/versiones`)

```json
{
  "nombre": "Loratadina 10 mg tableta",
  "principio_activo": "Loratadina",
  "concentracion_valor": "10",
  "concentracion_unidad": "mg",
  "forma_farmaceutica": "TABLETA",
  "via_administracion": "ORAL",
  "presentacion": "Caja con 10 tabletas",
  "registro_sanitario": "RS-FIC-0107",
  "vigente_desde": "2026-10-15"
}
```

Límites en `../semana-02/README.md`, sección 3. `registro_sanitario` y `vigente_desde` son opcionales
(sin `vigente_desde` se usa hoy). Si llegan `hospital_id` o `usuario`, **se ignoran**: salen del token.
En una nueva versión se envía la ficha completa y `vigente_desde` debe ser posterior al inicio de
la versión vigente.

### Baja (`POST /medicamentos/{codigo}/baja`)

```json
{ "fecha_baja": "2026-11-01", "motivo": "Retiro del formulario (dato ficticio)" }
```

## 4. Respuestas

Éxito (una versión):

```json
{ "datos": { "codigo": "MED-000107", "version": 1, "nombre": "Loratadina 10 mg tableta",
  "principio_activo": "Loratadina", "concentracion_valor": "10", "concentracion_unidad": "mg",
  "forma_farmaceutica": "TABLETA", "via_administracion": "ORAL", "presentacion": "Caja con 10 tabletas",
  "registro_sanitario": "RS-FIC-0107", "vigente_desde": "2026-10-15", "vigente_hasta": null, "motivo_cierre": null } }
```

Búsqueda: `{ "datos": [ … ], "meta": { "total": 2, "fecha": "2026-10-15", "limite": 50 } }`.

Error (formato único):

```json
{ "error": { "codigo": "DUPLICADO", "mensaje": "Ya existe MED-000102 con el mismo principio activo, …",
  "detalles": { "codigo_existente": "MED-000102" }, "request_id": "evidencia-0005" } }
```

## 5. Códigos de error

| HTTP | `codigo` | Cuándo |
|---|---|---|
| 400 | `CUERPO_INVALIDO` | JSON mal formado o que no es un objeto. |
| 401 | `NO_AUTENTICADO` | Falta el token o no existe. |
| 403 | `PROHIBIDO` | El rol no tiene ese permiso. |
| 404 | `NO_ENCONTRADO` | El código no existe **en el hospital del token** (no se revela si existe en otro). |
| 404 | `RUTA_NO_ENCONTRADA` | La ruta no existe. |
| 405 | `METODO_NO_PERMITIDO` | Método no aceptado (con `Allow`). |
| 409 | `DUPLICADO` | RN-04: equivalente vigente; `detalles.codigo_existente`. |
| 409 | `SOLAPE_VIGENCIA` | RN-01: la nueva versión no empieza después de la vigente. |
| 409 | `YA_DADO_DE_BAJA` | El código ya no tiene versión abierta. |
| 409 | `EN_USO` | RN-02: usado en prescripciones; `detalles.accion_sugerida = "baja"`. |
| 409 | `CONFLICTO_CONCURRENTE` | Otra persona cambió el mismo código a la vez; volver a cargar. |
| 409 | `INTEGRIDAD` | La base rechazó el cambio por una restricción (no debería ocurrir si el dominio valida). |
| 415 | `TIPO_NO_SOPORTADO` | El cuerpo no es `application/json`. |
| 422 | `VALIDACION` | RN-05: `detalles` = campo → mensaje (todos los errores a la vez). |
| 500 | `INTERNO` | Error inesperado; el detalle queda solo en el registro del servidor. |
| 503 | `PERSISTENCIA` | La base no responde; `Retry-After: 5`; sin SQL ni trazas. |

## 6. Idempotencia y reintentos

- `GET` es seguro e idempotente: el cliente puede reintentar con espera creciente.
- `POST` **no** es idempotente: si no llega respuesta, el cliente consulta con `GET` (por nombre o código) antes de repetir. Un alta repetida no crea duplicados: responde 409 `DUPLICADO` por RN-04.
- `DELETE` repetido responde 404 la segunda vez.

## 7. Versionado del contrato

Cambios compatibles (campos nuevos opcionales) se agregan a `v1`. Quitar o renombrar campos exige
`/api/v2` y convivir con `v1` mientras los consumidores migran (semana 5, `frontera-microservicio.md`).
