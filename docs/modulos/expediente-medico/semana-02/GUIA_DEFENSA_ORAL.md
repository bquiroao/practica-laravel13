# Guía de defensa oral — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-02-dip-requerimientos-diseno.md](semana-02-dip-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «DIP dice que la política no debe depender de los detalles. Antes, el servicio del expediente creaba su base con `new PDO`, su cliente del índice central con `new` y usaba `time()`. Ahora declara cuatro interfaces (base HOSPITAL, índice CENTRAL, auditoría y reloj) y las recibe por constructor. La prueba demuestra que las mismas reglas funcionan con dos bases distintas y, con un reloj fijo, que el acceso de emergencia dura una hora y que un consentimiento vencido ya no sirve.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Una interfaz por base federada | HOSPITAL y CENTRAL son sistemas distintos; la política no debe saber cómo se llega a cada uno. |
| `Reloj` inyectado | Sin él, R-07 (1 hora) solo se prueba esperando una hora real. |
| Todo sale por `fin()` | Un solo punto que audita antes de responder: ningún camino se salta R-08. |

## 3. Preguntas probables

**Si el índice central pasa de HTTP a una cola de mensajes, ¿qué cambia?** Solo la clase que implementa `IndiceCentral`; `ServicioExpediente` no se toca.

**¿Cómo se prueba que la emergencia dura 1 hora?** Con `RelojFijo(1000)`, el acceso se crea con `desde = 1000` y `hasta = 4600`; la mutación a 24 h hace fallar CA-05.

**¿Cómo se corre la evidencia?** `php docs/modulos/expediente-medico/semana-02/ejemplo-dip/verificar.php`; debe terminar con «RESULTADO: todo correcto» y exit 0.
