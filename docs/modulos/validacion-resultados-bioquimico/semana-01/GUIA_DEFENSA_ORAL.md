# Guía de defensa oral — Semana 1

**Estudiante según la captura:** Mercedes Azucena Lopez Perez · **Módulo:** Validación de resultados por bioquímico · **Documento:** [semana-01-uml-validacion-resultados.md](semana-01-uml-validacion-resultados.md)

## 1. Resumen en 60 segundos

> «Modelé cómo un bioquímico revisa un resultado pendiente y toma una decisión explícita. Primero el sistema verifica autorización, hospital y vigencia de la versión; después presenta el contexto de la orden, la prueba y la muestra para que el profesional revise el resultado según el procedimiento aplicable. Si la revisión es satisfactoria, el bioquímico valida esa versión y queda inmutable. Si no puede validarla, la rechaza con un motivo obligatorio; el registro conserva el resultado y se deriva para seguimiento del personal autorizado de laboratorio. Una falla técnica o un cambio concurrente nunca se presenta como una decisión clínica exitosa. Los tres diagramas comparten identificadores y reglas mediante una matriz de trazabilidad.»

## 2. Orden sugerido para exponer

1. Delimitar el proceso: validación profesional posterior a la captura, no ingreso del valor.
2. Identificar al bioquímico como actor decisor y al personal de laboratorio como receptor del seguimiento de un rechazo.
3. Presentar CU-01 y explicar qué información debe revisar el bioquímico antes de decidir.
4. Comparar CU-02 y CU-03: validar la versión o rechazarla indicando motivo.
5. Recorrer en actividad las verificaciones de permiso y vigencia y las salidas de error.
6. En secuencia, seguir la revalidación del estado antes de persistir la decisión.
7. Elegir una regla de la matriz (por ejemplo, R-06) y mostrar cómo aparece en los casos de uso, la actividad y la secuencia.

## 3. Decisiones clave

| Decisión | Justificación |
|---|---|
| La revisión pertenece al bioquímico | Distingue la decisión profesional de la captura de laboratorio. |
| La autorización se verifica al consultar y decidir | Una sesión abierta o el acceso a la cola no garantizan permiso para firmar una decisión. |
| La decisión se refiere a una versión concreta pendiente | Evita validar o rechazar un valor que se haya sustituido durante la revisión. |
| Rechazar exige motivo y conserva el resultado | El rechazo es una decisión auditable, no la eliminación del dato ni el rechazo de la muestra. |
| Una validación vuelve inmutable la versión | Mantiene el resultado validado como evidencia; una corrección posterior requiere un flujo distinto. |
| Un valor fuera de referencia no se rechaza automáticamente | La interpretación depende de la prueba y del procedimiento clínico; el modelo no inventa umbrales. |
| Un error técnico no es una decisión | Si falla la autorización, lectura o persistencia, no debe mostrarse éxito ni cambiarse el estado. |
| Los detalles persistidos del rechazo quedan como acuerdo pendiente | El esquema actual documenta entidades e invariantes, pero no fija el contrato de rechazo de este módulo. |

## 4. Preguntas probables

**¿Qué diferencia hay entre rechazar el resultado y rechazar la muestra?**
El bioquímico rechaza la validación del resultado ya capturado. No elimina el resultado ni cambia la decisión previa de aceptación de la muestra.

**¿Un resultado fuera de rango siempre se rechaza?**
No. Un resultado puede ser clínicamente anormal y aun así ser válido. La interpretación debe seguir criterios definidos por la prueba y el procedimiento institucional; este modelo no crea esos criterios.

**¿Qué se valida exactamente?**
El bioquímico revisa la versión pendiente, su asociación con orden, prueba y muestra, y el contexto requerido para la prueba. El detalle de los criterios analíticos es externo a esta entrega.

**¿Qué ocurre si el resultado cambia mientras está abierto?**
El sistema detecta que la versión dejó de ser la vigente o pendiente, no aplica la decisión obsoleta y solicita recargar el resultado.

**¿Qué pasa cuando el bioquímico rechaza un resultado?**
Se conserva el registro, se guarda un motivo junto con autoría y fecha/hora y queda para seguimiento de personal autorizado. La corrección del valor pertenece al proceso de captura.

**¿Se puede modificar un resultado después de validarlo?**
No mediante este flujo. La especificación del proyecto exige que los resultados validados sean inmutables; una rectificación necesita un proceso formal separado y versionado.

**¿Qué pasa si ocurre un error al guardar?**
El sistema informa que no pudo registrar la decisión. No confirma aprobación ni rechazo y no debe simular un estado final.

**¿Qué muestran los tres diagramas?**
Casos de uso identifica actores y objetivos; actividad muestra verificaciones, decisiones y excepciones; secuencia ordena los mensajes y respuestas. La matriz permite seguir cada regla a través de las vistas.

## 5. Recorrido de demostración

1. Abrir [el documento principal](semana-01-uml-validacion-resultados.md) y explicar narrativa, límites y actores.
2. Mostrar [casos de uso](diagramas/semana-01-casos-de-uso.puml) y separar revisar, validar y rechazar.
3. En [actividad](diagramas/semana-01-actividad.puml), seguir ambas ramas y señalar la salida de versión obsoleta.
4. En [secuencia](diagramas/semana-01-secuencia.puml), ubicar autorización, consulta del contexto, revalidación y persistencia.
5. Seguir R-06 en la matriz hasta CU-03, actividad A-13–A-14 y secuencia S-31, S-37–S-38.

## 6. Elementos que deben permanecer sincronizados

| Cambio solicitado | Partes a revisar |
|---|---|
| Cambiar quién puede validar | Actores, CU-01 a CU-03, R-01, actividad, secuencia y matriz. |
| Añadir un criterio de revisión | Alcance, supuestos, CU-01, R-03, decisión D-03, mensajes de secuencia y matriz; confirmar el criterio con el responsable clínico. |
| Permitir rectificación después de validar | Crear un proceso separado con actor, motivo, versionado, autorización y trazabilidad; no convertirlo en edición directa. |
| Cambiar los datos que exige el rechazo | CU-03, R-06, actividad, secuencia y matriz; coordinar el contrato con el módulo de captura. |
| Cambiar la representación del rechazo | Acordar primero estado, historial y cola entre módulos; actualizar reglas y diagramas sin borrar la evidencia. |

Para regenerar imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/validacion-resultados-bioquimico/semana-01/diagramas/*.puml
```

## 7. Integración prevista

El proceso consume resultados pendientes vinculados con `lab_order`, `lab_order_item`, `specimen` y `lab_result_version` en HOSPITAL. La especificación del proyecto establece la inmutabilidad de resultados validados y el versionado con motivo para correcciones. La cola de trabajo, los criterios del catálogo, los metadatos de la decisión y la vía de retorno de un rechazo requieren coordinación con los módulos de captura, catálogo de pruebas y alertas.
