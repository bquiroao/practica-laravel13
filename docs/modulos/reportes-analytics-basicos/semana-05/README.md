# Reportes y analytics básicos — Semana 5

**Tema:** cliente-servidor, API REST, microservicios e integración.
**Tarea individual:** evolucionar el MISMO «Micro-HIS Reportes y analytics básicos» y el flujo «cálculo y visualización trazable de un indicador hospitalario» hacia un diseño cliente-servidor y evaluar una frontera de microservicio: contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia, con diagramas y una migración razonada.
**Entregable:** contrato API y plan de issue, rama, worktree y PR.

| Archivo | Contenido |
|---|---|
| `contrato-api.md` y `openapi.yaml` | Contrato API v1: rutas, parámetros, respuestas, errores y permisos |
| `plan-issue-rama-pr.md` | Cómo llevar el contrato al repositorio real en PR de menos de 400 líneas |
| `../micro-his/` | Prototipo: servidor API JSON (`ApiReporteController`) y cliente separado (`public/cliente.html`) |
| `evidencia/` | Salida de las pruebas y respuestas reales de la API |

## 1. De la página única al diseño cliente-servidor

En la semana 4 el servidor armaba el HTML. Ahora el **servidor** expone el caso de uso como API REST v1 en JSON y el **cliente** (`cliente.html`) solo pide datos y los muestra. El caso de uso, el dominio y el Repository no cambiaron: la API es otra vista (`JsonIndicador`) y otro controlador (`ApiReporteController`) sobre el mismo `CalcularIndicador`.

| Parte | Responsabilidad | Sabe de la base |
|---|---|---|
| Cliente (navegador o Vue en el SHI) | Filtros, navegación entre los tres niveles, mensajes de error y de carga | No |
| Servidor API | Autenticación, permisos, cálculo, trazabilidad y errores con forma común | Sí, mediante el Repository |

Ejecución del prototipo: `php -S localhost:8000 -t public public/index.php` y abrir `http://localhost:8000/cliente.html`.

## 2. Evaluación de la frontera de microservicio

**Candidato:** separar el módulo como «servicio de analítica» con su propia base de proyecciones. Se evaluó con criterios medibles y la decisión es **no dividir ahora**:

| Criterio | Medida hoy | Umbral para separar | ¿Se cumple? |
|---|---|---|---|
| Rendimiento (RNF-01) | Indicador de un mes, medido con el campo `ms` del log | p95 > 2 s **después** de índices y réplica de lectura | No |
| Carga sobre la base clínica | Consultas del módulo en `pg_stat_statements` | > 30 % del tiempo de CPU de la base HOSPITAL | No medido en producción; sin indicios |
| Escalado independiente | Peticiones de reportes frente al resto | Reportes > 3 veces el tráfico de los módulos clínicos | No |
| Ritmo de cambios | Despliegues del módulo bloqueados por otros | > 2 por mes | No |
| Datos propios | Tablas que el módulo escribe | Necesita guardar proyecciones o históricos propios | No: solo lee |

Dividir hoy agregaría red, despliegue y consistencia eventual sin un problema medido que lo justifique. La frontera queda **diseñada** para el día en que un umbral se supere.

| Aspecto | Hoy (módulo dentro del SHI) | Si se separa (frontera candidata) |
|---|---|---|
| Propiedad de datos | No posee datos transaccionales; lee vistas de los módulos dueños | Posee solo sus proyecciones (`ocupacion_diaria`, …); los dueños siguen siendo dueños de sus tablas |
| Comunicación | Cliente → API REST síncrona; servidor → base por SQL de lectura | Cliente → API REST; dueños → eventos `outbox_event` → proyecciones (asíncrono) |
| Seguridad | JWT con rol y tenant, Spatie Permission, rol de base `reportes_lectura` | Igual, más autenticación entre servicios y validación del tenant en cada evento |
| Resiliencia | `statement_timeout` de 2 s; 503 `DATOS_NO_DISPONIBLES` con mensaje claro y reintento | Además: reintentos con espera creciente y *circuit breaker* hacia la base de proyecciones |
| Observabilidad | `X-Correlation-Id` de punta a punta y una línea JSON por petición con ruta, código y duración | Además: trazas distribuidas y métrica de atraso de las proyecciones |
| Consistencia | Fuerte: indicador, desglose y registros en una transacción `REPEATABLE READ` | Eventual: la ficha de trazabilidad muestra «datos al <fecha de corte>»; RN-07 se cumple dentro de la proyección |

## 3. Migración razonada (patrón *strangler fig*)

Cada fase se activa solo si se cumple su disparador y se puede revertir sin perder datos:

| Fase | Cambio | Disparador | Reversión |
|---|---|---|---|
| 0 · actual | Módulo dentro del monolito Laravel, contrato API v1 | — | — |
| 1 | Vistas `reportes.*` y rol `reportes_lectura` en la base HOSPITAL | Primer reporte en el SHI | Volver a leer tablas |
| 2 | Réplica de lectura de PostgreSQL para el módulo | p95 > 2 s o carga > 30 % | Apuntar el DSN a la base principal |
| 3 | Proyecciones alimentadas por `outbox_event` | Réplica insuficiente o necesidad de históricos | Calcular de nuevo desde las vistas |
| 4 | Extraer el servicio de analítica detrás de la misma API v1 | Escalado o despliegue independiente necesarios | El cliente no cambia: la ruta `v1` sigue igual |

El contrato API v1 es lo que hace posible la migración: el cliente nunca sabe si detrás hay un módulo o un servicio.

## Diagramas (PlantUML)

| Diagrama | Qué muestra |
|---|---|
| `diagramas/cliente-servidor.puml` | Secuencia de los tres niveles entre cliente, API, caso de uso, Repository y base, con el error 503 |
| `diagramas/frontera-microservicio.puml` | Contenedores de hoy y de la frontera candidata con su base de proyecciones |
| `diagramas/migracion.puml` | Fases de la migración con sus disparadores |

## Cómo ejecutarlo

```bash
cd docs/modulos/reportes-analytics-basicos/micro-his
docker compose up -d
php database/crear.php
php pruebas/ejecutar.php --evidencia=../semana-05/evidencia/salida-pruebas.txt
php -S localhost:8000 -t public public/index.php
```

## Fuentes

- Fielding, R. *Architectural Styles and the Design of Network-based Software Architectures* (REST).
- Newman, S. *Building Microservices*, 2.ª ed.: fronteras, propiedad de datos y migración.
- Fowler, M. *StranglerFigApplication*. https://martinfowler.com/bliki/StranglerFigApplication.html
- OpenAPI Initiative. *OpenAPI Specification 3.1*. https://spec.openapis.org/oas/v3.1.0
