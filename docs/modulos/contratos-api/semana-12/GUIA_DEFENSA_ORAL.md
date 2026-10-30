# Guía de defensa oral — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica · **Documentos:** [presentación](semana-12-presentacion.md) y [recorrido, matriz y cambio](semana-12-defensa-prototipo.md)

> «Con el prototipo muestro el flujo en 7 pasos: cada rol ve solo su audiencia, la versión obsoleta avisa y enlaza a la vigente, y lo ajeno responde igual que lo inexistente. El cambio solicitado fue buscar: el campo filtra dentro de lo que el rol ya ve y anuncia el resultado sin mover el foco. Al revisar la captura móvil encontré que las tarjetas seguían visibles; lo corregí y ahora la medición cuenta las filas que de verdad se ven.»

**¿Por qué anunciar el resultado sin mover el foco?** Porque quien escribe sigue en el campo. Si el foco saltara a la lista, perdería lo que estaba escribiendo. Un mensaje de estado (WCAG 4.1.3) informa sin interrumpir.

**¿Buscar «farmacia» como médico no revela que el documento existe?** No: se busca solo dentro de lo que el rol ya recibió, y responde «ningún documento de tus roles coincide». Es la misma idea de R-07: lo ajeno no se distingue de lo inexistente.

**¿Qué error encontraste y cómo?** En móvil, la regla `tr { display: block }` de las tarjetas le ganaba al atributo `hidden`: se anunciaba 1 resultado y se veían 2. Lo vi en la captura y lo corregí con `[hidden] { display: none !important; }`. Ahora la medición cuenta las filas visibles en cada ancho.

**¿Por qué una etiqueta visible y no solo un placeholder?** Porque el placeholder desaparece al escribir y muchos lectores no lo anuncian como nombre. La etiqueta queda siempre.

**¿El filtro reemplaza la seguridad del backend?** No. R-06 y R-07 los aplica el backend; el filtro solo ayuda a encontrar.
