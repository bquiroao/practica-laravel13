# Semana 3 — Diseño arquitectónico, vistas y patrones

**Tarea:** iniciar el «Micro-HIS Alertas críticas y notificaciones internas» en PHP 8.2+ vanilla para el flujo
«detección, envío, acuse y escalamiento de una alerta crítica», con capas Presentation, Application, Domain y
Persistence, PDO con sentencias preparadas y pruebas de camino feliz, regla de dominio y error de persistencia.
**Entregable:** vista arquitectónica C4/UML del módulo y dependencias.

El código vive en `../micro-his/` (fuera de las carpetas de semana) porque las semanas 4 y 5 lo continúan.

## 1. Vistas arquitectónicas

| Vista | Archivo |
|---|---|
| C4 nivel 2 — contenedores | `diagramas/c4-contenedores.puml` / `.png` |
| C4 nivel 3 — componentes del núcleo | `diagramas/c4-componentes.puml` / `.png` |
| Dependencias entre capas | `diagramas/dependencias-capas.puml` / `.png` |

Los .png se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.

## 2. Capas y responsabilidades

| Capa | Contenido | Regla de dependencia |
|---|---|---|
| Presentation | `consola.php`, `ConsolaAlertas` | Solo invoca casos de uso y traduce excepciones a mensajes y códigos de salida. Sin SQL ni reglas. |
| Application | `RegistrarAlertaCritica`, `AcusarAlerta`, `EscalarAlertasVencidas`, `CerrarAlerta`, `ConsultarHistorial`, `ListarAlertas`, `NotificarNivel`, `DespachadorNotificaciones` | Orquesta; depende solo de Domain. |
| Domain | `Alerta`, `PoliticaEscalamiento`, `CodigoPaciente`, `EstadoAlerta`, `Prioridad`, `EventoHistorial`, excepciones e **interfaces** `AlertaRepository`, `PuertoNotificacion`, `DirectorioDestinatarios`, `Reloj` | No depende de nada. |
| Persistence | `PdoAlertaRepository`, `ConexionPdo`, `NotificadorSimulado`, `DirectorioDestinatariosEnMemoria`, `RelojSistema`, `RelojFijo` | Implementa las interfaces del dominio. |
| Composition Root | `bootstrap.php` | Único lugar que conoce los adaptadores concretos. |

**Cambio respecto al diseño de la semana 2:** se agregó `NotificarNivel` en Application porque registrar y escalar comparten la secuencia «resolver destinatarios → entregar → subir de nivel si nadie recibió». Sin esa clase, la lógica de RN-03 y RN-08 quedaba duplicada en dos casos de uso. También se agregó `ListarAlertas` para que la consola no use el repositorio directamente.

### Verificación de dependencias (PowerShell, desde `micro-his/`)

```powershell
Select-String -Path src/Domain/*.php -Pattern 'AlertasCriticas\\(Application|Persistence|Presentation)'   # sin resultados
Select-String -Path src/Application/*.php -Pattern 'PDO|AlertasCriticas\\(Persistence|Presentation)'     # sin resultados
Select-String -Path src/Presentation/*.php -Pattern 'SELECT|INSERT|PDO'                                  # sin resultados
```

## 3. Patrones aplicados

| Patrón | Dónde | Por qué |
|---|---|---|
| Puertos y adaptadores | Interfaces en Domain, implementaciones en Persistence | Cambiar canal, base o reloj sin tocar reglas (RNF-06) |
| Repository | `AlertaRepository` / `PdoAlertaRepository` | El dominio no conoce SQL |
| Composition Root | `bootstrap.php` | Inyección de dependencias manual, en un solo lugar |
| Entidad con historial | `Alerta` registra un `EventoHistorial` en cada transición | RN-10: cualquier camino que cambia el estado deja evento |
| Objeto de valor | `CodigoPaciente` | RN-01 y RNF-02: solo códigos `PAC-00000` |
| Reloj inyectable | `Reloj` / `RelojFijo` | RNF-04: el escalamiento se prueba sin esperar |

## 4. Persistencia (propuesta del Micro-HIS)

El esquema real de HOSPITAL para alertas **no está publicado**; las tablas son **propuesta del Micro-HIS**:
`alerta_critica` (UUID, `timestamptz`, `UNIQUE (tenant_id, origen, id_origen)` para RN-02), `alerta_destinatario`
y `alerta_evento` (clave `(alerta_id, orden)`, con un *trigger* que rechaza UPDATE y DELETE para RN-10).
PDO con `ERRMODE_EXCEPTION`, sin emulación de sentencias y siempre `prepare()` + `execute()`; los `PDOException` se
traducen a `ErrorPersistenciaException`, así Application no conoce PDO.

## 5. Pruebas

Ejecutor propio (`pruebas/ejecutar.php`): carga cada archivo en su propio ámbito, cuenta cualquier *warning* como falla y escribe la evidencia en UTF-8.

| Lo que pide la tarea | Prueba |
|---|---|
| Camino feliz con dobles | `aplicacion/registrar-pruebas.php` → «CAMINO FELIZ CA-01/CA-05» |
| Camino feliz con base de prueba | `integracion/pdo-pruebas.php` → «CAMINO FELIZ con base de prueba» |
| Regla de dominio | `unidad/*-pruebas.php` (RN-01 a RN-11) y «REGLA DE DOMINIO» en registrar |
| Error de persistencia con doble | `registrar-pruebas.php` → «ERROR DE PERSISTENCIA» (no se notifica a nadie) |
| Error de persistencia real | `pdo-pruebas.php` → «ERROR DE PERSISTENCIA real» (sin tablas visibles) |
| Escalamiento por tiempo | Reloj fijo: 10:14 no escala, 10:15 sí (unidad, aplicación e integración) |
| Acuse idempotente | Dominio, aplicación y base (un solo evento «acusada») |

**Resultado en mi entorno de preparación** (PHP 8.3.6 y PostgreSQL 16.15 en el puerto 55444): 49 pruebas, 49 pasaron.
La evidencia oficial es la que genera Erick con PHP 8.4 en `evidencia/salida-pruebas.txt`.

**Errores encontrados al probar y ya corregidos:**
- En PHP, `"«$campo»"` toma el `»` como parte del nombre de la variable, porque los bytes UTF-8 son válidos en identificadores. Se cambió a `{$campo}` y el ejecutor ahora convierte *warnings* en fallas.
- `Alerta` dependía de una constante de `PoliticaEscalamiento`; el nivel máximo pasó a ser regla de la entidad.

## 6. Límites honestos

- `docker-compose.yml` no se pudo ejecutar en el entorno de preparación (sin Docker). Las pruebas sí corrieron contra PostgreSQL 16 en el mismo puerto. `docker compose up` y el estado `healthy` se verifican en la máquina de Erick.
- No hay envíos reales: `NotificadorSimulado` solo escribe una bitácora.
- Los contratos con `ingreso-resultados-laboratorio` y alergias clínicas siguen **por confirmar** con sus dueños.
