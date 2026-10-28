# Guía de defensa oral — Semana 12 (segunda evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-12-defensa-ux-prescripciones.md](semana-12-defensa-ux-prescripciones.md)

## 1. Resumen en 60 segundos

Defiendo seis semanas de decisiones con evidencia que se ejecuta:
- **Componentes:** la decisión clínica vive en una política del Dominio; medí que fuera de él bajó de 10 a 0.
- **UX y accesibilidad:** las alergias se ven antes de elegir el medicamento. Evalué mi propio diseño y corregí 14 de 15 hallazgos. El recorrido hace 86 comprobaciones con axe, teclado y foco, con 0 fallas.
- **Movilidad:** medí los reintentos contra el kernel real. La pantalla nunca duplica una prescripción ni adivina un resultado.

El prototipo responde **igual** que el kernel real en 42 peticiones.

El cambio solicitado cierra una brecha que venía desde la semana 8: la verificación ahora dice si el médico **puede** justificar la advertencia. Lo calcula la misma regla que decide al emitir. Pruebas primero, contrato 1.2.0, 111/111 y 35/35.

## 2. Orden sugerido (7 diapositivas, unos 10 minutos)

1. Portada y las tres cifras (30 s).
2. Componentes y refactor: la tabla 10 → 0 y la caracterización (1,5 min).
3. UX: los hallazgos que contradecían mi diseño (1,5 min).
4. Movilidad: los reintentos medidos (1,5 min).
5. **Demostración en vivo**, partes A y B del [recorrido](recorrido-navegable.md) (3 min).
6. Cambio solicitado: parte D del recorrido y el rojo → verde (2 min).
7. Cierre: matriz y riesgos (30 s).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué no calcular `puede_justificar` directamente en la previa? | Porque serían dos lugares con la misma regla y podrían divergir. El refactor de la semana 7 existió para evitar eso. `PoliticaEmision` responde las dos preguntas. |
| ¿Por qué la clave solo aparece con ADVERTENCIA? | Sin advertencia no hay nada que justificar; un `false` confundiría. Además obligaría a preguntar siempre a RBAC; JUS-02 y M35 vigilan que no se haga. |
| ¿Y si RBAC cambia entre la previa y la emisión? | La emisión sigue decidiendo (R-03) y la interfaz conserva el manejo del 409 (M-15). La previa informa, no autoriza. |
| ¿Por qué cambió la caracterización? ¿No era intocable? | Protege contra cambios **no explicados**. Este estaba explicado. La herramienta solo regraba si la única diferencia es la clave nueva: 31 iguales, 1 explicada, 0 otras. |
| ¿Cómo sabe que el prototipo no miente? | `paridad.cjs` lo compara con el kernel real. Como control, contra la API de la semana 11, detecta las 2 diferencias exactas. |
| ¿Qué queda abierto? | Anular no es idempotente, el hallazgo sin el nombre del alérgeno, sin lector de pantalla real y B-13 parcial. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Que la previa diga también si puede confirmar sin alergias» | No hace falta un permiso para eso: cualquier médico puede confirmar. La previa ya responde `CONFIRMACION_REQUERIDA`. |
| «Que la clave aparezca siempre» | Se puede, pero la previa preguntaría a RBAC en cada verificación. M35 dejaría de ser un defecto y habría que reescribir JUS-02. Lo desaconsejo. |
| «Mostrar en vivo que la emisión sigue decidiendo» | Paridad, paso 12: la Dra. Ruiz emite con justificación y recibe 409, igual que antes del cambio. |
