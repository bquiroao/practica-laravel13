# Guía de defensa oral — Semana 1

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-01-uml-proceso-prescripciones.md](semana-01-uml-proceso-prescripciones.md)

## 1. Resumen en 60 segundos

> «Modelé la creación de una prescripción electrónica con verificación previa de alergias. Mientras prepara la prescripción, el médico pide una verificación consultiva: el sistema lee las alergias activas del expediente y le muestra cada hallazgo antes de firmar. Al emitir, el servidor **vuelve a verificar**, porque no puede confiar en la pantalla. Antes valida el medicamento contra el catálogo, la completitud y la dosis máxima. Si el principio activo coincide con una alergia activa, se bloquea sin excepción. Si es del mismo grupo, es una advertencia que se puede justificar con un permiso especial, salvo que la alergia sea potencialmente mortal o de severidad desconocida. Si la fuente de alergias no responde, **no se emite**: la ausencia de datos nunca significa "sin alergias". Una prescripción emitida no se edita: se anula con motivo. Todo queda en la traza de Auditoría, y los tres diagramas comparten identificadores.»

## 2. Orden sugerido de la exposición

1. **Narrativa de alcance:** lo que el esquema real ya tiene (`allergies`) y lo que falta (las tablas de prescripción son la tarea 3.5 pendiente). Qué queda fuera: dispensación, interacciones medicamento–medicamento, registro de alergias.
2. **Casos de uso:** la ausencia de «editar» y de «omitir verificación»; `include` (CU-02, CU-03, CU-04, CU-08) frente a `extend` (CU-05, CU-06).
3. **Actividad:** el camino D-07 → D-08 → D-09 → D-12 → D-13 → D-14 → D-15, y por qué D-09 «no» lleva a un 503.
4. **Secuencia:** el bloque 2 con sus cuatro alternativas; señalar S-25 («se vuelve a consultar»).
5. **Matriz:** elegir R-07 (bloqueo seguro) y ubicarla en los tres diagramas.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Verificar **dos veces** (previa y en la emisión) | La previa es para el médico (usabilidad); la de la emisión es la que protege (seguridad). Entre ambas puede registrarse una alergia nueva, y la API la puede llamar otro cliente |
| *Fail-closed* (R-07) | Un 503 molesta; una prescripción de penicilina a un alérgico puede matar. Ante la duda, el sistema prefiere el error recuperable |
| Coincidencia directa = bloqueo absoluto | Es el error que el módulo existe para impedir. Si hubiera una razón clínica real (desensibilización), es un proceso aparte, no un botón |
| Severidad `unknown` = la peor | El esquema real la admite. No se puede justificar un riesgo que nadie midió |
| Confirmación «sin alergias conocidas» | El esquema no distingue «sin alergias» de «nadie preguntó». Una lista vacía no es evidencia |
| Inmutable tras emitir | La especificación del esquema exige que lo firmado no cambie; farmacia pudo haber actuado ya sobre la versión original |
| Idempotency-Key | Un doble clic o un reintento de red no debe emitir dos prescripciones del mismo medicamento |
| La traza sin datos clínicos | Auditoría la ven auditores; basta `prescripcion:<id>` y el resultado |

## 4. Preguntas probables y respuestas

**¿Por qué no basta la verificación en la interfaz?**
Porque el servidor no sabe si la interfaz la hizo, si la hizo con datos recientes, o si quien llama es la interfaz. La regla de seguridad tiene que estar donde no se puede saltar.

**¿Qué diferencia hay entre una alergia y una intolerancia?**
El esquema las distingue (`allergy_type`). En este modelo ambas se verifican; la intolerancia suele tener severidad leve y por eso terminaría como advertencia si es del mismo grupo. Es un punto para validar con el equipo clínico.

**¿Qué es la reactividad cruzada?**
Que una alergia a un medicamento predice reacción a otros del mismo grupo químico, como penicilina y otras betalactámicas. No es segura, por eso es advertencia y no bloqueo, salvo que la reacción previa haya sido grave.

**¿Qué pasa si el alérgeno está registrado solo con nombre?**
El esquema lo permite. Se compara por nombre normalizado; es menos fiable que el código, y es un riesgo que se documenta para la semana 2.

**¿Por qué el farmacéutico y enfermería son actores si solo consultan?**
Porque tienen un objetivo propio con el sistema: ver qué se verificó antes de dispensar o administrar. Si una prescripción se emitió con advertencia justificada, ellos deben verlo.

## 5. Recorrido de demostración

1. Abrir el diagrama de casos de uso y leer las dos notas.
2. Seguir R-04 (coincidencia directa): D-12 y E-09 en la actividad; S-30..S-33 en la secuencia.
3. Seguir R-07 (bloqueo seguro): D-09 y E-07 en la actividad; S-26..S-29 en la secuencia.
