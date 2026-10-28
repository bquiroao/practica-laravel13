# Cambio C06-01 — Una selección sin códigos repetidos

Solicitud construida para esta defensa: “Si una prueba aparece dos veces en la misma selección, rechazar la emisión completa y pedir corrección”. No se atribuye al docente/cliente una petición no recibida. Se asume código canónico y comparación exacta; no se aplica mayúscula/trim que altere el catálogo ni se define cantidad/repetición clínica en otros episodios.

Antes: con código disponible, LabCatalog de memoria devuelve VALID aunque esté repetido; IssueLabOrder crea partidas duplicadas. La prueba inicial conserva este comportamiento como evidencia de línea base.

Después: decorador UniqueTestCatalog implementa el mismo puerto; rechaza duplicados y delega disponibilidad del resto. Se compone al construir IssueLabOrder. Caso de uso, entidad, Repository, consulta y política de estado no cambian. No se editan archivos de semana 2.

Criterios: selección repetida → INVALID_TESTS sin escritura; distintas → CREATED con ambas partidas y contexto; código desconocido sigue rechazado; actor denegado no escribe; falla de persistencia no confirma; lectura fallida no aparenta estado actual. Ver pruebas y salidas.

Alternativas: deduplicar silenciosamente oculta una diferencia entre revisión y guardado; validar solo UI permite eludirla; cambiar SQL no aborda el error en aplicación. El decorador admite reversión quitándolo de la composición, con suite previa intacta. No migra datos históricos ni altera órdenes ya creadas. Acordar la regla con responsables clínicos antes de adopción real.

API propuesta: 422 INVALID_TESTS, mensaje de selección repetida tras autorización y sin volcar datos. No se añaden campos obligatorios ni cambia la firma pública de emisión. La validación de duplicados no equivale a idempotencia de POST ni protege contra doble envío de dos solicitudes válidas.