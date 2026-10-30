# Guía de defensa oral — Semana 1 (UML del proceso)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-01-uml-proceso-qa-regresion.md](semana-01-uml-proceso-qa-regresion.md)

## 1. Resumen en 60 segundos

El HIS ya hace regresiones, pero las anota a mano en `apply-progress.md`, del tipo «C1–C4 passed with 233 assertions». A ese registro le faltan cuatro cosas, y este módulo las da:
- **el vínculo con los requisitos;**
- **la evidencia;**
- **un defecto cuando algo falla;**
- **un criterio de salida.**

Modelé el proceso en tres vistas que comparten identificadores:
- **Casos de uso:** 14, con 5 actores humanos y 3 sistemas.
- **Actividad:** 26 decisiones, 19 excepciones y 10 resultados, entre ellos GO y NO-GO.
- **Secuencia:** 76 mensajes en 6 bloques.

Cuatro reglas pesan más que el resto:
- el alcance se valida contra la matriz de regresión al iniciar;
- sin evidencia confirmada no hay FALLA;
- el defecto lo cierra QA re-ejecutando, no el desarrollador;
- el GO se calcula: 0 críticos abiertos y la tasa de aprobados ≥ el umbral.

## 2. Orden sugerido

1. El punto de partida: el registro manual de `apply-progress.md` (§1).
2. Actores y segregación de funciones (§3).
3. El modelo de objetos (§4): por qué `Intento` y no «resultado del caso».
4. Casos de uso: lo que **no** está (editar resultado, borrar evidencia, el desarrollador cerrando).
5. Actividad: D-08 (alcance), D-15 (almacén) y F-07 (NO-GO como resultado).
6. Secuencia: el bloque 2 (FALLA) y el ejemplo numérico del cierre.
7. La matriz de trazabilidad y sus dos huecos declarados.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el resultado es un `Intento` y no un campo del caso? | Porque un caso se ejecuta varias veces: falla, se corrige y se vuelve a ejecutar. Si el resultado se sobrescribiera, se perdería la historia que justifica el cierre del defecto (R-11). |
| ¿Por qué no registrar la FALLA aunque el almacén esté caído? | Una FALLA sin evidencia nadie la puede revisar, y es justo lo que se decide a partir de ella. Es el mismo criterio *fail-closed* del resto del HIS: ante la duda, no se registra algo incompleto. |
| ¿Por qué el alcance se valida al iniciar y no al planificar? | Mientras se planifica, la matriz y los casos pueden cambiar. Lo que importa es que el alcance esté completo cuando se congela. |
| ¿Quién decide qué es «crítico»? | La matriz de regresión, que mantiene el Líder QA. «Crítico» se refiere al **caso**: si su funcionalidad cambia, corre siempre. No es la severidad de un defecto. |
| ¿Por qué faltan CU-01 y CU-02 en la secuencia? | Diseñar y aprobar casos ocurre antes de la campaña; la secuencia se concentra en la ejecución. Está en la actividad y declarado en la matriz. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Que el umbral de GO sea 100 %» | Es un parámetro del tenant (R-09). Con 100 %, un solo BLOQUEADO por un ambiente caído impediría el GO; por eso conviene separarlo de FALLA. |
| «Importar los resultados de PHPUnit» | Otro tipo de evidencia: el resultado importado de una suite automática. Es la extensión que la semana 2 trata con LSP. |
| «Permitir que el desarrollador cierre defectos triviales» | Se puede como excepción explícita (severidad TRIVIAL), pero rompe la segregación. Lo dejaría con una aprobación del Líder QA. |
