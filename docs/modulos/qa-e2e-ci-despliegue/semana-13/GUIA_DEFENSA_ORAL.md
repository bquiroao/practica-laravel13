# Guía de defensa oral — Semana 13 (revisión técnica formal)

**Estudiante:** Boris Alexander Quiroa Orellana

## 1. Resumen en 60 segundos

La revisión se hizo como una inspección formal, con los siguientes elementos:
- roles: moderador, autor, dos revisores y un escriba; el autor no clasifica sus propios hallazgos;
- criterios de entrada medibles: la suite, las mutaciones y el contrato en verde;
- un checklist de 14 ítems;
- un formato fijo para registrar cada hallazgo.

**Resultado:** 6 hallazgos, y la decisión de salida es **aprobado con condiciones**.

El hallazgo mayor, RT-01, apareció al comprobar un ítem que ninguna prueba cubría: si se reintenta el registro de un despliegue, el despliegue queda duplicado. Lo dejé reproducible con un script y se corrige en la semana 15.

## 2. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué RT-01 es mayor y no crítico? | No salta ninguna regla: la versión estaba aprobada y el digest coincide. Pero la trazabilidad queda con un despliegue que no ocurrió, y la auditoría también. |
| ¿Por qué las pruebas no lo detectaron? | Ninguna probaba reintentar un despliegue. La idempotencia se diseñó para reportar resultados (R-12), no para desplegar. |
| ¿Quién decide la severidad? | Los revisores y el moderador, no el autor. |
| ¿Por qué no se corrige ya? | La revisión registra los hallazgos; la corrección entra con su prueba en la semana 15, donde se registran los defectos. |
