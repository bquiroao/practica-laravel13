# Semana 4 — Arquitectura en capas y patrón Repository

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Recepción de muestras y códigos identificadores |
| **Tema de la semana** | Arquitectura en capas, MVC y patrón Repository |
| **Flujo** | «Recepción, identificación inequívoca y rechazo controlado de muestra» |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## 1. Introducción

Esta semana continúa el mismo flujo de trabajo del Micro-HIS del módulo de recepción, con cuatro objetivos puntuales:

1. organizar la entrada HTTP con MVC;
2. formalizar el patrón Repository para la recepción y el rechazo de muestras;
3. identificar objetos reutilizables y límites por capa; y
4. analizar la integración con el repositorio compartido del HIS y la trazabilidad del evento.

La idea central es que la lógica del negocio no se mezcle con la entrada HTTP ni con la persistencia. El elemento de decisión, la validación clínica y la identidad del recipiente deben mantenerse en el dominio y en la aplicación, no en controladores ni en SQL.

## 2. Qué cambió respecto de la semana 3

| Aspecto | Semana 3 | Semana 4 |
|---|---|---|
| Entrada HTTP | flujo implícito con validaciones y orquestación directa | MVC: controlador, formulario y vista |
| Reglas de negocio | presentes en la lógica de dominio de la recepción | siguen en dominio/aplicación; el controlador solo coordina |
| Persistencia | adaptador aislado para contenedor de muestra | Repository con interfaz del dominio y adaptadores PDO e InMemory |
| Trazabilidad | decisiones y eventos considerados | explícita en un objeto de custodia y en un evento de auditoría |
| Pruebas | validación del flujo principal | validación del contrato del repositorio y del control de entrada |
| Intercambialidad | parcial | clara: la misma operación funciona con memoria o PDO |

La arquitectura en capas no cambia la lógica de la semana 3; la reorganiza para clarificar responsabilidades.

## 3. Capas y responsabilidades

| Capa | Responsabilidad | Ejemplos de elementos |
|---|---|---|
| Presentación | recibir HTTP, validar el formato, decidir qué acción invoca y presentar la respuesta | `ReceptionController`, `ReceiveSampleForm`, `ReceptionView`, `ErrorView` |
| Aplicación | orquestar casos de uso, coordinar transacciones y persistencia, preparar comandos y resultados | `RegisterSampleReception`, `RejectSpecimen`, `AssignSpecimenId` |
| Dominio | definir reglas, entidades, valores y puertos de acceso a datos | `Specimen`, `SampleReception`, `OrderReference`, `SpecimenId`, `RepositoryPort` |
| Persistencia | implementar puertos del dominio con adaptadores concretos | `PdoSpecimenRepository`, `InMemorySpecimenRepository` |
| Composición | conectar la aplicación con los puertos y elegir el adaptador | `Container`, `Factory`, `Bootstrap` |

### Regla principal

El dominio y la aplicación forman el "modelo" de MVC. El controlador no ejecuta reglas clínicas ni conoce la estructura de la base de datos. Solo delega y entrega la respuesta final a una vista.

## 4. MVC en la entrada de la recepción

El flujo inicia con una petición HTTP que llega al controlador. Ese controlador no sabe cómo persistir ni cómo validar la lógica de aceptación; solo coordina.

```php
public function receive(Request $request): Response
{
    try {
        $command = ReceiveSampleForm::toCommand($request);
    } catch (InvalidData $e) {
        return ErrorView::invalidData($e->fields);
    }

    $result = $this->registerSampleReception->execute($command);
    return ReceptionView::render($result);
}
```

### Responsabilidad por pieza

| Pieza | Hace | No hace |
|---|---|---|
| Controlador | coordina la entrada y la salida | no valida reglas del dominio |
| Formulario | valida formato y estructura de la petición | no valida si la orden corresponde al paciente |
| Vista | define el formato de la respuesta | no consulta el dominio |
| Modelo (Aplicación + Dominio) | decide, ejecuta y persiste | no conoce HTTP |

### Reglas de control del controlador

- no debe incluir SQL;
- no debe saber el esquema de la base de datos;
- no debe decidir si la muestra es válida sin la lógica del dominio;
- no debe hacer `new` de entidades complejas ni de servicios de negocio;
- debe delegar en la capa de aplicación y devolver una vista determinada.

## 5. Patrón Repository

El patrón Repository es clave para aislar la lógica del negocio de la tecnología de persistencia. En este módulo, se separan las operaciones sobre la muestra y su custodia.

### 5.1 Interfaz del dominio

La interfaz debe vivir en la capa que necesita el servicio: el dominio.

```php
interface SpecimenRepository
{
    public function save(SampleReception $reception): void;
    public function findById(SpecimenId $id): ?SampleReception;
    public function findByOrderAndItem(string $orderId, string $itemId): array;
}
```

