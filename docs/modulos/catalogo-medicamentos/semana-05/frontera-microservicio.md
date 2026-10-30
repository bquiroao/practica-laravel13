# Frontera de microservicio — evaluación y migración razonada

**Decisión:** hoy el catálogo **sigue como módulo** del HIS (Laravel + PostgreSQL federado), con una
API v1 y una vista de solo lectura. Se separa en un servicio **solo** si se cumple alguno de los
gatillos medibles de la sección 7. No se construye infraestructura de producción en esta semana.

Diagramas: `diagramas/frontera-microservicio.png`, `diagramas/migracion-etapas.png`,
`diagramas/secuencia-api-alta.png`.

## 1. Por qué el catálogo es un buen candidato a frontera (pero no todavía)

- Tiene un **dueño claro** (el QF del catálogo) y reglas propias (RN-01…RN-06).
- Los demás módulos **solo leen**; casi no hay escrituras cruzadas.
- Su lenguaje es estable (código, versión, vigencia, clave de equivalencia).

En contra de separarlo hoy: un solo equipo, volumen pequeño, la consulta de uso en prescripciones
(RN-02) sería una llamada remota más, y una base aparte complica las claves foráneas.

## 2. Propiedad de datos

| Dato | Dueño | Otros módulos |
|---|---|---|
| `medicamentos` (versiones y vigencia) | Catálogo (único escritor) | Leen por API v1 o `v_medicamentos_vigentes` |
| `medicamentos_codigos` (ancla de FK) | Catálogo | Referencian con FK `ON DELETE RESTRICT` (propuesta) |
| Uso de un código en prescripciones | `prescripciones-alergias` | El catálogo pregunta (`ConsultaUsoPrescripciones`) |
| Existencias, lotes y precios | Farmacia / inventario | No están en el catálogo |

## 3. Comunicación

- **Lectura síncrona** por REST (`GET /api/v1/medicamentos…`) con timeout de 2 s en el cliente.
- **Escritura** solo desde el catálogo (UI del QF).
- **Eventos** (solo en la etapa E3): `MedicamentoRegistrado`, `MedicamentoVersionado`, `MedicamentoDadoDeBaja`, publicados con *outbox* en la misma transacción, para que los consumidores refresquen su caché.

## 4. Seguridad

- Token Bearer por petición; el **hospital (tenant) sale del token** y nunca del cuerpo (probado en `pruebas/api/api.php`).
- Mínimo privilegio con la matriz de `Permisos` (4 roles); `SISTEMA_PRESCRIPCIONES` solo lee.
- 404 para códigos de otro hospital: no se revela que existen.
- TLS entre servicios en el HIS real; en el Micro-HIS solo HTTP local.
- El registro no guarda tokens ni datos clínicos; el catálogo no contiene datos de pacientes.

## 5. Resiliencia

| Situación | Comportamiento |
|---|---|
| Base caída | 503 `PERSISTENCIA` + `Retry-After: 5`; sin datos parciales (transacciones). |
| Consumidor sin respuesta del catálogo | Reintenta solo `GET` (máx. 2, espera 0,5 s y 1 s). Usa su caché de lectura si la tiene, marcando la fecha de la copia. |
| Altas simultáneas | `EXCLUDE`/`UNIQUE` en la base → 409 `CONFLICTO_CONCURRENTE`, no 500. |
| Prescripciones no responde al eliminar | No se elimina (se trata como «usado»): 503 y se sugiere la baja. Falla en el lado seguro de RN-02. |

El último caso es una **propuesta**: hoy el adaptador de simulación lee una tabla local.

## 6. Observabilidad

- `X-Request-Id` en todas las respuestas y en el cuerpo de los errores; se reutiliza el del cliente.
- Una línea JSON por petición: `request_id`, método, ruta, estado, `ms`, usuario y hospital.
- Métricas a derivar del registro (semana 14): p95 de búsqueda, tasa de 5xx, tasa de 409 por código.

## 7. Consistencia

- **Dentro del catálogo:** fuerte. Cada operación es una transacción y la base repite RN-01/RN-04.
- **Entre módulos:** eventual para cachés de lectura (minutos). Se propone a `prescripciones-alergias` guardar `codigo` **y** `version`, para que un cambio posterior del catálogo no altere lo que se prescribió (**pendiente de acordar**).
- **RN-02 entre módulos:** sin FK (servicio aparte) hay una ventana entre «¿se usó?» y «eliminar». Se acepta porque eliminar es raro y solo aplica a registros erróneos recientes; la baja (que no borra) es la operación normal.

## 8. Gatillos medibles para separar el servicio

Se evalúan con datos de 2 meses; basta **uno**:

| ID | Gatillo | Umbral | Fuente |
|---|---|---|---|
| G1 | p95 de `GET /medicamentos` después de agregar índice `pg_trgm` | > 300 ms con ≥ 5 000 versiones | Registro JSON (`ms`) |
| G2 | Lecturas del catálogo desde otros módulos | > 50 peticiones/s sostenidas en hora pico | Registro JSON |
| G3 | Despliegues del HIS bloqueados por cambios del catálogo | ≥ 2 por mes | Historial de PR / Odoo |
| G4 | Hospitales que comparten un catálogo central con otro ritmo de cambios | ≥ 3 hospitales | Decisión de negocio |

## 9. Migración por etapas (strangler fig)

| Etapa | Qué se hace | Criterio de salida | Reversión |
|---|---|---|---|
| E0 (hoy) | Módulo con casos de uso e interfaz Repository (Micro-HIS S3–S5). | Pruebas en verde. | — |
| E1 | API v1 + `v_medicamentos_vigentes`; solo el catálogo escribe. | 0 lecturas directas de la tabla desde otros módulos. | Volver a dar `SELECT` sobre la tabla. |
| E2 | Consumidores leen por API o vista, con prueba de contrato del consumidor. | Contrato en verde en `prescripciones-alergias`. | Los consumidores vuelven a la vista. |
| E3 | Si se cumple un gatillo: servicio aparte con base propia; el módulo delega por HTTP; outbox. | p95 y tasa de 5xx iguales o mejores que en E2. | El módulo deja de delegar (mismo contrato). |
| E4 | Retirar la vista y las lecturas directas. | Sin consumidores de la vista por 1 mes. | Volver a publicar la vista. |

El Micro-HIS ya demuestra que E3 no cambia los casos de uso: solo cambia el adaptador detrás de
`RepositorioMedicamentos` (como PDO ↔ InMemory en la semana 4).
