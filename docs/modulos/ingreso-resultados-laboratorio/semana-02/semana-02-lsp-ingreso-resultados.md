# Semana 2 — LSP en el ingreso de resultados de laboratorio

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Ingreso de resultados de laboratorio |
| **Tema de la semana** | Proceso y modelo de diseño; principios SOLID |
| **Principio aplicado** | Liskov Substitution Principle (LSP) |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Email** | mlopez58@miumg.edu.gt |
| **Fecha** | Octubre 2026 |

## 1. Objetivo y alcance

Aplicar LSP al flujo «captura y corrección controlada de resultados pendientes». El diseño debe impedir que un subtipo de resultado cambie silenciosamente el contrato esperado para una captura o corrección. Una corrección solo crea una nueva versión mientras el resultado vigente siga pendiente; se exige motivo, se conserva la versión anterior y la versión nueva permanece pendiente.

El ejemplo ejecutable se concentra en el dominio de captura/corrección y en el límite de persistencia. No define valores clínicos, rangos de referencia, unidades por prueba, interfaces con analizadores, permisos definitivos ni validación bioquímica. Usa datos ficticios.

**Nota de alcance:** la captura de Semana 2 solicita explícitamente LSP. La matriz del `README.md` base del repositorio nombra ISP para este módulo en Semana 2. Este documento sigue el principio indicado en la captura recibida; no modifica la matriz general del repositorio.

## 2. Requerimientos funcionales y no funcionales

### Requerimientos funcionales

| ID | Requerimiento |
|---|---|
| RF-01 | Capturar un valor para un ítem de orden existente y registrar la primera versión con estado pendiente. |
| RF-02 | Corregir solo el resultado vigente que continúe pendiente; la corrección requiere valor nuevo y motivo no vacío. |
| RF-03 | Una corrección produce una versión nueva enlazada a la previa; nunca sobrescribe ni borra el historial. |
| RF-04 | La versión corregida permanece pendiente de validación; este flujo no la valida ni la publica. |
| RF-05 | Un resultado validado no puede sustituirse por un pendiente ni corregirse mediante el flujo de pendientes. |
| RF-06 | El servicio depende de un contrato de repositorio; cualquier implementación sustituible conserva búsqueda, creación inicial y append condicional de corrección. |
| RF-07 | Si la versión cambia entre lectura y escritura, la corrección se rechaza como conflicto y no modifica el historial. |

### Requerimientos no funcionales

| ID | Requerimiento |
|---|---|
| RNF-01 | El ejemplo corre en PHP 8.2+ sin framework ni dependencias de terceros. |
| RNF-02 | Las versiones son objetos inmutables y usan tipos explícitos para distinguir resultados pendientes y validados. |
| RNF-03 | Los conflictos y rechazos de negocio se comunican mediante resultados explícitos, no se convierten en éxito ni se silencian. |
| RNF-04 | Las implementaciones del repositorio se verifican con el mismo contrato de comportamiento. |
| RNF-05 | La capa de dominio no realiza I/O y puede probarse sin un servidor de base de datos. |

## 3. Criterios de aceptación

| ID | Criterio verificable | Evidencia |
|---|---|---|
| CA-01 | Capturar un valor no vacío sin resultado vigente crea versión 1 pendiente. | `php tests/run.php`. |
| CA-02 | Corregir una versión pendiente con valor y motivo crea versión 2 pendiente y enlaza la versión 1. | Pruebas de corrección/versionado en `tests/run.php`. |
| CA-03 | Valor vacío o motivo de corrección vacío es rechazado; no se crea una nueva versión. | Pruebas de validación de entrada. |
| CA-04 | Un resultado validado no se puede corregir a través del servicio de pendientes. | Prueba con un `ValidatedLabResult` en el repositorio. |
| CA-05 | Una corrección basada en una versión que ya dejó de ser vigente falla con conflicto y no altera la versión actual. | Prueba de escritura condicional y versiones obsoletas. |
| CA-06 | Los dos adaptadores de `PendingResultRepository` satisfacen el mismo contrato observable. | Suite de contrato ejecutada sobre adaptador en memoria y decorador de registro. |
| CA-07 | PHP informa sintaxis válida en todos los archivos de fuente y pruebas. | Comandos `php -l` documentados. |

## 4. Diseño antes: subtipo que viola LSP

El contrato base sugiere que todo resultado se puede corregir. El subtipo validado conserva la firma, pero restringe la operación lanzando una excepción que el consumidor no espera. Sustituir `LabResult` por `ValidatedLabResult` cambia el comportamiento y obliga al servicio a inspeccionar el tipo concreto.

```php
abstract class LabResult
{
    abstract public function correct(string $value, string $reason): LabResult;
}

final class PendingLabResult extends LabResult
{
    public function correct(string $value, string $reason): LabResult
    {
        return new PendingLabResult($value, $reason);
    }
}

final class ValidatedLabResult extends LabResult
{
    public function correct(string $value, string $reason): LabResult
    {
        throw new LogicException('Un resultado validado es inmutable.');
    }
}
```

**Problema observable:** código que recibe un `LabResult` y llama `correct()` funciona con el tipo pendiente, pero falla con el subtipo validado. El subtipo promete el mismo contrato sintáctico y rompe su contrato conductual. Capturar esa excepción en cada consumidor no arregla la abstracción.

## 5. Diseño después: tipos separados y contrato sustituble

El diseño no presenta los estados mutuamente excluyentes como subtipos de una entidad mutable con todas las operaciones. `PendingLabResult` y `ValidatedLabResult` son valores finales distintos que comparten los datos inmutables de `ResultVersion`; solo el pendiente expone la operación de crear una corrección. El servicio consulta el contrato `PendingResultRepository` y aplica una escritura condicional contra el ID de la versión vigente.

