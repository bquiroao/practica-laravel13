# Guía de defensa oral — Semana 2: DIP

**Estudiante según la captura:** Lopez Perez, Mercedes Azucena · **Módulo:** Validación de resultados por bioquímico · **Flujo:** revisión, validación o rechazo de un resultado.

## Resumen en 60 segundos

> «Apliqué el principio de inversión de dependencias al flujo de revisión de resultados pendientes. Antes, un solo servicio conocía el rol concreto, PDO, SQL y el reloj del sistema. Después, el caso de uso `ReviewPendingLabResult` depende de tres contratos: autorización, reloj y repositorio de decisiones. La decisión de guardado vuelve a comprobar que la versión siga pendiente y vigente; el adaptador persiste el estado y la auditoría como una sola operación. El ejemplo en PHP 8.2 se prueba con un repositorio en memoria e incluye validación, rechazo con motivo, denegación, conflicto concurrente y falla de escritura. No inventé rangos clínicos: la interpretación permanece a cargo del bioquímico.»

## Orden sugerido para exponer

1. Recordar el flujo y las reglas de Semana 1: R-01, R-02, R-04, R-05, R-06, R-08 y R-09.
2. Mostrar el diseño antes y señalar que mezcla política, PDO/SQL, autorización concreta y reloj.
3. Definir DIP y diferenciar la regla de alto nivel de los mecanismos de infraestructura.
4. Mostrar las interfaces `ReviewerAuthorization`, `Clock` y `LabResultDecisionRepository`.
5. Explicar cómo se construye el caso de uso con dependencias inyectadas y cómo se sustituye el repositorio por uno en memoria.
6. Ejecutar `php docs/modulos/validacion-resultados-bioquimico/semana-02/ejemplo-dip/verificar.php`.
7. Demostrar CA-09 (cambio entre lectura y escritura) y CA-10 (falla de persistencia sin estado ni auditoría parcial).
8. Cerrar con los límites: sin reglas bioquímicas automáticas ni esquema de base productivo.

## Decisiones de diseño

| Decisión | Justificación |
|---|---|
| La autorización es un puerto | La política de roles puede evolucionar sin que el caso de uso compare cadenas de rol o conozca el framework de autenticación. |
| El momento se solicita a `Clock` | Hace deterministas las pruebas y evita que la regla de aplicación lea la hora global. |
| El repositorio expone `decideAtomically` | Estado y auditoría deben guardarse juntos, y la versión debe volver a comprobarse dentro de la operación de escritura. |
| El caso de uso recibe interfaces por constructor | Facilita la sustitución sin reescribir la regla de revisión. |
| La decisión conserva el dato | Validar o rechazar no altera el valor observado por el profesional. |
| El motivo es obligatorio al rechazar | Permite seguimiento sin borrar la evidencia ni confundir rechazo del resultado con rechazo de la muestra. |
| No se codifican umbrales de laboratorio | Los criterios dependen de la prueba y del procedimiento clínico; no se deben inventar en este ejercicio. |
| La persistencia en memoria es solo evidencia | PDO y el esquema real se abordarán en el micro-HIS de Semana 3. |

## Preguntas probables

**¿Qué dependencia se invierte?**

El caso de uso de alto nivel ya no importa ni crea PDO, una clase de autorización concreta ni el reloj del sistema. Define/consume interfaces y recibe implementaciones desde fuera.

**¿Poner una interfaz ya significa aplicar DIP?**

No. La clase de alto nivel debe depender de esa abstracción y recibirla mediante una composición explícita. CA-13 revisa los tipos que recibe el constructor.

**¿Cómo se mantiene la integridad si el resultado cambia mientras se revisa?**

La solicitud incluye la versión que se abrió; el repositorio vuelve a comparar versión y estado al guardar. Si cambió, devuelve conflicto y no registra la decisión.

**¿Por qué `decideAtomically` combina cambio y auditoría?**

Separarlos permitiría que el resultado quedara validado sin evidencia o que hubiera una auditoría de una decisión nunca confirmada. El adaptador de producción debe proteger ambos en una transacción.

**¿Un resultado fuera de rango se rechaza automáticamente?**

No. El ejemplo no implementa rangos bioquímicos. La decisión de validar o rechazar es profesional y debe seguir el procedimiento de la prueba.

**¿Qué pasa si falla la base de datos?**

El puerto comunica `FAILED`, el caso de uso responde `PERSISTENCE_FAILURE` y no informa éxito. La prueba comprueba que no haya cambio de estado ni registro parcial.

**¿Ya se cambió el controlador del sistema hospitalario?**

No. Esta entrega contiene un prototipo académico independiente. La integración en el micro-HIS corresponde al trabajo de Semana 3.

## Recorrido de demostración

1. Abrir [el documento principal](semana-02-dip-validacion-resultados.md) y leer CA-01 a CA-13.
2. Mostrar [el servicio acoplado](ejemplo-dip/antes/CoupledLabResultReview.php).
3. En [el diseño DIP](ejemplo-dip/despues/ReviewWorkflow.php), señalar los contratos y el constructor.
4. Mostrar [el repositorio en memoria](ejemplo-dip/despues/InMemoryLabResultDecisionRepository.php), en particular la comprobación atómica de estado y versión.
5. Ejecutar las pruebas y localizar CA-09 y CA-10 en [verificar.php](ejemplo-dip/verificar.php).

## Cambios que requieren revisar la trazabilidad

| Cambio | Elementos a revisar |
|---|---|
| Cambiar quién puede decidir | RF-01, contrato de autorización, CA-04 y política de seguridad. |
| Cambiar el ciclo de vida o versionado | RF-03, contrato de repositorio, CA-07 a CA-09 y reglas de Semana 1. |
| Cambiar la estructura de auditoría | RF-06 y RF-07, operación atómica, CA-01, CA-02 y CA-10. |
| Añadir reglas clínicas | Confirmar fuente con responsable clínico; actualizar alcance, RF, CA y pruebas. No inferirlas de un número aislado. |
| Integrar persistencia real | Mantener los contratos y verificar transacción, prepared statements, tenant y control de concurrencia en el adaptador PDO. |
