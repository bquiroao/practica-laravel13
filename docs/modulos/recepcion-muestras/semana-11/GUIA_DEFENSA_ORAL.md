# Guía de defensa oral - Semana 11 (Recepción de muestras)

## Resumen en 60 segundos

El prototipo navega la búsqueda de orden, verificación física, aceptación o rechazo y registro de la primera custodia. El error crítico demuestra que un código ya asociado a otra recepción se bloquea, no crea una nueva custodia y requiere escalar.

## Demostración

1. Abrir `public/prototipos/recepcion-semana-11/`.
2. Camino feliz: seleccionar el ítem de `ORD-2026-0418`, verificar los datos y guardar la recepción.
3. Error crítico: reiniciar y elegir **Probar código duplicado**; comprobar que no existe acción para forzar el alta.
4. Rechazo: reiniciar, desmarcar una verificación, continuar, elegir motivo confirmado y guardar.
5. Cambiar a ancho móvil y comprobar que la navegación y acciones siguen disponibles.

## Preguntas probables

1. **¿Por qué una impresión fallida no rechaza la muestra?**

   Impresión y decisión de recepción son estados diferentes.

2. **¿Qué garantiza que no se duplica la recepción?**

   El prototipo muestra un bloqueo determinista. En producción, la restricción única e idempotencia deben imponerse en el servidor.

3. **¿Qué pasa si falta información?**

   Se bloquea la aceptación y se escala; no se transforma en rechazo clínico.

4. **¿Qué datos registra la custodia inicial?**

   Persona, ubicación, fecha/hora con zona horaria, identificador y decisión.

5. **¿Qué está implementado realmente?**

   Un prototipo navegable local. No hay backend ni persistencia real.
