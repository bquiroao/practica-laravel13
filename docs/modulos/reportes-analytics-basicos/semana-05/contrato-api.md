# Contrato API v1 · Reportes y analytics básicos

Contrato del servidor del diseño cliente-servidor (semana 5). El prototipo lo implementa en `../micro-his/` y el archivo `openapi.yaml` lo describe en formato OpenAPI 3.1. En el SHI se implementa en Laravel con las mismas rutas.

## Reglas generales

| Tema | Regla |
|---|---|
| Base | `/api/v1/reportes` · solo `GET` (el módulo es de solo lectura, RNF-04) |
| Formato | JSON UTF-8; fechas `AAAA-MM-DD`; porcentajes de 0 a 100 con un decimal |
| Versionado | La versión va en la ruta (`v1`). Un cambio incompatible crea `v2`; `v1` se mantiene mientras haya clientes |
| Autenticación | SHI: `Authorization: Bearer <JWT>` (tymon/jwt-auth) con rol y tenant en los *claims*. Prototipo: cabecera `X-Rol` |
| Trazas | El cliente puede enviar `X-Correlation-Id`; el servidor lo devuelve siempre (o genera uno) y lo escribe en su log |
| Tenant | El hospital sale del token, nunca de un parámetro: un usuario no puede pedir datos de otro hospital |

## Endpoints

| Método | Ruta | Nivel | Permiso (rol) | Descripción |
|---|---|---|---|---|
| GET | `/api/v1/reportes/{tipo}` | 1 | Administrador y Jefe de área: los 10 reportes; Médico: solo `citas` (sus citas) | Valor del indicador y ficha de trazabilidad |
| GET | `/api/v1/reportes/{tipo}/desglose` | 2 | Igual que el nivel 1 (RN-08) | Valor repartido por una dimensión; la suma coincide con el numerador |
| GET | `/api/v1/reportes/{tipo}/registros` | 3 | Igual que el nivel 1 (RN-08) | Registros de origen de una parte del desglose, con datos mínimos |

`{tipo}`: `pacientes`, `citas`, `ocupacion`, `laboratorio`, `medicamentos`, `mortalidad`, `diagnosticos`, `medicos`, `alergias`, `signos_vitales`. El prototipo implementa `ocupacion`.

### Parámetros

| Parámetro | Dónde | Obligatorio | Regla |
|---|---|---|---|
| `desde`, `hasta` | consulta | Sí | Fechas válidas y `desde <= hasta` (RN-02); máximo 366 días |
| `dimension` | consulta | No (usa la primera que declara el reporte) | Debe estar en las dimensiones del reporte |
| `parte` | consulta | Sí en `/registros` | Una parte que aparece en el desglose |
| `granularidad` | consulta | No | `dia`, `semana` o `mes` para la tendencia (RN-03); fuera del prototipo |
| `pagina`, `limite` | consulta | No | Solo `/registros`; `limite` máximo 100 (propuesto, fuera del prototipo) |

## Respuestas

**200 · éxito (`/desglose`)**

```json
{
  "estado": "exito",
  "datos": {
    "indicador": { "nombre": "Ocupación de camas", "valor": 78.0, "unidad": "%", "numerador": 39 },
    "trazabilidad": {
      "formula": "camas ocupadas / camas habilitadas × 100",
      "periodo": "2026-10-01 a 2026-10-31",
      "filtros": "ninguno adicional",
      "fuente": "ward, bed, bed_stay",
      "calculado_en": "2026-10-19 10:30",
      "detalle": "39 de 50 camas habilitadas"
    },
    "desglose": { "dimension": "sala", "partes": { "Medicina": 18, "Cirugía": 14, "Pediatría": 7 }, "total": 39 }
  }
}
```

`/registros` agrega `"registros": [{ "cama": "MED-01", "paciente": "PAC-00465", "ingreso": "2026-10-16", "egreso": null }, …]`, con tantos elementos como el valor de la parte (RN-07).

**200 · sin datos (RN-06):** `{ "estado": "sin_datos", "mensaje": "Sin datos de ocupación del 2025-01-01 a 2025-01-31 (RN-06)", "datos": null }`. Nunca se responde un cero inventado.

## Errores

Todos los errores tienen la misma forma: `{ "error": { "codigo": "…", "mensaje": "…", "correlationId": "…" } }`.

| HTTP | `codigo` | Cuándo | Qué hace el cliente |
|---|---|---|---|
| 401 | `NO_AUTENTICADO` | Token ausente o vencido (SHI) | Pide iniciar sesión |
| 403 | `SIN_PERMISO` | El rol no puede ver ese reporte o esos registros (RN-01, RN-08) | Muestra el mensaje; no reintenta |
| 404 | `NO_EXISTE` | `{tipo}` desconocido | Corrige la selección |
| 422 | `VALIDACION` | Período inválido, dimensión no declarada o falta `parte` | Marca el campo y deja corregir |
| 503 | `DATOS_NO_DISPONIBLES` | La base no respondió | Ofrece reintentar; el mensaje no trae detalles técnicos |

## Garantías del servidor

- El mismo `ContratoReporte` de la semana 2 respalda las tres rutas: `desglose.total = indicador.numerador` y `count(registros) = partes[parte]`.
- Los registros identifican al paciente solo con su código (RN-09).
- Un indicador de un mes responde en menos de 2 segundos (RNF-01); el log de cada petición incluye la duración en milisegundos para medirlo.
