# Guía de defensa oral - Semana 6 (Primera evaluación parcial)

## Resumen en 60 segundos

La arquitectura propuesta conserva capas y Repository del prototipo de Semana 4, agrega contrato API para evolución cliente-servidor y defiende un cambio práctico: exigir valor con unidad y clasificar rango sin romper la corrección controlada por versión ni el estado pendiente.

## Preguntas probables

1. **¿Qué parte es actual y qué parte es propuesta?**  
   Actual: caso de uso, agregado, repositorios, versión esperada.  
   Propuesta: política valor-unidad-rango en forma explícita y contrato API cliente-servidor.

2. **¿Cuál fue el cambio práctico defendido?**  
   Rechazar resultados sin unidad y marcar `LOW/NORMAL/HIGH` según rango por ítem.

3. **¿Cómo se demuestra?**  
   Con `verificacion/cambio-practico.php` y su evidencia en `evidencia/cambio-practico-semana-06.txt`.

4. **¿Se rompe la corrección controlada?**  
   No. Se conserva versión esperada, conflicto por obsolescencia y estado pendiente.

5. **¿Por qué no extraer microservicio en este punto?**  
   Aún no hay justificación medible; se prioriza fortalecer contrato y semántica del dato.

6. **¿Cómo conecta con RF/RNF y SOLID?**  
   RF de captura/corrección y RNF de trazabilidad/consistencia; SRP y DIP en capas + puertos.

