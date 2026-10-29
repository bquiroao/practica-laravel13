# Contrato API v1 — Admisión hospitalaria y asignación de cama

Implementado en `micro-his/src/Presentation/Api/ApiKernel.php` y probado en `micro-his/pruebas/unitarias/prueba-api.php`. La API usa los **mismos casos de uso** que la consola y la web MVC.

## 1. Reglas generales

| Tema | Regla |
|---|---|
| Base y versión | `/api/v1`. Un cambio incompatible crea `/api/v2`; v1 se mantiene mientras haya clientes. |
| Formato | JSON UTF-8 (`Content-Type: application/json; charset=utf-8`). Fechas en ISO 8601 UTC. |
| Autenticación | `Authorization: Bearer <token>`. En el Micro-HIS los tokens son **ficticios** (`tok-adm-01`, `tok-sup-01`, `tok-med-01`, `tok-adm-02`). En el HIS los emitiría el proveedor de identidad (propuesta). |
| Hospital (tenant) | Se toma **del token**. Si el cuerpo trae `tenant_id`, la petición se rechaza con 400 (RNF-02). |
| Correlación | `X-Request-Id`: si llega con 8 a 64 caracteres `[A-Za-z0-9._-]` se respeta; si no, se genera `req-<uuid>`. Vuelve siempre en la respuesta y queda en los eventos y en el registro (RNF-05). |
| Datos del paciente | Solo `codigo_paciente`. Ninguna respuesta lleva nombres ni diagnóstico (RNF-03). |
| Transporte | HTTPS obligatorio en el HIS. El Micro-HIS usa HTTP local con datos ficticios. |

## 2. Endpoints

| Método | Ruta | Permiso / rol | Descripción |
|---|---|---|---|
| GET | `/api/v1/camas-disponibles?servicio=&tipo_cama=&requiere_aislamiento=true\|false` | `ADMISIONISTA`, `SUPERVISOR_CAMAS` | CU-03: camas operativas del hospital del token, sin asignación activa y compatibles |
| POST | `/api/v1/admisiones` | `ADMISIONISTA`, `SUPERVISOR_CAMAS` | CU-01: crea la admisión y asigna la cama en una transacción |
| GET | `/api/v1/admisiones/{id}` | `ADMISIONISTA`, `SUPERVISOR_CAMAS`, `MEDICO` | CU-06: consulta una admisión del propio hospital |
| GET | `/api/v1/admisiones/{id}/eventos` | `SUPERVISOR_CAMAS` | RF-08: trazabilidad en orden |

### POST /api/v1/admisiones

Petición:
```json
{
  "codigo_paciente": "PAC-00451",
  "servicio": "MEDICINA_INTERNA",
  "tipo_cama": "GENERAL",
  "requiere_aislamiento": false,
  "origen": "EMERGENCIA",
  "cama_id": "c0000000-0000-4000-8000-000000000201"
}
```
Todos los campos son obligatorios. `requiere_aislamiento` es booleano y los demás son texto. `origen` puede ser `EMERGENCIA`, `CONSULTA_EXTERNA` o `REFERENCIA`.

Respuesta **201** con `Location: /api/v1/admisiones/{id}` y `X-Request-Id`:
```json
{
  "datos": {
    "id": "fd48d870-…",
    "codigo_paciente": "PAC-00451",
    "estado": "ACTIVA",
    "cama": { "id": "c0000000-0000-4000-8000-000000000201", "codigo": "B-201" },
    "servicio": "MEDICINA_INTERNA",
    "origen": "EMERGENCIA",
    "creada_por": "usr-adm-01",
    "creada_en": "2026-03-02T08:15:00+00:00"
  }
}
```
`GET /api/v1/admisiones/{id}` devuelve el mismo objeto `datos` con 200.

### GET /api/v1/camas-disponibles

Respuesta 200:
```json
{ "datos": [ { "id": "c0000000-…-000000000201", "codigo": "B-201", "servicio": "MEDICINA_INTERNA", "tipo_cama": "GENERAL", "aislamiento": false } ], "total": 1 }
```
Una lista vacía (`"datos": [], "total": 0`) es el estado vacío (E-05), no un error.

### GET /api/v1/admisiones/{id}/eventos

Respuesta 200:
```json
{ "datos": [ { "tipo": "ADMISION_CREADA", "usuario": "usr-adm-01", "rol": "ADMISIONISTA", "solicitud_id": "req-…", "ocurrido_en": "…" },
             { "tipo": "CAMA_ASIGNADA", "usuario": "usr-adm-01", "rol": "ADMISIONISTA", "solicitud_id": "req-…", "ocurrido_en": "…" } ] }
```

