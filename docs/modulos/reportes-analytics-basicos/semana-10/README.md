# Reportes y analytics básicos — Semana 10

**Tema:** diseño para movilidad.
**Tarea individual:** adaptar «cálculo y visualización trazable de un indicador hospitalario» a móvil (320–430 px): jerarquizar datos y acciones, reducir la carga cognitiva y definir navegación, tablas/formularios, confirmaciones y recuperación ante conexión limitada.
**Entregable:** propuesta responsive de 3–5 pantallas, reglas de breakpoint y dos escenarios móviles con decisiones de contenido y error.

## 1. Jerarquía en móvil

En una pantalla de 320–430 px cabe una idea a la vez. El orden de importancia es:

| Prioridad | Contenido | Decisión |
|---|---|---|
| 1 | Valor del indicador con su unidad | Primero y grande; arriba del pliegue |
| 2 | Período y comparación | Una línea debajo del valor |
| 3 | «¿De dónde sale?» | Botón de ancho completo, al alcance del pulgar |
| 4 | Ficha de trazabilidad | Plegada; se abre con un toque (sigue siempre disponible, RN-05) |
| 5 | Filtros | Resumidos en una barra («Ocupación · Oct 2026 · Cambiar») que abre una hoja inferior |

## 2. Reglas de breakpoint

| Ancho | Diseño | Tablas | Navegación |
|---|---|---|---|
| 320–430 px (móvil) | Una columna; tarjetas apiladas | El desglose se muestra como **lista de tarjetas** (parte, conteo, %); los registros como tarjetas de 3 líneas | Barra superior con «Atrás» y migas abreviadas «Indicador › Desglose» |
| 431–767 px (móvil grande / tableta vertical) | Una columna más ancha | Tabla de 3 columnas sin la columna de porcentaje | Igual que móvil |
| 768–1023 px (tableta) | Dos columnas: filtros a la izquierda | Tabla completa | Migas completas |
| ≥ 1024 px (escritorio) | Diseño de la semana 8 | Tabla completa con paginación | Migas completas |

Reglas comunes: áreas táctiles de al menos 44 × 44 px, texto base de 16 px, sin desplazamiento horizontal salvo dentro de una tabla, y la precisión del indicador es la misma en todos los tamaños.

## 3. Formularios, confirmaciones y carga cognitiva

- **Filtros en hoja inferior:** períodos rápidos («Este mes», «Mes anterior», «Últimos 7 días») antes que el selector de fechas; menos escritura y menos errores (RN-02).
- **Confirmación:** el botón «Calcular» muestra el resumen («Ocupación · 01/10 a 31/10»); no hay acciones que modifiquen datos, así que no se piden confirmaciones extra.
- **Una tarea por pantalla:** indicador, desglose y registros son pantallas distintas; nunca las tres juntas.
- **Registros:** 10 por página con «Cargar más»; cada tarjeta muestra cama, código de paciente y fechas (RN-09).

## 4. Conexión limitada

| Situación | Comportamiento |
|---|---|
| Red lenta (> 2 s) | Esqueleto de la tarjeta y «Sigue calculando…»; la petición pide solo el nivel que se ve |
| Sin conexión al calcular | Mensaje «Sin conexión. Revise su red» con «Reintentar»; los filtros se conservan |
| Se cae la conexión con un resultado en pantalla | El resultado se queda visible con la etiqueta «Calculado a las 10:30 · sin conexión» |
| Vuelve la conexión | Aviso «Conexión restablecida» con «Actualizar»; no se recalcula solo para no cambiar el número mientras se lee |
| Error 503 del servidor | Igual que en escritorio: mensaje claro, código de seguimiento y «Reintentar» |

Nunca se muestra un número viejo como si fuera actual: la hora del cálculo de la ficha (RN-05) siempre está visible.

## 5. Escenarios móviles

### Escenario A · Jefe de área en la ronda de la mañana

| | |
|---|---|
| Contexto | Teléfono de 360 px, recorriendo los pasillos, con wifi intermitente |
| Objetivo | Saber la ocupación de ayer y qué sala está más llena |
| Decisiones de contenido | Período rápido «Ayer»; valor grande; desglose en tarjetas ordenadas de mayor a menor; su sala marcada |
| Error que se presenta | Se pierde la señal al abrir el desglose |
| Decisión ante el error | El indicador sigue visible con la hora del cálculo; el desglose muestra «Sin conexión · Reintentar»; al volver la señal, un toque carga solo el desglose |

### Escenario B · Médico entre consultas

| | |
|---|---|
| Contexto | Teléfono de 400 px, 2 minutos entre pacientes, datos móviles lentos |
| Objetivo | Ver cuántos pacientes no asistieron a sus citas esta semana |
| Decisiones de contenido | Abre directo en «Mis citas · Esta semana»; muestra asistencia en % y el número de «No asistió»; el filtro de médico no aparece porque está fijo (RN-08) |
| Error que se presenta | Intenta abrir un enlace compartido del reporte de Ocupación |
| Decisión ante el error | 403 «No tiene permiso para ver este reporte» con botón «Ir a mis citas»; no se muestran datos parciales |

## 6. Pantallas (PlantUML Salt, 360 px)

| Pantalla | Archivo | Qué muestra |
|---|---|---|
| M1 | `pantallas/m1-indicador.puml` | Indicador con barra de filtros resumida y ficha plegada |
| M2 | `pantallas/m2-filtros.puml` | Hoja inferior de filtros con períodos rápidos |
| M3 | `pantallas/m3-desglose.puml` | Desglose como lista de tarjetas |
| M4 | `pantallas/m4-registros.puml` | Registros como tarjetas con «Cargar más» |
| M5 | `pantallas/m5-sin-conexion.puml` | Resultado conservado sin conexión y reintento |
