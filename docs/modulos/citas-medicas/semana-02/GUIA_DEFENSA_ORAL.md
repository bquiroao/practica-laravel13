# Guía de defensa oral — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-02-dip-requerimientos-diseno.md](semana-02-dip-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «DIP dice que la política de alto nivel no debe depender de los detalles: los dos deben depender de abstracciones. Antes, el servicio de citas creaba su propia base con `new PDO` y leía la hora con `time()`. Ahora define tres interfaces (agenda, reloj y auditoría) y las recibe por constructor. La prueba lo demuestra: el mismo servicio pasa los mismos casos con una agenda en memoria y con una PDO sobre SQLite, y con un reloj fijo se prueba la regla de no agendar en el pasado.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Las interfaces viven en el dominio | Las define quien las necesita (la política), no quien las implementa (la base). |
| `Reloj` como dependencia | Sin él, R-01 solo se puede probar esperando a que pase el tiempo. |
| Dos adaptadores reales | La prueba de que el servicio no depende de la base es que funciona igual con las dos. |
| Inyección por constructor | Las dependencias quedan visibles y la reflexión las puede verificar. |

## 3. Preguntas probables

**¿En qué se diferencia DIP de la inyección de dependencias?** La inyección es la técnica (pasar las dependencias por constructor); DIP es el principio (que la política dependa de abstracciones suyas). Se puede inyectar una clase concreta y seguir violando DIP.

**Para pasar a PostgreSQL, ¿qué cambia?** Solo el `PDO` que recibe `AgendaPdo`; `ServicioCitas` no se toca (RNF-02).

**¿Cómo se corre la evidencia?** `php docs/modulos/citas-medicas/semana-02/ejemplo-dip/verificar.php`; debe terminar con «RESULTADO: todo correcto» y exit 0.
