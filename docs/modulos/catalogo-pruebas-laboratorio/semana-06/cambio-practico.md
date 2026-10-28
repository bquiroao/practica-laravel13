# Registro del cambio práctico defendido

> **Nota:** el cambio real lo plantea el ingeniero durante la evaluación. Este registro está preparado con un cambio probable y con el formato listo; si el cambio solicitado es otro, se reemplazan las secciones 1 a 5 con el mismo formato.

## 1. Cambio solicitado

«Una prueba puede aceptar **más de un tipo de muestra**; por ejemplo, la glucosa se puede procesar en plasma (tubo tapa gris) o en suero (tubo tapa roja), cada uno con su propio contenedor, volumen y estabilidad.»

## 2. Impacto por capa

| Capa / artefacto | Hoy | Cambio |
|---|---|---|
| UML S1 | CU-02 define un requisito de muestra | CU-02 define uno o más requisitos; el flujo de actividad agrega «¿Agregar otro tipo de muestra?» |
| RF/RNF S2 | RF-02 habla de «los requisitos» de la prueba | RF-02: «uno o más requisitos de muestra, sin repetir tipo de muestra»; nuevo criterio de aceptación para el tipo repetido |
| Domain | `LabTest` tiene un `RequisitoMuestra` | `LabTest` tiene una lista de `RequisitoMuestra` (mínimo 1, tipos distintos); RN-03 se aplica a cada uno |
| Application | `RegistrarPrueba` sin cambios de flujo | Igual; la validación nueva vive en el dominio |
| Persistence | Requisitos como columnas de `lab_test` | Tabla hija `lab_test_specimen_requirement` (lab_test_id, specimen_type, …) guardada en la misma transacción (RN-07) |
| API S5 | `requisitos_muestra` es un objeto | `requisitos_muestra` pasa a ser un arreglo → cambio incompatible: se publica en `/v2` y `/v1` devuelve el primer requisito mientras Órdenes migra |
| Pruebas | T-01, T-09, T-13 esperan un objeto | Nuevas pruebas: dos requisitos válidos; tipo repetido → 422; rollback si falla la tabla hija |

## 3. Regla de vigencia

Agregar o quitar un tipo de muestra **es un cambio de requisitos**, así que se registra como versión nueva (RN-05); las órdenes anteriores mantienen la versión con un solo tipo.

## 4. Por qué la arquitectura lo soporta

- El cambio queda en Domain y Persistence; Presentation y Application casi no cambian (separación de capas y SRP).
- El Repository oculta la tabla hija: los casos de uso siguen llamando a `guardarNuevaVersion`.
- El versionado de la API evita romper a Órdenes.

## 5. Estimación

| Tarea | Archivos |
|---|---|
| Dominio y validación | `LabTest.php`, `RequisitoMuestra.php` |
| Esquema y adaptadores | `sql/esquema.sql`, `PdoLabTestRepository.php`, `InMemoryLabTestRepository.php` |
| API v2 | `ApiLabTestController.php`, `contrato-api.md` |
| Pruebas | 3 pruebas nuevas y ajuste de T-01, T-09 y T-13 |
