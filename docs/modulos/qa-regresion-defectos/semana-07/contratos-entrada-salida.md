# Contratos de entrada y salida por componente

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · Diagrama: [semana-07-componentes.png](diagramas/semana-07-componentes.png)

Cada componente se describe con lo que recibe, lo que entrega y lo que **nunca** hace. Así se puede reemplazar sin mirar su interior. La última columna dice qué prueba fija el contrato.

## Frontend (SPA, semanas 8–11)

| Id | Componente | Entrada | Salida | Nunca | Fijado por |
|---|---|---|---|---|---|
| F1 | VistaCampanas | Acciones del Líder QA: nombre, funcionalidades, casos, build | Llamadas a F5; muestra GO / NO-GO con sus `causas` | Calcular la decisión (la da el servidor) | Semanas 8–11 |
| F2 | VistaEjecucion | Resultado, archivos (captura, log, JUnit), defecto propuesto, `verifica_defecto` | `ClienteQa::registrarResultado`; muestra CONFIRMADO / RECHAZADO (con `detalle`) / PENDIENTE | Decidir si un resultado necesita evidencia; guardar el contenido fuera de la bandeja | Semanas 8–11 |
| F3 | VistaDefectos | Severidad, prioridad, motivo, build | `clasificar`, `corregir` | Ofrecer «cerrar defecto» (no existe, R-08) | — |
| F4 | VistaTrazabilidad | Id de campaña | Tabla RF → caso → último resultado → huellas → defectos con `bloquea_go` | Mostrar el contenido de una evidencia (R-13) | API-03 |
| F5 | ClienteQa | Operación y datos; token y tenant | `ResultadoCliente` (CONFIRMADO, RECHAZADO, PENDIENTE, INCIERTO), `clave` y `requestId` | Reintentar un 4xx; reintentar una operación sin clave | CLI-01..07 |
| F6 | BandejaPendientes | `guardar(clave, ruta, cuerpo)`, `quitar(clave)` | `todos()` | Cambiar el cuerpo guardado (se reenvía idéntico) | CLI-03, CLI-05, CLI-06 |

## Backend (API QA)

| Id | Componente | Entrada | Salida | Nunca | Fijado por |
|---|---|---|---|---|---|
| B1 | Kernel | `Solicitud` (método, ruta, cabeceras, cuerpo) | `Respuesta` con `X-Request-ID`; 401/400/403/404/405/500 propios; una línea de acceso | Leer el tenant del cuerpo; mostrar detalles de un 500 | HTTP-02, HTTP-03, HTTP-06, OBS-01..03 |
| B2 | Formularios → Controladores → Vistas | `Solicitud` + `Identidad` + parámetros de ruta | `Respuesta` (400 si la forma no sirve; si no, la de la `Salida`) | SQL; reglas de QA; llamar al caso de uso con una entrada mal formada | MVC-01..05, ARQ-08 |
| B3 | Casos de uso (9) | Usuario, tenant y datos ya leídos | `Salida` (tipo, código, datos) | Lanzar excepciones hacia la Presentación; conocer HTTP o PDO | APP-01..15, ARQ-02 |
| B4 | **SolicitudResultado** (S7) | `datos` del resultado | Objeto válido (resultado, evidencias, referencias, motivo), o `ReglaRechazada` con el **mismo código y orden** que antes; `resolverDefecto()` → defecto nuevo o `null` | Tocar puertos ni infraestructura | CAR-01 (37 escenarios), M34, M35 |
| B5 | Modelo de dominio | Comandos (`iniciar`, `cerrar`, `clasificar`, `verificar`…) | Nuevo estado, o `ReglaRechazada` | Persistir; conocer el tenant de la petición | DOM-01..09, CAM-01 |
| B6 | Repositorios | Objetos del dominio + tenant | Objetos del dominio; `ErrorPersistencia`, `ClaveEnUso` o `ReglaRechazada` (estado concurrente) | Devolver datos de otro tenant; dejar salir un `PDOException` | CON-01..07 × 2, PER-01..05, INT-* |
| B7 | AlmacenEvidencias | `guardar(tenant, evidencia)`, `leer(ubicación)` | Ubicación confirmada por su huella; bytes verificados | Devolver una ubicación no confirmada (fail-closed) | AHT-01..04, APP-11 |

## Entre frontend y backend

El contrato es **[api/openapi.yaml](../micro-his-qa/api/openapi.yaml) 1.1.0**. Las pruebas API-01..03 lo comparan con el código en 33 escenarios reales.

La regla que atraviesa los dos lados es la **`Idempotency-Key`**:
- la crea F5, una por resultado;
- F6 la guarda con la petición;
- B3 la consulta antes de cualquier otra regla (APP-15);
- B6 la hace única en la base.
