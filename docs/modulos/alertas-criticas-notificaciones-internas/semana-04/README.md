# Semana 4 — Arquitectura en capas y patrón repositorio

**Tarea:** continuar el MISMO Micro-HIS y el flujo «detección, envío, acuse y escalamiento de una alerta crítica»:
entrada MVC, interfaz Repository en el límite apropiado con adaptadores PDO e InMemory, controlador sin SQL ni
reglas, y análisis con diagrama de cómo un repositorio de datos compartido integraría el módulo.
**Entregable:** capas, responsabilidades y objetos reutilizables.

| Parte | Contenido |
|---|---|
| A | Núcleo MVC: `Router`, `Peticion`, `Respuesta`, `Vista` (escape HTML), `SesionDemo` |
| B | `AlertaController`, `Rutas`, plantillas y `public/index.php` |
| C | `InMemoryAlertaRepository` y prueba de contrato contra PDO e InMemory |
| D | Este README, diagramas y capturas |

## 1. Capas y responsabilidades (después de la semana 4)

| Capa | Objetos | Responsabilidad | Prohibido |
|---|---|---|---|
| Presentation · Web (MVC) | `public/index.php`, `Router`, `AlertaController`, `Vista`, plantillas | Traducir HTTP ↔ casos de uso; códigos 303/403/404/422/503 | SQL, reglas de negocio |
| Presentation · Consola | `ConsolaAlertas` | Lo mismo por línea de comandos | Ídem |
| Application | Casos de uso, `NotificarNivel`, `DespachadorNotificaciones` | Orquestar el flujo | PDO, HTML |
| Domain | `Alerta`, `PoliticaEscalamiento`, objetos de valor, **`AlertaRepository`** y demás puertos | Reglas RN-01 a RN-11 | Cualquier dependencia externa |
| Persistence | `PdoAlertaRepository`, **`InMemoryAlertaRepository`**, `NotificadorSimulado`, relojes | Implementar puertos | Reglas de negocio |

**¿Por qué la interfaz Repository está en Domain?** Porque la necesidad («guardar y recuperar alertas») la define el dominio y la usan los casos de uso. Los adaptadores dependen de ella, nunca al revés. Así Application no cambia si mañana el repositorio es la base HOSPITAL real o un servicio.

## 2. MVC

- **Modelo:** casos de uso + dominio (no se duplicó nada para la web).
- **Vista:** `plantillas/*.php`. Toda variable se imprime con `$e()` (`htmlspecialchars` con `ENT_QUOTES`). No se usa `extract()`: las plantillas reciben `$d` y `$e`.
- **Controlador:** `AlertaController` lee la petición, llama un caso de uso y elige vista o redirección (POST → 303 → GET). Las reglas llegan como excepciones y solo se traducen a códigos HTTP.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/` | Lista de alertas del tenant |
| POST | `/alertas` | Simula el evento del sistema origen (en la semana 5 llega por la API) |
| GET | `/alertas/{id}` | Detalle e historial |
| POST | `/alertas/{id}/acuse` | Acusar |
| POST | `/alertas/{id}/cierre` | Cerrar con nota |
| POST | `/escalamientos` | Simula al planificador |
| POST | `/sesion` | Cambia el usuario de **demostración** |

Recorrido completo de un acuse: `diagramas/mvc-secuencia-acuse.png`.

**Cómo ejecutarlo** (desde `micro-his/`, con el contenedor *healthy*): `php -S 127.0.0.1:8084 -t public` y abrir `http://127.0.0.1:8084/`.

## 3. Repository: PDO e InMemory con el mismo contrato

`diagramas/repository-adaptadores.png`. `pruebas/contrato/repositorio-contrato-pruebas.php` corre **las mismas 8 pruebas** contra ambos adaptadores:

| # | Comportamiento exigido |
|---|---|
| 1 | Guardar y buscar devuelve la misma alerta (historial idéntico, destinatarios por nivel y usuario) |
| 2 | Otro tenant, id inexistente o id no válido → `null` (RNF-01) |
| 3 | `buscarPorOrigen` por tenant + origen + id de origen |
| 4 | Mismo origen con otro id → `ErrorPersistenciaException` (RN-02) |
| 5 | Guardar de nuevo actualiza el estado sin duplicar eventos (RN-10) |
| 6 | Lo leído es una copia: cambios sin guardar no se ven |
| 7 | `pendientesDeAcuse` excluye acusadas y agotadas |
| 8 | `listar` solo del tenant, más recientes primero |

