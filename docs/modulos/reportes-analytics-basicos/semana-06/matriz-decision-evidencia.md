# Matriz decisión → evidencia · Reportes y analytics básicos

| # | Decisión | Requerimiento que la exige | Alternativa descartada y por qué | Evidencia verificable |
|---|---|---|---|---|
| D-01 | Trazabilidad en 3 niveles: indicador → desglose → registros | RF-03, RF-04, RF-05, RN-07 | Mostrar solo el número: no se puede auditar | `semana-01` (secuencia) y `micro-his` nivel 3: `/?nivel=registros&parte=Medicina` |
| D-02 | `ContratoReporte` único con pre y postcondiciones (LSP) | RNF-05, RNF-06 | Jerarquía con excepciones por reporte: cliente lleno de `instanceof` | `semana-02/ejemplo-lsp/verificar.php` (16 pruebas OK) |
| D-03 | Los porcentajes siempre de 0 a 100, la unidad la declara el indicador | RF-01, CA-01 | Cada reporte con su escala: 0.78 vs 78 | `Indicador` lanza error fuera de rango; prueba «ocupación = 78 %» |
| D-04 | La suma del desglose debe ser igual al numerador; los registros, a su parte | RN-07 | Desglose informativo sin control | Pruebas «desglose suma 39 = numerador» y «Medicina: 18 registros» |
| D-05 | Cuatro capas con dependencias hacia el dominio | RNF-05 | Lógica en el controlador | `semana-03/diagramas/c4-componentes.png` y tabla de dependencias |
| D-06 | Repository en Domain con adaptadores PDO e InMemory | RNF-06, RNF-04 | SQL dentro del reporte: no se puede probar sin base | Prueba «PDO e InMemory dan el mismo desglose» |
| D-07 | Controlador sin SQL ni reglas | Consigna semana 4 | Controlador gordo | Prueba «los controladores no contienen SQL ni PDO» |
| D-08 | Permisos en Application (`PoliticaPermisos`) | RF-07, RNF-02, RN-01 | Revisar el rol en cada vista: se olvida en alguna | Prueba «el Médico no puede ver Ocupación: 403» |
| D-09 | Error de persistencia como 503 sin detalles técnicos | RNF-02 (seguridad) | Mostrar la excepción de PDO | Prueba con `RepositorioQueFalla` |
| D-10 | «Sin datos» en lugar de cero | RN-06 | Devolver 0 %: se confunde con hospital vacío | Prueba «período sin datos informa Sin datos» |
| D-11 | Paciente solo con código en los registros | RN-09, RNF-03 | Nombre del paciente: expone datos sensibles | `RegistroOrigen` valida `PAC-NNNNN`; `CHECK` en `local_patient` |
| D-12 | API REST v1 con errores de forma común y `X-Correlation-Id` | Consigna semana 5 | Respuestas libres por endpoint | `semana-05/contrato-api.md`, `openapi.yaml` y 6 pruebas de API |
| D-13 | No separar en microservicio todavía | RNF-01 medido | Dividir por moda | Tabla de umbrales en `semana-05/README.md` |
| D-14 | PostgreSQL 16 también en el prototipo | Coherencia con el SHI y `openspec/config.yaml` | SQLite: pruebas que no reflejan el motor real | `micro-his/docker-compose.yml` y pruebas sobre `micro_his_test` |
