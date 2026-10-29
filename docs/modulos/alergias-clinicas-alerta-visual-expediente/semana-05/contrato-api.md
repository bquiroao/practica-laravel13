# Contrato API v1 — Alergias clínicas y alerta visual en expediente

Implementado en `micro-his/src/Presentacion/Api/KernelApi.php` y probado en `pruebas/semana05.php` (T-35 a T-44). Es JSON en UTF-8 y usa los **mismos casos de uso** que la consola y el MVC.

## Encabezados

| Encabezado | Dirección | Obligatorio | Regla |
|---|---|---|---|
| `Authorization: Bearer <token>` | petición | sí | Sin token válido → 401 `NO_AUTENTICADO`. Los tokens del Micro-HIS son **ficticios** (`tok-med-h01`, `tok-enf-h01`, `tok-adm-h01`, `tok-med-h02`) |
| `X-Tenant-ID` | petición | no | Si viene, debe coincidir con el hospital **del token**. El tenant nunca se toma del encabezado |
| `X-Request-Id` | ambas | no | Si llega uno válido (8–64 caracteres `[A-Za-z0-9-]`) se devuelve el mismo; si no, se genera |
| `Content-Type: application/json` | ambas | en POST | — |

## Operaciones

| Método | Ruta | Permiso (rol) | Caso de uso | Éxito |
|---|---|---|---|---|
| GET | `/api/v1/pacientes/{codigo}/alerta` | `alerta.consultar` (médico, enfermería) | CU-03 + CU-04 | 200 `Alerta` |
| POST | `/api/v1/pacientes/{codigo}/alergias` | `alergias.registrar` (médico) | CU-01 + CU-02 | 201 `Alerta` + `Location: /api/v1/pacientes/{codigo}/alerta` |
| POST | `/api/v1/pacientes/{codigo}/alerta/reconocimientos` | `alerta.reconocer` (médico, enfermería) | CU-05 | 201 `Alerta` con `reconocida: true` |

`{codigo}` tiene el formato `PAC-NNNNN`. Otro formato → 404 `RUTA_NO_ENCONTRADA`.

### Cuerpo de `POST …/alergias`

```json
{
  "alergeno_nombre": "Penicilina",
  "alergeno_codigo": null,
  "tipo": "allergy",
  "severidad": "life_threatening",
  "reaccion": "Edema de glotis",
  "fecha_inicio": "2019-03-02"
}
```

Las reglas son las del Dominio: RN-01, RN-02, RN-04, RN-05 y RN-06. Si se omite, `tipo` vale `allergy`.

### Cuerpo de `POST …/alerta/reconocimientos`

```json
{ "huella": "d470db1a82030bde" }
```

La huella es la que devolvió la última consulta. Si cambió, la respuesta es 409 `ALERTA_CAMBIO`.

### Respuesta `Alerta`

```json
{
  "paciente": "PAC-00451",
  "nivel": "CRITICA",
  "huella": "d470db1a82030bde",
  "requiere_reconocimiento": true,
  "reconocida": false,
  "alergias": [
    {"alergeno": "Penicilina", "tipo": "allergy", "severidad": "life_threatening", "nivel": "CRITICA", "reaccion": "Edema de glotis", "fecha_inicio": "2019-03-02"},
    {"alergeno": "Ibuprofeno", "tipo": "allergy", "severidad": "moderate", "nivel": "MODERADA", "reaccion": "Urticaria", "fecha_inicio": "2021-07-15"}
  ]
}
```

`nivel` ∈ `SIN_REGISTRO | INFORMATIVA | MODERADA | ALTA | CRITICA`. Una lista vacía con `SIN_REGISTRO` **no** significa «sin alergias»: significa que no hay registros.

## Errores (formato único)

```json
{ "error": { "codigo": "ALERGIA_DUPLICADA", "mensaje": "El paciente ya tiene esta alergia activa.", "request_id": "be41a50e49f48af1" } }
```

| Estado | Código | Cuándo |
|---|---|---|
| 400 | `FORMATO_INVALIDO` | JSON mal formado, no es objeto o falta `huella` |
| 401 | `NO_AUTENTICADO` | Falta el token o no es válido |
| 403 | `TENANT_DISTINTO` | El hospital del token no es este, o `X-Tenant-ID` no coincide |
| 403 | `PERMISO_DENEGADO` | El rol no tiene el permiso de la operación |
| 404 | `PACIENTE_NO_ENCONTRADO` / `RUTA_NO_ENCONTRADA` | — |
| 409 | `ALERGIA_DUPLICADA` / `ALERTA_CAMBIO` | RN-03 / RN-09 |
| 422 | `ALERGENO_REQUERIDO`, `TIPO_INVALIDO`, `SEVERIDAD_INVALIDA`, `REACCION_EN_BLANCO`, `REACCION_DEMASIADO_LARGA`, `FECHA_INVALIDA`, `FECHA_INICIO_FUTURA`, `PACIENTE_INACTIVO`, `RECONOCIMIENTO_NO_REQUERIDO` | Reglas de dominio |
| 503 | `PERSISTENCIA_NO_DISPONIBLE` | La base no respondió; no se guardó nada |

## Permisos por rol (propuesta; en el HIS los asigna RBAC)

| Rol | consultar | registrar | reconocer |
|---|---|---|---|
| médico | sí | sí | sí |
| enfermería | sí | no (403) | sí |
| admisión | no (403) | no (403) | no (403) |

## Probar desde PowerShell 5

```powershell
php -S 127.0.0.1:8042 -t public
# en otra terminal:
$h = @{ Authorization = "Bearer tok-enf-h01" }
Invoke-RestMethod http://127.0.0.1:8042/api/v1/pacientes/PAC-00451/alerta -Headers $h
try {
  Invoke-RestMethod http://127.0.0.1:8042/api/v1/pacientes/PAC-00451/alergias -Method Post -Headers $h -ContentType "application/json" -Body '{"alergeno_nombre":"Latex","severidad":"severe"}'
} catch { $_.Exception.Message; $_.ErrorDetails.Message }   # 403 PERMISO_DENEGADO
```
