# Módulo 23 · Reportes y analytics básicos — Semana 1

**Proceso modelado:** cálculo y visualización trazable de un indicador hospitalario.

## Narrativa de alcance

El módulo calcula y muestra **indicadores agregados** del hospital (conteos, porcentajes, tasas y tendencias), por ejemplo la ocupación de camas, la estancia promedio, las citas o la tasa de mortalidad. Trabaja **solo en modo lectura** sobre los datos que generan otros módulos y no tiene lógica clínica propia.

Un indicador es **trazable** en dos sentidos:

1. **Ficha de trazabilidad:** junto al valor se muestra **de dónde sale**: la fórmula, el período, los filtros aplicados, la fuente de los datos y la fecha y hora del cálculo.
2. **Desglose hasta el origen:** el usuario puede **bajar desde el número hasta los registros que lo forman**. Primero lo desglosa por una dimensión (sala, servicio, médico, estado o día) y después ve los registros individuales de cualquier parte.

Ejemplos:

| Indicador | Desglose | Registros de origen |
|---|---|---|
| Ocupación del 10/10: 78 % | Por sala: Medicina 90 %, Cirugía 70 %, Pediatría 65 % | Las camas de Medicina con su estado y cada estancia (desde cuándo está ocupada) |
| Citas de octubre: 120 | Por estado: atendidas 95, canceladas 15, no asistió 10 | Cada cita atendida: fecha, hora, médico y servicio |

Así cualquier cifra se puede **verificar**: la suma del desglose coincide con el valor del indicador, y cada parte del desglose se puede abrir hasta sus registros.

| Dentro del alcance | Fuera del alcance (otro módulo) |
|---|---|
| Calcular indicadores agregados por período y filtros | Exportar a PDF o CSV (Módulo 27) |
| Mostrar valor, comparación con el período anterior y tendencia | Dashboard de ocupación en tiempo real (Módulo 9) |
| Mostrar la ficha de trazabilidad de cada indicador | Registrar o modificar datos (módulos fuente) |
| Desglosar el indicador por dimensión y ver sus registros de origen | Ver el expediente clínico completo de un paciente (Módulo 10) |
| Anonimizar los grupos con menos de 3 casos | Bitácora de auditoría (Módulo 22) |
| Restringir cada reporte y cada nivel de detalle según el rol | |

## Funcionamiento con ejemplos

El usuario recorre **tres niveles**, de arriba hacia abajo, siempre con **los mismos filtros y permisos**:

| Nivel | Qué muestra | Caso de uso |
|---|---|---|
| 1 · Indicador | El número resumido, su comparación, su tendencia y su ficha de trazabilidad | CU-01, CU-04 |
| 2 · Desglose | Ese número repartido por una dimensión (sala, médico, estado o día) | CU-07 |
| 3 · Registros | Los registros reales que forman cada parte del desglose | CU-08 |

### Ejemplo 1: ocupación de camas

**Fuente:** salas (`ward`), camas (`bed`) y estancias (`bed_stay`) de la base HOSPITAL. **Fórmula:** camas ocupadas ÷ camas habilitadas × 100.

**Nivel 1.** El jefe de área consulta del 01/10 al 10/10: **ocupación 78 %**, 5 puntos más que el período anterior. Su ficha de trazabilidad:

| Campo | Valor |
|---|---|
| Fórmula | camas ocupadas / camas habilitadas × 100 |
| Período | 01/10/2026 al 10/10/2026, por día |
| Filtros | todas las salas |
| Fuente | `ward`, `bed`, `bed_stay` |
| Datos | 39 camas ocupadas de 50 habilitadas (al 10/10) |
| Calculado | 10/10/2026 10:15 |

**Nivel 2.** Desglosa por sala:

| Sala | Ocupadas | Habilitadas | Ocupación |
|---|---:|---:|---:|
| Medicina | 18 | 20 | 90 % |
| Cirugía | 14 | 20 | 70 % |
| Pediatría | 7 | 10 | 70 % |
| **Total** | **39** | **50** | **78 %** (coincide con el indicador) |

**Nivel 3.** Abre Medicina y ve cada cama:

| Cama | Estado | Ocupada desde | Paciente | Días |
|---|---|---|---|---:|
| 101 | Ocupada | 08/10 14:30 | PAC-00451 | 2 |
| 102 | Libre | — | — | — |
| 103 | Ocupada | 05/10 09:10 | PAC-00398 | 5 |

### Ejemplo 2: citas

**Fuente:** citas (`appointment`) con su médico y su estado. **Fórmulas:** total de citas del período; asistencia = atendidas ÷ (atendidas + no asistió) × 100.

**Nivel 1.** Octubre: **120 citas**, asistencia **90,5 %**. **Nivel 2**, por estado:

| Estado | Citas |
|---|---:|
| Atendidas | 95 |
| Canceladas | 15 |
| No asistió | 10 |
| **Total** | **120** (coincide con el indicador) |

**Nivel 3.** Abre "Atendidas" y ve cada cita:

| Cita | Fecha y hora | Médico | Servicio | Paciente |
|---|---|---|---|---|
| #4521 | 10/10 09:00 | Dr. López | Medicina interna | PAC-00451 |
| #4522 | 10/10 09:30 | Dra. Pérez | Pediatría | PAC-00512 |

