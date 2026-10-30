# Checklist de la revisión y pre-revisión del autor

La columna **Autor** es mi pre-revisión, hecha sobre el código del Micro-HIS. Las columnas **R1** y **R2** las llenan los revisores en la preparación.

## 1. Checklist (14 ítems)

| ID | Pregunta | Cómo verificar | Autor | R1 | R2 |
|---|---|---|---|---|---|
| C-01 | ¿RN-01 se garantiza en la base y no solo con una validación previa? | `EsquemaMicroHis` (`ux_asignacion_cama_activa`) y prueba «RN-01 simultáneo…» | Sí | | |
| C-02 | ¿Admisión, asignación y eventos se guardan en una sola transacción, con reversión ante cualquier falla? | `PdoAdmisionRepository::registrar` y prueba «Error de persistencia a mitad de la transacción revierte todo» | Sí | | |
| C-03 | ¿RN-02 (una admisión activa por paciente) está en la base y en el caso de uso? | `ux_admision_paciente_activa`; prueba «RN-02 en la base…» | Sí | | |
| C-04 | ¿RN-03 y RN-04 se aplican igual en la consulta y en el registro? | `ConsultarCamasDisponibles` y `Admision::abrir` | Sí, pero duplicado (RT-02) | | |
| C-05 | ¿Cada operación exige rol (RN-06) en el caso de uso y no en la UI? | `exigirRol` en los 4 casos de uso | Sí | | |
| C-06 | ¿El tenant sale del token o la sesión, y **toda** consulta filtra por `tenant_id`? | `AutenticadorTokens`; `WHERE tenant_id` en los repositorios PDO | Sí en la API; en la web viene del selector de demostración (RT-05) | | |
| C-07 | ¿Los errores de la API siguen el formato único y el estado correcto del contrato? | `ApiKernel::error`, `MapaErrores`; `prueba-api.php` | Sí | | |
| C-08 | ¿La traducción de errores de la base es robusta? | `PdoAdmisionRepository::traducir` | Parcial (RT-01) | | |
| C-09 | ¿Los eventos de trazabilidad son inmutables y llevan usuario, rol, hospital y solicitud? | Trigger `tr_evento_admision_inmutable`; `EventoAdmision` | Sí | | |
| C-10 | ¿Ninguna salida (pantalla, API o registro) expone nombres o datos clínicos? | `ResultadoAdmision`, `aJson`, el registro JSON | Sí | | |
| C-11 | ¿Se escapa toda salida HTML y toda SQL usa `prepare()` + `execute()`? | `Vista`, `$e(...)`; `grep -n "->query(\|->exec(" src` | Sí | | |
| C-12 | ¿El módulo solo **lee** datos de otros módulos? | `CatalogoCamas` y `DirectorioPacientes` sin métodos de escritura | Sí | | |
| C-13 | ¿Las dependencias son las estrictamente necesarias y apuntan hacia Domain? | `prueba-dependencias.php` | Sí | | |
| C-14 | ¿Las formas de cambiar estado desde el navegador están protegidas contra peticiones forjadas? | Formularios POST de la web MVC | No (RT-04) | | |

## 2. Hallazgos de la pre-revisión del autor

| ID | Ítem | Ubicación | Descripción | Severidad | Acción propuesta | Estado |
|---|---|---|---|---|---|---|
| RT-01 | C-08 | `PdoAdmisionRepository.php`, método `traducir` | Distingue RN-01 de RN-02 buscando el nombre del índice dentro del **texto** del mensaje de PostgreSQL (PDO no entrega el nombre de la restricción por separado). Si alguien renombra el índice en el esquema, un conflicto real terminaría como `ERROR_PERSISTENCIA` y ninguna prueba lo detectaría a tiempo si no se corre la integración | Mayor | Centralizar los nombres de los índices en constantes de `EsquemaMicroHis` que usen tanto el esquema como `traducir()`, y exigir la prueba de integración en el criterio de entrada | Abierto |
| RT-02 | C-04 | `AdmitirPacienteYAsignarCama`, `ConsultarCamasDisponibles` | «Cama disponible» se arma en dos casos de uso | Menor | Refactor `DisponibilidadCamas` (semana 7) | Abierto |
| RT-03 | C-04 | `AdmisionRepository::camasOcupadas` | Trae **todos** los ids ocupados del hospital para verificar una sola cama | Menor | Agregar `camaOcupada(tenant, camaId): bool` y conservar la lista solo para la consulta | Abierto |
| RT-04 | C-14 | `vistas/camas.php` (POST `/admisiones`) | La web MVC no tiene token anti-CSRF. En el Micro-HIS no hay sesión real; en el HIS sí sería un riesgo | Mayor (para el HIS) | Token CSRF por sesión, o usar solo la API con Bearer desde el cliente | Abierto |
| RT-05 | C-06 | `formulario.php`, `UsuariosFicticios` | En la web el usuario (y con él, rol y hospital) se elige con un campo del formulario. Es de demostración y está marcado, pero no debe pasar al HIS | Mayor (para el HIS) | En el HIS, el contexto sale de la sesión; se mantiene el aviso (B-08) | Abierto |
| RT-06 | C-06 | `UsuariosFicticios::contexto` | Un usuario desconocido lanza `AccesoDenegado` (403). Lo correcto sería «no autenticado» (401) | Menor | Excepción propia, convertida a 401 en la web | Abierto |

Ningún hallazgo crítico en la pre-revisión. Son **observaciones del autor**; la severidad final la decide la reunión.
