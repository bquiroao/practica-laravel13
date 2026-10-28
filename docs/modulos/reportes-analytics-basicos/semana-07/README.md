# Reportes y analytics básicos — Semana 7

**Tema:** diseño de componentes y refactorización.
**Tarea individual:** diseñar los componentes backend y frontend propios del módulo para «cálculo y visualización trazable de un indicador hospitalario» y refactorizar conceptualmente el punto con mayor acoplamiento, separando UI, aplicación, dominio y persistencia sin ampliar el módulo.
**Entregable:** diagrama de componentes, contratos de entrada/salida y comparación antes/después de un refactor concreto con justificación.

Los componentes se diseñan para el SHI real (Laravel 12 + Vue 3 + PostgreSQL) a partir de lo comprobado en el Micro-HIS. El módulo **no se amplía**: siguen siendo los mismos tres niveles y los mismos permisos.

## Componentes backend (Laravel)

| Componente | Capa | Responsabilidad | Equivale en el Micro-HIS |
|---|---|---|---|
| `ReporteController` (API) | UI / entrada | Recibe la petición, delega y devuelve JSON | `ApiReporteController` |
| `FiltrosReporteRequest` | UI / entrada | Valida `desde`, `hasta`, `dimension`, `parte` (422) | Construcción de `Filtros` |
| `IndicadorResource` | UI / salida | Da forma al JSON del contrato v1 | `JsonIndicador` |
| `CalcularIndicadorAction` | Aplicación | Orquesta los tres niveles | `CalcularIndicador` |
| `ReportePolicy` | Aplicación | Permisos por rol con Spatie (`reportes.ver`, `reportes.ver_citas_propias`) | `PoliticaPermisos` |
| `ContratoReporte`, `ReportRegistry`, reportes | Dominio | Cálculo, desglose y registros con sus postcondiciones | Igual |
| Objetos de valor | Dominio | `Filtros`, `Indicador`, `Desglose`, `RegistroOrigen`, `Precision` | Igual + `Precision` (refactor) |
| Interfaces `*Repository` | Dominio | Qué datos necesita cada reporte | `OcupacionRepository` |
| `*PgRepository` | Persistencia | Consultas de solo lectura sobre la conexión `hospital` | `OcupacionPdoRepository` |
| `ReportesServiceProvider` | Composición | Registra los reportes y enlaza interfaces con adaptadores | `bootstrap.php` |

## Componentes frontend (Vue 3)

| Componente | Responsabilidad | No hace |
|---|---|---|
| `ReportesView` | Página del módulo; arma la pantalla según el nivel | No llama a la API directamente |
| `FiltrosReporte` | Formulario de reporte, período y dimensión con validación | No calcula |
| `TarjetaIndicador` | Muestra valor, unidad y tendencia | No redondea por su cuenta (usa el formateador) |
| `FichaTrazabilidad` | Fórmula, período, filtros, fuente y fecha | — |
| `TablaDesglose` | Partes y total; cada parte enlaza al nivel 3 | No vuelve a sumar ni a calcular porcentajes |
| `TablaRegistros` | Registros con datos mínimos, paginados | No muestra datos clínicos |
| `EstadoConsulta` | Carga, vacío («Sin datos»), error recuperable y sin permiso | — |
| `useReportesStore` (Pinia) | Estado de la consulta y llamadas al cliente API | No formatea |
| `reportesApi` | Cliente HTTP del contrato v1 (Axios, `X-Correlation-Id`) | No conoce componentes |
| `formatearIndicador` | Única función que convierte un valor en texto con su precisión y unidad | — |

## Contratos de entrada/salida

| Componente | Entrada | Salida |
|---|---|---|
| `GET /api/v1/reportes/{tipo}[/desglose\|/registros]` | `desde`, `hasta`, `dimension?`, `parte?`, JWT | JSON v1 (ver `semana-05/contrato-api.md`) |
| `CalcularIndicadorAction::ejecutar` | rol, tipo, `Filtros`, nivel, dimensión, parte | `ResultadoConsulta` (éxito, sin datos o error con código) |
| `ContratoReporte::calcular` | `Filtros` | `Indicador` con `numerador`, `denominador`, `precision` y ficha |
| `*Repository` | `Filtros` (+ parte) | Conteos enteros y `RegistroOrigen[]`; lanza `DatosNoDisponibles` |
| `FiltrosReporte` (Vue) | props: `reportes`, `valorInicial` | evento `consultar({ tipo, desde, hasta, dimension })` |
| `TarjetaIndicador` (Vue) | props: `indicador`, `precision` | — |
| `TablaDesglose` (Vue) | props: `desglose`, `numerador` | evento `abrirParte(parte)` |
| `TablaRegistros` (Vue) | props: `registros`, `pagina` | evento `cambiarPagina(n)` |
| `formatearIndicador` | `valor`, `unidad`, `precision` | texto, por ejemplo «78.0 %» |

## Diagramas (PlantUML)

| Diagrama | Qué muestra |
|---|---|
| `diagramas/componentes-backend.puml` | Componentes Laravel por capa y sus interfaces |
| `diagramas/componentes-frontend.puml` | Componentes Vue, store, cliente API y formateador |
| `diagramas/refactor-antes-despues.puml` | Dependencias del punto acoplado antes y después del refactor |

El refactor concreto, con su justificación y el código antes/después, está en `refactor.md` y en la carpeta `refactor/`.
