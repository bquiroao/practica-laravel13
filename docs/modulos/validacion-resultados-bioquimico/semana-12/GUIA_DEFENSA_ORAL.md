# Guía de defensa oral — Semana 12

## Resumen en 60 segundos

«Defiendo la validación de resultados por bioquímico con un recorrido ejecutable de 10 pasos sobre el prototipo de la Semana 2. El caso de uso decide con tres contratos: autorización, reloj y repositorio. Muestro un bioquímico que valida y otro que rechaza con motivo, y los errores: sin permiso, otro hospital, contexto incompleto, resultado ya decidido, versión desactualizada y falla de escritura con reintento. La matriz une cada decisión con sus RF/RNF y su evidencia, y marca lo que no está demostrado: la interfaz y la firma. Documento también la respuesta a un cambio, que quien captura no pueda validar.»

## Demostración

1. Ejecutar el recorrido y señalar los pasos 1, 2, 4, 8 y 9.
2. Abrir la matriz de la sección 5 y mostrar las filas D11 y D12 como no demostradas.
3. Abrir la sección 7 y explicar CC-01.

## Preguntas probables

**¿Dónde está la interfaz?** No existe; UX, accesibilidad y movilidad son decisiones de diseño sin probar con usuarios.

**¿Por qué el otro hospital da «no encontrado»?** Para no revelar que el resultado existe.

**¿Qué pasa si falla la escritura?** Se informa el fallo, no queda decisión parcial y el reintento funciona (pasos 9 y 10).

**¿Hay firma?** No. La decisión guarda actor, hospital y momento, pero no firma ni resumen del valor (REV13-03).

**¿De dónde viene CC-01?** Es hipotético; no recibí una solicitud concreta. Lo elegí porque responde al hallazgo REV13-02.

**¿Por qué «aceptado con condiciones»?** Si solo hay un bioquímico, la regla bloquearía la operación; el responsable debe decidir la excepción.

## Evidencia

- [Documento](semana-12-defensa-validacion-resultados.md)
- [Recorrido](recorrido/recorrido.php) · [Salida](evidencia/recorrido-semana-12.txt)
