# Guía de defensa oral — Semana 9 (evaluación UX y accesibilidad)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-09-evaluacion-ux-prescripciones.md](semana-09-evaluacion-ux-prescripciones.md)

## 1. Resumen en 60 segundos

Evalué mis propios wireframes de la semana 8 con un checklist de 40 ítems: Nielsen, WCAG 2.2 AA y 9 ítems propios del módulo. El resultado fue 17 ✅, 14 ⚠️ y 9 ❌, con 15 hallazgos. Cada hallazgo tiene un recorte marcado y una corrección con un criterio que se puede comprobar.

Los más graves son los que tocan la seguridad del paciente:
- con el teclado, no se sabe por qué «Emitir» está deshabilitado (H-01);
- la casilla «sin alergias conocidas» es pequeña y casi invisible (H-03);
- un lector de pantalla no sabe a qué ítem pertenece una dosis (H-07).

El contraste no lo estimé a ojo: lo **medí**. Fallan 13 pares y la paleta propuesta los corrige todos.

## 2. Orden sugerido

1. Método y escala de prioridades (§2); destacar que «emitir con el dato equivocado» sube un nivel.
2. Tabla de contraste (§3) y correr `python herramientas/contraste.py` en vivo.
3. H-01, H-03 y H-07, con sus recortes.
4. H-02 y H-10: dos contradicciones de mi propio diseño de la semana 8.
5. Backlog (§6) y qué cambia en la semana 8 (§6.1).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué un botón deshabilitado es un problema, si es lo normal? | `disabled` saca el botón del orden de Tab y oculta su explicación. Con `aria-disabled` y un texto visible, todos saben qué falta. |
| ¿Cómo sabe que 4,25:1 falla, si se ve bien? | WCAG pide 4,5:1 para texto de menos de 18 pt. Lo calcula `contraste.py` con la fórmula de luminancia relativa; no es una opinión. |
| ¿Por qué H-03 es severidad 3 si es solo el tamaño? | Porque es la afirmación «no tiene alergias», que queda guardada con el nombre del médico (R-06). En este módulo, un error ahí puede dañar a un paciente. |
| ¿Usted evaluó su propio diseño? ¿No es sesgado? | Sí, y está declarado como limitación. Para compensarlo, usé un instrumento medible (contraste), un recorrido de teclado y la comparación con el contrato de la API, que encontró H-10. |
| ¿Por qué «Deshacer» y no una confirmación para quitar un ítem? | Una confirmación en cada clic se acepta sin leer. «Deshacer» protege sin frenar a quien quita a propósito. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Agregar un criterio WCAG más» | Se agrega una fila al checklist, y si falla, un hallazgo y su B-xx. Por ejemplo, 2.2.1 (tiempo ajustable) para la sesión. |
| «Cambiar el verde por otro» | Se cambia la propuesta en `contraste.py` y se vuelve a correr: el comando dice si cumple. |
| «Bajar H-07 a P2» | Lo discutiría: en una prescripción, editar la dosis del ítem equivocado es un error clínico, no cosmético. |
