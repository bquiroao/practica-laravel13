Aviso de actualización: este documento conserva el diseño inicial. La [entrega navegable final y sus reglas](README.md) precisa alcance: ítem demo fijo, estado pendiente sin selector clínico, corrección versionada y evidencia real de navegador. No se consideran implementadas otras propuestas de este documento.

# Semana 11 — Prototipo navegable para ingreso de resultados de laboratorio

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Ingreso de resultados de laboratorio |
| **Tema** | Mejores prácticas para diseño móvil/web |
| **Proceso** | Captura y corrección controlada de resultados pendientes |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## 1. Objetivo

Construir un prototipo navegable desktop y móvil del flujo principal del ingreso de resultados de laboratorio. El foco es una experiencia clara, segura y accesible para el personal responsable de registrar resultados pendientes, corregir errores con motivo y mantener el estado de validación controlado.

La propuesta no reemplaza la lógica de negocio del prototipo técnico de la Semana 3; es una capa de experiencia de usuario orientada a una operación responsable, con mínimo riesgo clínico y consistencia visual entre pantallas.

## 2. Alcance del prototipo

Se considera el recorrido principal:

- seleccionar una orden y un ítem de examen;
- revisar el resultado pendiente o el valor actual;
- ingresar un resultado nuevo o corregir un valor vigente;
- validar unidades, datos y rango si el catálogo lo permite;
- guardar un resultado en estado `pending` o devolver un error crítico;
- registrar motivo y autorización para corrección controlada;
- confirmar el estado final y conservar la trazabilidad.

Se excluye el cálculo clínico, la firma profesional, la publicación definitiva del expediente y la integración con sistemas externos de laboratorio.

## 3. Principios de diseño aplicados

1. Una tarea por pantalla.
2. Contexto clínico mínimo pero suficiente.
3. Un único punto de decisión por vista.
4. Prevención de errores antes que corrección tardía.
5. Consistencia entre desktop y móvil.
6. Bajo riesgo de ambigüedad: no ocultar errores ni estados críticos.
7. Accesibilidad real: foco visible, contraste suficiente, tamaños táctiles y navegación por teclado.

## 4. Prototipo navegable: flujo principal

### Pantalla A — Lista de pendientes

Objetivo: presentar a la persona responsable el conjunto de resultados pendientes por validación.

Elementos clave:

- buscador por orden, paciente o ítem;
- filtro por estado (`pending`, `rejected`, `corrected`);
- tarjeta por examen con resumen mínimo;
- acción principal: `Ingresar resultado`;
- accesos de ayuda y seguridad; no mostrar datos clínicos sensibles innecesarios.

Buenas prácticas:

- usar una tarjeta por ítem con información restringida;
- mantener el estado visible en un chip con texto y color no dependiente del color;
- permitir la búsqueda con un único campo y filtros simples.

### Pantalla B — Detalle del ítem y resultado actual

Objetivo: revisar el contexto antes de ingresar la nueva respuesta.

Elementos clave:

- número de orden, paciente, ítem, tipo de prueba, fecha de solicitud;
- resultado actual y estado; si el resultado está pendiente, se marca claramente;
- botón `Editar resultado` o `Corregir`;
- detalle de última versión y motivo si aplica;
- aviso si el valor ingresado requiere revisión antes de guardar.

Buenas prácticas:

- distinguir claramente entre resultado vigente y resultado pendiente;
- separar la lectura de la edición para reducir errores de sobrescritura;
- mostrar un resumen breve antes de confirmar registro.

### Pantalla C — Ingreso del resultado

Objetivo: registrar un valor con la menor fricción posible y máxima claridad.

Elementos clave:

- campo `valor` con unidad y formato sugerido;
- campo `resultado observado` y/o `valor nuevo`;
- selector de estado: `pendiente`, `corregido`, `rechazado` según flujo autorizado;
- validación inmediata con mensajes asociados al campo;
- ejemplo de formato de entrada dependiendo del tipo de prueba;
- botón `Guardar resultado`.

Reglas de diseño:

- si el valor está vacío, mostrar un error asociado al campo;
- si el valor excede el rango, mostrar mensaje explícito sin depender solo de color;
- si la prueba necesita unidades, mostrarlas junto al campo y no solo en texto auxiliar;
- permitir deshacer la edición antes del guardado.

### Pantalla D — Corrección controlada

Objetivo: permitir una corrección de un resultado previo cuando el sistema lo autoriza.

Elementos clave:

