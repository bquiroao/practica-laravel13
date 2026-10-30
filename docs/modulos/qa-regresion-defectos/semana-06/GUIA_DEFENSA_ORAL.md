# Guía de defensa oral — Semana 6 (primera evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Presentación:** [semana-06-defensa-qa-regresion.pptx](presentacion/semana-06-defensa-qa-regresion.pptx)

## 1. Resumen en 60 segundos

El módulo registra campañas de regresión manual sobre un build:
- con los críticos de la matriz obligatorios;
- con evidencia verificable;
- con defectos que solo una re-ejecución cierra;
- con una decisión GO / NO-GO calculada.

La arquitectura tiene cuatro capas, MVC en la entrada y Repository con dos adaptadores que pasan la misma suite. El contrato OpenAPI se compara con el código, y la frontera la decidí midiendo: se separa el almacén de evidencias, no el módulo.

El cambio práctico, **R-15**, hace que un defecto MAYOR abierto en un caso crítico bloquee el GO. Tocó el dominio, un puerto y sus dos adaptadores, sin migración y con el contrato aditivo. Hubo pruebas primero (5 en rojo, luego en verde), 99 en total y 33/33 mutaciones. Una mutación encontró un hueco real que cerré.

## 2. Guion por diapositiva (≈ 12 minutos)

| # | Diapositiva | Mensaje | Tiempo |
|---|---|---|---|
| 1 | Portada | Qué hace el módulo; las tres cifras | 1 min |
| 2 | Actores y UML | Lo que NO existe en el diagrama es la regla más fuerte | 1,5 min |
| 3 | Requisitos y LSP | Dos evidencias que mentían → un contrato que no se puede romper | 1,5 min |
| 4 | Capas, MVC y Repository | La suite de contrato encontró lo que los dobles escondían | 2 min |
| 5 | Contrato y frontera | Medí: almacén sí, QA no, y por qué | 2 min |
| 6 | Diagrama trazable | Cada decisión → regla → lugar → prueba → mutación | 1 min |
| 7 | Cambio práctico | Los seis temas; rojo → verde; el hueco que encontró M32 | 2 min |
| 8 | Cierre | Lo que no cambió; comandos en vivo | 1 min |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué R-15 cuenta un CORREGIDO? | Porque CORREGIDO es lo que dice el desarrollador, y nadie lo verificó (R-08). Solo una re-ejecución lo cierra. |
| ¿Por qué no basta con exigir `verifica_defecto` al re-ejecutar? | Sería otra regla útil, pero el riesgo del pedido era el GO. R-15 protege el GO aunque alguien olvide verificar. Lo otro queda como mejora de interfaz (semana 8). |
| ¿Por qué «crítico para las funcionalidades de la campaña» y no crítico en general? | La matriz es por funcionalidad. Un caso crítico para otra funcionalidad bloquearía campañas que no la tocan. M31 lo comprueba. |
| ¿Cambió la base? | No. La consulta usa columnas existentes; no hay migración. |
| ¿Se rompen los clientes? | No: `bloquea_go` es un campo nuevo (1.1.0) y API-01..03 siguen en verde sin tocarlas. |
| ¿Qué demuestra que la matriz decisión→evidencia no es decorativa? | Cada fila tiene una prueba que se puede correr, y 18 tienen una mutación que la rompe. |

## 4. Si piden otro cambio en vivo

| Cambio | Dónde | Cómo verificar |
|---|---|---|
| «Los MENOR no bloquean nunca» (ya es así) | — | CAM-05: el MENOR no cuenta |
| «MAYOR en cualquier caso bloquea» | `CerrarCampana`: `mayoresAbiertos($tenant, $casos)` | CAM-02 debe cambiar su expectativa (es la mutación M31) |
| «Umbral 90 %» | `PlanificarCampana::UMBRAL_POR_DEFECTO` | DOM-05/06 |
