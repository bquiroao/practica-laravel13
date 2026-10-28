# Contrato API — Catálogo de pruebas de laboratorio (v1)

Base: `/api/v1/laboratorio/pruebas` · Formato: JSON UTF-8 · Autenticación: `Authorization: Bearer <token>`
Implementación de referencia: `micro-his/src/Presentation/Api/ApiLabTestController.php` (pruebas T-13 a T-16).

## 1. Endpoints

| Método | Ruta | Permiso | Roles | Descripción | Respuestas |
|---|---|---|---|---|---|
| GET | `/api/v1/laboratorio/pruebas?texto=&seccion=&tipo_muestra=&fecha=` | `lab.catalogo.consultar` | Jefe de laboratorio, laboratorista, médico, módulo Órdenes | Pruebas vigentes en `fecha` (por defecto hoy) — CU-04 | 200, 401, 403, 422 |
| GET | `/api/v1/laboratorio/pruebas/{codigo}?fecha=` | `lab.catalogo.consultar` | Los mismos | Detalle y requisitos de la versión vigente — CU-05 | 200, 401, 403, 404, 422 |
| POST | `/api/v1/laboratorio/pruebas` | `lab.catalogo.gestionar` | Jefe de laboratorio | Alta de una prueba o de una versión nueva — CU-01 a CU-03 | 201, 400, 401, 403, 409, 422, 500 |

El hospital (tenant) sale del token, nunca del cuerpo ni de la URL; así un usuario no puede pedir el catálogo de otro hospital.

## 2. Cuerpo del POST

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| `codigo` | string | Sí | `AAA-000` en mayúsculas (RN-02) |
| `nombre` | string | Sí | No vacío |
| `seccion` | string | Sí | `hematologia`, `quimica-clinica`, `microbiologia`, `inmunologia`, `uroanalisis`, `coprologia` |
| `tiempo_respuesta_horas` | entero | Sí | Mayor que 0 |
| `tipo_muestra` | string | Sí | `sangre-total`, `suero`, `plasma`, `orina`, `heces`, `hisopado` (RN-03) |
| `contenedor` | string | Sí | No vacío |
| `volumen_minimo_ml` | número | Sí | Mayor que 0 |
| `horas_ayuno` | entero | Sí | 0 o más |
| `instrucciones_preparacion` | string | No | Texto libre |
| `conservacion` | string | Sí | `ambiente`, `refrigerada`, `congelada` |
| `estabilidad_horas` | entero | Sí | Mayor que 0 |
| `vigente_desde` | fecha `AAAA-MM-DD` | Sí | No anterior a hoy (RN-04) |
| `vigente_hasta` | fecha o vacío | No | Igual o posterior a `vigente_desde` |

## 3. Respuesta correcta (201 o 200)

```json
{
  "data": {
    "id": "6f1c2b9e-…",
    "codigo": "HEM-001",
    "version": 1,
    "nombre": "Hematología completa",
    "seccion": "hematologia",
    "tiempo_respuesta_horas": 4,
    "requisitos_muestra": {
      "tipo_muestra": "sangre-total",
      "contenedor": "Tubo tapa lila (EDTA)",
      "volumen_minimo_ml": 3,
      "horas_ayuno": 0,
      "instrucciones_preparacion": "",
      "conservacion": "refrigerada",
      "estabilidad_horas": 24
    },
    "vigencia": { "desde": "2027-01-04", "hasta": null }
  },
  "request_id": "34b63fa5a8935235"
}
```

En el GET de lista, `data` es un arreglo con objetos de la misma forma (vacío si no hay vigentes).

## 4. Errores

Todos los errores usan la misma forma: `{"error": {"codigo", "mensaje", …detalle}, "request_id"}`.

| Estado | `codigo` | Cuándo | Detalle extra |
|---|---|---|---|
| 400 | `JSON_INVALIDO` | El cuerpo no es un objeto JSON | — |
| 401 | `NO_AUTENTICADO` | Falta el token o no es válido | — |
| 403 | `SIN_PERMISO` | El rol no tiene el permiso (RN-01) | — |
| 404 | `PRUEBA_NO_VIGENTE` | El código no existe o no está vigente en la fecha (RN-06) | `vigencia: {desde, hasta}` si existe |
| 404 | `RUTA_NO_ENCONTRADA` | Ruta inexistente | — |
| 409 | `VIGENCIA_SUPERPUESTA` | La versión nueva no empieza después de la vigente (RN-05) | — |
| 422 | `DATOS_INVALIDOS` | Uno o más campos no cumplen RN-02 a RN-04 | `campos: {campo: mensaje}` |
| 500 | `ERROR_PERSISTENCIA` | Falla de la base; no se guardó nada (RN-07) | — |

## 5. Ejemplos con PowerShell (Micro-HIS en el puerto 8081)

```powershell
$h = @{ Authorization = "Bearer tok-jefe" }
$cuerpo = Get-Content datos/hem-001.json -Raw -Encoding UTF8
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8081/api/v1/laboratorio/pruebas -Headers $h -Body $cuerpo -ContentType "application/json; charset=utf-8"
Invoke-RestMethod -Uri "http://127.0.0.1:8081/api/v1/laboratorio/pruebas?fecha=2027-01-15" -Headers @{ Authorization = "Bearer tok-medico" }
```

En el Micro-HIS los tokens son ficticios (`tok-jefe`, `tok-laboratorista`, `tok-medico`, `tok-ordenes`, todos del hospital `HOSP-01`). En el SHI se reemplazan por el JWT y los permisos del módulo RBAC.

## 6. Versionado del contrato

La versión va en la ruta (`/v1`). Agregar campos opcionales no cambia la versión; quitar o renombrar campos, o cambiar su significado, exige `/v2` y mantener `/v1` mientras Órdenes migra.
