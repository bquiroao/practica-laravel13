# Guía de defensa oral — Semana 7

## Resumen en 60 segundos

«Diseñé los componentes de la validación de resultados: en el frontend una pantalla de revisión y un cliente de API, en el backend un controlador y un mapeador de respuestas, el caso de uso con tres contratos, el dominio y los adaptadores de persistencia. El punto más acoplado era `CoupledLabResultReview`: mezclaba rol por texto, SQL, transacciones, reloj y resultados como texto libre. El refactor lo separa: el caso de uso solo conoce autorización, reloj y repositorio. Lo medí: PDO, SQL, transacciones, reloj, rol fijo y texto libre bajan de presentes a cero, y las 33 pruebas de la Semana 2 pasan. El código total no baja, porque se reparte en piezas; la ganancia es la dirección de las dependencias.»

## Demostración

1. Mostrar el diagrama y distinguir lo que existe de lo propuesto.
2. Ejecutar `php semana-07/verificacion/acoplamiento.php`.
3. Abrir los contratos de entrada y salida de la sección 3.

## Preguntas probables

**¿Escribiste código nuevo?** No. El refactor concreto es el «antes/después» de la Semana 2; aquí lo justifico y completo el diseño con presentación y frontend, que son propuestas.

**¿Por qué no bajó el código?** Porque se separan piezas; la mejora es poder cambiar base de datos o permisos sin tocar las reglas.

**¿Qué defecto tenía el «antes»?** Un resultado inexistente se respondía como estado inválido, y el rol llegaba como texto del cliente.

**¿Cómo se relacionan los errores con la interfaz?** El mapeador convierte los nueve resultados en respuestas; la tabla HTTP es una propuesta.

**¿Se amplió el módulo?** No: no hay captura, entrega ni reglas bioquímicas.

**¿Qué falta?** Adaptador PDO, roles, reloj, firma y separación de funciones.

## Evidencia

- [Documento](semana-07-componentes-refactorizacion-validacion.md)
- [Diagrama](diagramas/semana-07-componentes.svg) · [Fuente](diagramas/semana-07-componentes.puml)
- [Medición](verificacion/acoplamiento.php) · [Salida](evidencia/acoplamiento-semana-07.txt)
