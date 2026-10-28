# Guía de defensa oral — Semana 12

## Resumen en 60 segundos

«Defiendo el módulo de recepción de muestras con un recorrido ejecutable sobre el código real de la Semana 3: 10 pasos y 12 comprobaciones, todas pasan. Demuestro aceptación, rechazo por sello roto solo en sangre, acumulación de motivos, identificadores únicos y que una falla de guardado no se oculta. También muestro dos límites que el recorrido evidencia: el prototipo acepta una orden que no existe y acepta cualquier texto como motivo de rechazo. El rol está simulado en el script, porque el prototipo no tiene autorización. Respondo a un cambio hipotético, el motivo desde un catálogo, indicando qué componentes cambian y cuáles no.»

## Demostración

1. Ejecutar `php docs/modulos/recepcion-muestras/semana-12/recorrido/recorrido.php`.
2. Señalar los pasos 8 y 9 como límites, no como logros.
3. Abrir la matriz y mostrar qué decisiones están demostradas, en diseño o no implementadas.

## Preguntas probables

**¿Dónde está el recorrido navegable?** No hay interfaz. El recorrido ejecutable demuestra la lógica y los wireframes de la Semana 8 son el diseño de la navegación.

**¿El rol funciona?** No en el prototipo. La guarda del paso 1 está en el script y muestra el comportamiento esperado.

**¿Por qué dos pasos «que muestran fallas»?** Porque defender también es declarar lo que no está resuelto: orden inexistente y motivo libre.

**¿Cuál era el cambio solicitado?** La captura no lo traía; usé uno hipotético, el catálogo de motivos, y lo declaré.

**¿Qué componentes cambian con el catálogo?** Validación del dominio, columna de persistencia y selector; no cambian la política, los puertos ni el generador.

**¿Inventaste motivos de rechazo?** No: el catálogo real lo define el laboratorio.

## Evidencia

- [Documento](semana-12-defensa-recepcion-muestras.md)
- [Recorrido](recorrido/recorrido.php) · [Salida](evidencia/recorrido-semana-12.txt)
