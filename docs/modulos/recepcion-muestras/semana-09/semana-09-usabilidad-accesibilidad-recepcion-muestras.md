# Semana 9 — Evaluación de usabilidad y accesibilidad

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Recepción de muestras y códigos identificadores |
| **Tema** | Evaluación de usabilidad y accesibilidad |
| **Flujo** | «Recepción, identificación inequívoca y rechazo controlado de muestra» |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## 1. Objetivo

Evaluar la propuesta de interacción para la recepción de muestras desde la perspectiva de usabilidad, accesibilidad y seguridad operativa. El foco está en que el personal autorizado pueda completar la recepción sin ambigüedad, detectar errores de manera clara y evitar decisiones clínicas no sustentadas.

La evaluación es cualitativa y se apoya en la propuesta de UX de la Semana 8. No sustituye un estudio de usuario formal ni una validación con personal laboratorista real, pero sí permite detectar fricciones relevantes antes de la implementación.

## 2. Alcance del análisis

Se revisan los siguientes escenarios:

- búsqueda de la orden y del ítem a recibir;
- verificación de la muestra, paciente y orden;
- confirmación de aceptación o rechazo;
- manejo de errores recuperables y estados de carga;
- trazabilidad de custodia y motivo del rechazo;
- usabilidad por teclado, foco y lectura con tecnologías de asistencia.

Se asume que la persona operaria ya tiene permisos del hospital, que la muestra física se entrega en un punto controlado y que cada decisión deben quedar registradas con actor, momento y motivo.

## 3. Checklist de usabilidad y accesibilidad

| Criterio | Estado | Evidencia | Observación / acción |
|---|---|---|---|
| El propósito de la pantalla es claro al entrar | Cumple | Inicio de recepción + resumen de orden | La pantalla indica qué se va a verificar antes de confirmar.
| Flujo orientado a una tarea por vez | Cumple | Orden e ítem → verificación → confirmación | Se evita la sobrecarga de decisiones simultáneas.
| Mensajes de error específicos y accionables | Parcial | Error recuperable y rechazo con motivo | Falta reforzar la diferenciación entre error técnico, inconsistencia clínica y falta de permisos.
| Recuperación de errores sin pérdida de contexto | Cumple | Entrada persistente y reintento | La información no desaparece al corregir búsqueda o motivo.
| Mínima carga cognitiva | Cumple | Diagnóstico resumido y pasos lineales | La información clave aparece antes que el detalle.
| Confirmación explícita antes de aceptar o rechazar | Cumple | Pantalla de confirmación | La decisión final requiere revisar resumen y no se hace "en silencio".
| Estado de progreso visible | Cumple | Carga al buscar y al guardar | Hay continuidad de feedback durante la operación.
| Uso de color sin depender de él | Parcial | Se propone estado visual | Debe añadirse iconografía, texto y semántica para evitar dependencia cromática.
| Etiquetas y campos accesibles | Parcial | Formulario con datos mínimos | Se requiere asegurar `label`, asociación visible y nombres entendibles por lector de pantalla.
| Foco y navegación por teclado | Parcial | No validado en flujo completo | Deben definirse orden de tabulación, atajos y foco tras errores.
| Contraste suficiente | Parcial | No verificado en mockup | Necesario revisar combinación de fondo, texto y estados de error/alerta.
| Mensajes de validación asociados al campo | Parcial | Rechazo requiere motivo | Se deben anclar al campo y permitir corrección sin reabrir todo el flujo.
| Soporte para lectura de pantalla | Parcial | No validado en interacción | Las alertas de validación deben aparecer como mensajes accesibles y no solo visuales.
| Trazabilidad de decisiones | Cumple | Registro de actor, motivo y momento | La evidencia queda documentada tras aceptar o rechazar.
| Claridad del rol y permisos por acción | Cumple | Tabla de permisos UX | La interfaz debe impedir acciones fuera de la función autorizada.

## 4. Hallazgos principales

