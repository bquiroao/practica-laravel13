# Guía de defensa oral — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-14-plan-sqa.md](semana-14-plan-sqa.md)

> «El plan SQA tiene 7 métricas, cada una con fuente, umbral, responsable y acción, y una línea base medida hoy con un script: 4 cumplen y 3 no. Las que no cumplen son la trazabilidad de reglas, la de casos de uso y los hallazgos abiertos de la inspección. La matriz tiene 8 riesgos; los 4 altos tienen un control preventivo, uno detectivo y una semana para cerrarse.»

**¿Por qué publicar métricas que no cumplen?** Porque la línea base sirve para saber dónde se está, no para quedar bien. Si M-02 dijera «cumple» sin medirlo, el plan no protegería nada. Las acciones dicen qué prueba falta (R-02, R-07 y R-08) y cuándo se agrega (semana 15).

**¿Qué diferencia hay entre un control preventivo y uno detectivo?** El preventivo evita que el error entre: por ejemplo, permitir solo sentencias preparadas. El detectivo lo encuentra si entró: por ejemplo, la prueba que busca SQL concatenado. Cada riesgo alto tiene uno de cada tipo.

**¿Cómo sé que el script mide bien?** Con mutaciones: en una copia se agregó una consulta inyectable y un método de 21 líneas, y el script marcó M-04 y M-05 como «NO CUMPLE».
