# Guía de defensa oral — Semana 9

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API (centro de documentación por rol) · **Documento:** [semana-09-evaluacion-usabilidad.md](semana-09-evaluacion-usabilidad.md)

> «Evalué mis wireframes del centro de documentación con las 10 heurísticas de Nielsen y WCAG 2.2 AA. Encontré 9 problemas con evidencia y los ordené por impacto: primero la audiencia, que define los permisos, el aviso de versión obsoleta y el uso con teclado.»

**¿Por qué el aviso naranja es grave?** Porque `#ffa500` sobre blanco da 1.97:1 y el texto necesita 4.5:1. Si el aviso no se lee, un integrador sigue usando los endpoints de una versión obsoleta. Con `#b45309`, icono y borde se llega a 5.02:1.

**¿Por qué «Aprobar» usa `aria-disabled` en vez de `disabled`?** Un botón con `disabled` no recibe el foco, y quien usa teclado o lector nunca oye por qué no puede aprobar. Con `aria-disabled` sigue siendo alcanzable y anuncia «debe aprobarla otra persona» (R-05).
