# Guía de defensa oral — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación por rol) · **Documento:** [semana-02-isp-requerimientos-diseno.md](semana-02-isp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «ISP dice que ningún cliente debe depender de métodos que no usa. Antes había una interfaz de 7 métodos para todos: la consulta por rol usaba uno y una caché de lectura tenía que fingir los otros seis lanzando "no soportado". Ahora hay tres interfaces, una por cliente: lector (1 método), autor (2) y revisor (4). El script demuestra que la consulta funciona igual con el repositorio y con la caché, y por reflexión que cada servicio depende solo de lo que usa.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Cortar por cliente, no por tabla | Cada actor de la Semana 1 tiene necesidades distintas: esa es la frontera natural. |
| El repositorio implementa las tres | ISP no obliga a partir la clase, sino a que cada cliente vea solo su parte. |
| Caché de lectura como prueba | Es el caso real donde la interfaz gorda obliga a escribir código muerto. |

## 3. Preguntas probables

**¿En qué se diferencia ISP de SRP?** SRP mira la clase («una razón para cambiar»); ISP mira al cliente («no depender de lo que no uso»). Aquí el repositorio puede tener varias interfaces sin violar SRP.

**¿Qué pasa si cambia la firma de `rechazar()`?** Solo se afecta `RevisionPublicaciones` y su cliente; `ServicioConsulta` y `CacheLectura` no se tocan (RNF-03).

**¿Cómo se corre la evidencia?** `php docs/modulos/contratos-api/semana-02/ejemplo-isp/verificar.php`; debe terminar con «RESULTADO: todo correcto» y exit 0.