**Comprobación de que el contrato detecta diferencias:** al quitar a propósito el filtro de tenant de `InMemoryAlertaRepository.buscar()`, falló «[InMemory] RNF-01 …». Se restauró y vuelve a pasar.

## 4. Objetos reutilizables

| Objeto | Reutilizable por | Motivo |
|---|---|---|
| `AlertaRepository` + `InMemoryAlertaRepository` | Pruebas de cualquier capa, API de la semana 5 | Mismo contrato que PDO, sin base |
| `PuertoNotificacion` + `NotificadorSimulado` | Cualquier módulo que necesite «enviar» en el curso | Canal intercambiable (RNF-06) |
| `Reloj` + `RelojFijo` | Cualquier regla con plazos | Pruebas sin esperar (RNF-04) |
| `Router`, `Peticion`, `Respuesta`, `Vista` | La API de la semana 5 y otros micro-HIS | No conocen nada de alertas |
| `CodigoPaciente` | Módulos que reciben pacientes por código | Valida `PAC-00000` (RNF-02) |
| Casos de uso | Consola, web y API | Se escribieron una vez |

## 5. Análisis: integración con un repositorio de datos compartido (propuesta)

`diagramas/repositorio-compartido.png`. Supuesto, por confirmar con el grupo: PostgreSQL federado con **CENTRAL** (usuarios, roles, tenants) y **HOSPITAL** (datos operativos por tenant).

| Opción | Cómo sería | Evaluación |
|---|---|---|
| A. Leer tablas ajenas directamente (JOIN a resultados o expediente) | Alertas consulta las tablas de laboratorio | **Rechazada:** acopla esquemas, rompe la propiedad de datos y expone datos clínicos de más |
| B. Vistas de solo lectura publicadas por el dueño | Laboratorio publica una vista con lo mínimo | Aceptable para consultas, pero no avisa: Alertas tendría que sondear |
| C. **Bandeja compartida** `evento_clinico_critico` (contrato publicado) | El productor inserta; Alertas lee pendientes con `FOR UPDATE SKIP LOCKED`, llama `RegistrarAlertaCritica` y marca procesado | **Propuesta:** el único dato compartido es el evento. La unicidad `(tenant, origen, id_origen)` ya hace idempotente el reproceso (RN-02) |
| D. API o eventos entre servicios | Se evalúa en la semana 5 | Solo si hay gatillos medibles |

Reglas de la propuesta C:
- Alertas escribe **solo** en `alerta_critica`, `alerta_destinatario` y `alerta_evento`.
- Roles y tenant se consultan a `rbac` por contrato, nunca con JOIN a CENTRAL.
- La bandeja trae solo `id_origen`, `codigo_paciente`, `servicio`, `prioridad` y una descripción corta, sin nombres.
- Para integrarse basta un adaptador nuevo («lector de bandeja») en Persistence; dominio y casos de uso no cambian.

Pendiente de confirmar: el nombre y las columnas de la bandeja con los dueños de `ingreso-resultados-laboratorio` y de alergias clínicas, y si HOSPITAL es una base por hospital o una base con `tenant_id`.

## 6. Evidencia

- Capturas reales (Chromium 1280×800, generadas en el entorno de preparación con el servidor PHP y PostgreSQL 16):
  - `capturas/01-lista-vacia.png`
  - `capturas/02-detalle-enviada.png`
  - `capturas/03-error-403-no-notificado.png`: `jefe.servicio01` intenta acusar una alerta de nivel 0 (RN-07).
  - `capturas/04-detalle-acusada.png`
  - Sin errores de JavaScript ni desplazamiento horizontal.
- Pruebas: 80 en total y 80 pasaron en el entorno de preparación (PHP 8.3.6). La evidencia oficial es `evidencia/salida-pruebas.txt`, que genera Erick con:
  `php pruebas/ejecutar.php --evidencia=../semana-04/evidencia/salida-pruebas.txt`

Los .png se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.

## 7. Riesgos y pendientes

- `SesionDemo` **no es autenticación**: el usuario se elige en un selector. La semana 5 lo reemplaza por tokens ficticios con rol y tenant.
- Por eso todavía no se aplica RF-08 por rol: en la captura 04, `enf.turno01` ve el formulario de cierre. La regla de rol (solo médico tratante o jefe de servicio cierran) se aplica en la semana 5.
- Los formularios no tienen token CSRF. Queda registrado para la revisión de la semana 9 y para la API de la semana 5, que usa encabezado `Authorization`, no cookies.
