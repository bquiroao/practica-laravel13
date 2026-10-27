# Guía de defensa oral — Semana 10

## Resumen en 60 segundos

«Adapté el flujo de recepción a móvil, de 320 a 430 píxeles, con cuatro pantallas: buscar la orden y el ítem, verificar la muestra, decidir y ver el guardado. Cada pantalla tiene un solo objetivo y una acción principal. Las tablas pasan a tarjetas, los botones miden 48 píxeles y los estados llevan texto además de color. Defino dos escenarios: rechazar una muestra de sangre con sello roto y confirmar con conexión limitada. En el segundo, nunca muestro "guardado" hasta que el servidor lo confirma; la decisión queda pendiente y el reintento usa la misma clave para no duplicar la recepción. Es una propuesta: no hay app ni pruebas en dispositivos.»

## Preguntas probables

**¿Por qué una sola columna?** El flujo es secuencial y el ancho es de 320 a 430 px; una columna reduce la carga cognitiva.

**¿Qué pasa si se cae la conexión?** La decisión queda pendiente, no se informa éxito, se conservan los datos y el reintento no crea una segunda recepción.

**¿Por qué no se muestra «aceptada» sin confirmación?** Porque una aceptación falsa podría procesar una muestra sin registro.

**¿Cómo se maneja la tabla de custodia?** Como tarjetas con hora, lugar y persona.

**¿Por qué el sello solo en sangre?** Es la regla del prototipo de la Semana 3.

**¿Se probó en un celular?** No; son wireframes y reglas propuestas.

**¿Inventaste motivos de rechazo?** No: el catálogo lo define el laboratorio.

## Evidencia

- [Documento](semana-10-movilidad-recepcion-muestras.md)
- [M1](wireframes/M1-buscar-orden-item.svg) · [M2](wireframes/M2-verificar-muestra.svg) · [M3](wireframes/M3-decision-rechazo.svg) · [M4](wireframes/M4-guardado-y-conexion.svg)
