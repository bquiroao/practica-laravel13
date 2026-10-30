# Guía de defensa oral — Semana 14 (plan SQA)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-14-sqa-prescripciones.md](semana-14-sqa-prescripciones.md)

## 1. Resumen en 60 segundos

El plan asegura una cosa: **que ninguna prescripción se emita contra una alergia que debía impedirla**. Tiene:
- **7 métricas**, cada una con fuente, umbral, responsable y acción. Un script las mide sobre el código real.
- **7 controles preventivos y 7 detectivos.** Los detectivos SQL se probaron en una base con el esquema real.
- **Una matriz de 11 riesgos**, responsables y calendario.

La línea base tiene **3 rojos**, y son los tres hallazgos graves de la revisión, ahora medidos. El más importante es **M3**: la sensibilidad de la verificación sobre un corpus de alergias escritas de distintas formas da **9 de 12**. Las 111 pruebas pasan, pero tres alergias graves no se detectan.

Comprobé que las métricas detectan defectos: inyecté 4 y los detectaron todos. Uno, el código de contrato sin prueba, solo lo ve M6.

## 2. Orden sugerido

1. Propósito y atributos (plan §1).
2. Las 7 métricas y la línea base: correr `medir-sqa.php` y detenerse en M3.
3. `probar-deteccion.php`: por qué se compara el valor y no el color.
4. La matriz: RK-01 con 9, y sus dos controles detectivos (M3 y C-D4).
5. Plan de acción y calendario (plan §7–§8).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué M3 si ya hay 111 pruebas? | Las pruebas verifican el código contra los nombres del catálogo. El corpus mide contra cómo se registran las alergias en el mundo. Por eso lo mantiene farmacia, no el desarrollador. |
| ¿No es trampa poner métricas en rojo? | Al revés: un plan todo en verde mide lo que ya se sabía. Cada rojo tiene acción, responsable y fecha. |
| ¿Por qué un farmacéutico en el plan? | Porque decide qué es un falso negativo clínico (AR-03) y qué hacer con una intolerancia (AR-07). Sin farmacia, M3 mide solo lo que imagina el autor. |
| ¿Cómo sabe que las métricas no dan falsos rojos? | No lo sabía: M5 dio un falso rojo en la primera versión, por un error de la herramienta. Lo reproduje aparte y lo corregí (§4 del documento). |
| ¿Qué pasa con `SERVICIO_NO_DISPONIBLE`? | M6 encontró que el contrato lo documenta pero el servidor nunca lo emite. Se retira del contrato o se explica quién lo emite (plan §7). |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Agregar un caso al corpus» | Una línea en `$corpus` de `medir-sqa.php`, con la alergia, el medicamento y los resultados aceptables. |
| «Bajar el umbral de M3 al 90 %» | Lo desaconsejo: un 10 % de falsos negativos son pacientes expuestos. Si se decide, lo aprueba el líder técnico como riesgo aceptado (plan §2). |
| «Correr los controles en la base real» | `psql -f herramientas/controles-detectivos.sql` con un usuario de solo lectura; no modifica nada. |
