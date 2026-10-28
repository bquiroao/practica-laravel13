# Guía de defensa oral — Semana 10 (diseño para movilidad)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-10-movil-prescripciones.md](semana-10-movil-prescripciones.md)

## 1. Resumen en 60 segundos

Adapté el flujo a teléfonos de 320 a 430 px con 4 pantallas: prescribir, verificación, emisión, y consultar o anular. Hay tres decisiones centrales:
- **Alergias siempre a la vista:** la franja de alergias está fija y nunca se pliega.
- **Una sola acción principal por estado,** al alcance del pulgar.
- **La red inestable nunca duplica ni miente.**

Esto último no lo supuse: lo **medí** contra el kernel real. Con la misma clave de idempotencia, el reintento devuelve `YA_EMITIDA`; con una clave nueva, hay **dos** prescripciones. Por eso, mientras el resultado sea incierto, el borrador se bloquea.

También encontré que anular no es idempotente: el reintento responde 409 aunque la anulación sí se hizo. La UI consulta y lo muestra como éxito.

Las reglas de breakpoint se verifican con un comando: 46 vistas, 0 fallas.

## 2. Orden sugerido

1. Quién usa el teléfono y qué prioriza (§1, §2).
2. Breakpoints (§4) y correr `verificar-responsive.cjs` en vivo.
3. La tabla de reintentos (§6.3): casos 2, 3 y 6.
4. Escenario 1 (incierto → `YA_EMITIDA`) y escenario 2 (409 reconciliado).
5. Los dos defectos que encontré, uno de ellos mirando las capturas, y cómo corregí el instrumento (§4.1).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué bloquear el borrador si el resultado es incierto? | Medido: si cambia la dosis y reintenta con la misma clave, recibe 409; con otra clave, puede duplicar. Primero se resuelve la intención anterior. |
| ¿Por qué no guardar la prescripción para enviarla cuando vuelva la red? | El servidor tiene que reverificar las alergias en el momento de emitir (R-03), con el médico presente. Una emisión diferida podría usar alergias viejas. |
| ¿Por qué la franja de alergias ocupa espacio fijo en una pantalla tan chica? | Es la información que evita el daño. Prefiero desplazarme más por los ítems a prescribir sin ver las alergias. |
| ¿Cómo sabe que el instrumento no da falsos «OK»? | No lo sabía: la franja corrida pasó la primera regla. La corregí y la corrí como control sobre el prototipo sin arreglar: 8 fallas. |
| ¿El 409 de anular es un error de la API? | Es coherente (no se anula dos veces), pero incómodo con red inestable. Propongo `Idempotency-Key` en la anulación para la semana 12. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Agregar un breakpoint para 390 px» | Se agrega la vista a `VISTAS` y se corre el verificador; la base ya funciona de 320 en adelante. |
| «Mostrar la tabla de ítems también en el teléfono» | A 320 px, 6 columnas no caben sin desborde (R-W1 lo marcaría). La tabla vuelve desde 1024 px. |
| «Reintentar automáticamente al volver la red» | Solo para la verificación previa, que no tiene efectos (caso 1). Emitir y anular quedan manuales. |
