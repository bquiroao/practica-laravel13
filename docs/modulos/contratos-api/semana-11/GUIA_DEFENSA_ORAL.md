# Guía de defensa oral — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación por rol) · **Documento:** [semana-11-prototipo.md](semana-11-prototipo.md)

> «El prototipo muestra a cada rol solo su documentación. Abre un contrato OpenAPI con sus endpoints, errores y ejemplos, y avisa cuando se lee una versión obsoleta. El error crítico es un enlace directo a un documento de otra audiencia: responde “Documento no encontrado”, igual que si no existiera.»

**¿Cómo se comprobó que no se revela que el documento existe?** Se capturó un documento inexistente y uno ajeno, y las dos imágenes son idénticas byte a byte (`cmp`). Con el rol farmacia, el mismo enlace sí abre el manual. **¿Por qué `<details>`?** Porque es un control nativo: se abre con teclado y el lector anuncia si está plegado, sin JavaScript extra.
