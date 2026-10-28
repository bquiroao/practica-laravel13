# Semana 10 — Diseño móvil de «publicación y consulta de documentación y manuales diferenciados por rol»

| | |
|---|---|
| **Tarea Odoo** | 2482 — Semana 10: Diseño para movilidad |
| **Módulo** | Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) |
| **Base** | Flujo y wireframes de la semana 8 (PR #204) y backlog de la semana 9 (PR #210); reglas R-01 a R-10 de la [Semana 1](../semana-01/semana-01-uml-documentacion-rol.md) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Jerarquía en móvil (320–430 px)

En el teléfono, lo más común es consultar: un médico que revisa un manual o un integrador que busca un endpoint. Revisar una versión se hace a veces, y publicar es lo menos frecuente. Por eso:

1. **Arriba:** el nombre del documento, su tipo (manual, OpenAPI o colección Postman) y la versión vigente con su fecha. El versionado documental decide qué se lee.
2. **Al leer:** las secciones van plegadas, en este orden: endpoints (método y ruta), errores y ejemplos. Así una pantalla de 320 px no se llena de JSON. Las colecciones Postman se muestran igual, como su lista de peticiones, con «Descargar colección» para importarla completa, porque en el teléfono no se ejecutan. Una versión obsoleta muestra su aviso antes que el contenido.
3. **Acciones:** revisar y publicar están en pantallas propias. En la revisión, los permisos se ven antes de decidir: quién envió la versión y para qué audiencia es.

Para reducir la carga cognitiva, la publicación se divide en 2 pasos (documento y versión; audiencia y archivo). El botón final repite a quién le llegará («Enviar: lo verán médico y enfermería»), y el selector de archivo dice qué formatos acepta.

## 2. Pantallas

| M | Pantalla | Navegación | Tabla o formulario | Confirmación y error |
|---|---|---|---|---|
| [M1](diagramas/semana-10-m1-mi-documentacion.png) | Mi documentación | Raíz del módulo; «≡ Menú» abre los demás módulos | La tabla de la semana 8 pasa a una tarjeta por documento; el nombre es un enlace | «2 documentos» se anuncia al buscar; lo ajeno al rol no se lista (R-06) |
| [M2](diagramas/semana-10-m2-leer.png) | Leer un documento | «< Documentos» vuelve; el selector cambia de versión | Secciones plegables; cada endpoint en su línea | Una versión obsoleta muestra el aviso arriba (R-08) |
| [M3](diagramas/semana-10-m3-revisar.png) | Revisar una versión | «< Pendientes» vuelve sin decidir | Autor, audiencia, cambios, validación y motivo | «Aprobar» queda alcanzable aunque no se pueda usar, y explica por qué (R-05); el motivo es obligatorio para rechazar |
| [M4](diagramas/semana-10-m4-publicar-audiencia.png) | Publicar, paso 2 | «< Paso 1» vuelve sin perder lo llenado | Grupo «Audiencia» con leyenda; archivo con formato y tamaño | El botón nombra la audiencia antes de enviar |
| [M5](diagramas/semana-10-m5-conexion-limitada.png) | Conexión limitada | Se abre sobre M2, M3 o M4 | — | Copia guardada con su fecha; «No sabemos si se publicó»; subida interrumpida |

Cada pantalla completa recibe el foco en su título al abrirse, lo que resuelve en móvil el foco de los diálogos de la semana 9 (B-01).

## 3. Reglas de breakpoint

| Ancho | Regla |
|---|---|
| 320–359 px | Una columna. Una tarjeta por documento. Los endpoints se muestran como «método + ruta» en una línea que puede saltar; los ejemplos JSON saltan de línea en vez de forzar scroll horizontal, con «Copiar» para llevarlos completos. |
| 360–430 px | Igual, pero el nombre y el tipo del documento caben en la misma línea. |
| 431 px o más | Fuera del alcance móvil: vuelve la tabla de W1 de la semana 8 y las secciones se muestran abiertas. |

**Reglas comunes:** sin scroll horizontal a 320 px (WCAG 1.4.10), incluidos los ejemplos de código; objetivos táctiles de al menos 44 × 44 px (2.5.8 exige 24 px); texto de los campos de 16 px; texto ampliable al 200 % (1.4.4); funciona en vertical y en horizontal (1.3.4).

## 4. Escenarios móviles

| | E-1: consultar un manual con señal débil | E-2: aprobar un contrato desde el teléfono |
|---|---|---|
| **Quién y dónde** | Médico en un pasillo, con un teléfono de 360 px | Responsable de integración fuera de su escritorio, con un teléfono de 320 px |
| **Tarea** | Leer en la guía de citas qué significa un error de la agenda | Revisar y aprobar citas-api 2.1.0, enviada por ana |
| **Decisión de contenido** | La sección «Errores» va plegada junto a «Endpoints», y el médico abre solo la que necesita. Los ejemplos se omiten de la vista inicial | M3 muestra primero el autor y la audiencia (permisos) y después un resumen de cambios («+1 endpoint · 1 ejemplo actualizado»). El diff completo queda para el escritorio |
| **Error** | Se pierde la señal al abrir el documento | La conexión se corta al pulsar «Aprobar y publicar» |
| **Decisión de error** | M5 muestra la copia guardada con su versión y su fecha, y avisa que no puede comprobar si hay una más nueva. Así el médico sabe que podría estar leyendo una versión obsoleta | «No sabemos si se publicó» y «Comprobar el estado». La aprobación **no** se guarda para enviarla después, porque otra persona pudo rechazar esa versión mientras tanto. Si ana fuera quien revisa, «Aprobar» seguiría deshabilitado (R-05) |

## 5. Trazabilidad con la semana 9

| Corrección del backlog (PR #210) | Dónde queda en móvil |
|---|---|
| B-01 Foco definido | Pantallas completas con el foco en su título |
| B-02 Audiencia agrupada y resumida antes de enviar | M4 |
| B-03 Aviso de versión obsoleta legible y anunciado | M2 y M5 |
| B-04 «Aprobar» alcanzable con su motivo | M3 |
| B-05 Formatos de archivo según el tipo | M4 («Manual: .pdf · máx. 10 MB») |
| B-06 El nombre del documento es un enlace | M1 |
| B-08 Resultados de búsqueda anunciados | M1 («2 documentos») |

Las rutas de M2 son las del front controller de Citas de la semana 4 (PR #195, aún no integrado): `POST /citas`, `POST /citas/{id}/confirmacion` y `POST /citas/{id}/cancelacion`. Fuentes editables en [`diagramas/`](diagramas/) (PlantUML Salt); los PNG se generaron con PlantUML local. Salt no fija el ancho en píxeles, así que los wireframes representan la estructura a 320 px y el ancho real se valida en el prototipo de la semana 11.

**Bibliografía:** W3C (2023). *WCAG 2.2*: 1.3.4, 1.4.4, 1.4.10 y 2.5.8 · Wroblewski, L. (2011). *Mobile First*. A Book Apart.
