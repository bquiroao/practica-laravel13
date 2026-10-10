# Guía de defensa oral — Semana 1

**Estudiante:** Mercedes Azucena Lopez Perez · **Módulo:** Recepción de muestras y códigos identificadores · **Documento:** [semana-01-uml-recepcion-muestras.md](semana-01-uml-recepcion-muestras.md)

## 1. Resumen en 60 segundos

> «Modelé el proceso de recepción de una muestra de laboratorio desde que llega al área hasta que se registra su resultado. Primero, el sistema valida que exista una orden y que la muestra corresponda al paciente y al examen. Después se revisan los criterios aplicables. Si todo es correcto, se asigna un identificador único, se emite la etiqueta y se confirma la recepción. Si hay una no conformidad confirmada, se registra el rechazo con su motivo, quién lo hizo y cuándo. Si ocurre una falla técnica, como no poder validar la orden o imprimir la etiqueta, no se confirma la aceptación ni se la confunde con un rechazo clínico: queda pendiente de resolución. Los tres diagramas están conectados con identificadores y reglas comunes.»

## 2. Orden sugerido para exponer

1. Presentar el objetivo y delimitar qué significa recibir e identificar una muestra.
2. Mostrar al técnico/a de laboratorio como actor principal y los casos de uso de recepción y rechazo controlado.
3. Recorrer la actividad: validar orden y correspondencia, evaluar la muestra y distinguir aceptación, rechazo y pendiente técnico.
4. Recorrer la secuencia con el caso aceptado y luego con un rechazo controlado.
5. Elegir una regla de la matriz y localizarla en los casos de uso, la actividad y la secuencia.

## 3. Decisiones clave

| Decisión | Cómo explicarla |
|---|---|
| El código de la muestra es distinto del paciente y de la orden | Identifica el recipiente/muestra recibida; no se reutilizan identificadores para representar conceptos diferentes. |
| La validación relaciona paciente, orden y examen | Evita asociar una muestra a una solicitud equivocada; el nombre no es prueba suficiente de identidad. |
| Los criterios dependen del examen | La consigna no define requisitos clínicos concretos; se modela una validación sin inventar umbrales. |
| Rechazo y falla técnica son resultados distintos | Una discordancia confirmada permite un rechazo justificado. Una falla del sistema o impresora no es una decisión clínica y deja el registro pendiente. |
| No se borra la evidencia del rechazo | La decisión queda registrada con motivo, actor y fecha/hora. |
| La etiqueta contiene lo mínimo necesario | La etiqueta se modela con el código de muestra, sin asumir que deba exponer datos personales o clínicos. |
| El diseño aún no es una API implementada | `lab_order`, `lab_order_item` y `specimen` están previstos en el diseño de base de datos del proyecto; esta entrega describe el proceso, no declara endpoints ni funciones ya disponibles. |

## 4. Preguntas probables

**¿Qué significa identificación inequívoca?**

Que el código asignado identifica una muestra concreta sin colisionar con otra y se mantiene vinculable con su orden y examen. No es el ID del paciente ni sustituye la validación de identidad.

**¿Cuándo se rechaza una muestra?**

Cuando existe una no conformidad confirmada, como una discordancia con la orden o el incumplimiento de un criterio aplicable. El motivo se registra; los criterios precisos dependen del examen y quedan fuera de esta entrega.

**¿Qué pasa si no se puede consultar la orden o imprimir la etiqueta?**

Se informa la condición y no se confirma la recepción como aceptada. Tampoco se registra una decisión clínica de rechazo solo por la falla técnica.

**¿Por qué se usan tres diagramas?**

Casos de uso explica qué objetivos tiene cada actor; actividad muestra el flujo, sus decisiones y excepciones; secuencia detalla el orden de mensajes y validaciones entre participantes.

**¿Cómo se comprueba la trazabilidad?**

Cada regla R se enlaza a un caso de uso, a actividades/decisiones/excepciones identificadas y a mensajes de secuencia. La matriz de la sección 8 permite revisar esas correspondencias.

**¿Qué parte ya existe en el proyecto?**

El diseño federado contempla las entidades `lab_order`, `lab_order_item` y `specimen` para HOSPITAL. El flujo de recepción, sus estados definitivos, API e interfaz no se afirman como implementados.

## 5. Recorrido de demostración

1. Abrir [semana-01-uml-recepcion-muestras.md](semana-01-uml-recepcion-muestras.md) y leer la narrativa y las reglas.
2. Mostrar [el diagrama de casos de uso](diagramas/semana-01-casos-de-uso.puml) y delimitar al actor principal y sus objetivos.
3. En [el diagrama de actividad](diagramas/semana-01-actividad.puml), seguir primero el camino aceptado y luego una no conformidad confirmada; comparar ambos con una falla técnica.
4. En [el diagrama de secuencia](diagramas/semana-01-secuencia.puml), localizar las verificaciones y las respuestas de esos caminos.
5. Cerrar con una regla de la matriz y demostrar que su código se conserva en las tres vistas.

## 6. Cómo modificar los diagramas

| Cambio solicitado | Archivos que deben mantenerse sincronizados |
|---|---|
| Agregar un motivo de rechazo | Especificación de CU-02, regla R-05, actividad y secuencia; después actualizar la matriz. |
| Añadir una validación propia de un examen | Supuestos/reglas, caso CU-01 y decisiones del diagrama de actividad y secuencia; no incorporar umbrales sin fuente clínica. |
| Cambiar el formato del identificador | Regla R-04, generación y validación del código en actividad/secuencia, y postcondición de CU-01. |
| Incluir una notificación al remitente | Agregar el actor y su objetivo, y alinear los mensajes con actividad, secuencia y matriz. |

Para regenerar las imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/recepcion-muestras/semana-01/diagramas/*.puml
```

## 7. Referencia técnica del proyecto

El diseño federado vigente describe `lab_order`, `lab_order_item` y `specimen` como entidades previstas para la base HOSPITAL. La definición final de estados, criterios de aceptación, API y permisos requiere coordinación con los módulos de órdenes y catálogo de pruebas.