### 5.2 Adaptadores

| Adaptador | Propósito | Ventaja |
|---|---|---|
| `PdoSpecimenRepository` | persistencia real con PostgreSQL o SQLite para pruebas | refleja el almacenamiento final |
| `InMemorySpecimenRepository` | pruebas y prototipo rápido | permite probar comportamiento sin base de datos |

La decisión de cuál usar se toma por composición o factoría, no por lógica de negocio.

### 5.3 Regla de dependencia

- La persistencia depende del dominio.
- El dominio no depende de PDO, de Laravel ni de un framework web.
- Las reglas del flujo reciben un puerto y no conocen la implementación real.

Esto permite cambiar de almacenamiento sin reescribir la decisión de negocio.

## 6. Objetos reutilizables del módulo

| Objeto | Tipo | Función |
|---|---|---|
| `OrderReference` | valor | identifica la orden sin depender del texto libre del operador |
| `SpecimenId` | valor | identificador único del recipiente, separado del código externo |
| `PatientId` | valor | vinculación con la identidad del paciente |
| `SampleType` | valor | especifica el tipo de muestra y su regla de aceptación |
| `ReceptionDecision` | agregado o resultado | encapsula aceptación, rechazo o pendiente |
| `CustodyEvent` | agregado | conserva el evento de recepción, transporte, rechazo y seguimiento |
| `RejectReason` | valor | define el motivo de rechazo y evita texto libre ambiguo |

Estos elementos ayudan a evitar errores de tipo y a mantener un dominio explícito, más legible y menos frágil.

## 7. Vista dinámica: una recepción a través de MVC y Repository

Un caso típico de flujo es:

1. el usuario envía la referencia de la orden y el ítem;
2. el controlador valida formato;
3. el caso de uso busca la orden y prepara la recepción;
4. el dominio valida correspondencia, identidad y condiciones físicas;
5. el Repository persiste la recepción o el rechazo;
6. la vista devuelve un resumen con el `specimenId`, el motivo o la confirmación.

El punto clave es que el flujograma de la tarea no pasa por SQL ni por la capa web de manera directa.

## 8. Integración con el repositorio compartido del HIS

El módulo reutiliza la misma lógica de manejo de identidad y trazabilidad que exige el HIS. En práctica, el repositorio de datos compartido debe preservar:

- la relación entre orden, paciente, servicio y tipo de muestra;
- el `specimenId` único para cada recipiente;
- la auditoría de la decisión y del motivo;
- la consistencia del estado del recipiente (pendiente, recibido, rechazado, en custodia).

No debe existir un escenario donde la recepción se complete a partir de un texto libre sin correlación con la orden autorizada.

## 9. Evidencia de cumplimiento

La arquitectura de la semana 4 queda validada si cumple estas condiciones:

- entrada HTTP separada del dominio;
- interfaz de Repository definida en el dominio;
- adaptadores PDO e InMemory intercambiables;
- controladores con responsabilidad de coordinación;
- objetos de valor para identidad, orden y motivo;
- trazabilidad de rechazo y aceptación.

## 10. Trazabilidad

| Regla clave | Evidencia de diseño |
|---|---|
| Controlador no toca lógica de negocio | `ReceiveSampleForm` y `ReceptionController` despachan al caso de uso |
| Dominio no conoce HTTP | entidades y puertos están separados de la entrada web |
| Persistencia no reemplaza la decisión | Repository almacena resultados, no valida negocio |
| Rechazo queda documentado | motivo y actor deben persistirse junto con la decisión |
| Identidad inequívoca | `SpecimenId` y `OrderReference` deben estar representados como valores formales |

## 11. Limitaciones y trabajo siguiente

- La responsabilidad de rol y permisos debe definirse con el módulo de RBAC y el middleware de tenant.
- La validación del tipo de muestra y la correspondencia con la orden aún requiere estudio de catalogación clínica.
- La persistencia real debe definirse con el esquema del HIS y no como una capa aislada.

## 12. Conclusión

La Semana 4 consolida la idea de que la arquitectura es una forma de manejar complejidad. Al separar la entrada, la lógica y la persistencia, el módulo de recepción se vuelve más verificable, más testable y más defendible en una discusión técnica.

La lección central es clara: no importa si la solución final será monolítica o distribuida: la estructura debe mantener la información de paciente, orden y muestra separada de la forma en que llega la petición y del mecanismo real de almacenamiento.

## 13. Bibliografía

- Principios de diseño y evolución de software orientado a capas.
- Patrones de acceso a datos: Repository.
- Arquitectura de aplicaciones clínicas y módulos HIS.
- Reglas de trazabilidad, auditoría y seguridad hospitalaria.
