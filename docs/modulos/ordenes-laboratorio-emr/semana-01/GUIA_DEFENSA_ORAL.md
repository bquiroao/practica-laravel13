# Guía de defensa oral — Semana 1

**Estudiante:** Lopez Perez, Mercedes Azucena · **Módulo:** Órdenes de laboratorio desde EMR · **Documento:** [semana-01-uml-ordenes-laboratorio-emr.md](semana-01-uml-ordenes-laboratorio-emr.md)

## 1. Resumen en 60 segundos

> «Modelé cómo un profesional autorizado emite una orden de laboratorio desde el expediente electrónico y consulta su estado posteriormente. Antes de emitir, el sistema comprueba que el profesional tenga acceso al expediente, que el paciente y el episodio sean los correctos y que exista al menos una prueba vigente. La orden y sus partidas se guardan como una sola operación; si falla el guardado, no se presenta como emitida. Para el seguimiento, se muestra el último estado confirmado y se distingue un resultado vacío de una falla técnica. La recepción de muestras y los resultados pertenecen a otros módulos. Los diagramas usan identificadores compartidos para demostrar la trazabilidad.»

## 2. Orden sugerido para exponer

1. Explicar el problema: solicitar pruebas desde el expediente y no perder la trazabilidad.
2. Identificar al profesional solicitante y los sistemas que proporcionan autorización, expediente y catálogo.
3. Mostrar los casos de uso de emisión, seguimiento y actualización del estado desde el proceso de laboratorio.
4. Recorrer la actividad, señalando validaciones, caminos de error y la confirmación atómica.
5. Recorrer la secuencia y mostrar cómo se evita declarar éxito ante una falla.
6. Seleccionar una regla de la matriz y localizarla en actividad y secuencia.

## 3. Decisiones clave

| Decisión | Cómo explicarla |
|---|---|
| Orden ligada a paciente y episodio | Evita asociar una solicitud a un paciente o visita equivocados; no basta con coincidir por nombre. |
| Autorización antes de acceder | La consulta o modificación de datos clínicos requiere permiso contextual. |
| Al menos una prueba vigente | Una orden sin pruebas válidas no se emite; el catálogo es responsabilidad de otro módulo. |
| Orden y partidas como unidad | Evita una orden confirmada con detalles incompletos por una falla parcial de persistencia. |
| Estado consultado, no supuesto | El EMR presenta el estado registrado; este módulo no inventa transiciones de laboratorio. |
| Límites entre módulos | La recepción de muestras y el registro/validación de resultados quedan fuera del alcance. |

## 4. Preguntas probables

**¿Qué pasa si el profesional selecciona un paciente o episodio incorrecto?**

El sistema vuelve a validar la relación con el contexto del EMR antes de emitir. Si no coincide, no crea la orden y solicita corregir el contexto.

**¿Se pueden emitir órdenes sin una prueba del catálogo?**

No en este modelo. La orden debe incluir al menos una prueba vigente, y el módulo de órdenes no administra el catálogo.

**¿Qué pasa si falla la base de datos después de iniciar la emisión?**

La operación no se presenta como exitosa. Orden y partidas deben persistirse en una sola transacción; si falla, no se confirma una orden parcial.

**¿Qué estados exactos tendrá una orden?**

Todavía no están aprobados. El documento usa «estado actual» de forma conceptual y deja la definición del ciclo de vida para coordinar con los módulos de laboratorio relacionados.

**¿Qué pasa si no existen órdenes para el episodio?**

Se muestra un estado vacío. No se interpreta como falla; una falla de consulta se comunica por separado.

**¿Ya existe este flujo en el repositorio?**

El diseño de datos prevé `lab_order` y `lab_order_item` en HOSPITAL, pero eso no demuestra que exista la pantalla, el servicio, la API ni los estados operativos del flujo.

## 5. Recorrido de demostración

1. Abrir [el documento principal](semana-01-uml-ordenes-laboratorio-emr.md) y explicar el objetivo, alcance y supuestos.
2. Mostrar [casos de uso](diagramas/semana-01-casos-de-uso.puml) y delimitar responsabilidades del profesional y los sistemas integrados.
3. Recorrer [actividad](diagramas/semana-01-actividad.puml), siguiendo primero una emisión correcta y luego una excepción.
4. En [secuencia](diagramas/semana-01-secuencia.puml), demostrar autorización, asociación al episodio, consulta del catálogo, persistencia y consulta de seguimiento.
5. Cerrar con una regla de la matriz de trazabilidad y explicar dónde aparece en cada vista.

## 6. Cómo modificar los diagramas

| Cambio solicitado | Elementos que deben mantenerse sincronizados |
|---|---|
| Añadir una validación del expediente | CU-01, actividad, mensajes de secuencia y R correspondiente. |
| Cambiar la regla de selección de pruebas | CU-01, D-03/E-03, secuencia de catálogo y matriz. |
| Aprobar un estado o transición nuevo | CU-02/CU-03, actividad, secuencia y matriz; confirmar con los módulos dependientes. |
| Incorporar cancelación de una orden | Especificar actor y permisos, precondiciones, transición y trazabilidad antes de actualizar los diagramas. |
| Añadir una notificación | Definir actor/sistema receptor y alinear alcance, secuencia, actividad y reglas. |

## 7. Referencia técnica del proyecto

La especificación del esquema federado contempla `lab_order` y `lab_order_item` entre las entidades previstas para HOSPITAL, sin establecer aquí el contrato final de estados ni el flujo de usuario. La recepción de muestras y los resultados tienen límites propios; la orden desde EMR debe integrarse con esos módulos sin asumir sus responsabilidades.
