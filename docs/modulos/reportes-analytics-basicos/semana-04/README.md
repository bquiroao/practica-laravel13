# Reportes y analytics básicos — Semana 4

**Tema:** arquitectura en capas y patrón repositorio.
**Tarea individual:** continuar el MISMO «Micro-HIS Reportes y analytics básicos» y el flujo «cálculo y visualización trazable de un indicador hospitalario»: entrada organizada con MVC, interfaz Repository en el límite apropiado con adaptadores PDO e InMemory, controlador sin SQL ni reglas de negocio, y análisis de cómo un repositorio de datos compartido integraría el módulo.
**Entregable:** capas, responsabilidades y objetos reutilizables.

## Qué cambió en el mismo Micro-HIS

| Semana 3 | Semana 4 | Por qué |
|---|---|---|
| `public/index.php` hacía de todo en la entrada | `index.php` solo despacha; `Enrutador` + `ReporteController` + `VistaIndicador` | Separar la entrada con MVC |
| Puerto `LectorOcupacion` | Interfaz `OcupacionRepository` en Domain | Formalizar el patrón Repository |
| Un adaptador (`OcupacionPdo`) | `OcupacionPdoRepository` y `OcupacionInMemoryRepository` | Dos implementaciones intercambiables |
| Doble `LectorQueFalla` | Doble `RepositorioQueFalla` | Mismo propósito, nombre del patrón |
| 11 pruebas | 23 pruebas | Se agregan contrato del Repository y MVC |

El código está en `../micro-his/`; esta carpeta tiene la documentación, los diagramas y la evidencia de la semana.

## MVC en la entrada

| Rol MVC | Componente | Responsabilidad | No hace |
|---|---|---|---|
| Front Controller | `public/index.php` + `Http/Enrutador` | Asocia método y ruta con una acción | No conoce reportes ni datos |
| Controlador | `Controller/ReporteController` | Lee la petición, arma `Filtros`, llama al caso de uso y elige la vista | No tiene SQL ni reglas de negocio |
| Modelo | `CalcularIndicador` (Application) + Domain | Permisos, cálculo, desglose, registros y reglas RN-01 a RN-09 | No sabe de HTTP ni HTML |
| Vista | `View/VistaIndicador` | Presenta el `ResultadoConsulta` en HTML, escapando todo el texto | No calcula ni consulta |

**Evidencia de que el controlador no tiene SQL:** la prueba `[mvc] los controladores no contienen SQL ni PDO` revisa el código de `src/Presentation/Controller/` y falla si aparece `SELECT`, `INSERT`, `UPDATE`, `DELETE` o `PDO`. Las reglas tampoco están ahí: el 403, el 422 y el «Sin datos» los decide el modelo; el controlador solo los traslada a la respuesta.

## Repository en el límite apropiado

`OcupacionRepository` vive en **Domain** y no en Persistence. El dominio es quien sabe qué datos necesita la ocupación (camas habilitadas, ocupadas por sala, registros de una sala); Persistence solo decide **cómo** obtenerlos. Si la interfaz estuviera en Persistence, el dominio dependería de la capa de infraestructura y se rompería la regla de dependencias de la semana 3.

| Método | Devuelve | Regla que respeta |
|---|---|---|
| `camasHabilitadas()` | Entero | Las camas en mantenimiento no cuentan |
| `ocupadasPorSala(Filtros)` | Sala → camas ocupadas, de mayor a menor | Una estancia cuenta si se cruza con el período |
| `registrosDeSala(Filtros, sala)` | Lista de `RegistroOrigen`, una por cama | Paciente solo con código (RN-09) |

| Adaptador | Fuente | Uso |
|---|---|---|
| `OcupacionPdoRepository` | PostgreSQL con PDO, siempre `prepare()` + `execute()` | Ejecución real y pruebas sobre `micro_his_test` |
| `OcupacionInMemoryRepository` | Arreglos en memoria (`pruebas/datos/ocupacion-octubre.php`) | Pruebas rápidas sin base y desarrollo sin Docker |
| `RepositorioQueFalla` (doble) | Siempre lanza `DatosNoDisponibles` | Probar el error de persistencia |

La prueba `[repository] PDO e InMemory dan el mismo desglose / los mismos registros` demuestra que los dos adaptadores son sustituibles (LSP de la semana 2 aplicado a la persistencia).

## Capas y responsabilidades

| Capa | Componentes | Responsabilidad |
|---|---|---|
| Presentation | `index.php`, `Enrutador`, `Respuesta`, `ReporteController`, `VistaIndicador` | Entrada HTTP, coordinación y presentación |
| Application | `CalcularIndicador`, `PoliticaPermisos`, `ResultadoConsulta`, `AccesoDenegado` | Caso de uso, permisos y traducción de errores a resultados |
| Domain | `ContratoReporte`, `ReportRegistry`, `ReporteOcupacion`, objetos de valor, `OcupacionRepository`, excepciones | Reglas del negocio y contratos |
| Persistence | `ConexionPdo`, `OcupacionPdoRepository`, `OcupacionInMemoryRepository` | Acceso a datos |

