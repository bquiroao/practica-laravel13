# Guía de defensa oral — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-14-plan-sqa.md](semana-14-plan-sqa.md)

> «El plan SQA tiene 7 métricas con fuente, umbral, responsable y acción, y una línea base medida hoy sobre `develop`, ya con la semana 4: 5 cumplen y 2 no. Las que no cumplen son los casos de uso sin prueba y los 2 hallazgos mayores de la inspección. La matriz tiene 8 riesgos; los dos altos son el tablero sin permiso y dos pacientes en una misma cama sin marcar.»

**¿Por qué el riesgo de dos estancias es alto si nunca debería pasar?** Precisamente por eso: el tablero es el detector. Si admisión deja dos estancias abiertas en una cama, R-04 pide marcarlo, y hoy `EXISTS` lo esconde. El control preventivo es contar las estancias; el detectivo, una prueba que lo provoque.

**¿Qué dice M-02 que no diga la inspección?** Lo mismo por otro camino. La única regla sin prueba es R-08 (permiso), y la inspección encontró que no hay permiso (H-01). Dos fuentes independientes que coinciden dan más confianza que una sola.

**¿Qué diferencia hay entre un control preventivo y uno detectivo?** El preventivo evita que el error entre, como los puertos sin métodos para cambiar camas. El detectivo lo encuentra si entró, como la prueba que confirma que consolidar no cambia ningún estado.
