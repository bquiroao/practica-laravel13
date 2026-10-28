# Verificación de Semana 11

Validación del prototipo local con Chromium en viewport móvil de 390 × 844 px. Los datos son ficticios y no se conectó el HIS.

| Escenario | Resultado |
|---|---|
| Camino feliz | Aceptación confirmada y un único evento inicial de custodia. |
| No conformidad confirmada | Se propone rechazo cuando una verificación física no se confirma. |
| Motivo obligatorio | Sin motivo, el flujo permanece en la decisión y muestra el error. |
| Rechazo con motivo | Se guarda el rechazo de demostración y la confirmación muestra el motivo seleccionado. |
| Código duplicado | Se muestra el bloqueo; no se habilita el guardado de custodia ni se registra otra recepción. |
| Cerrar y escalar | El botón está disponible en móvil; vuelve a búsqueda y muestra el aviso de escalamiento sin crear recepción ni custodia. |
| Orden desconocida | Se muestra el error de búsqueda y no se crea una recepción. |
| Layout móvil | `innerWidth`, `clientWidth` y `scrollWidth` son 390 px; no hay elementos visibles fuera del viewport. |

Evidencia visual:

- Camino feliz: [móvil](movil-camino-feliz.png) y [desktop](desktop-camino-feliz.png).
- Error crítico: [móvil](movil-error-critico.png) y [desktop](desktop-error-critico.png).
- Rechazo: [desktop](desktop-rechazo.png).

La validación es una prueba de interfaz del prototipo, no sustituye pruebas de integración ni confirma persistencia en un sistema clínico.
