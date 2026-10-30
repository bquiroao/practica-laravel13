# Semana 6 — Registro del cambio práctico (CASO PREPARADO)

> El cambio real lo pide el ingeniero durante el parcial. Esto es un **caso preparado** para demostrar cómo responde el diseño; si el cambio solicitado es otro, se registra aquí con el mismo formato.

## Cambio preparado
«Las alertas `CRITICA` deben escalar a los **10 minutos** (no 15) y la entrega debe intentar **5 veces** (no 3).»

| Aspecto | Impacto |
|---|---|
| Alertas críticas y prioridad | `PoliticaEscalamiento` ya acepta plazos configurables; 10 está dentro de RN-11 (5–60). |
| Reintentos y entrega | `DespachadorNotificaciones` ya recibe `maxIntentos` por constructor. |
| Destinatarios, acuse, escalamiento | Sin cambios. |
| Código que cambia | Solo `bootstrap.php` (Composition Root): `new PoliticaEscalamiento(['CRITICA' => 10])` y `new DespachadorNotificaciones($notificador, 5)`. |
| Pruebas | Agregar 2: «CRITICA vence a las 10:10 y no a las 10:09» y «5 intentos al destinatario que falla». |
| Documentación | RN-04 y RN-08 pasan a decir «configurable» con los nuevos valores. |
| Riesgo | Más intentos alargan el registro, porque el envío es en línea. Si se nota, se aplica la bandeja de la semana 5. |

## Defensa
El cambio no toca `Alerta`, los casos de uso ni la persistencia: es la prueba práctica de SRP y de inyección de dependencias.
