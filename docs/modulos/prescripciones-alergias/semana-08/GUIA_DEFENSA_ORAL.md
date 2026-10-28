# Guía de defensa oral — Semana 8 (UX)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-08-ux-prescripciones.md](semana-08-ux-prescripciones.md)

## 1. Resumen en 60 segundos

Hay dos roles con pantalla:
- **el médico,** que prepara, verifica, emite y anula;
- **farmacia y enfermería,** que consultan.

El diseño se apoya en cuatro ideas:
- **La seguridad la decide el servidor:** la emisión reverifica, así que existe el estado «se encontró una alergia nueva».
- **«Sin registros» no es «sin alergias»:** se pide una confirmación explícita, desmarcada.
- **El bloqueo no se puede forzar.**
- **Si falla, falla cerrado y lo dice.**

Son 6 wireframes con 38 anotaciones y 22 mensajes, y cada código de la API tiene el suyo. La única brecha está marcada: la previa no dice si el médico tiene override.

## 2. Orden sugerido

1. Roles (§2) y principios (§3).
2. Flujo del médico: el bucle de verificación y los cuatro finales de la emisión.
3. W1 → W2: los cinco resultados de la previa.
4. W3: la justificación y la confirmación; mostrar la BRECHA.
5. W4: emitida, rechazada, 503 e incierta.
6. Flujo de farmacia y W5, incluida la anulada.
7. W6: anular, los errores recuperables y la protección de datos.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| Si la UI ya verificó, ¿por qué puede fallar al emitir? | Entre la previa y la emisión pueden registrarse alergias nuevas. El servidor vuelve a consultar (R-03) y la UI lo explica (W4, M-08). |
| ¿Por qué «Emitir» se deshabilita al cambiar un ítem? | Para no emitir con el resultado de otro borrador. La verificación vale solo para lo que se verificó. |
| ¿Por qué la casilla «sin alergias conocidas» empieza desmarcada? | Marcarla es una afirmación clínica que queda guardada con su autor (R-06). Por defecto, nadie la afirmó. |
| ¿Qué pasa si se cae la red al emitir? | Estado INCIERTO (M-13). Reintentar con la misma `Idempotency-Key` es seguro: devuelve `YA_EMITIDA` si ya existía (CLI-02). |
| ¿Por qué no hay un botón «forzar» en el bloqueo? | Una alergia directa o grave no se justifica (R-04). La salida es cambiar el medicamento. |
| ¿Cómo sabe la UI si el médico tiene override? | Hoy, por el perfil de RBAC. Es una BRECHA documentada: si se equivoca, el servidor responde 409 y la UI lo muestra (M-15). Para la semana 12 se propone `puede_justificar` en la previa. |
| ¿Por qué farmacia no ve «Anular»? | No tiene `prescripcion.anular` (ANU-04). Anular es una decisión del prescriptor. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Que la advertencia bloquee siempre» | Es una regla de Dominio (`PoliticaEmision`), no de la UI. La pantalla mostraría M-08 en lugar de M-06 sin cambiar nada más. |
| «Imprimir la prescripción» | Amplía el módulo y debería auditarse. Se anota como mejora. |
| «Guardar el borrador para después» | Habría que guardarlo en el servidor, nunca en `localStorage` (datos clínicos). Se anota y no se improvisa. |
