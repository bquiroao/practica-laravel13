# Reportes y analytics básicos — Semana 15

**Tema:** pruebas unitarias, caja blanca y caja negra.
**Tarea individual:** diseñar y ejecutar pruebas del módulo trazadas a RF/RNF: unitarias para reglas de indicadores, agregaciones, filtros, fuentes, precisión y visualización; caja blanca con ramas y condiciones del flujo «cálculo y visualización trazable de un indicador hospitalario»; caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos.
**Entregable:** matriz RF/RNF→prueba, mínimo 3 unitarias, 2 de caja blanca y 4 de caja negra, comandos/resultados, capturas y defectos registrados.

Las pruebas están en `../micro-his/pruebas/semana15.php` y prueban solo el código del módulo: Domain, Application, el adaptador InMemory y la API v1 contra la base de prueba `micro_his_test`. No llaman a otros módulos del SHI.

| Tipo | Pedidas | Entregadas |
|---|---|---|
| Unitarias | 3 | 6 (U-01 a U-06) |
| Caja blanca | 2 | 2 (CB-01 con 9 caminos y CB-02 con 5 condiciones) |
| Caja negra | 4 | 6 (CN-01 a CN-06) |

## 1. Matriz RF/RNF → prueba

| Requerimiento | Qué exige | Pruebas |
|---|---|---|
| RF-01 calcular indicador | Valor en porcentaje entre 0 y 100, con precisión de un decimal | U-02, U-05, CN-02 |
| RF-02 validar filtros (RN-02) | Período obligatorio, fechas válidas, inicial ≤ final | U-01, CN-01 |
| RF-03 ficha de trazabilidad (RN-05) | Fórmula, período, filtros, fuente y fecha | U-02 |
| RF-04 desglosar (RN-07) | La suma de las partes es igual al numerador | U-03, CB-01 |
| RF-05 registros de origen (RN-09) | Paciente solo con código; registros de una parte existente | U-04, CN-05 |
| RF-07 restringir por rol (RN-01) | Sin permiso → 403 | CB-01, CN-03 |
| RN-06 sin datos | «Sin datos», nunca 0 | U-06, CB-01, CN-02 |
| RNF-01 rendimiento | Límite de período para responder en menos de 2 s | CN-06 |
| RNF-02 seguridad | 403 sin permiso; errores sin detalles técnicos | CN-03, CN-04 |
| RNF-03 privacidad | Sin nombres ni datos clínicos | U-04 |

## 2. Unitarias (Domain)

| ID | Qué prueba | Entradas | Resultado esperado |
|---|---|---|---|
| U-01 | `Filtros` | `01/10/2026`, `2026-02-30`, período invertido; un solo día | Los tres primeros lanzan error; el de un día es válido |
| U-02 | `Indicador` | 100.1 %, ficha sin fuente; 100.0 % con ficha completa | Rechaza los dos primeros; acepta 100.0 |
| U-03 | `Desglose` | A=2, B=1; A=−1 | Total 3; rechaza el negativo |
| U-04 | `RegistroOrigen` | «Juan Pérez»; `PAC-00001` | Rechaza el nombre; acepta el código |
| U-05 | Precisión de `ReporteOcupacion` | 2 de 3 camas ocupadas | 66.7 % (66.666… redondeado a un decimal) |
| U-06 | Sin datos | Período sin estancias | Lanza `SinDatos`, no devuelve 0 |

## 3. Caja blanca

**CB-01 · Caminos de `CalcularIndicador::ejecutar`.** Se recorre cada rama del método, una prueba por camino:

| Camino | Condición en el código | Resultado |
|---|---|---|
| 1 | `nivel` no está en `NIVELES` | 422 |
| 2 | `PoliticaPermisos` lanza `AccesoDenegado` | 403 |
| 3 | `ReportRegistry` lanza `ReporteNoExiste` | 404 |
| 4 | El reporte lanza `SinDatos` | `sin_datos` |
| 5 | `nivel = indicador` | Sin desglose ni registros |
| 6 | `nivel = desglose` | Con desglose, sin registros |
| 7 | `nivel = registros` y `parte` nula | 422 |
| 8 | Dimensión no declarada (`exigirDimension`) | 422 |
| 9 | El repositorio lanza `DatosNoDisponibles` | 503 |

