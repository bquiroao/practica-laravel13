# 1. Proceso y alcance

**Primera evaluación parcial — Mercedes Azucena Lopez Perez**

Emisión desde expediente y seguimiento. Profesional autorizado confirma pruebas de un paciente/episodio; laboratorio informa estado. No incluye recepción de muestras, ejecución de pruebas ni validación de resultados.

Defensa: la emisión y la consulta comparten contexto; cambiar estado requiere origen distinto autorizado.

---

# 2. Actores y requerimientos

- Clínico/EMR: RF-01..04 contexto, al menos una prueba disponible, orden completa.
- Usuario autorizado: RF-05/06 seguimiento; vacío no equivale a falla.
- Laboratorio: RF-07/08 origen confiable y transición confirmada.
- Catálogo externo al módulo: RF-09.

RNF-02 seguridad, RNF-03 integridad, RNF-04 trazabilidad y RNF-05 estado veraz guían las decisiones. Referencia: documento de semana 2, criterios CA-01..16.

---

# 3. UML y capas

Abrir README (Mermaid) y diagramas/arquitectura.puml.

Presentación HTTP propuesta → aplicación con tres casos de uso → contratos/políticas → adaptadores. La flecha hacia Repository marca inversión de dependencia; el dominio no conoce PDO. Reutilizar UML de semana 1 para explicar emisión, no inventar otro proceso.

---

# 4. SOLID: SRP y Repository

IssueLabOrder emite; FindLabOrders consulta; ApplyLabOrderStatus recibe seguimiento. Autorización, catálogo, ciclo y persistencia cambian por motivos distintos.

Repository promete escritura completa y actualización condicional. Actual implementación es doble de memoria; PDO transaccional queda propuesto. Inyectar una política evita fijar estados clínicos desde la pantalla.

---

# 5. Contrato API y errores

POST desde paciente/episodio: 201 solo persistido. GET: 200 [] solo consulta válida vacía. PATCH: origen autorizado y 409 ante conflicto. Ver tabla del README para entradas y errores.

Actor y tenant salen de contexto autenticado; no se confía en cuerpo. No se despliega API en esta entrega. Payload/contrato propuesto sirve para revisar dependencias de implementación.

---

# 6. Cambio práctico solicitado para el ejercicio

Regla nueva de diseño: un código de prueba aparece una vez por orden. No se impide una repetición clínica justificada en otra orden; la política institucional debe aprobar esta decisión antes de uso real.

Antes: fixture acepta TEST-A,TEST-A. Después: UniqueTestCatalog rechaza selección completa con INVALID_TESTS, sin guardar parcial. Decisión explícita frente a deduplicar silenciosamente lo que el clínico revisó.

---

# 7. Evidencia del cambio

Ejecutar cambio/verificar.php: antes/después, rechazo sin escritura, dos pruebas válidas, trazabilidad paciente/episodio, catálogo desconocido, autorización, falla de escritura y seguimiento no disponible.

Regresión de semana 2 por separado. Presentar resultados guardados en evidencia; dobles comprueban coordinación, no garantías de infraestructura clínica.

---

# 8. Defensa, límites y siguiente paso

¿Por qué no cambiar IssueLabOrder? El cambio está en validación de selección y se compone mediante LabCatalog.

¿Qué permanece? Misma orden con expediente, partidas, estado y solicitante; mismos casos de uso y Repository.

¿Qué falta? Acordar política/ciclo y contrato con responsables; implementar adaptador PDO/API, identidad y aislamiento, auditoría persistente e idempotencia. No declarar pruebas clínicas, JWT o revisión independiente.