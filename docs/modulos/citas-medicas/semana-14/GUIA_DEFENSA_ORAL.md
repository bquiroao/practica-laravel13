# Guía de defensa oral — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-14-plan-sqa.md](semana-14-plan-sqa.md)

> «El plan SQA tiene 7 métricas con fuente, umbral, responsable y acción, y una línea base medida hoy: 5 cumplen y 2 no. Las que no cumplen son la trazabilidad de los casos de uso y los 5 hallazgos mayores de la inspección. La matriz tiene 8 riesgos; el más alto es operar sin permiso, y el que más pesa para la agenda es la doble reserva por concurrencia.»

**¿Por qué la doble reserva es un riesgo alto si la base tiene exclusión?** Porque la barrera evita la cita doble, pero hoy el error llegaría como 503 E8 y no como 409 E6. El control preventivo es la barrera traducida a E6, y el detectivo es una prueba que intercala la segunda solicitud.

**M-02 da 80 %: ¿por qué no basta?** Porque cumple justo en el umbral, y una regla nueva sin prueba lo haría caer. Por eso tiene su propio riesgo (RG-06): faltan R-06 (motivo obligatorio) y R-09 (permisos).

**¿Qué diferencia hay entre un control preventivo y uno detectivo?** El preventivo evita que el error entre, como el reloj inyectado en `confirmar`. El detectivo lo encuentra si entró, como la prueba que confirma una cita después de su inicio.