**CB-02 · Condición compuesta «la estancia está en el período»** (`OcupacionInMemoryRepository::estanciasEnPeriodo`, la misma regla del SQL):

`cama habilitada` **y** `ingreso ≤ hasta` **y** (`egreso` nulo **o** `egreso ≥ desde`)

| Caso | Término que se prueba | ¿Cuenta? |
|---|---|---|
| Egreso nulo, ingreso antes del período | `egreso` nulo | Sí |
| Egreso igual a `desde` (límite) | `egreso ≥ desde` | Sí |
| Egreso un día antes de `desde` | `egreso ≥ desde` falso | No |
| Cama en mantenimiento | `cama habilitada` falso | No |
| Ingreso después de `hasta` | `ingreso ≤ hasta` falso | No |

Cada término cambia el resultado por sí solo; el resultado esperado es sala A = 2 y sala B sin camas.

## 4. Caja negra (API v1, base de prueba)

| ID | Técnica | Casos | Esperado |
|---|---|---|---|
| CN-01 | Partición de equivalencia del período | Válido; invertido; formato `1/10/2026`; fecha `2026-02-30` | 200; 422; 422; 422 |
| CN-02 | Valores límite | Un solo día (01/10); día del egreso (20/09); día siguiente (21/09) | 2.0 %; 2.0 %; «Sin datos» |
| CN-03 | Permisos | Administrador; Jefe de área; Médico; rol `invitado` | 200; 200; 403 `SIN_PERMISO`; 403 |
| CN-04 | Errores | Tipo `inexistente`; dimensión `medico`; registros sin `parte`; fuente caída | 404; 422; 422; 503 sin `SQLSTATE` |
| CN-05 | Equivalencia de `parte` | `parte=Urgencias` (no existe en el desglose) | 422 |
| CN-06 | Límite del período | 01/01/2024 a 31/10/2026 (más de 366 días) | 422 |

## 5. Comandos y resultados

```bash
cd docs/modulos/reportes-analytics-basicos/micro-his
docker compose up -d
php pruebas/semana15.php --evidencia=../semana-15/evidencia/salida-pruebas.txt
```

Resultado: **14 pruebas, 12 correctas y 2 con defecto registrado** (CN-05 y CN-06). La salida completa está en `evidencia/salida-pruebas.txt`. Las pruebas de las semanas 3 a 5 (`php pruebas/ejecutar.php`) siguen pasando las 29.

| Captura | Contenido |
|---|---|
| `evidencia/salida-pruebas.png` | Terminal con la ejecución completa |
| `evidencia/defecto-d01.png` | Respuesta 200 de `/registros?parte=Urgencias` |
| `evidencia/defecto-d02.png` | Respuesta 200 con un período de casi 3 años |

## 6. Defectos registrados

| ID | Defecto | Prueba | Severidad | Corrección propuesta | Estado |
|---|---|---|---|---|---|
| D-01 | `/registros` con una parte que no existe responde 200 con una lista vacía; el usuario no distingue «no hay registros» de «la parte no existe» | CN-05 | Menor | En `ReporteOcupacion::registros`, validar que la parte esté en el desglose y lanzar `InvalidArgumentException` (422) | Abierto |
| D-02 | El período no tiene límite, aunque el contrato API dice máximo 366 días; un período enorme puede pasar de los 2 s de RNF-01 | CN-06 | Menor | En `Filtros`, rechazar períodos de más de 366 días | Abierto |
| D-03 | El Médico no queda restringido a sus propias citas: `PoliticaPermisos` no compara `medicoId` con el usuario (RN-08) | Sin prueba automática: el reporte de Citas aún no existe en el Micro-HIS | Mayor | Exigir `medicoId` igual al usuario en Citas, desglose y registros, con prueba de caja negra | Abierto (es RTF-01 de la semana 13 y R-03 de la semana 14) |

Las pruebas CN-05 y CN-06 se dejaron en el script a propósito: fallarán hasta que se corrijan D-01 y D-02, y entonces pasarán sin cambiarlas.
