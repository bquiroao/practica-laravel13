# Contratos de entrada/salida entre componentes

Diseño propuesto, no API desplegada. IDs/estados ficticios en ejemplos; ningún DTO concede permisos. No incluir nombre completo, diagnóstico, token o credenciales en evidencia/log.

## UI → cliente → controlador

| Operación | Entrada | Salida observable |
|---|---|---|
| Preparar selección | patientRef/encounterRef del contexto permitido, testCodes locales | Draft sin efecto; errores de forma junto a campos. |
| Emitir | POST /api/v1/patients/{patient}/encounters/{encounter}/lab-orders, cuerpo testCodes:[TEST-A,TEST-B], sesión por mecanismo HIS | 201 id/refs/pruebas/estado confirmado; code/message/requestId en error. Actor no editable en cuerpo. |
| Consultar | GET mismo recurso colección, contexto vigente | 200 lista y estado persistido; [] solo lectura vacía confirmada. |
| Cancelar revisión | Acción local antes de enviar | Retorno sin POST; después de enviar no significa rollback remoto. |

Mapeo propuesto: 400 formato, 401 sesión, 403 permiso, 422 contexto o pruebas inválidos, 503 dependencia/persistencia. Nunca serializar stack SQL ni resultado privado de otro contexto. POST desconocido por timeout no es equivalente a 503 confirmado sin escritura. Controlador debe conservar esa distinción antes de activar reintentos.

## Aplicación → puertos después del refactor

| Puerto | Entrada | Salida y precondición |
|---|---|---|
| ClinicalAuthorization | actor autenticado, paciente, episodio y contexto tenant confiable en integración | ALLOWED / DENIED / UNAVAILABLE; se evalúa antes de leer datos. Tenant no existe en modelo demo actual y debe acordarse antes de multitenant real. |
| ClinicalContextValidator | paciente/episodio autorizados | VALID / INVALID / UNAVAILABLE; no convierte indisponibilidad en permiso. |
| LabCatalog | lista no vacía de códigos canónicos | VALID / INVALID / UNAVAILABLE; rechaza selección completa no disponible. No administra catálogo. |
| OrderLifecyclePolicy | contexto de operación; estado actual/siguiente para actualización | initialStatus o allowsTransition; valores clínicos se acuerdan con laboratorio, TEST_* son demo. |
| Repository.save | Order inmutable validada | id solo tras commit; PersistenceFailure si cabecera/partidas no quedan completas. |
| Repository.find | paciente/episodio ya autorizados | lista persistida / PersistenceFailure; vacío no es error. |

Contratos triestado son propuesta compatible con idea de semana 2. ClinicalRules de semana 3 hoy usa bool; no se afirma sustitución binaria sin migrar consumidores. Repository de semana 3 se preserva sin añadir funciones universales de EMR/catálogo.

## Resultado para UI

```json
{"status":"CREATED","order":{"id":"ORDER-DEMO-1","patient":"PAC-DEMO","encounter":"EPI-DEMO","tests":["TEST-A","TEST-B"],"status":"TEST_INITIAL"},"requestId":"REQ-DEMO-1"}
```

Este es DTO propuesto, no salida actual de Issue de semana 3 (que devuelve status e id). Hidratación autorizada del detalle requiere consulta posterior o devolución tipada acordada; evitar afirmar campos no emitidos actualmente.

```json
{"status":"UNAVAILABLE","message":"No se pudo consultar el estado actual.","requestId":"REQ-DEMO-2"}
```

En interfaz conservar selección no sensible solo para el mismo contexto y con permiso; al cambiar sesión/hospital limpiar. Estado mostrado incluye origen/fecha cuando contrato lo incorpore; no fabricar esos metadatos en cliente.

## Seguimiento desde laboratorio

Contrato interno separado: source autenticado, orderId y nextStatus; autorización de origen antes de lookup, política de transición y comparación de estado esperado al escribir. UPDATED/REJECTED/NOT_FOUND/CONFLICT/UNAVAILABLE distinguidos (ejemplo semana 2). No permitir que OrderStatusList envíe estado libre ni que referencia de origen en cuerpo sustituya autenticación.