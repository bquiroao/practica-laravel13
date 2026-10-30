# Guía de defensa oral - Semana 6 (Primera evaluación parcial)

## Resumen en 60 segundos

La arquitectura se mantiene por capas con `ReviewPendingLabResult` como núcleo y Repository por puertos. Se defiende una evolución cliente-servidor y un cambio práctico: firma obligatoria y `traceId` por decisión, preservando validación/rechazo, versión, estado y aislamiento por hospital.

## Preguntas probables

1. **¿Qué límite real se encontró?**  
   El diseño actual puede validar/rechazar sin firma explícita del bioquímico.

2. **¿Qué cambia con la propuesta?**  
   Sin firma, la decisión se rechaza; con firma se genera `traceId` y hash para auditoría.

3. **¿Se alteran las reglas clínicas del caso de uso?**  
   No. Se conservan estado pendiente, versión esperada y motivo obligatorio en rechazo.

4. **¿Cómo se evita suplantación?**  
   Actor y hospital se toman de sesión, no del payload.

5. **¿Qué pruebas respaldan esto?**  
   `verificacion/cambio-practico.php` con evidencia en `evidencia/cambio-practico-semana-06.txt`.

6. **¿Qué falta para producción?**  
   Persistir hash de firma y `traceId` en adaptador PDO y cerrar contrato API real.

