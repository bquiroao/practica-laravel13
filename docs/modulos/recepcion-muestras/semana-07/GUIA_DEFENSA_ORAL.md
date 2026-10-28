# Guía de defensa oral — Semana 7

## Presentación breve

«Separé la propuesta de recepción en frontend, adaptador de entrada, aplicación, dominio y persistencia. El frontend entrega datos tipados; el caso de uso conserva la decisión y coordina los puertos. La consulta de órdenes y el registro de custodia se dibujan como límites todavía no integrados. El refactor conceptual parte de `ReceiveSpecimenCommand::run()`, que hoy interpreta la CLI, construye la solicitud y escribe la respuesta; separo mapper y presenter para que una futura UI web reutilice el mismo caso de uso sin depender de la consola.»

## Preguntas para explicar el diseño

1. **¿Qué está implementado hoy?** Un prototipo PHP CLI por capas con caso de uso, política de dominio, generador de identificadores y repositorio PDO. No hay UI web ni endpoint de recepción.
2. **¿Por qué separar mapper y presenter?** El mapper traduce el formato de entrada; el presenter comunica el resultado. Así las reglas y el caso de uso no dependen de CLI, HTTP, HTML o JSON.
3. **¿El frontend decide aceptar o rechazar?** No. Envía datos observados; el backend verifica el ámbito y las reglas del dominio producen la decisión.
4. **¿Una orden no disponible significa rechazo?** No. `UNAVAILABLE` es un error técnico recuperable; no equivale a `NO_MATCH` ni a una no conformidad confirmada.
5. **¿Cómo se relaciona la custodia con la recepción?** El diseño requiere un evento ligado al `specimenId`, sin reescribir eventos previos. La atomicidad o reconciliación queda pendiente porque el prototipo solo guarda la recepción.
6. **¿Qué identificador muestra la pantalla?** El `specimenId` identifica el recipiente; no reemplaza orden, paciente, ítem ni código externo.
7. **¿Qué no promete el entregable?** No implementa ni prueba API, autenticación, consulta EMR, custodia, impresión ni reglas clínicas nuevas.

## Recorrido de evidencia

- [Documento de componentes y refactor](semana-07-componentes-y-refactor-recepcion-muestras.md)
- Diagrama Mermaid: sección 3.
- Contratos frontend/backend y puertos: sección 4.
- Refactor conceptual antes/después: sección 5.
- Validación del prototipo: sección 6.
