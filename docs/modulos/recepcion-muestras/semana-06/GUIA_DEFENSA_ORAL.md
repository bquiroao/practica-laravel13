# Guía de defensa oral — Semana 6

## Duración sugerida

8 diapositivas en 6–8 minutos; aproximadamente 45–55 segundos por diapositiva.

## Mensaje central

«Un código identifica el recipiente; una orden identifica el trabajo clínico; la cadena de custodia demuestra quién recibió y transfirió el recipiente. El diseño debe conservar esos tres vínculos sin confirmar una operación que no se persistió.»

## Preguntas probables

1. **¿Por qué no usar el código de la orden como identificador de muestra?** Una orden puede contener varios ítems y recipientes; cada recipiente físico necesita su propia identidad.
2. **¿Qué sucede si el servicio de órdenes no responde?** No confirmo recepción ni impresión; reporto dependencia no disponible y permito reintentar con idempotencia.
3. **¿Cómo se conserva la custodia?** Eventos inmutables asociados al `specimenId`, con actor, ubicación y momento; no se reemplaza el historial al cambiar el estado.
4. **¿El contrato API ya está implementado?** No. La entrega actual es un prototipo CLI y PDO; el endpoint es una propuesta para la siguiente integración.
5. **¿Cumple DIP perfectamente el prototipo actual?** No completamente: el repositorio y generador son abstraídos, pero política y reloj se pueden desacoplar mejor. La presentación registra esa mejora en vez de afirmar cumplimiento total.
6. **¿Qué evidencia existe hoy?** Las pruebas PHP de Semana 3 y una prueba PDO SQLite para aceptación/rechazo; la cadena completa y el EMR son criterios de aceptación futuros.

## Referencias de la defensa

- [Presentación de 8 diapositivas](presentacion-semana-06.md)
- [Registro del cambio práctico](registro-cambio-practico.md)
- [Arquitectura y prototipo Semana 3](../semana-03/semana-03-arquitectura-recepcion-muestras.md)
