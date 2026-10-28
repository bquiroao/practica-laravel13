# Registro del cambio solicitado — «¿Quién vio la ficha de este paciente?»

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Fecha:** 26/10/2026

## 1. Solicitud

> «Una paciente denuncia que alguien sin motivo consultó su ficha. Necesito ver **quién accedió a la ficha P-000001**, en mi hospital, en un período.»
> — Jefa de un servicio (escenario de la evaluación)

**Por qué hoy no se puede:**
- La consulta filtra por usuario, módulo, acción y resultado, pero **no por recurso**. Con lo que hay, habría que traer el día entero y revisar fila por fila.
- Si se intenta, un parámetro `recurso` responde 422 `FILTRO_NO_SOPORTADO`.

## 2. Requisitos nuevos

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| **RF-18** | Filtrar la consulta por recurso exacto (`paciente:P-000001`) | Dado un día con 3 accesos a P-000001 en Oriente, 1 a P-000002, 1 sin recurso y 1 a P-000001 en Norte, cuando el auditor de Oriente filtra por P-000001, entonces ve solo los 2 de Oriente, más recientes primero (REC-01) |
| **RF-19** | El filtro respeta el tenant | El acceso de Norte no aparece para Oriente; la red ve los 3 (REC-02) |
| **RF-20** | Mismo máximo que al registrar | Vacío o más de 128 caracteres → 422 en `recurso`; 128 se acepta; los demás filtros siguen en 64 (REC-03) |
| **RF-21** | Pregunta contextual en la interfaz | Desde el detalle de un movimiento con recurso, «¿Quién más accedió a esta ficha?» lleva a los filtros con el recurso puesto y el foco en él, **sin consultar todavía** |
| **RNF-11** | Contrato = código | Los parámetros documentados de `GET /movimientos` son exactamente los que reclaman los filtros, más `cursor` (REC-04) |

## 3. Análisis de impacto (antes de programar)

| Pieza | ¿Cambia? | Por qué |
|---|---|---|
| `ConsultarMovimientos`, `CatalogoFiltros`, `Alcance` | **No** | Los filtros son el punto de extensión de la semana 2. El refactor de la semana 7 dejó la interpretación de los parámetros en `CatalogoFiltros`, que los descubre de los filtros |
| `FiltroPorCampo` | Sí, mínimo | Fijaba 64 caracteres para todos; `recurso` admite 128 al registrar. Se agrega el punto de extensión `maximo()` |
| `FiltroRecurso` | **Nuevo** | Una clase de 30 líneas |
| `contenedor.php` | Una línea | Registrar el filtro |
| `PdoBitacora` | **No** | Compone el `WHERE` con el `sql()` de cada filtro (semana 3) |
| `schema.sql`, `integracion-central.sql` | Un índice | `(recurso, ocurrido_en DESC)`: la pregunta va por recurso |
| `api/openapi.yaml` | 1.1.0 → **1.2.0** | Un parámetro opcional nuevo: cambio compatible |
| Cliente (`ClienteAuditoria`) | **No** | Solo registra |
| Prototipo | Sí | Campo, acceso contextual, API simulada, paridad y recorrido |

**Riesgos considerados:**
- **Protección de datos:** el recurso identifica a un paciente. No puede ir en la URL: se agregó `recurso` a la regla R-D1 y el recorrido lo verifica.
- **Meta-auditoría:** la consulta debe guardar el recurso buscado, porque «quién investigó a quién» también es evidencia (REC-01).

## 4. Implementación

| Archivo | Cambio |
|---|---|
| [`src/Dominio/Filtros/FiltroRecurso.php`](../micro-his-auditoria/src/Dominio/Filtros/FiltroRecurso.php) | Nuevo: parámetro y columna `recurso`, máximo 128; un movimiento sin recurso nunca coincide |
| [`src/Dominio/Filtros/FiltroPorCampo.php`](../micro-his-auditoria/src/Dominio/Filtros/FiltroPorCampo.php) | `maximo()` protegido (64 por defecto) |
| [`bootstrap/contenedor.php`](../micro-his-auditoria/bootstrap/contenedor.php) | `new FiltroRecurso()` en `filtrosDeConsulta()` |
| [`api/openapi.yaml`](../micro-his-auditoria/api/openapi.yaml) | 1.2.0; parámetro `recurso` (maxLength 128) |
| `database/*.sql` | Índice `audit_logs_recurso_fecha` |
| [`tests/Aplicacion/RecursoPrueba.php`](../micro-his-auditoria/tests/Aplicacion/RecursoPrueba.php) | REC-01..04 |
| `tests/mutaciones.php` | M25 (compara otro campo) y M26 (hereda 64) |
| `tests/Presentacion/CorreccionHttpPrueba.php` | CORR-07 exigía el texto literal `version: 1.1.0`; ahora exige una versión ≥ 1.1.0. Era una prueba frágil: lo que protege es que la corrección esté documentada desde 1.1.0, no que la versión no suba |
| `semana-12/prototipo/*` | Campo «Recurso», botón contextual en el detalle, filtro en la API simulada |

