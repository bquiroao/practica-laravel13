# Semana 2 — Requerimientos, criterios de aceptación y aplicación de DIP

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Validación de resultados por bioquímico |
| **Tema de la semana** | Proceso y modelo de diseño; principios SOLID |
| **Flujo** | «Revisión, validación o rechazo de un resultado por bioquímico» |
| **Principio aplicado** | DIP — Principio de inversión de dependencias |
| **Estudiante según la captura** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## Índice

1. [Introducción y alcance](#1-introducción-y-alcance)
2. [Requerimientos funcionales](#2-requerimientos-funcionales)
3. [Requerimientos no funcionales](#3-requerimientos-no-funcionales)
4. [Criterios de aceptación](#4-criterios-de-aceptación)
5. [DIP aplicado al flujo](#5-dip-aplicado-al-flujo)
6. [Diseño antes de DIP](#6-diseño-antes-de-dip)
7. [Diseño después de DIP](#7-diseño-después-de-dip)
8. [Responsabilidades y dependencias](#8-responsabilidades-y-dependencias)
9. [Evidencia verificable](#9-evidencia-verificable)
10. [Trazabilidad con Semana 1](#10-trazabilidad-con-semana-1)
11. [Conclusión y límites](#11-conclusión-y-límites)
12. [Referencias](#12-referencias)

## 1. Introducción y alcance

En Semana 1 se modeló cómo un bioquímico autorizado revisa un resultado pendiente y decide validar la versión vigente o rechazarla con un motivo. Esta entrega pasa del modelo del proceso a un diseño que permite cambiar la persistencia, la autorización o el reloj sin acoplar la política de revisión a clases concretas de infraestructura.

El principio elegido es **DIP**. El caso de uso de alto nivel contiene las reglas de la decisión profesional. No debe conocer PDO, SQL, un reloj del sistema, ni una clase concreta que compruebe roles. En cambio, expresa lo que necesita mediante contratos; los adaptadores de infraestructura implementan dichos contratos y se conectan en la composición de la aplicación.

**Incluye:** comprobación de autorización y ámbito hospitalario; lectura de una versión identificada; verificación del estado pendiente y de la versión esperada; decisión explícita de validar o rechazar; motivo obligatorio para rechazar; asociación de actor y momento; y respuesta diferenciada ante denegación, conflicto y error de persistencia.

**No incluye:** captura o corrección del valor, reglas bioquímicas, intervalos de referencia, determinación automática de normalidad, endpoints, pantallas, ni un esquema productivo. No se interpreta un valor fuera de referencia como inválido. La decisión clínica permanece a cargo del bioquímico.

El ejemplo es un prototipo aislado en PHP 8.2+ vanilla para demostrar la dirección de las dependencias. Su adaptador en memoria permite ejecutar las pruebas sin base de datos. La implementación PDO del micro-HIS corresponde a la evolución prevista para la Semana 3.

## 2. Requerimientos funcionales

| ID | Requerimiento | Origen |
|---|---|---|
| RF-01 | El sistema debe permitir que solo un bioquímico autorizado consulte y decida dentro del hospital al que pertenece el resultado. | R-01 |
| RF-02 | El sistema debe cargar el resultado con sus asociaciones disponibles de orden, examen y muestra, sin revelar si existe cuando pertenece a otro hospital. | R-01, R-03, E-03 |
| RF-03 | El sistema debe aceptar decisiones únicamente sobre la versión esperada que aún está pendiente. | R-02 |
| RF-04 | El sistema debe registrar una decisión explícita de validación sin modificar el valor analítico ni su unidad. | R-04 |
| RF-05 | El sistema debe permitir rechazar la versión pendiente solo con un motivo no vacío, conservando el resultado. | R-06 |
| RF-06 | Toda decisión exitosa debe conservar identificador de resultado y versión, actor, hospital, decisión, momento y motivo cuando aplique. | R-05, R-06 |
| RF-07 | El almacenamiento debe persistir la transición y el registro de auditoría como una sola operación atómica. | R-05, R-09 |
| RF-08 | El sistema debe devolver resultados distintos para denegación, entrada inválida, estado no permitido, conflicto y falla de persistencia. | R-09, E-01, E-04, E-05, E-06 |

## 3. Requerimientos no funcionales

| ID | Atributo | Requerimiento | Verificación |
|---|---|---|---|
| RNF-01 | Inversión de dependencias | El caso de uso dependerá de contratos de autorización, reloj y persistencia, no de adaptadores concretos. | CA-13 |
| RNF-02 | Integridad | Estado y auditoría deben confirmarse juntos o no persistirse; el adaptador debe revalidar estado y versión al escribir. | CA-09, CA-10 |
| RNF-03 | Seguridad | Una denegación o una diferencia de hospital no debe cambiar el estado ni exponer la existencia de resultados ajenos. | CA-04, CA-11 |
| RNF-04 | Trazabilidad | Una decisión exitosa identifica al bioquímico y su momento; un rechazo conserva su motivo. | CA-01, CA-02 |
| RNF-05 | Inmutabilidad | Validar o rechazar no modifica el valor analítico revisado. | CA-01, CA-02 |
| RNF-06 | Testabilidad | La lógica de aplicación debe probarse con implementaciones en memoria y dobles, sin instalar un framework ni una base de datos. | Todas las pruebas del ejemplo |
| RNF-07 | Integridad clínica | El prototipo no codifica límites bioquímicos ni reemplaza el criterio profesional. | Revisión del alcance y del código |

## 4. Criterios de aceptación

Formato *Dado / Cuando / Entonces*. Cada CA tiene una o más aserciones con el mismo identificador en [`ejemplo-dip/verificar.php`](ejemplo-dip/verificar.php).

| ID | RF/RNF | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-04, RF-06, RNF-04, RNF-05 | un bioquímico autorizado y una versión pendiente vigente | valida el resultado | la operación es exitosa, el estado queda validado, el valor no cambia y se guardan actor y momento |
| CA-02 | RF-05, RF-06, RNF-04, RNF-05 | una versión pendiente vigente | el bioquímico la rechaza con motivo | el estado queda rechazado, se conserva el valor y se registra el motivo |
| CA-03 | RF-05 | una versión pendiente | se solicita rechazarla sin motivo | se informa entrada inválida y no cambia el estado ni se registra decisión |
| CA-04 | RF-01, RF-08, RNF-03 | un actor sin autorización | intenta validar | el sistema deniega la operación y no cambia ni registra una decisión |
| CA-05 | RF-02 | falta una asociación necesaria a la orden, examen o muestra | el bioquímico intenta decidir | se informa contexto inválido y no se registra decisión |
| CA-06 | RF-02 | no existe el resultado solicitado | el bioquímico intenta decidir | se informa que no está disponible y no se registra decisión |
| CA-07 | RF-03 | un resultado ya validado | se intenta volver a decidir | se informa estado no permitido y no se crea una segunda decisión |
| CA-08 | RF-03 | el resultado tiene una versión posterior a la consultada | se envía la versión esperada antigua | se devuelve conflicto sin registrar una decisión ni cambiar el estado vigente |
| CA-09 | RF-03, RNF-02 | el resultado cambia después de la lectura y antes de escribir | el adaptador intenta guardar la decisión de la versión leída | la verificación atómica detecta el cambio, devuelve conflicto y conserva el valor más reciente |
| CA-10 | RF-07, RF-08, RNF-02 | la escritura de la decisión o su auditoría falla | se solicita validar | se informa falla de persistencia y no queda estado ni auditoría parcial |
| CA-11 | RF-02, RNF-03 | un resultado pertenece a otro hospital | un usuario consulta la decisión en su hospital | se informa que no está disponible, sin revelar el resultado ni registrar cambios |
| CA-12 | RF-08 | la versión esperada no es válida | se solicita la decisión | se informa entrada inválida y no se persiste |
| CA-13 | RNF-01, RNF-06 | el caso de uso se construye con dependencias de prueba | se inspeccionan sus dependencias | recibe interfaces, sustituibles por adaptadores, y no exige clases concretas |

## 5. DIP aplicado al flujo

DIP expresa dos ideas de diseño:

1. Las políticas de alto nivel no deben depender directamente de mecanismos de bajo nivel.
2. Ambos lados se relacionan mediante abstracciones estables definidas en el límite de la aplicación.

En este flujo:

- **Alto nivel:** `ReviewPendingLabResult`, que coordina la autorización, verifica la solicitud y decide qué respuesta entregar.
- **Abstracciones:** `ReviewerAuthorization`, `Clock` y `LabResultDecisionRepository`.
- **Bajo nivel:** adaptadores concretos de autorización, reloj del sistema y base de datos; el ejemplo sustituye la base por un repositorio en memoria.
- **Composición:** el punto de arranque crea las implementaciones y las entrega al caso de uso. Las reglas no instancian PDO ni leen la hora global.

Definir una interfaz sin usarla en el constructor no invertiría la dependencia. La prueba CA-13 verifica que el caso de uso recibe los contratos. Las pruebas funcionales además ejercitan su colaboración con un adaptador en memoria y con un adaptador que simula una escritura fallida.

## 6. Diseño antes de DIP

El ejemplo [`CoupledLabResultReview.php`](ejemplo-dip/antes/CoupledLabResultReview.php) concentra las reglas y detalles en un único servicio:

```text
Controlador
    └── CoupledLabResultReview
          ├── compara el texto del rol
          ├── abre una conexión PDO
          ├── conoce SQL y tablas
          ├── interpreta estados y versiones
          ├── obtiene la hora global
          └── escribe decisión y auditoría
```

Aunque una transacción agrupa las escrituras, el servicio solo se puede usar directamente con PDO, la autenticación se acopla al nombre de rol y el tiempo es global. Cambiar el almacenamiento o probar errores sin una base real obliga a reemplazar o preparar la clase concreta. La lógica de alto nivel conoce mecanismos que deberían quedar detrás de límites.

El código anterior es ilustrativo, con sentencias preparadas y comprobación de concurrencia. No representa un esquema aprobado ni se ejecuta contra datos del proyecto.

## 7. Diseño después de DIP

El ejemplo [`ReviewWorkflow.php`](ejemplo-dip/despues/ReviewWorkflow.php) mantiene la política del flujo separada de los adaptadores:

```text
                    ┌─────────────────────────────┐
                    │ ReviewPendingLabResult      │
                    │ (caso de uso de alto nivel) │
                    └──────────────┬──────────────┘
                                   │ depende de
                 ┌─────────────────┼──────────────────┐
                 ▼                 ▼                  ▼
       ReviewerAuthorization     Clock     LabResultDecisionRepository
                 ▲                 ▲                  ▲
                 └── adaptador ────┴── adaptador ─────┘
                                      │
                       InMemoryLabResultDecisionRepository
                   (en producción: adaptador de persistencia)
```

El caso de uso valida entradas, autorización, hospital, las asociaciones requeridas de orden/examen/muestra, versión, estado y motivo. El puerto de repositorio expresa una operación atómica: guardar la transición y su auditoría solo si la versión sigue pendiente y es la esperada. El adaptador de producción deberá cargar el contexto asociado y garantizar la escritura dentro de una transacción verificando las filas afectadas; no basta con que la aplicación lea el estado antes del `UPDATE`.

La respuesta es explícita: `VALIDATED`, `REJECTED`, `DENIED`, `INVALID_INPUT`, `INVALID_STATE`, `NOT_FOUND`, `CONFLICT` o `PERSISTENCE_FAILURE`. Un error de escritura no se convierte en éxito. El valor revisado se conserva y la decisión se asocia a actor, hospital, versión y momento.

## 8. Responsabilidades y dependencias

| Componente | Responsabilidad | Depende de |
|---|---|---|
| `ReviewPendingLabResult` | Coordinar una decisión profesional y traducir el resultado del guardado a una respuesta de aplicación. | `ReviewerAuthorization`, `Clock`, `LabResultDecisionRepository` |
| `ReviewerAuthorization` | Contrato para comprobar si el actor puede decidir en el hospital indicado. | La política se concreta fuera del caso de uso. |
| `Clock` | Contrato para obtener el momento de la decisión. | El adaptador concreto de reloj. |
| `LabResultDecisionRepository` | Contrato para leer el resultado y guardar decisión/auditoría atómicamente con control de versión. | El adaptador de persistencia. |
| `InMemoryLabResultDecisionRepository` | Implementación de prueba que conserva resultados y decisiones en memoria y permite simular fallas. | Contrato de repositorio y modelo del ejemplo. |
| `FixedReviewerAuthorization`, `FixedClock` | Dobles de prueba con autorización y momento controlados. | Contratos, no servicios concretos. |
| `verificar.php` | Componer los dobles y comprobar criterios de aceptación. | Contratos y adaptadores de prueba. |

El contrato de decisión atómica es intencionalmente una sola operación: el estado y su registro de auditoría no pueden divergir. La implementación productiva y los nombres definitivos de tabla/columna quedan para la Semana 3, luego de acordar el esquema con los módulos de captura y auditoría.

## 9. Evidencia verificable

Desde la raíz del repositorio, ejecutar:

```bash
php docs/modulos/validacion-resultados-bioquimico/semana-02/ejemplo-dip/verificar.php
```

La suite comprueba los CA-01 a CA-13, incluye una actualización concurrente simulada y una falla de escritura sin efectos parciales. La salida de referencia se guarda en [`evidencia/salida-verificar.txt`](evidencia/salida-verificar.txt).

El diseño anterior también se valida con `php -l`, pero no se conecta a una base: su SQL depende de una tabla ilustrativa y deliberadamente no define el esquema del HIS.

## 10. Trazabilidad con Semana 1

| Regla Semana 1 | Aplicación en este diseño | Criterio |
|---|---|---|
| R-01 — autorización y hospital | El caso de uso consulta `ReviewerAuthorization` y no revela un resultado de otro hospital. | CA-04, CA-11 |
| R-02 — versión vigente pendiente | El caso de uso compara la versión leída y el puerto vuelve a comprobarla atómicamente al guardar. | CA-07, CA-08, CA-09 |
| R-03 — contexto asociado, sin criterios inventados | La lectura debe incluir asociaciones de orden, examen y muestra; el ejemplo rechaza contexto incompleto sin aplicar umbrales clínicos. | CA-05 |
| R-04 — decisión de validación sin alterar el valor | La transición cambia el estado, no el resultado analítico. | CA-01 |
| R-05 — actor y momento | La decisión registrada contiene actor e instante obtenidos de dependencias inyectadas. | CA-01 |
| R-06 — rechazo con motivo y conservación | Se rechaza motivo vacío; el dato permanece y el motivo queda registrado. | CA-02, CA-03 |
| R-08 — inmutabilidad tras validar | El flujo no ofrece una operación para modificar el valor de la versión validada. | CA-01, CA-07 |
| R-09 — errores y concurrencia no son decisiones | Conflictos y errores se traducen a estados explícitos sin registrar una decisión parcial. | CA-08, CA-09, CA-10 |

## 11. Conclusión y límites

DIP permite que la regla de revisión profesional se mantenga independiente del motor de almacenamiento, la política concreta de autorización y el reloj. La prueba no solo observa interfaces: sustituye dependencias, prueba el rechazo, un conflicto concurrente y una falla de persistencia.

La Semana 2 demuestra el principio en un ejemplo independiente. No afirma que el controlador Laravel señalado en el workspace ya implemente este flujo ni cambia el sistema productivo. La conexión con PDO, la separación de Presentation/Application/Domain/Persistence y las reglas clínicas acordadas serán parte de la evolución del micro-HIS en Semana 3.

## 12. Referencias

- Captura de la tarea «Semana 2: Validación de resultados por bioquímico», compartida por la estudiante.
- Entrega del mismo módulo: [Semana 1 — Modelado UML de la validación de resultados por bioquímico](../semana-01/semana-01-uml-validacion-resultados.md), reglas R-01 a R-09.
- Robert C. Martin, *Agile Software Development: Principles, Patterns, and Practices*, capítulo sobre Dependency Inversion Principle.
- Asignación y descripción del módulo en [`README.md`](../../../../README.md).
