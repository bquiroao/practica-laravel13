# Guía de defensa oral — Semana 8 (UX por rol y wireframes)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-08-ux-qa-regresion.md](semana-08-ux-qa-regresion.md)

## 1. Resumen en 60 segundos

Diseñé el flujo de cada rol autorizado (Tester, Líder QA, Desarrollador, Analista y Responsable de producto) con inicio, decisión, confirmación, estados y errores recuperables. Hice cinco wireframes anotados que cubren lo que pide la tarea.

Tres ideas guían el diseño:
- **Lo que pasó no se edita**, así que hay confirmación antes de registrar.
- **Nada se pierde:** un resultado sin conexión queda pendiente y se reenvía con la misma clave.
- **La decisión se anticipa:** antes de cerrar se ve qué defecto bloquea el GO.

Cada mensaje está catalogado (M-01..M-23) y cada regla del dominio tiene su lugar en la pantalla.

## 2. Orden sugerido

1. Tabla de roles: qué acciones NO aparecen para cada uno (§2).
2. Flujo del Tester (el más usado) y W2–W3.
3. W1: el error 422 que se resuelve con un clic.
4. W4: la evidencia clínica protegida y «marcar corregido no lo cierra».
5. W5: `bloquea_go` antes de cerrar.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué PENDIENTE y no un error? | Porque el resultado no se perdió: está guardado y se reenvía. Llamarlo error haría que el tester lo registre otra vez. |
| ¿La UI valida reglas? | Las anticipa como ayuda (por ejemplo, el tamaño al adjuntar), pero decide el servidor, y la UI muestra lo que este respondió. |
| ¿Por qué el botón dice «Cerrar con NO_GO» y no «Aceptar»? | Porque la acción es irreversible y el botón debe nombrar la consecuencia concreta. |
| ¿Cómo se protege la evidencia clínica? | Solo huella y tipo sin el permiso clínico; nada clínico en la URL; la bandeja cifrada y borrada al cerrar la sesión. |
| ¿Qué falta? | La pantalla de «ver contenido de evidencia» (CU-13), en la semana 11. |
