# Guía de defensa oral — Semana 1

**Estudiante:** Mercedes Azucena Lopez Perez · **Módulo:** Ingreso de resultados de laboratorio · **Documento:** [semana-01-uml-ingreso-resultados.md](semana-01-uml-ingreso-resultados.md)

## 1. Resumen en 60 segundos

> «Modelé cómo el personal autorizado del laboratorio captura un resultado y cómo corrige un resultado antes de que sea validado. Para capturarlo, primero se confirma que la orden y el ítem de prueba existan y correspondan; después se verifican los campos definidos por el tipo de examen. El sistema guarda una primera versión pendiente, con actor y fecha. Si se detecta un error mientras sigue pendiente, el capturista proporciona el dato correcto y un motivo; el sistema no sobrescribe, sino que crea una versión vinculada y conserva la anterior. La versión corregida continúa pendiente para el bioquímico. La validación bioquímica es un proceso separado y no está modelada como parte de esta entrega.»

## 2. Orden sugerido para exponer

1. Explicar el alcance: captura y corrección únicamente antes de validar.
2. Mostrar quién captura y qué resultado necesita conseguir; distinguir el trabajo posterior del bioquímico.
3. Recorrer el caso de uso de captura y mostrar orden, ítem, campos y estado pendiente.
4. Recorrer el caso de corrección: comprobar estado, exigir motivo y crear una nueva versión.
5. Usar una regla de la matriz para señalar su representación en caso de uso, actividad y secuencia.

## 3. Decisiones clave

| Decisión | Justificación |
|---|---|
| Captura no significa validación | El dato se guarda como pendiente para que la validación bioquímica ocurra en otro proceso. |
| La orden y el ítem se verifican antes de guardar | Evita asociar un valor a una prueba o solicitud diferente. |
| No se inventan unidades ni rangos | Dependen del catálogo y del tipo de examen; el modelo deja esos detalles a la especificación correspondiente. |
| La corrección solo aplica a la versión vigente pendiente | Evita editar un dato después de iniciar el proceso de validación o cambiar su estado. |
| Cada corrección tiene un motivo | Permite entender por qué se cambió el dato original. |
| La corrección crea una versión nueva | Conserva el historial y no hace una sustitución silenciosa. La especificación del HIS requiere versionar las correcciones. |
| Resultado validado es inmutable | La tarea abarca pendientes; una rectificación posterior necesita un proceso formal distinto. |
| El modelado no afirma que exista una API | El diseño del esquema contempla las entidades, pero esta entrega describe el comportamiento de negocio. |

## 4. Preguntas probables

**¿Qué significa que un resultado esté pendiente?**
Que fue capturado y guardado, pero aún no pasó por la validación bioquímica. No debe presentarse como validado ni como definitivo.

**¿Se puede cambiar directamente el resultado capturado?**
No. Si sigue pendiente y se detecta un error, se conserva la versión anterior y se crea otra enlazada con el motivo y la autoría correspondientes.

**¿Qué pasa si el resultado ya fue validado?**
Este flujo rechaza el cambio. La especificación del proyecto señala que los resultados validados son inmutables; una corrección posterior requiere otro proceso formal, fuera de alcance.

**¿Por qué el modelo no fija una unidad o rango de referencia?**
Porque dependen de la prueba y deben venir de un catálogo definido por el módulo responsable. Inventarlos podría introducir una interpretación clínica incorrecta.

**¿Por qué se relaciona el resultado con un ítem de orden?**
Una orden puede contener distintas pruebas. El ítem individual identifica a cuál de ellas pertenece el valor capturado.

**¿Qué pasa si ocurre un error al guardar?**
El sistema informa que no pudo persistir el cambio y no muestra una confirmación de éxito. En una corrección, la versión anterior sigue disponible.

**¿Qué muestran los tres diagramas?**
Casos de uso define quién captura y corrige; actividad describe las decisiones y excepciones; secuencia enseña el orden de mensajes entre el usuario, el servicio y el almacenamiento.

## 5. Recorrido de demostración

1. Abrir [el documento principal](semana-01-uml-ingreso-resultados.md) y presentar la narrativa.
2. Mostrar [casos de uso](diagramas/semana-01-casos-de-uso.puml) y diferenciar CU-01 de CU-02.
3. En [actividad](diagramas/semana-01-actividad.puml), seguir captura pendiente, y después la rama de corrección, incluida la excepción de resultado ya validado.
4. En [secuencia](diagramas/semana-01-secuencia.puml), ubicar las comprobaciones del ítem, motivo, versión previa y nueva versión.
5. Elegir R-06 y seguirla desde CU-02 hasta actividad, secuencia y matriz.

## 6. Cambios que deben quedar sincronizados

| Cambio solicitado | Partes a revisar |
|---|---|
| Agregar un campo requerido por el tipo de prueba | Supuestos, CU-01, R-03, decisión D-03, mensajes de secuencia y matriz. |
| Agregar un motivo de corrección | CU-02, R-06, actividad y secuencia; actualizar la matriz. |
| Incluir rectificación después de validar | Requiere ampliar alcance y definir flujo, permisos y versionado con el responsable de validación; no es una extensión silenciosa de este proceso. |
| Cambiar el estado posterior a la corrección | Actualizar CU-02, actividad, secuencia y trazabilidad, coordinándolo con el proceso de validación. |

Para regenerar imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/ingreso-resultados-laboratorio/semana-01/diagramas/*.puml
```

## 7. Integración prevista

El esquema HOSPITAL planea `lab_order`, `lab_order_item` y `lab_result_version`. La captura depende de la existencia del ítem de prueba y deja el resultado pendiente para el módulo de validación bioquímica. Los campos concretos, los criterios analíticos, la cola de trabajo y la API requieren acuerdo entre ambos módulos.
