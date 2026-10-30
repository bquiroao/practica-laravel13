# Semana 5 (parte C) — Frontera de microservicio y migración razonada

**Conclusión:** hoy **NO** conviene separar el módulo como microservicio. El diseño queda listo para hacerlo (puertos, API v1, datos propios), pero solo se separa cuando se cumpla un gatillo medible. Esto es diseño: no se construye infraestructura de producción.

## 1. Frontera candidata

`diagramas/frontera-microservicio.png`. El servicio candidato incluye la API v1, el despachador/escalador y las tablas `alerta_critica`, `alerta_destinatario` y `alerta_evento`. Es una buena frontera porque:
- **Datos propios y sin JOIN ajenos:** solo recibe el código de paciente.
- **Ciclo de vida propio:** el ciclo creada → enviada → acusada → escalada → cerrada no depende de otros módulos.
- **Carga distinta:** el proceso de plazos corre cada minuto, aunque no haya usuarios.

## 2. Gatillos medibles (cualquiera justifica pasar a la etapa 2)

| Gatillo | Umbral | Fuente |
|---|---|---|
| Revisión de plazos lenta | `EscalarAlertasVencidas` tarda más de 20 s en procesar las pendientes (debe correr cada minuto) | Tiempo por ejecución en la bitácora |
| Volumen | Más de 5 000 alertas por día en un tenant | `count(*)` por día en `alerta_critica` |
| Impacto de despliegues | Más de 2 despliegues al mes del monolito interrumpen el escalamiento más de 1 minuto | Registro de despliegues e historial |
| Canal real | Se integra un canal externo (SMS o push) con límites de tasa propios | Decisión del grupo |

Hoy ninguno se cumple ni se puede medir: el sistema es de curso y no hay volumen real.

## 3. Propiedad de datos, comunicación y consistencia

| Aspecto | Decisión |
|---|---|
| Propiedad de datos | Alertas es dueño único de sus 3 tablas. Pacientes, resultados y usuarios quedan en sus módulos y no se copian; solo viajan el código de paciente y el usuario. |
| Comunicación de entrada | Síncrona HTTP (`POST /api/v1/alertas`), idempotente por `id_origen` (RN-02): el productor puede reintentar sin duplicar. Alternativa asíncrona: la bandeja `evento_clinico_critico` de la semana 4. |
| Comunicación de salida | `PuertoNotificacion`. En el curso, un adaptador simulado. |
| Consistencia | Fuerte **dentro** del módulo (transacción PDO por alerta). **Eventual** con los productores: si la API no responde, el productor reintenta y la idempotencia evita duplicados. No hay transacciones distribuidas. |
| Seguridad | Token con rol y tenant emitido por `rbac`. El servicio valida el token y nunca acepta el tenant en el cuerpo. |

## 4. Resiliencia

- **Reintentos de entrega:** hasta 3 por destinatario (RN-08); si se agotan, escalamiento inmediato.
- **Productor → API:** reintento con espera creciente (1 s, 2 s, 4 s) ante 503. Es seguro porque el registro es idempotente.
- **Base caída:** 503 `persistencia_no_disponible`. La alerta no se notifica sin estar guardada; la prueba «ERROR DE PERSISTENCIA» lo verifica.
- **Escalador detenido:** las alertas siguen en `pendientesDeAcuse` y se escalan en la siguiente ejecución, porque los plazos se calculan con `plazo_desde` y no con temporizadores en memoria.

## 5. ¿Cola o eventos para las notificaciones?

| Opción | A favor | En contra | Decisión |
|---|---|---|---|
| Envío en línea (actual) | Simple, sin infraestructura; los reintentos ya existen | El registro espera los intentos | **Se mantiene** en el curso |
| Bandeja en PostgreSQL (outbox) | Sin infraestructura nueva; transaccional con la alerta | Requiere un proceso que la lea | **Primer paso** si se cumple un gatillo (etapa 2) |
| Cola de mensajes (RabbitMQ u otra) | Escala y desacopla canales | Infraestructura y operación nuevas; un componente más que puede fallar | Solo en la etapa 4, si la bandeja no alcanza |

No se construye ninguna cola: la decisión queda condicionada a los gatillos.

## 6. Migración por etapas

`diagramas/migracion-etapas.png`. Cada etapa conserva el contrato API v1 y las pruebas de contrato del repositorio, que son la red de seguridad del cambio.

| Etapa | Qué cambia | Criterio para avanzar |
|---|---|---|
| 0 | Micro-HIS actual | 90 pruebas en verde |
| 1 | Se porta al monolito Laravel con las mismas tablas | Pruebas de contrato en verde contra el adaptador Laravel |
| 2 | El despachador/escalador pasa a un proceso aparte con bandeja | Gatillo cumplido; tiempo de revisión menor a 20 s |
| 3 | Base de datos propia; productores solo por API o bandeja | Ningún JOIN externo a las tablas de alertas (revisión de consultas) |
| 4 | Cola de mensajes (opcional) | La bandeja supera su capacidad medida |
