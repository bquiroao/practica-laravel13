# Semana 2 — LSP en la recepción de muestras

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Recepción de muestras y códigos identificadores |
| **Tema de la semana** | Proceso y modelo de diseño; principios SOLID |
| **Principio aplicado** | Liskov Substitution Principle (LSP) |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Email** | mlopez58@miumg.edu.gt |
| **Fecha** | Octubre 2026 |

## 1. Objetivo y alcance

Aplicar el principio de sustitución de Liskov al diseño del flujo «recepción, identificación inequívoca y rechazo controlado de muestra». El diseño debe poder incorporar validadores distintos sin que una muestra de otro tipo rompa el contrato, produzca una excepción inesperada o sea aceptada por error.

El ejemplo se limita a la decisión de aceptación. No crea una API, tablas, impresora ni criterios clínicos por tipo de examen. La recepción física, la relación con la orden y el registro persistente siguen sujetos a autorización y a los contratos de los módulos clínicos correspondientes.

## 2. Requerimientos funcionales y no funcionales

### Requerimientos funcionales

| ID | Requerimiento |
|---|---|
| RF-01 | Evaluar si una muestra está identificada y su recipiente está íntegro antes de aceptar su recepción. |
| RF-02 | Permitir agregar validaciones especializadas para una categoría de muestra sin cambiar el servicio que coordina las reglas. |
| RF-03 | Una validación específica de una categoría debe devolver explícitamente «no aplica» para otras categorías; el coordinador ignora ese resultado y ejecuta las reglas generales. |
| RF-04 | Si una regla aplicable detecta una no conformidad, devolver el rechazo con una razón legible; no tratar la falla como aceptación. |
| RF-05 | El mismo consumidor debe poder usar cualquier implementación del contrato `SpecimenAcceptancePolicy` sin conocer su tipo concreto. |

### Requerimientos no funcionales

| ID | Requerimiento |
|---|---|
| RNF-01 | El ejemplo debe ejecutarse con PHP 8.2+ vanilla, sin framework ni dependencias externas. |
| RNF-02 | Los tipos y resultados deben ser explícitos; las condiciones de negocio se representan como una decisión y no como excepciones de flujo. |
| RNF-03 | Las políticas de decisión deben ser puras: no realizan I/O ni modifican la muestra recibida. |
| RNF-04 | La suite automatizada debe reproducir criterios de aceptación y comprobar la sustitución de las políticas. |

## 3. Criterios de aceptación

| ID | Criterio verificable | Evidencia |
|---|---|---|
| CA-01 | Una muestra identificada e íntegra se acepta con la política general. | `php tests/run.php` |
| CA-02 | Una muestra sin etiqueta o con recipiente dañado se rechaza con razón explícita. | Casos `etiqueta ausente` e `integridad` en `tests/run.php`. |
| CA-03 | Una muestra de sangre con sello requerido y ausente se rechaza; con sello presente puede aceptarse. | Casos de sello sanguíneo en `tests/run.php`. |
| CA-04 | La política especializada devuelve explícitamente «no aplica» para orina, no lanza una excepción, y añadirla al conjunto de reglas no altera la decisión general. | Contrato de sustitución parametrizado en `tests/run.php`. |
| CA-05 | El servicio consumidor obtiene la decisión usando políticas inyectadas y sin condicionales sobre sus clases concretas. | `ReceptionDecisionService` y su prueba de sustitución. |
| CA-06 | Fuente y pruebas pasan el lint de PHP 8.2+. | `php -l src/SpecimenAcceptance.php` y `php -l tests/run.php`. |

## 4. Diseño antes: contrato que no se puede sustituir

Una implementación heredada puede aceptar solo la categoría para la que fue escrita y lanzar una excepción al recibir cualquier otra. Aunque conserva la firma, fortalece la precondición de la clase base («evalúa una muestra») y rompe al consumidor que procesa una colección heterogénea.

```php
abstract class SpecimenPolicy
{
    public function evaluate(Specimen $specimen): bool
    {
        // La clase base promete evaluar cualquier muestra.
        return true;
    }
}

final class BloodOnlyPolicy extends SpecimenPolicy
{
    public function evaluate(Specimen $specimen): bool
    {
        if ($specimen->type !== 'blood') {
            throw new InvalidArgumentException('Esta política solo admite sangre');
        }

        return $specimen->labelPresent && $specimen->containerIntact;
    }
}
```

**Falla observable:** un servicio que sustituye `SpecimenPolicy` por `BloodOnlyPolicy` y procesa una muestra de orina recibe una excepción nueva en lugar del resultado del contrato. El tipo concreto se filtra al consumidor y agregar validadores obliga a modificar el flujo central.

## 5. Diseño después: decisiones compatibles por contrato

El contrato ahora promete un resultado para cualquier `Specimen`. Las implementaciones no estrechan el tipo aceptado: la política especializada devuelve «no aplica» para tipos distintos de sangre y el coordinador ignora ese resultado mientras sigue ejecutando las reglas generales. Para sangre, la política devuelve el resultado de su propia regla y el coordinador consolida sus motivos con los de la política general. Si ninguna regla aplica, el servicio rechaza de forma segura en vez de asumir aceptación.