## Objetos reutilizables

| Objeto | Capa | Se reutiliza en | Cómo |
|---|---|---|---|
| `Filtros` | Domain | Los 10 reportes | Misma precondición de período (RN-02) para todos |
| `Indicador`, `Desglose`, `RegistroOrigen` | Domain | Los 10 reportes | Mismas postcondiciones de trazabilidad (RN-05, RN-07, RN-09) |
| `ContratoReporte` + `ReportRegistry` | Domain | Los 10 reportes | Un reporte nuevo es una clase registrada, sin tocar el cliente (OCP) |
| Interfaz Repository por reporte | Domain | `CitasRepository`, `MortalidadRepository`, … | Mismo patrón: el dominio define, Persistence implementa |
| `ConexionPdo` | Persistence | Todos los adaptadores PDO | Una sola forma de conectar, configurada fuera del código |
| `CalcularIndicador` + `ResultadoConsulta` | Application | Vista HTML (semana 4) y API JSON (semana 5) | El mismo caso de uso sirve a cualquier entrada |
| `PoliticaPermisos` | Application | Todas las consultas | RN-01 en un solo lugar |
| `Enrutador` + `Respuesta` | Presentation | HTML y JSON | Agregar una ruta no cambia lo demás |
| `OcupacionInMemoryRepository` + datos de prueba | Persistence / pruebas | Pruebas y demos sin base | Mismo comportamiento que PDO, comprobado por prueba |

## Repositorio de datos compartido: cómo se integraría el módulo

En el SHI los módulos no se llaman entre sí para leer datos: comparten la base **HOSPITAL** de cada tenant (y CENTRAL para la red). Es el estilo arquitectónico de **repositorio de datos compartido**: cada módulo dueño escribe sus tablas y otros módulos las leen. Reportes y analytics básicos es un **consumidor de solo lectura** (RNF-04).

| Aspecto | Decisión propuesta |
|---|---|
| Qué lee | Ocupación: `ward`, `bed` (salas y camas), `bed_stay`, `care_episode` (admisión, traslados y altas); Citas: `appointment`; el resto según la tabla de fuentes de la semana 1 |
| Cómo lee | Por **vistas de lectura** en un esquema `reportes` (por ejemplo `reportes.v_estancia_cama`) acordadas con el módulo dueño, en lugar de leer las tablas directamente |
| Con qué permisos | Rol de base de datos `reportes_lectura` con solo `SELECT` sobre esas vistas; nunca `INSERT`, `UPDATE` ni `DELETE` |
| Tenant | La conexión `hospital` de cada tenant (Stancl Tenancy) aísla los datos de cada hospital; el reporte nunca mezcla hospitales |
| Privacidad | Las vistas exponen solo códigos de paciente y datos mínimos (RN-09); las columnas clínicas no se publican |
| Consistencia | Indicador, desglose y registros se leen en la misma transacción `REPEATABLE READ` para que RN-07 se cumpla aunque otro módulo esté escribiendo |
| Dónde entra el Repository | Un adaptador `OcupacionPgRepository` del SHI implementa la misma interfaz `OcupacionRepository` leyendo las vistas; Domain y Application no cambian |

| Riesgo del repositorio compartido | Mitigación |
|---|---|
| Un cambio de esquema del módulo dueño rompe los reportes | Las vistas son el contrato; las pruebas del Repository corren contra PostgreSQL en cada PR |
| Consultas agregadas pesadas compiten con la operación clínica | Índices por período (`bed_stay_periodo_idx`) y, si RNF-01 no se cumple, réplica de lectura (se evalúa en la semana 5) |
| Acceso de más a datos sensibles | Rol `reportes_lectura` limitado a las vistas y auditoría de consultas |
| Lecturas inconsistentes entre niveles | Transacción `REPEATABLE READ` por consulta trazable |

## Diagramas (PlantUML)

| Diagrama | Qué muestra |
|---|---|
| `diagramas/capas-mvc.puml` | Las 4 capas con los roles MVC y la dirección de las dependencias |
| `diagramas/repository-clases.puml` | La interfaz Repository, sus dos adaptadores, el doble de prueba y quién depende de quién |
| `diagramas/repositorio-compartido.puml` | Cómo el módulo se integra a la base HOSPITAL compartida mediante vistas y un rol de solo lectura |

## Cómo ejecutarlo

```bash
cd docs/modulos/reportes-analytics-basicos/micro-his
docker compose up -d
php database/crear.php
php pruebas/ejecutar.php --evidencia=../semana-04/evidencia/salida-pruebas.txt
php -S localhost:8000 -t public
```

## Fuentes

- Fowler, M. *Patterns of Enterprise Application Architecture*: Repository, Model View Controller y Front Controller. https://martinfowler.com/eaaCatalog/
- Bass, L., Clements, P. y Kazman, R. *Software Architecture in Practice*: estilo de repositorio de datos compartido.
- The PHP Group. *PHP Data Objects (PDO)*. https://www.php.net/manual/es/book.pdo.php
