# Guía de defensa oral — Semana 15

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación por rol) · **Documento:** [semana-15-pruebas.md](semana-15-pruebas.md)

> «Diseñé 14 pruebas trazadas a RF/RNF: 6 unitarias, 2 de caja blanca y 6 de caja negra por HTTP. Contra `develop` pasan 11 de 14. Las 3 que fallan son defectos reales y quedan registrados: dos envíos simultáneos de la misma versión dan 503 en vez de 409, cualquiera publica o aprueba sin identidad ni permiso, y con la base caída se ve el error de PHP.»

**¿Por qué entregas pruebas que fallan?** Porque la prueba está para encontrar defectos. Si la ajustara para que pase, escondería el problema. El exit 1 es la evidencia, y cada falla tiene su defecto con causa y propuesta.

**¿Cómo sabes que las pruebas detectan errores?** Corrí dos mutaciones de una línea en una copia. Permitir publicada → en revisión la detecta U-01, y dejar que el autor apruebe lo suyo la detectan CB-01 y CB-02.

**¿Por qué V(G) = 4 y 5 caminos?** `cambiar` tiene 3 decisiones (versión inexistente, autor = revisor y el resultado del repositorio): 3 + 1 = 4. La excepción de `validarCambioA` agrega una salida más, y por eso son 5 caminos.

**¿Por qué el 404 de CN-03 compara cuerpos?** Porque RNF-04 pide no revelar si el documento existe. No basta el mismo código: el cuerpo de «fuera de audiencia» debe ser idéntico al de «no existe».

**¿Por qué no probaste el RBAC?** Es otro módulo. CN-04 solo comprueba que el centro rechace a quien no tiene identidad o permiso, y no lo hace (DEF-02).

**¿Y las reglas de OpenAPI y Postman?** U-02 prueba los tipos y el versionado, y la prueba de contrato de la semana 5 comprueba que los endpoints respondan lo documentado (18 de 18). El lint del contenido de cada versión (R-04) no está implementado: queda como brecha declarada.
