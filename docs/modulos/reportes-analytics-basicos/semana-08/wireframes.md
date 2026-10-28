# Wireframes anotados · Reportes y analytics básicos

Seis wireframes de baja fidelidad hechos en PlantUML (Salt), en `wireframes/`. Los números del período anterior son ilustrativos; los de octubre son los datos ficticios del Micro-HIS.

## W1 · Inicio y filtros (`w1-filtros`)

| Elemento | Regla de interacción / validación | Mensaje |
|---|---|---|
| Selector de reporte | Solo muestra los reportes permitidos al rol (RN-01) | Ayuda «i» con la fórmula en lenguaje simple |
| Desde / Hasta | Obligatorios; se valida al salir del campo y al calcular (RN-02) | «La fecha inicial no puede ser mayor que la final» |
| Dimensión | Solo las que declara el reporte | — |
| Granularidad | Día, semana o mes (RN-03) | — |
| Calcular | Deshabilitado con errores; muestra el resumen antes de confirmar | «Ocupación · 01/10/2026 a 31/10/2026» |

## W2 · Estados de la consulta (`w2-carga-error`)

| Estado | Cuándo | Regla |
|---|---|---|
| Carga | Mientras llega la respuesta | Texto anunciado con `aria-live`; aviso extra si pasa de 2 s (RNF-01) |
| Vacío | `estado = sin_datos` | Nunca se muestra 0 (RN-06); ofrece cambiar el período |
| Error recuperable | 503 | Conserva los filtros; «Reintentar» repite la misma consulta; muestra el código de seguimiento (`correlationId`) |
| Sin permiso | 403 | Sin reintento; no revela si el reporte tiene datos |

## W3 · Indicador con ficha (`w3-indicador`)

| Elemento | Regla |
|---|---|
| Valor | Con unidad y la precisión que declara el indicador (1 decimal para %) |
| Comparación | Diferencia en puntos contra el período anterior del mismo largo |
| Ficha de trazabilidad | Siempre visible: fórmula, período, filtros, fuente y fecha (RN-05) |
| «¿De dónde sale?» | Lleva al desglose con los mismos filtros (RN-08) |

## W4 · Desglose (`w4-desglose`)

| Elemento | Regla |
|---|---|
| Tabla | Conteos enteros por parte; el porcentaje se calcula al mostrar desde los conteos |
| Fila total | Marca de verificación si la suma es igual al numerador (RN-07); si no, «Revisar fuente» |
| «Otro» | Agrupa las partes con menos de 3 casos (RN-04) |
| Ver registros | Abre el nivel 3 de esa parte |

## W5 · Registros de origen (`w5-registros`)

| Elemento | Regla |
|---|---|
| Columnas | Cama, código de paciente, ingreso y egreso: datos mínimos (RN-09) |
| Paginación | 10 por página; el total del encabezado coincide con la parte del desglose |
| Código de paciente | Texto simple, sin enlace al expediente |

## W6 · Vista del Médico (`w6-medico`)

| Elemento | Regla |
|---|---|
| Médico | Fijo en el usuario, con candado; no se puede cambiar (RN-08) |
| Reportes | Solo Citas; otro reporte por URL responde 403 |
| Resultado | Citas, asistencia y desglose por estado; registros solo de sus citas |