En los registros el paciente aparece **solo con su código**, sin datos clínicos (RN-09).

### Qué ve cada rol

| Rol | Ocupación | Citas |
|---|---|---|
| Administrador | Los tres niveles | Los tres niveles |
| Jefe de área | Los tres niveles | Los tres niveles |
| Médico | Sin acceso (403) | Los tres niveles, **solo con sus propias citas** |

### Endpoints de cada nivel

| Nivel | Endpoint | Ejemplo |
|---|---|---|
| 1 | `GET /api/v1/reportes/{tipo}` | `/reportes/ocupacion?desde=2026-10-01&hasta=2026-10-10&granularidad=dia` |
| 2 | `GET /api/v1/reportes/{tipo}/desglose` | `/reportes/ocupacion/desglose?dimension=sala&desde=...&hasta=...` |
| 3 | `GET /api/v1/reportes/{tipo}/registros` | `/reportes/ocupacion/registros?sala_id=3&desde=...&hasta=...` |

Cada tipo de reporte tiene **su propio servicio** con tres operaciones: `calcular`, `desglosar` y `registros`. El `ReportRegistry` elige el servicio según el `{tipo}`, así que agregar un indicador nuevo es agregar un servicio, sin modificar los demás.

## Actores

| Actor | Objetivo |
|---|---|
| **Administrador** | Consultar todos los indicadores del hospital y rastrearlos hasta sus registros |
| **Jefe de área** | Consultar y rastrear los indicadores para la toma de decisiones de su área |
| **Médico** | Consultar el indicador de Citas y rastrearlo, limitado a sus propias citas |
| **Módulos fuente** (sistema) | Proveer los datos de origen en modo lectura (camas, estancias, citas, admisiones, etc.) |

## Casos de uso

| ID | Caso de uso | Actor |
|---|---|---|
| CU-01 | Consultar indicador | Administrador, Jefe de área, Médico |
| CU-02 | Aplicar filtros (período, granularidad y filtros del reporte) | Administrador, Jefe de área, Médico |
| CU-03 | Calcular indicador | Sistema (incluido en CU-01) |
| CU-04 | Ver la ficha de trazabilidad | Administrador, Jefe de área, Médico |
| CU-05 | Validar permiso del rol | Sistema (incluido en CU-01, CU-07 y CU-08) |
| CU-06 | Anonimizar grupos pequeños | Sistema (extiende CU-03 cuando un grupo tiene menos de 3 casos) |
| CU-07 | Desglosar indicador por dimensión (sala, servicio, médico, estado o día) | Administrador, Jefe de área, Médico |
| CU-08 | Ver registros de origen de una parte del desglose | Administrador, Jefe de área, Médico (solo sus citas) |

## Reglas principales

| ID | Regla |
|---|---|
| RN-01 | Solo se consultan los indicadores que el rol tiene permitidos |
| RN-02 | El período es obligatorio y la fecha inicial no puede ser mayor que la final |
| RN-03 | La granularidad de la tendencia es día, semana o mes |
| RN-04 | En los gráficos, los grupos con menos de 3 casos se muestran agrupados como "Otro" |
| RN-05 | Todo indicador muestra su ficha de trazabilidad: fórmula, período, filtros, fuente y fecha del cálculo |
| RN-06 | Si no hay datos en el período, se informa "Sin datos" en lugar de mostrar cero |
| RN-07 | **Todo valor mostrado se puede desglosar hasta los registros que lo forman**, y la suma del desglose coincide con el valor del indicador |
| RN-08 | El desglose y los registros usan **los mismos filtros, período y permisos** que el indicador. El Médico solo rastrea sus propias citas |
| RN-09 | Los registros de origen muestran solo los **datos mínimos** para verificar la cifra (por ejemplo, sala, cama, fechas, médico y estado); el paciente se identifica con su código, sin datos clínicos |

## Trazabilidad entre diagramas

| Caso de uso | Diagrama de actividad | Diagrama de secuencia |
|---|---|---|
| CU-01 Consultar indicador | Inicio del flujo | Sección 1 |
| CU-05 Validar permiso | Decisión "¿El rol tiene permiso?" | `alt` Sin permiso → 403 |
| CU-02 Aplicar filtros | Decisión "¿Filtros válidos?" | `alt` Filtros inválidos → 422 |
| CU-03 Calcular indicador | Acciones de consulta y cálculo | Llamadas al servicio y a la base |
| CU-06 Anonimizar | Decisión "¿Grupo con menos de 3 casos?" | `opt` Anonimizar |
| CU-04 Ver trazabilidad | Armar la ficha de trazabilidad | Respuesta 200 con `trazabilidad` |
| CU-07 Desglosar indicador | Decisión "¿Desea desglosar?" | Sección 2 (`/desglose`) |
| CU-08 Ver registros de origen | Decisión "¿Desea ver los registros?" | Sección 3 (`/registros`) |

## Diagramas

`01_casos_de_uso.puml`, `02_actividad.puml` y `03_secuencia.puml`, con sus imágenes `.png`.
