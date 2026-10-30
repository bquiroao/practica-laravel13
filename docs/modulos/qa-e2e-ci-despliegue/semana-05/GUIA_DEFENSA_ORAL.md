# Guía de defensa oral — Semana 5 (cliente-servidor y frontera)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Documento:** [semana-05-cliente-servidor-frontera.md](semana-05-cliente-servidor-frontera.md)

## 1. Resumen en 60 segundos

Escribí el contrato OpenAPI 1.0.0: 8 operaciones con su permiso y 36 códigos de error. Cuatro pruebas comparan el contrato con el código en cada corrida.

El cliente del pipeline reintenta con la misma clave. Si la API está caída, deja el resultado pendiente en el workspace del job y lo reenvía al final, sin perder ni duplicar nada.

Evalué separar un servicio de ingesta midiendo:
- agrega 21 ms por resultado;
- con la API caída no aísla nada: falla igual y tarda 2 segundos en decirlo.

Cero de cinco criterios para separar se cumplen: queda el monolito modular, y la resiliencia se resuelve en el borde.

Además dejé el plan de issue, rama, worktree y PR según las reglas del repositorio.

## 2. Demostración sugerida (3 minutos)

1. `php tests/ejecutar.php --solo=API`: contrato = código.
2. `--solo=CLI`: CLI-02, respuesta perdida, 200 y un solo resultado.
3. Mostrar la tabla de `medicion-frontera.txt`: la fila E.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué no separar la ingesta si el CI escribe mucho? | Lo medí: +21 ms por resultado, no aísla fallas (la ingesta depende de la API) y necesitaría los datos del módulo para validar. 0 de 5 criterios. |
| ¿Y si la API se cae en medio de un job? | Los resultados quedan pendientes en el workspace del job con su clave y se reenvían en el paso final. El gate no evalúa una ejecución incompleta. |
| ¿Por qué la clave es determinística? | Para que un job reiniciado no duplique: el mismo escenario en la misma ejecución da la misma clave. |
| ¿Por qué no se reintenta un 422? | Porque el servidor ya decidió; reintentarlo solo carga la API y retrasa el job. |
| ¿Qué es el X-Request-ID? | Un identificador que viaja del job al servidor y aparece en los dos logs. |
| ¿Corrió un validador de OpenAPI? | API-04 valida la estructura; Redocly no se pudo descargar en este entorno, y queda como paso del job de CI. |
| ¿Por qué un código distinto para 200 y 409? | Antes `RESULTADO_YA_REPORTADO` significaba las dos cosas, y el pipeline no podía distinguir un reintento exitoso de un conflicto. |
