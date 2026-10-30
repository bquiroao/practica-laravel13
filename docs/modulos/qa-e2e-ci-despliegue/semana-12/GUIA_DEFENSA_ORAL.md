# Guía de defensa oral — Semana 12

**Estudiante:** Boris Alexander Quiroa Orellana · **Presentación:** [PDF](presentacion/semana-12-qa-e2e.pdf) (5 diapositivas, unos 7 minutos)

## 1. Resumen en 60 segundos

El cambio solicitado nace de un hallazgo de la semana 9: la consola decía qué decisión iba a registrar el gate, pero quien decide es el servidor al registrar.

Agregué una **vista previa sin efectos**. Usa la misma función pura que el registro, así que la pantalla anticipa sin copiar la política. Si al registrar la decisión cambia, por tiempo o por política, la consola lo dice.

**Cómo se hizo:**
- prueba primero: APP-11;
- el contrato verificado se puso en rojo hasta documentar la operación (1.2.0, aditivo);
- 89/89 pruebas;
- una mutación nueva detectada.

El recorrido navegable sobre la API real da 88/88 con el cambio.

## 2. Orden

| Diapositiva | Minutos |
|---|---|
| 1 · Portada | 0:30 |
| 2 · Recorrido (en vivo si se puede) | 2:00 |
| 3 · Matriz decisión → RF/RNF → evidencia | 1:30 |
| 4 · Cambio solicitado | 2:00 |
| 5 · Lo que queda | 0:45 |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué POST si no tiene efectos? | Recibe el mismo cuerpo que la evaluación (`{ejecucion}`) y así se reusa el formulario. Es seguro repetirlo. Un `GET` con parámetro de consulta sería más purista, pero también más código. |
| ¿Puede la vista previa mentir? | Por código, no: es la misma función. Por tiempo, sí: la ejecución puede cruzar las 24 h o la política puede cambiar. Ese caso se muestra («La decisión cambió»). |
| ¿Cómo sabés que no guarda nada? | APP-11 comprueba que no hay evaluación, estado ni traza nuevos, y M34 (hacer que la vista previa registre) la rompe. |
| ¿Por qué 1.2.0 y no 2.0.0? | Es aditivo: una operación nueva y nada de lo que existía cambia. |
