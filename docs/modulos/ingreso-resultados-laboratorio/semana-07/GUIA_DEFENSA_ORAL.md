# Guía de defensa oral - Semana 7 (Ingreso de resultados)

## Resumen en 60 segundos

El acoplamiento mayor estaba en `CaptureOrCorrectResult`: recibía muchos escalares, mezclaba reglas con mensajes y trataba el valor como texto. Se propone separar en `EntryService` (orquestación), `ResultValue/ReferenceRange` (dominio) y `OutcomeView` (UI). La evidencia compara antes/después y ejecuta pruebas del comportamiento.

## Preguntas probables

1. **¿Cuál fue el punto más acoplado?**  
   El eje valor-unidad-rango más estado-versión-corrección, todo dentro del mismo caso de uso.

2. **¿Qué cambió en contratos de entrada?**  
   De 8 escalares (capture/correct) a 2 comandos tipados (`CaptureCommand`, `CorrectCommand`).

3. **¿Cómo se separa UI de aplicación?**  
   `Outcome` devuelve razones y datos; `OutcomeView` convierte eso en texto y código.

4. **¿Qué gana el dominio?**  
   `ResultValue` normaliza el dato y `ReferenceRange` clasifica `LOW/NORMAL/HIGH`.

5. **¿Se amplió el módulo?**  
   No. Se conserva el agregado y repositorio; el refactor es conceptual y verificable.

6. **¿Qué evidencia cuantitativa hay?**  
   Menos escalares, cero mensajes de UI en aplicación, y tipos explícitos de valor/unidad/rango.

