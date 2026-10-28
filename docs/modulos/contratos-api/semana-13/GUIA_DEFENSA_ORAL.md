# Guía de defensa oral — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API / Centro de documentación y manuales por rol · **Documento:** [semana-13-revision-tecnica.md](semana-13-revision-tecnica.md)

> «Es una inspección del Micro-HIS del centro de documentación, congelado en un commit de `develop`, con seis fases, roles separados y un checklist de 15 ítems que cubre OpenAPI, Postman, ejemplos, permisos, audiencia, versiones obsoletas y auditoría. La autorrevisión encontró tres hallazgos mayores y dos menores, cada uno reproducible con un script.»

**¿Cuál es el hallazgo más grave?** H-01: el cliente envía sus propios `roles`, así que la audiencia (R-06) y el 404 (R-07) se pueden saltar escribiendo otro rol. Es mayor y no crítico porque hoy no hay sesión ni RBAC conectados; se pospone con fecha (semana 15, RNF-01) y no se descarta.

**¿Por qué C-05 a C-07 dan «no» si no hay código que falle?** Porque la versión no guarda contenido (H-02): no hay nada contra qué comparar la colección Postman con su OpenAPI. Un ítem que no se puede verificar no se marca «sí».

**¿Por qué el autor no modera?** Porque defendería su código y decidiría si su trabajo sale de la revisión. El moderador corta las discusiones de solución: en la reunión se encuentran errores, no se corrigen.
