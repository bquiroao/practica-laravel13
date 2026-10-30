# Guía de defensa oral — Semana 5

## Resumen en 60 segundos

«Evolucioné el mismo Micro-HIS de recepción hacia cliente-servidor. Propongo un contrato REST con cuatro endpoints, idempotencia obligatoria y el hospital tomado de la sesión. Antes de decidir sobre microservicios medí el prototipo: una tabla propia, cero claves foráneas, cero PDO ni SQL en el núcleo y las referencias a orden y paciente sin copiar datos. La frontera es limpia, pero eso es solo una condición necesaria: no hay equipos separados, ni datos de carga, y separar la recepción de la custodia rompería una transacción local. Por eso decido un monolito modular con API y dejo disparadores medibles para extraer un servicio más adelante. Todo el contrato es una propuesta; no hay API implementada.»

## Demostración

1. Ejecutar `php docs/modulos/recepcion-muestras/semana-05/verificacion/frontera.php`.
2. Abrir el diagrama de componentes y la secuencia.
3. Recorrer la tabla de códigos de respuesta y la de criterios de microservicio.

## Preguntas probables

**¿Por qué no dividir en microservicio?** La frontera es limpia, pero no hay necesidad medida: un equipo, sin datos de carga y con una transacción local que se volvería eventual.

**¿Qué haría reconsiderarlo?** Los cuatro disparadores: ritmo de despliegue, latencia p95, propiedad por otro equipo y caídas en cascada. Los umbrales son una propuesta.

**¿Cómo se evita duplicar una recepción por un timeout?** Con `Idempotency-Key`: misma clave y contenido devuelve la original; con contenido distinto, `409`.

**¿Qué pasa si Órdenes no responde?** `503` con `Retry-After`, sin guardar nada. Nunca se informa como rechazo.

**¿De dónde sale el hospital?** De la sesión, no del cuerpo; así no se puede escribir en otro hospital.

**¿Cómo se mantiene consistente con otros módulos?** Fuerte dentro del módulo con una transacción y outbox; eventual hacia afuera con eventos.

**¿Está implementada la API?** No. Es diseño; lo medido es la frontera del prototipo.

## Evidencia

- [Documento](semana-05-api-microservicio-recepcion-muestras.md)
- [Medición](verificacion/frontera.php) · [Salida](evidencia/frontera-semana-05.txt)
- [Componentes](diagramas/semana-05-componentes.svg) · [Secuencia](diagramas/semana-05-secuencia.svg)
