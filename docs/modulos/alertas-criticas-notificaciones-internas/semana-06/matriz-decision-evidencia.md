# Semana 6 — Matriz decisión → evidencia

| # | Decisión | Requisito / regla | Evidencia verificable |
|---|---|---|---|
| D1 | El tiempo se inyecta con `Reloj` | RNF-04, RN-04, RN-05 | `pruebas/unidad/politica-pruebas.php` (10:14 no vence, 10:15 sí) |
| D2 | Acuse idempotente en la entidad | RN-06, CA-08 | `alerta-pruebas.php` y la prueba PDO «un solo evento acusada» |
| D3 | Envío por `PuertoNotificacion` con adaptador simulado | RNF-06, RN-08 | `registrar-pruebas.php` (CA-06: 3 intentos y escalamiento) |
| D4 | Historial que solo crece | RN-10, RNF-03 | *Trigger* en `crear-esquema.php` y prueba «la base rechaza modificar o borrar el historial» |
| D5 | SRP: separar validación, envío, plazos y persistencia | Tarea S2 | `semana-02/srp/` y `verificar-srp.php` |
| D6 | Repository en Domain con PDO e InMemory | Tarea S4 | `pruebas/contrato/repositorio-contrato-pruebas.php` (8 × 2) |
| D7 | Controlador sin SQL ni reglas | Tarea S4 | Prueba estática en `controlador-pruebas.php` |
| D8 | Tenant tomado del token | RNF-01 | `api-pruebas.php` («un tenant en el cuerpo se ignora») |
| D9 | Unicidad por origen | RN-02 | `UNIQUE (tenant_id, origen, id_origen)` y prueba de contrato 4 |
| D10 | No separar en microservicio todavía | Tarea S5 | `semana-05/frontera-microservicio.md` (tabla de gatillos) |
| D11 | Solo médico o jefe cierran | RF-08 | `api-pruebas.php` (enfermería → 403) |
| D12 | Guardar antes de notificar | RNF-05 | «ERROR DE PERSISTENCIA: no se notifica a nadie» |