| # | Severidad | Hallazgo | Impacto | Recomendación |
|---:|---|---|---|---|
| 1 | Alta | La validación de correspondencia entre orden, muestra y paciente está resumida, pero no se refuerza con una decisión visual explícita cuando el vínculo no coincide. | Riesgo de error humano y aceptación indebida. | Añadir bloqueo de acción con mensaje claro: "No coincide con la orden seleccionada" y una acción de retorno directa al paso anterior.
| 2 | Alta | El flujo usa estados visuales que podrían depender del color. | Problema de accesibilidad y comprensión en ambientes de baja visión. | Usar texto, iconos y patrón de estado independiente del color para cada estado: aceptado, rechazado, pendiente, error.
| 3 | Media | Las pantallas de error y vacíos están definidas, pero no quedan claramente separadas de la ausencia de resultado real. | El usuario puede interpretar mal la causa del problema y repetir acciones incorrectas. | Diferenciar claramente: "sin resultados disponibles", "servicio no disponible", "error de permisos" y "muestra no corresponde".
| 4 | Media | El motivo de rechazo se presenta como un campo importante, pero la validación exacta de obligatoriedad y el tipo de observación no quedan definidos en la misma vista. | El rechazo podría completarse incompleto o con texto no estandarizado. | Mostrar el catálogo de motivos ordenados y requerir validación por regla del laboratorio; separar observación libre de la causa principal.
| 5 | Media | La navegación por teclado y el foco tras un error necesitan definirse en la interacción real. | Dificultad para usuarios con lector de pantalla o teclado. | Definir orden de tabulación, foco al primer error y mensajes de validación con `aria-live` o equivalente.
| 6 | Baja | La trazabilidad del evento inicial de custodia está prevista, pero no queda clara la diferencia entre "registro guardado" y "impresión/etiqueta emitida". | Genera confusión operativa. | Separar de forma explícita los estados de "persistido", "impreso" y "pendiente de resolución".

## 5. Evaluación de la experiencia por escenario

### 5.1 Búsqueda de orden e ítem

La experiencia es clara y tiene un buen nivel de orientación. La persona operaria comprende qué está buscando antes de entrar al flujo. Sin embargo, el sistema debe distinguir con mayor precisión entre:

- orden no encontrada;
- orden encontrada pero no asignada a ese hospital o servicio;
- servicio temporalmente no disponible;
- muestra ya recibida o en proceso.

Esto evita la falsa sensación de “resultado vacío” cuando la causa es un error de infraestructura o de permisos.

### 5.2 Verificación de identidad de la muestra

Es el punto más crítico del flujo. La pantalla de verificación tiene buena carga cognitiva porque resume la información relevante. La mejora clave es reforzar la decisión de bloqueo cuando la muestra no coincide con la orden o un dato clave no está validado.

### 5.3 Confirmación de aceptación

La decisión final es comprensible y segura porque exige una confirmación de la tarea. La mejora principal aquí es reforzar la correspondencia entre la acción y el identificador único generado, para que la persona operaria entienda claramente qué se está registrando.

### 5.4 Rechazo controlado

El rechazo es la parte más sensible del flujo por el riesgo clínico y operativo. La propuesta es fuerte porque exige motivo. Falta reforzar la diferenciación entre:

- rechazo por no conformidad del recipiente;
- rechazo por inconsistencia de identificación;
- rechazo por condiciones del transporte o la muestra;
- rechazo por criterio del laboratorio determinate por procedimiento.

## 6. Recomendaciones priorizadas

1. Reforzar la validación de coincidencia entre orden, muestra, paciente e ítem con bloqueo visual y textual antes de autorizar la acción.
2. Separar claramente los estados de error, vacío, servicio no disponible y rechazo según la causa, para reducir interpretaciones equivocadas.
3. Garantizar accesibilidad operativa: contraste, iconografía, texto alternativo, mensajes asociados a campo y foco consistente.
4. Añadir una capa de validación por rol para que la recepción, el laboratorio y la jefatura vean solo lo que corresponde a su función.
5. Documentar la diferencia entre "guardado en sistema" y "etiqueta/impresión entregada" para prevenir confusión de estado.
6. Para cada decisión crítica, dejar una huella de evidencia: actor, hora, orden, motivo y resultado del registro.

## 7. Criterios de mejora para la siguiente iteración

La propuesta de UX puede considerarse lista para la siguiente iteración si cumple al menos estas condiciones:

- Los errores se distinguen claramente de la ausencia de resultados.
- El flujo conserva contexto al corregir errores sin perder la entrada del usuario.
- El bloqueo de acción ocurre cuando una muestra no corresponde a la orden.
- El sistema se entiende sin depender del color como única señal.
- La navegación por teclado y el foco quedan definidos.
- Los motivos de rechazo tienen un catálogo claro y una obligatoriedad explícita.
- La información de custodia y el identificador único quedan visibles en el momento de la confirmación.

## 8. Conclusión

La propuesta de recepción de muestras presenta un flujo lógico, seguro y bien estructurado para personal autorizado. La mayor fortaleza es que el proceso mantiene la decisión centrada en la evidencia física y en la trazabilidad del evento. La principal mejora pendiente es reforzar la accesibilidad y la diferenciación de errores para que el sistema sea robusto tanto para usuarios con baja visión como para operarios bajo presión en la recepción.

En términos de diseño, la solución está en buen camino: tiene un orden de tareas claro, confirma decisiones clave y deja una traza documental de identidad y rechazo. La implementación futura debe priorizar accesibilidad, claridad de estado y validación de correspondencia para convertir este flujo de laboratorio en una operación segura y confiable.
