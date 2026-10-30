# Guía de defensa oral — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-14-plan-sqa.md](semana-14-plan-sqa.md)

> «El plan SQA tiene 7 métricas con fuente, umbral, responsable y acción, y una línea base medida hoy sobre `develop`: 4 cumplen y 3 no. Las que no cumplen son la trazabilidad de reglas y de casos de uso, y los 4 hallazgos mayores de la inspección. La matriz tiene 8 riesgos y 4 altos, todos sobre datos sensibles: auditoría, alcance, rol de apertura y acceso de emergencia.»

**¿Por qué el expediente tiene más riesgos altos que los otros módulos?** Porque el impacto de casi todo es 3: un acceso indebido a un dato clínico no se puede deshacer. Con el mismo error de código, el riesgo sube más aquí que en un tablero de conteos.

**¿Cómo se ve que las métricas y la inspección coinciden?** Las reglas sin prueba de M-02 incluyen el alcance (R-09) y la emergencia (R-07), y la inspección encontró que no hay alcance (H-03) y dejó la emergencia como «no aplica». Dos fuentes independientes apuntan al mismo hueco.

**¿Qué diferencia hay entre un control preventivo y uno detectivo?** El preventivo evita que el error entre, como un trigger que bloquea el `UPDATE` de la auditoría. El detectivo lo encuentra si entró, como la prueba que intenta modificar un evento y espera que falle.