## 3. Formato único de errores

Todas las respuestas de error tienen esta forma:
```json
{ "error": { "codigo": "CAMA_YA_ASIGNADA", "mensaje": "La cama ya fue asignada a otra admisión. Elija otra cama.",
             "reintentable": false, "request_id": "req-…", "detalles": { } } }
```
`detalles` solo aparece cuando aporta algo: `campos` en 400 y `motivos` en `CAMA_NO_ELEGIBLE`. Un error 500 nunca incluye detalles técnicos.

| Estado | Código | Cuándo | `reintentable` |
|---|---|---|---|
| 400 | `SOLICITUD_INVALIDA` | JSON mal formado, campos que faltan o con tipo incorrecto, `tenant_id` en el cuerpo, `requiere_aislamiento` distinto de true/false | no |
| 401 | `NO_AUTENTICADO` | Falta el token o no es válido (con `WWW-Authenticate: Bearer`) | no |
| 403 | `ACCESO_DENEGADO` | El rol no permite la operación (RN-06) | no |
| 404 | `PACIENTE_NO_ENCONTRADO` | El código no existe o el paciente está inactivo en el hospital | no |
| 404 | `CAMA_NO_ENCONTRADA` | La cama no existe **en el hospital del token** (RN-04) | no |
| 404 | `ADMISION_NO_ENCONTRADA` | La admisión no existe en el hospital del token | no |
| 404 / 405 | `RECURSO_NO_ENCONTRADO` / `METODO_NO_PERMITIDO` | Ruta o método no definidos en v1 | no |
| 409 | `PACIENTE_CON_ADMISION_ACTIVA` | RN-02 | no |
| 409 | `CAMA_YA_ASIGNADA` | RN-01: otra admisión ya tiene la cama. El cliente vuelve a consultar camas (CU-05). | no |
| 409 | `CONFLICTO_CONCURRENCIA` | Otra transacción tiene la cama bloqueada (más de 2 s) | **sí** |
| 422 | `VALIDACION` | El formato de un dato no es válido (`PAC-NNNNN`, servicio, tipo, origen) | no |
| 422 | `CAMA_NO_ELEGIBLE` | RN-03 / RN-04: la cama no es compatible o no está operativa; incluye `motivos` | no |
| 503 | `ERROR_PERSISTENCIA` | Falla de la base; no se guardó nada (RNF-08) | **sí** |
| 500 | `ERROR_INTERNO` | Error inesperado; se informa el `request_id` | no |

400 frente a 422: el 400 dice que la **forma** de la petición está mal; el 422 dice que la forma es correcta pero un **dato** no cumple una regla del dominio.

## 4. Reintentos e idempotencia
- `POST /admisiones` no se reintenta a ciegas. Ante 503 o un corte de red, el cliente consulta antes de reintentar: si la admisión se creó, RN-02 hace que el segundo intento devuelva 409 `PACIENTE_CON_ADMISION_ACTIVA` y no duplica nada.
- Ante `CONFLICTO_CONCURRENCIA` se puede reintentar una vez tras 1 a 2 s. Ante `CAMA_YA_ASIGNADA` se vuelve a consultar la lista.
- **Propuesta para una versión futura:** cabecera `Idempotency-Key`. No está implementada.

## 5. Probarlo con PowerShell 5

```powershell
$h = @{ Authorization = "Bearer tok-adm-01"; "X-Request-Id" = "prueba-manual-0001" }
Invoke-RestMethod -Uri "http://127.0.0.1:8085/api/v1/camas-disponibles?servicio=MEDICINA_INTERNA&tipo_cama=GENERAL&requiere_aislamiento=false" -Headers $h
$cuerpo = '{"codigo_paciente":"PAC-00451","servicio":"MEDICINA_INTERNA","tipo_cama":"GENERAL","requiere_aislamiento":false,"origen":"EMERGENCIA","cama_id":"c0000000-0000-4000-8000-000000000201"}'
try { Invoke-RestMethod -Method Post -Uri "http://127.0.0.1:8085/api/v1/admisiones" -Headers $h -ContentType "application/json" -Body $cuerpo }
catch { $_.Exception.Message; $_.ErrorDetails.Message }
```
El `try/catch` hace falta porque PowerShell 5 no muestra el cuerpo de un error HTTP sin `$_.ErrorDetails.Message`.
