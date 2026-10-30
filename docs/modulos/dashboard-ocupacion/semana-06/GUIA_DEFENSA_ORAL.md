# Guía de defensa oral — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documentos:** [presentación](semana-06-diapositivas.md) y [matriz y registro del cambio](semana-06-defensa-arquitectura.md)

> «El tablero tiene un hilo trazable: el temporizador consolida, la jefatura consulta, RF-01 y R-04 fijan las cifras y su consistencia, el contrato lo expone y cada capa hace una parte. El cambio práctico fue la procedencia del dato "ocupada": ahora se cuentan las estancias abiertas, y una cama con dos pacientes se marca como inconsistente. El cambio entró en la consulta y en la regla, sin tocar los puertos, el controlador ni el contrato.»

**¿Qué principio SOLID defiendes y dónde se ve?** ISP: tres puertos, y ninguno permite cambiar un estado de cama. El consolidador lee camas y escribe cortes; la consulta solo lee cortes. Por eso el tablero no puede corregir datos ni por error (R-09).

**¿Por qué no basta el `EXISTS`?** Porque responde «hay al menos una», y R-04 pide «hay exactamente una». Con dos estancias abiertas, el `EXISTS` decía que la cama estaba bien, y la cifra de ocupación escondía a un paciente.

**¿Por qué la ocupación no cambió en el ejemplo?** Porque la fórmula cuenta camas, no estancias: A1 sigue siendo una cama ocupada. Lo que cambia es que la cama aparece en `inconsistentes`, para que alguien revise de dónde salió el dato.

**¿Cómo mantuviste compatibles los guiones de las semanas 5 y 13?** InMemory convierte `true` y `false` a 1 y 0, así que los datos de prueba anteriores significan lo mismo.

**¿Qué falta?** El permiso en la entrada (H-01, semana 15) y marcar la cama ocupada en una sala inactiva (H-04).
