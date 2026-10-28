# Guía de defensa oral — Semana 1

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación por rol) · **Documento:** [semana-01-uml-documentacion-rol.md](semana-01-uml-documentacion-rol.md)

## 1. Resumen en 60 segundos

> «Cada módulo escribe su contrato OpenAPI, su colección Postman o su manual, pero los publica aquí. El autor envía una versión con tipo y audiencia; el sistema revisa que la versión sea nueva, que el OpenAPI pase el lint y que no traiga datos reales. Otra persona la aprueba, nunca el mismo autor. Cada usuario ve solo lo que incluye alguno de sus roles, y lo ajeno responde 404. Las versiones no se editan ni se borran: se marcan obsoletas con enlace a la vigente.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Revisor distinto del autor (R-05) | Separación de funciones: nadie publica sin una segunda mirada. |
| 404 y no 403 para lo ajeno (R-07) | Un 403 confirma que el documento existe; un 404 no revela nada. |
| Versiones inmutables y obsoletas, no borradas (R-03, R-08) | Un integrador que usó la versión 1.2 debe poder volver a leerla. |
| Validador como servicio interno, no actor | No tiene objetivo propio: responde cuando el servicio le pregunta. |

## 3. Preguntas probables

**¿Dónde se guardan los documentos?** No hay tablas en el esquema: es un supuesto abierto que se decide en la Semana 4 (capas y repositorio).

**¿Por qué la audiencia usa `role_code`?** Es lo único que el esquema tiene hoy para los roles (`hospital_memberships`); por eso R-06 exige que esté activo y vigente.

**Si un autor envía la 1.2.0 y la vigente es la 1.3.0, ¿qué lo detiene?** D4 (¿versión nueva y mayor que la vigente?) → E3, y la API responde 409 (R-02).

**¿Por qué un lector sin el rol recibe 404 y no 403?** Porque un 403 confirma que el documento existe; con 404 no se revela nada (R-07).