```text
ReceptionDecisionService
          |
          | depende de
          v
SpecimenAcceptancePolicy
       /            \
GeneralAcceptance   BloodTubeAcceptance
       \            /
   AcceptanceDecision (acepta / rechaza / no aplica)
```

| Diseño anterior | Diseño corregido |
|---|---|
| La subclase `BloodOnlyPolicy` exige un tipo que la clase base no exigía. | Cada política acepta el mismo `Specimen` y devuelve `AcceptanceDecision`. |
| Un tipo no compatible genera excepción inesperada durante el flujo. | La política especializada devuelve «no aplica» para otros tipos; el servicio continúa con las reglas generales. |
| El consumidor podría necesitar preguntar qué clase concreta recibió. | `ReceptionDecisionService` itera el contrato; no ramifica por clase. |
| Una regla mezcla la validación con el mecanismo de error. | Cada política calcula razones; el servicio reúne decisiones sin I/O. |

## 6. Responsabilidades y dependencias

| Componente | Responsabilidad | Dependencias |
|---|---|---|
| `Specimen` | Datos inmutables necesarios para decidir sobre una muestra concreta. | Ninguna. |
| `SpecimenAcceptancePolicy` | Contrato estable para evaluar cualquier muestra. | `Specimen`, `AcceptanceDecision`. |
| `GeneralAcceptancePolicy` | Verificar identificación e integridad comunes a todas las categorías. | Contrato y objeto de dominio. |
| `BloodTubeAcceptancePolicy` | Evaluar la condición del sello para sangre y devolver «no aplica» para otras categorías. | `Specimen`, `AcceptanceDecision`; no conoce persistencia ni UI. |
| `ReceptionDecisionService` | Ejecutar políticas inyectadas, ignorar resultados «no aplica», consolidar rechazos y fallar cerrado si no hay regla aplicable. | Abstracción `SpecimenAcceptancePolicy`, no clases concretas. |
| `tests/run.php` | Ejecutar pruebas de comportamiento y contrato de sustitución. | Código del ejemplo PHP. |

La inversión hacia la abstracción permite reemplazar o ampliar políticas sin modificar el servicio. El principio central de LSP es el comportamiento: no basta con heredar o implementar una firma si un sustituto añade precondiciones que el cliente no espera.

## 7. Evidencia verificable

El ejemplo ejecutable y autónomo se encuentra en [`ejemplo-lsp/`](ejemplo-lsp/README.md).

Desde la carpeta `docs/modulos/recepcion-muestras/semana-02/ejemplo-lsp/`, ejecutar:

```powershell
php -l src/SpecimenAcceptance.php
php -l tests/run.php
php tests/run.php
```

La suite contiene un contrato compartido para todas las políticas, un caso de muestra heterogénea que reproduce la excepción del diseño anterior, validaciones de etiqueta/integridad/sello y comprobación de la decisión agregada del servicio. No requiere base de datos: prueba la regla de dominio antes de integrar persistencia.

## 8. Trazabilidad

| Requerimiento / criterio | Diseño | Prueba |
|---|---|---|
| RF-01, CA-01, CA-02 | `GeneralAcceptancePolicy` | Aceptación válida; etiqueta ausente; recipiente dañado. |
| RF-02, RF-03, CA-03, CA-04 | `BloodTubeAcceptancePolicy`, `ReceptionDecisionService` | Sello presente/ausente; resultado «no aplica» para orina; resultado general sin cambios. |
| RF-04, CA-02 | `AcceptanceDecision` | Rechazo con razones no vacías. |
| RF-05, RNF-02, CA-05 | Interfaz y servicio consumidor | Contrato compartido y políticas inyectadas sin branching por clase. |
| RNF-01, RNF-04, CA-06 | PHP vanilla y harness de pruebas | Ejecución y lint documentados. |

## 9. Límites del ejemplo y conclusión

El ejemplo demuestra sustitución de validadores en memoria; no demuestra persistencia, generación de código único, impresión de etiquetas ni seguridad de una API. Esos aspectos pertenecen a etapas posteriores y requieren contratos propios.

LSP evita que un sustituto sorprenda a quien depende de la abstracción. En este caso, cualquier política debe recibir la misma muestra general, devolver el resultado acordado y conservar las reglas comunes. Las reglas especializadas se agregan de forma compatible, y la aceptación final continúa bajo las validaciones aplicables al proceso real.

## 10. Referencias

1. Consigna Semana 2 del repositorio: aplicación de LSP al flujo de recepción, definición de RF/RNF, criterios de aceptación, diseño antes/después y evidencia.
2. Entrega de Semana 1: [semana-01-uml-recepcion-muestras.md](../semana-01/semana-01-uml-recepcion-muestras.md), narrativa, actores y límites del proceso.
3. Repositorio, `openspec/changes/postgresql-federated-schema-rebuild/specs/hospital-clinical-schema/spec.md`: entidades previstas del dominio clínico; no define reglas de aceptación de muestras.
