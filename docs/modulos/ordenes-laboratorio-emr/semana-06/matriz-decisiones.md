# Matriz decisión → evidencia

| Decisión | RF/RNF | Actor/UML | Evidencia y límite |
|---|---|---|---|
| Emitir desde contexto autorizado | RF01 RNF02 | Clínico → IssueLabOrder | semana-02/ejemplo-srp/src/LabOrderWorkflow.php; CA03/04. Identidad real pendiente. |
| Validar toda selección | RF02/03 | Catálogo → emisión | CA02/05, cambio/verificar.php. Catálogo externo simulado. |
| Orden/partidas como unidad | RF04 RNF03 | Repository | createAtomically; CA07. Transacción PDO propuesta, no ejecutada. |
| Consultar antes de mostrar estado | RF05/06 RNF05 | Usuario → FindLabOrders | CA08..10; error no genera vacío falso. |
| Validar origen/ciclo y conflicto | RF07/08 RNF06 | Laboratorio → ApplyLabOrderStatus | CA11..13 y updateStatus esperado; etiquetas sintéticas. |
| Separar casos de uso por SRP | RNF01 | Tres aplicaciones | CA14..16 y arquitectura.puml; contratos sin HTTP/SQL. |
| Exponer API con contexto seguro | RNF02/04 | Controlador propuesto | README: operaciones/mapeos, pendiente implementación. |
| Rechazar duplicados sin normalizar | RF03 + regla C06-01 propuesta | UniqueTestCatalog | antes/después ejecutable y rechazo sin escritura; no es requisito institucional ya aprobado. |
| Conservar trazabilidad tras cambio | RNF04 | Orden → paciente/episodio | cambio/verificar.php comprueba referencias y partidas. Auditoría histórica persistente pendiente. |