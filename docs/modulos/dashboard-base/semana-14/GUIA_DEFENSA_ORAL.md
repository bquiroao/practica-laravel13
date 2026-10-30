# Guía de defensa oral — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-14-plan-sqa.md](semana-14-plan-sqa.md)

> «El plan SQA tiene 7 métricas con fuente, umbral, responsable y acción, y una línea base medida hoy sobre `develop`, ya con la semana 4: 5 cumplen y 2 no. Las que no cumplen son los casos de uso sin prueba (CU-03 y CU-05) y el hallazgo mayor de la inspección. La matriz tiene 8 riesgos y uno solo es alto: que el cliente envíe su propio hospital y sus permisos.»

**¿Por qué M-04 y M-01 son métricas distintas, si las dos miran el SQL?** Porque miden cosas distintas. En la prueba de mutación, una consulta inyectable en `RepositorioEnMemoria.php` dejó M-01 en verde, porque la prueba del Micro-HIS solo revisa `RepositorioPdo.php`. M-04, en cambio, revisa toda la carpeta y la detectó.

**¿Por qué M-03 importa si todas las reglas tienen prueba?** Porque una regla probada no garantiza que el caso de uso funcione de punta a punta. Faltan justo navegar (CU-03) y autorizar (CU-05), lo mismo que la inspección encontró por otro camino.

**¿Qué diferencia hay entre un control preventivo y uno detectivo?** El preventivo evita que el error entre, como hacer solo `COUNT` en las tarjetas. El detectivo lo encuentra si entró, como la prueba que comprueba que la tarjeta solo trae id, estado y texto.
