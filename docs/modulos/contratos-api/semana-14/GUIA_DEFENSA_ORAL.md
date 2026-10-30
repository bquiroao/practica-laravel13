# Guía de defensa oral — Semana 14

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API / Centro de documentación y manuales por rol · **Documento:** [semana-14-plan-sqa.md](semana-14-plan-sqa.md)

> «El plan SQA tiene 7 métricas con fuente, umbral, responsable y acción, y una línea base medida hoy: 4 cumplen y 3 no. La matriz tiene 8 riesgos. Los dos más altos son que un lector vea documentos ajenos enviando sus propios roles y que se publique un OpenAPI inválido o con datos reales.»

**¿Por qué faltan justo R-04, R-09 y R-10 en M-02?** Porque el Micro-HIS todavía no guarda el contenido de las versiones (hallazgo H-02 de la semana 13), y sin contenido no hay lint ni búsqueda de datos reales que probar. Es la misma causa vista desde dos lados: la inspección lo encontró y la métrica lo mide.

**¿Cómo se controla que Postman y OpenAPI coincidan?** Con un control preventivo y uno detectivo. El preventivo (CP-05) valida la colección contra el OpenAPI al enviarla. El detectivo (CD-04) es el revisor, que compara endpoints y métodos. Mientras el contenido no se guarde, solo funciona el segundo, y por eso el riesgo sigue abierto.

**¿Por qué M-05 «cumple» si está justo en 15?** Porque el umbral es «máximo 15». Pero un cambio más lo rompe, y por eso tiene su propio riesgo (RG-07), en vez de darse por resuelto.