## 5. Pruebas: rojo → verde

| Paso | Resultado | Evidencia |
|---|---|---|
| 1. Pruebas escritas, sin código | 98 pruebas · **3 fallan** (REC-01..03). REC-04 pasa: contrato y código todavía coinciden | [cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt) |
| 2. Filtro en el código, contrato sin tocar | REC-01..03 pasan; **REC-04 falla**: la guarda detecta el parámetro sin documentar | [cambio-2-codigo-sin-contrato.txt](evidencia/cambio-2-codigo-sin-contrato.txt) |
| 3. Contrato 1.2.0 | 98 · 81 pasan · **0 fallan** · 17 omitidas (sin base) | [cambio-3-despues-verde.txt](evidencia/cambio-3-despues-verde.txt) |
| 4. PostgreSQL 18 | **98/98** | [cambio-4-postgres.txt](evidencia/cambio-4-postgres.txt) |
| 5. Mutaciones | **26/26**; M25 la detectan REC-01 y REC-02; M26, REC-03 | [mutaciones.txt](evidencia/mutaciones.txt) |
| 6. Lint del contrato | Válido | [lint-openapi.txt](evidencia/lint-openapi.txt) |

## 6. Demostración de punta a punta con el prototipo

| Paso | Escritorio | Móvil |
|---|---|---|
| 08a · El detalle de #55 ofrece «¿Quién más accedió a esta ficha?» | [captura](capturas/escritorio/08a-detalle-con-recurso.png) | [captura](capturas/movil/08a-detalle-con-recurso.png) |
| 08b · Filtros con el recurso puesto, el resto limpio, el foco en el campo; **todavía no se consultó** | [captura](capturas/escritorio/08b-filtros-con-recurso.png) | [captura](capturas/movil/08b-filtros-con-recurso.png) |
| 08c · «Buscar»: 3 accesos a P-000001 (u-luis, u-equivocado y su corrección) | [captura](capturas/escritorio/08c-quien-vio-la-ficha.png) | [captura](capturas/movil/08c-quien-vio-la-ficha.png) |

- **Paridad con el kernel real:** 4 pasos nuevos (recurso, vacío, 129 caracteres y la ficha de Norte desde la red); 26 pasos, **0 diferencias** ([paridad.txt](evidencia/paridad.txt)).
- **Recorrido:** 58 comprobaciones, 0 fallas ([recorrido.txt](evidencia/recorrido.txt)).

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa descartada | Motivo |
|---|---|---|
| Coincidencia **exacta** del recurso | Búsqueda parcial («P-0000») | Una búsqueda parcial devuelve fichas de otros pacientes: más datos de los necesarios para la pregunta. Además, no aprovecha el índice |
| El botón del detalle **no** consulta: lleva a los filtros | Consultar de inmediato | Cada consulta queda registrada (R-08). La persona debe ver qué va a buscar y en qué período |
| `maximo()` en la clase base | Duplicar la validación en `FiltroRecurso` | Una sola regla de forma; el filtro nuevo solo declara su límite |
| Contrato 1.2.0 (menor) | 2.0.0 | Es un parámetro opcional: ningún cliente existente se rompe |

## 8. Riesgos y pendientes

- **El recurso es texto libre del emisor** (`paciente:P-000001`). Si un módulo lo escribe distinto (`P-000001` a secas), la búsqueda no lo encuentra. Lo mitiga ReglaFormatoCampos al registrar, pero no hay un catálogo de prefijos: se propone para la revisión técnica (semana 13).
- **Volumen:** el índice nuevo ocupa espacio en una tabla que solo crece. Se mide en el plan SQA (semana 14).
