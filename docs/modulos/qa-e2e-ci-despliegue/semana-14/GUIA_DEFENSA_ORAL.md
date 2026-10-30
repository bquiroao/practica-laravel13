# Guía de defensa oral — Semana 14 (plan SQA)

**Estudiante:** Boris Alexander Quiroa Orellana

## 1. Resumen en 60 segundos

El plan tiene cuatro partes:
- **7 métricas.** Cada una tiene dueño, umbral, el comando que la calcula, su valor de hoy y qué se hace si no cumple.
- **Una matriz de 7 riesgos.**
- **Controles preventivos y detectivos,** cada uno ligado a un riesgo.
- **Un calendario,** del cambio individual a la revisión de cada iteración.

**Dos riesgos son altos:**
- un reintento que duplica registros (RT-01, que se corrige en la semana 15);
- pruebas en verde que no prueban lo importante. Para esto las mutaciones y las contrapruebas son el control detectivo.

**El RNF-08 se midió por fin:** evaluar el gate de 300 escenarios toma 0,41 ms (p95), contra un umbral de 1 s.

## 2. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué estas 7 métricas? | Cada una corresponde a algo que ya falló o casi falló en el proyecto: una mutación sobreviviente, el contrato, las reglas duplicadas, la accesibilidad y el reintento. |
| ¿0,41 ms no es demasiado bueno? | Mide solo la función del dominio, que es lo que promete el RNF. El tiempo por HTTP con la base queda en el calendario, antes de producción. |
| ¿Quién corre esto sin CI? | Hoy, el autor con los comandos de la tabla. El plan está escrito para que el pipeline los adopte tal cual. |
| ¿Preventivo o detectivo? | Preventivo: impide que el error ocurra (R-01 en el dominio, el trigger del despliegue). Detectivo: lo encuentra si ocurrió (mutaciones, API-01, la revisión). |