- versión actual esperada;
- campo para ingresar el nuevo valor;
- motivo obligatorio: `transcripción`, `valor erróneo`, `unidad incorrecta`, `otro`;
- comentario opcional si corresponde;
- botón `Confirmar corrección`.

Reglas de seguridad UX:

- la corrección no se realiza sin mostrar la versión previa y la nueva propuesta;
- el sistema debe indicar si la corrección no aplica porque la versión ya no está pendiente;
- la acción debe mantenerse reversible solo por un flujo administrativo dedicado, no por un cierre rápido del formulario.

### Pantalla E — Confirmación de guardado

Objetivo: confirmar la operación sin dejar dudas sobre el estado final.

Elementos clave:

- resumen del valor ingresado y de la operación ejecutada;
- estado final: `pendiente`, `corregido`, `rechazado`;
- identificación del actor y del momento de registro;
- botón `Volver a pendientes` o `Continuar`; 
- aviso si el resultado requiere validación posterior.

## 5. Error crítico y manejo de riesgo

El error crítico de este flujo es permitir que un valor, una unidad o un rango se registre con pérdida de contexto o sin motivo de corrección. Para evitar esto, el prototipo define mensajes y estados muy claros:

| Error crítico | Señal UX | Acción recomendada |
|---|---|---|
| Valor vacío o no válido | error asociado al campo y bloqueo de guardado | pedir corrección inmediata |
| Unidad incompatible | mensaje junto al campo y resaltado | requerir validación antes de guardar |
| Resultado fuera de rango | aviso de riesgo con texto claro | sugerir revisión humana y mantenér en estado pendiente |
| Corrección con versión obsoleta | bloqueo con explicación | volver a cargar el ítem y repetir la operación |
| Falta de motivo de corrección | bloqueo del botón | pedir motivo obligatorio |
| Estado no permitido para la operación | mensaje de rechazo técnico | no guardar ni continuar |

## 6. Responsive desktop/móvil

### Desktop

- dos columnas: lista de pendientes + detalle/edición;
- estructura clara para personal de laboratorio o bioquímico;
- suficiente espacio para revisar resultados y motivos antes de guardar.

### Móvil

- una columna con navegación por secciones;
- encabezado sticky con estado, paciente y número de orden;
- botones grandes con etiqueta clara;
- campos con entrada autocompletada o sugerida; no sobrecargar la pantalla.

### Reglas de consistencia

- misma jerarquía de información;
- misma nomenclatura entre pantallas;
- misma prioridad de botones: primario, secundario, de advertencia;
- mismo patrón de validación y de guardado.

## 7. Accesibilidad

Se aplican criterios de accesibilidad centrándose en la operación segura:

- contraste suficiente entre fondo y texto;
- foco visible en todos los controles;
- tamaños táctiles mínimos para móvil;
- etiquetas visibles próximas a campos;
- mensajes de error leídos por lectores de pantalla;
- iconos y estados con texto asociado, no solo color;
- flujo navegable por teclado en desktop;
- mensajes de advertencia con lenguaje claro y accionable.

## 8. Prototipo propuesto por pantallas

### 8.1 Ruta principal

```
Pendientes → Detalle de ítem → Ingreso/Corrección → Confirmación
```

### 8.2 Ruta alternativa: error crítico

```
Pendientes → Detalle → Valor inválido o fuera de rango → Error asociado al campo → Revisión antes de guardar
```

### 8.3 Ruta alternativa: corrección controlada

```
Pendientes → Detalle → Elegir corregir → Confirmar motivo → Ver resumen → Guardar y volver a pendientes
```

## 9. Criterios de éxito de la propuesta

El prototipo se considera correcto si cumple lo siguiente:

- permite ingresar resultados y corregir resultados pendientes con claridad;
- mantiene la información mínima y relevante para no saturar la pantalla;
- diferencia claramente entre resultado vigente, pendiente y corregido;
- presenta errores críticos sin ambigüedad;
- funciona en móvil y desktop sin perder consistencia;
- respeta accesibilidad y patrones de seguridad operativa.

## 10. Conclusión

El diseño del prototipo para ingreso de resultados de laboratorio debe priorizar la seguridad del dato y la claridad de la decisión. Un flujo con pocos pasos, validación temprana y mensajes asociados al campo ayuda a reducir errores en tiempo real sin frenar a la persona operaria. La pantalla no debe “prometer” un resultado clínico, sino reforzar una acción responsable, trazable y validable.

La propuesta para esta semana queda en el nivel de experiencia de usuario y proceso, con foco en la ergonomía de trabajo, consistencia visual y prevención de errores críticos.
