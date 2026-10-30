# Guía de defensa oral — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-02-isp-requerimientos-diseno.md](semana-02-isp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «ISP dice que ningún cliente debe depender de métodos que no usa. Antes, el tablero dependía del almacén del catálogo, que tiene seis métodos, entre ellos uno para cambiar el estado de una cama, aunque el tablero solo debe leer. Ahora hay tres interfaces: leer camas, guardar cortes y leer cortes. El consolidador usa las dos primeras y la consulta solo la tercera. El script prueba la fórmula de ocupación con mantenimiento, una sala sin camas operativas e inconsistencias, y demuestra por reflexión que nada del tablero puede cambiar una cama.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Quitar `cambiarEstadoCama()` de la vista del tablero | Hace cumplir R-09: lo que no se ve no se puede llamar por error. |
| Dos interfaces para el consolidador | Lee camas y escribe cortes: son dos necesidades distintas. |
| Datos de prueba con casos difíciles | Mantenimiento, sala sin camas operativas e inconsistencias en los dos sentidos. |

## 3. Preguntas probables

**¿Qué ocupación tiene una sala con 2 disponibles, 2 ocupadas y 1 en mantenimiento?** 2 ÷ (2 + 2) = 50 %: la cama en mantenimiento no se puede asignar, así que no cuenta (R-01, CA-01).

**¿Por qué el tablero no corrige la inconsistencia de A4?** No es dueño del estado de la cama; la marca para que catálogo y admisión la resuelvan (R-04, R-09).

**¿Cómo se corre la evidencia?** `php docs/modulos/dashboard-ocupacion/semana-02/ejemplo-isp/verificar.php`; debe terminar con «RESULTADO: todo correcto» y exit 0.