```text
CaptureOrCorrectPendingResult
              |
              v
     PendingResultRepository
       /                 \
InMemory adapter   Recording decorator
       |
       +-- current(item) -> LabResultRecord
       +-- appendCorrection(expectedVersionId, newPendingVersion)

LabResultRecord
     /         \
PendingLabResult  ValidatedLabResult
 correctable      no correction operation
```

| Diseño anterior | Diseño corregido |
|---|---|
| Un tipo base mutable promete `correct()` para todo resultado. | Estados con capacidades distintas no heredan un método que uno de ellos no puede cumplir. |
| `ValidatedLabResult` rompe sustitución mediante excepción. | La corrección recibe solo `PendingLabResult`; los validados son una variante separada de solo lectura. |
| La aplicación necesita revisar tipos concretos o capturar excepciones. | La aplicación depende del puerto `PendingResultRepository` y maneja resultados de operación explícitos. |
| Una corrección podría sobrescribir el dato previo. | Cada corrección agrega una nueva versión con vínculo y motivo. |
| La comprobación de estado ocurre solo al cargar la pantalla. | El repositorio confirma atómicamente que el ID esperado aún sea la versión pendiente vigente. |

La composición y la segregación del modelo eliminan el falso polimorfismo; LSP se aplica al puerto de persistencia, cuyos adaptadores se someten al mismo contrato de comportamiento.

## 6. Responsabilidades y dependencias

| Componente | Responsabilidad | Dependencias |
|---|---|---|
| `ResultVersion` | Datos inmutables, secuencia y enlace entre versiones. | Ninguna infraestructura. |
| `PendingLabResult` | Representar una versión que aún admite corrección y producir un reemplazo enlazado. | `ResultVersion`. |
| `ValidatedLabResult` | Representar evidencia validada sin exponer operación de corrección. | `ResultVersion`. |
| `PendingResultRepository` | Puerto para leer versión actual, guardar captura inicial y añadir corrección si coincide la versión vigente. | Tipos de dominio; no framework. |
| `CaptureOrCorrectPendingResult` | Coordinar captura/corrección, validar entradas, proteger resultado validado y reportar conflicto. | Interfaz `PendingResultRepository`. |
| `InMemoryPendingResultRepository` | Adaptador ejecutable para verificar reglas e historial sin infraestructura externa. | Dominio y memoria del proceso. |
| `RecordingPendingResultRepository` | Decorador de prueba que registra llamadas y delega en el adaptador en memoria. | Interfaz y adaptador envuelto. |
| `tests/run.php` | Pruebas de aceptación y contrato compartido por adaptadores. | PHP CLI. |

## 7. Evidencia verificable

El ejemplo autónomo está en [`ejemplo-lsp/`](ejemplo-lsp/README.md).

Desde `docs/modulos/ingreso-resultados-laboratorio/semana-02/ejemplo-lsp/`, ejecutar:

```powershell
php -l src/LabResultWorkflow.php
php -l tests/run.php
php tests/run.php
```

La suite verifica captura, nueva versión enlazada, motivo obligatorio, rechazo de resultados validados, concurrencia por versión esperada y el mismo contrato de repositorio sobre dos sustitutos. No requiere instalación ni datos clínicos.

## 8. Trazabilidad

| Requerimiento / criterio | Diseño | Pruebas |
|---|---|---|
| RF-01, CA-01 | `capture()` y `saveInitial()` | Captura inicial pendiente; no duplicar resultado vigente. |
| RF-02, RF-03, RF-04, CA-02, CA-03 | `correct()` y `PendingLabResult::corrected()` | Motivo obligatorio, versión siguiente, link a anterior y estado pendiente. |
| RF-05, CA-04 | Tipos separados `PendingLabResult` / `ValidatedLabResult` | Servicio rechaza resultado validado. |
| RF-07, CA-05 | `appendCorrection(expectedVersionId, replacement)` | Versión obsoleta produce conflicto y no cambia estado actual. |
| RF-06, RNF-04, CA-06 | Puerto `PendingResultRepository` | Suite de contrato con ambos adaptadores. |
| RNF-01, RNF-02, RNF-03, RNF-05, CA-07 | PHP vanilla, valores inmutables, resultado explícito | Lint y runner PHP. |

## 9. Límites y conclusión

El ejemplo demuestra invariantes en memoria y el contrato sustituible de persistencia; no implementa base PostgreSQL, autenticación, rango clínico, validación bioquímica ni API. La especificación de datos del proyecto considera `lab_result_version` y requiere inmutabilidad de resultados validados y correcciones como versiones enlazadas con motivo. Las políticas exactas de identidad, auditoría y transacción deben concretarse al implementar.

La lección LSP no es «hacer que cualquier subtipo acepte cualquier operación». Es definir abstracciones que representen capacidades reales. Separar los estados que no comparten las mismas operaciones y probar adaptadores contra el mismo contrato evita que una sustitución cambie silenciosamente el resultado del flujo.

## 10. Referencias

1. Consigna Semana 2 recibida: LSP aplicado a captura y corrección controlada de resultados pendientes, RF/RNF, criterios, diseño antes/después y ejemplo SOLID.
2. Semana 1 del módulo: [semana-01-uml-ingreso-resultados.md](../semana-01/semana-01-uml-ingreso-resultados.md).
3. `openspec/changes/postgresql-federated-schema-rebuild/specs/hospital-clinical-schema/spec.md`: entidades HOSPITAL previstas y regla de inmutabilidad/versionado de resultados validados.
