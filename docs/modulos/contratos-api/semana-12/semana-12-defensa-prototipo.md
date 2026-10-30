# Semana 12 — Defensa de componentes, UX, accesibilidad y movilidad de Contratos API y documentación por rol

| | |
|---|---|
| **Tarea Odoo** | 2484 — Semana 12: Segunda evaluación parcial |
| **Flujo** | «Publicación y consulta de documentación y manuales diferenciados por rol» |
| **Presentación** | [semana-12-presentacion.md](semana-12-presentacion.md) (5 diapositivas) |
| **Prototipo** | [semana-11/prototipo/index.html](../semana-11/prototipo/index.html): se abre en el navegador, sin servidor |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Recorrido navegable

Se abre `index.html` y se sigue la tabla. El rol se cambia en el panel del prototipo, al pie de la página.

| Paso | Ruta | Rol | Qué demuestra | Evidencia |
|---|---|---|---|---|
| 1 | `#docs` | Médico | Solo los documentos de su audiencia (R-06); «2 documentos para tus roles» se anuncia | [S11, captura 01](../semana-11/capturas/escritorio/01-mi-documentacion.png) |
| 2 | `#doc/citas-api` | Médico | OpenAPI vigente con endpoints; errores y ejemplos plegados en `<details>` | [S11, captura 02](../semana-11/capturas/escritorio/02-citas-api-vigente.png) |
| 3 | Elegir la 1.0.0 | Médico | Aviso de versión obsoleta con enlace a la vigente (R-08), anunciado | [S11, captura 03](../semana-11/capturas/escritorio/03-version-obsoleta.png) |
| 4 | `#doc/inventario-farmacia` | Médico | Lo ajeno responde igual que lo inexistente (R-07) | [S11, captura 04](../semana-11/capturas/escritorio/04-error-documento-ajeno.png) |
| 5 | `#docs` | Médico | **Cambio solicitado**: buscador con etiqueta visible | [S12, captura 01](capturas/escritorio/01-buscador.png) |
| 6 | Escribir «openapi» | Médico | Filtra a 1 resultado; «1 documento encontrado.» sin mover el foco (B-08) | [escritorio](capturas/escritorio/02-busqueda-un-resultado.png) · [móvil](capturas/movil/02-busqueda-un-resultado.png) |
| 7 | Escribir «farmacia» | Médico | 0 resultados: buscar no revela documentos fuera de la audiencia (R-06, R-07) | [escritorio](capturas/escritorio/03-busqueda-ajena-sin-resultados.png) · [móvil](capturas/movil/03-busqueda-ajena-sin-resultados.png) |

## 2. Matriz decisión → RF/RNF → evidencia

| # | Decisión (componentes, UX, accesibilidad, movilidad) | RF/RNF y regla | Evidencia |
|---|---|---|---|
| D-01 | Dos componentes de consulta, «mi documentación» y documento, más el cliente HTTP (S7) | RF-04 | Componentes de la S7 (PR #457, sin integrar) y `vistas` de `app.js` |
| D-02 | La lista muestra solo la audiencia del rol | RF-04, RNF-01 (R-06) | Pasos 1 y 7 |
| D-03 | Lo ajeno y lo inexistente responden igual | RF-05, RNF-04 (R-07) | Paso 4; captura idéntica byte a byte en la S11 |
| D-04 | Aviso de obsoleta con borde, texto y `role="status"`, y enlace a la vigente | RF-06 (R-08), B-03 | Paso 3 |
| D-05 | Errores y ejemplos en `<details>`; rutas y JSON que saltan de línea | B-06; WCAG 1.4.10 | Paso 2; clase `.texto-largo` |
| D-06 | Foco en el `<h1>`, títulos distintos y «Saltar al contenido» | B-01; WCAG 2.4.2, 2.4.3 | [Medición S11](../semana-11/evidencia/salida-medicion.txt) |
| D-07 | Tarjetas en móvil, objetivos de 44 px y sin scroll horizontal | Movilidad (S10); WCAG 1.4.10 | Mediciones de la S11 y 9 de 9 de la S12 a 320, 360 y 430 px |
| D-08 | **Cambio solicitado**: búsqueda con resultado anunciado | RF-04, B-08; WCAG 4.1.3 | §4 y [salida-medicion.txt](evidencia/salida-medicion.txt) |

## 3. Roles, estados, errores y riesgos en el prototipo

| Aspecto | Cómo lo trata el prototipo | Riesgo que cubre |
|---|---|---|
| Roles | Médico, integrador y farmacia ven listas distintas | Exponer contratos a roles sin permiso |
| Estados | Versión vigente u obsoleta, en texto y con aviso | Usar endpoints de una versión obsoleta (B-03) |
| Errores | «Documento no encontrado» para lo ajeno o inexistente; búsqueda sin resultados explicada | Revelar que un documento existe |
| Versionado | Selector con «(vigente)» y «(obsoleta)» | Confundir versiones |

## 4. Respuesta documentada al cambio solicitado

**Solicitud (integradores):** «Con muchos contratos y manuales, no encontramos el que buscamos, y con lector de pantalla no sabemos si la búsqueda encontró algo.» Es B-08 (P2) de la S9, que el prototipo de la S11 no tenía.

| | Antes | Después |
|---|---|---|
| Lista | Todos los documentos del rol | Campo «Buscar en mi documentación» con etiqueta visible y 44 px de alto |
| Resultado | — | Filtra por nombre y tipo, y anuncia «1 documento encontrado.» en `role="status"`, sin mover el foco |
| Sin coincidencias | — | «Ningún documento de tus roles coincide con «farmacia».» |
| Alcance | — | Solo busca dentro de lo que el rol ya ve: buscar no revela nada ajeno |

El cambio está en [`app.js`](../semana-11/prototipo/app.js) (`prepararBusqueda` y el atributo `data-busqueda` de cada fila) y en [`estilos.css`](../semana-11/prototipo/estilos.css). **La revisión visual encontró un error propio:** en móvil, las tarjetas (`display: block`) anulaban el atributo `hidden`, y la búsqueda anunciaba 1 resultado mostrando 2. Se corrigió con `[hidden] { display: none !important; }`. La medición final cuenta las filas visibles en cada ancho: 2, 1 y 0, sin scroll horizontal (9 de 9).

**Lo que no cambia:** R-06 y R-07 los sigue aplicando el backend; el filtro es solo una ayuda para encontrar lo que el rol ya recibió.

## 5. Bibliografía

Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design* (1: visibilidad del estado del sistema) · W3C (2023). *WCAG 2.2*: 1.4.10, 2.4.3 y 4.1.3 (mensajes de estado).
