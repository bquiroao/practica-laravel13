# Semana 9 — Evaluación de usabilidad y accesibilidad del flujo UX de Contratos API (centro de documentación por rol)

| | |
|---|---|
| **Tarea Odoo** | 2481 — Semana 9: Evaluación del diseño, usabilidad y accesibilidad |
| **Módulo** | Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) |
| **Objeto evaluado** | User flow y wireframes W1–W5 de la semana 8 (commit `09608d4`, PR #204) y los colores reales del frontend compartido (`AppLayout.vue`, `LoginPage.vue`) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Método

- **Heurísticas:** las 10 de Nielsen, revisadas pantalla por pantalla.
- **WCAG 2.2 nivel AA:** los criterios del enunciado (teclado, foco, contraste, etiquetas, mensajes y prevención de errores) y el uso del color.
- **Evidencia:** cada hallazgo cita la línea exacta del wireframe ([lineas-wireframes-s8.txt](evidencia/lineas-wireframes-s8.txt)) o una medición. El contraste se mide con [contraste.php](evidencia/contraste.php) (fórmula de luminancia relativa de WCAG, con autoprueba 21:1 y 1:1), y su salida está en [salida-contraste.txt](evidencia/salida-contraste.txt). Los colores de los wireframes son los nombres HTML que usa PlantUML: `orange` = `#ffa500` y `green` = `#008000`.
- **Gravedad** (escala de Nielsen, 0–4) e **impacto en la documentación**: la prioridad sube si el problema puede publicar para la audiencia equivocada, hacer que alguien use una versión obsoleta o impedir la tarea a quien usa teclado.
- **Limitación:** hubo un solo evaluador, y Nielsen recomienda de 3 a 5.

## 2. Checklist

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | Parcial | W2 muestra «validando» y W3 el resultado de la validación; el resultado de la búsqueda de W1 no se anuncia (H-08) |
| 2 | Relación con el mundo real | Cumple | Versión vigente u obsoleta, audiencia y revisión son términos del equipo técnico |
| 3 | Control y libertad | Cumple | «Cancelar» en W2; «Ir a la 1.1.0» desde una obsoleta; «Reemplazar archivo» en W5 |
| 4 | Consistencia y estándares | Parcial | El motivo de W3 dice «(obligatorio para rechazar)» en vez del «*» que usan W2 y los demás formularios (H-03) |
| 5 | Prevención de errores | Parcial | La versión se valida contra la vigente (R-02); el archivo no dice qué formatos acepta (H-04) |
| 6 | Reconocer antes que recordar | Cumple | W2 muestra la versión vigente junto al campo; W5 sugiere versiones válidas |
| 7 | Flexibilidad y eficiencia | Cumple | Búsqueda por nombre y filtro por tipo en W1 |
| 8 | Diseño estético y minimalista | Cumple | Lo ajeno al rol no se lista (R-06) |
| 9 | Reconocer y corregir errores | Parcial | W5 señala la línea del problema; sugiere dos versiones pero el botón solo ofrece una (H-09) |
| 10 | Ayuda y documentación | Cumple | «[?] Ayuda» en W2 y W3 |
| 11 | Teclado (2.1.1, 2.1.2) | No cumple | No hay una acción para abrir un documento desde W1 (H-05), y «Aprobar» deshabilitado no recibe el foco (H-03) |
| 12 | Foco (2.4.3, 2.4.7, 2.4.11) | No cumple | 0 menciones de «foco» en los 8 archivos de la semana 8 (H-06) |
| 13 | Contraste de texto (1.4.3) | No cumple | Aviso de versión obsoleta `#ffa500` a 1.97:1, cuando el mínimo es 4.5:1 (H-01); validación `#008000` 5.14:1 |
| 14 | Contraste de componentes (1.4.11) | No cumple | Borde de campo `#cbd5f5` a 1.46:1, cuando el mínimo es 3:1 (H-07) |
| 15 | Uso del color (1.4.1) | Cumple | La validación de W3 lleva «✓» y texto; el aviso de W4 se entiende por su texto |
| 16 | Etiquetas e instrucciones (1.3.1, 3.3.2, 4.1.2) | No cumple | «Tipo» y «Audiencia» no tienen un nombre de grupo (H-02); el archivo no indica formatos (H-04) |
| 17 | Mensajes de error y de estado (3.3.1, 3.3.3, 4.1.3) | Parcial | W5 explica cada error y sugiere la corrección; la búsqueda no se anuncia (H-08) |
| 18 | Prevención de errores (3.3.4) | Parcial | La publicación pasa por la revisión de otra persona (R-05); la audiencia elegida no se resume antes de enviar (H-02) |

## 3. Hallazgos

| ID | Dónde | Problema | Criterio | Gravedad | Evidencia |
|---|---|---|---|---|---|
| H-01 | W4, línea 5 | El aviso «Estás viendo una versión obsoleta» casi no se lee: naranja sobre blanco | 1.4.3; heurística 1 | 3 | `#ffa500` sobre `#ffffff`: 1.97:1 |
| H-02 | W2, líneas 6 y 8 | Los radios «Tipo» y las casillas «Audiencia» no tienen un nombre de grupo, y la audiencia no se resume antes de enviar, aunque define quién verá el documento | 1.3.1, 3.3.4; heurística 5 | 3 | La etiqueta está en otra celda que el grupo |
| H-03 | W3, líneas 7 a 9 | «Aprobar» deshabilitado no recibe el foco y su motivo no está asociado al botón; «Rechazar» está activo con el motivo vacío, y ese campo no usa el «*» | 2.1.1, 4.1.2, 3.3.2; heurística 4 | 2 | Texto de las líneas 7, 8 y 9 |
| H-04 | W2, línea 9 | «Elegir archivo» no dice qué formatos acepta según el tipo (OpenAPI, colección Postman o manual) ni el tamaño máximo | 3.3.2; heurística 5 | 2 | Solo se indica «Sin datos reales de pacientes» |
| H-05 | W1, líneas 8 y 9 | No se define cómo se abre un documento: las filas no tienen enlace ni botón, así que con teclado no hay a dónde llegar | 2.1.1, 2.4.4 | 2 | Filas sin acción |
| H-06 | W1–W5 | No se define el foco: ni el inicial de W3 y W5, ni el retorno al cerrar, ni el orden de tabulación del formulario de W2 | 2.4.3, 2.4.7 | 4 | 0 menciones de «foco» en la semana 8 |
| H-07 | Campos de W2 y W3 (estilo compartido) | Si los formularios reutilizan el estilo del login, el borde del campo no se distingue del fondo | 1.4.11 | 2 | `#cbd5f5` sobre `#ffffff`: 1.46:1 |
| H-08 | W1, línea 5 | Al buscar o filtrar, la tabla cambia sin anunciar cuántos documentos quedan | 4.1.3; heurística 1 | 2 | Buscador y filtro sin mensaje de resultado |
| H-09 | W5, línea 5 | Sugiere 1.0.1 o 1.1.0, pero el único botón es «Usar 1.1.0» | Heurística 4 | 1 | Texto de la línea 5 |

## 4. Backlog priorizado

La prioridad sale del impacto en OpenAPI, colecciones Postman, endpoints, ejemplos, errores, permisos y versionado documental. P1 = puede publicar para la audiencia equivocada, hacer que se use una versión obsoleta o impedir la tarea con teclado · P2 = dificulta la tarea · P3 = mejora menor.

| Orden | Hallazgo | Prioridad | Impacto en la documentación | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|---|
| B-01 | H-06 | P1 | Sin teclado no se publica, revisa ni consulta ningún contrato | Orden de tabulación igual al visual en W2; al abrir W3 o W5, el foco va al título; al cerrar, vuelve al control que lo abrió; anillo de foco de 2 px en `#2563eb` | Recorrido solo con teclado de W1 a W5 sin ratón; anillo de foco ≥ 3:1 (medido: 5.17:1) |
| B-02 | H-02 | P1 | Una audiencia mal elegida expone un documento a roles sin permiso | `fieldset` con leyenda para «Tipo» y «Audiencia», y antes de enviar el resumen «Lo verán: médico y enfermería» | El lector anuncia «Audiencia, grupo»; el botón de enviar muestra los roles elegidos |
| B-03 | H-01 | P1 | Un integrador usa endpoints de una versión obsoleta sin notarlo | Aviso con icono, borde y texto `#b45309` sobre fondo claro, anunciado con `role="status"` al abrir | `contraste.php`: 5.02:1 ≥ 4.5:1; el lector anuncia el aviso al cargar |
| B-04 | H-03 | P2 | Rechazos sin motivo y dudas sobre el permiso de aprobar | «Aprobar» con `aria-disabled="true"`: sigue recibiendo el foco y anuncia su motivo con `aria-describedby`. El motivo lleva «*» y valida al pulsar «Rechazar» | Con Tab se llega a «Aprobar» y se oye «debe aprobarla otra persona»; rechazar con el motivo vacío muestra «El motivo es obligatorio» |
| B-05 | H-04 | P2 | Se sube un OpenAPI o una colección Postman en un formato que el validador rechaza | Texto bajo el campo según el tipo: «OpenAPI: .yaml o .json · Postman: .json (v2.1) · Manual: .pdf · máx. 10 MB», y el selector filtra por extensión | El texto cambia al cambiar el tipo; el selector no ofrece otras extensiones |
| B-06 | H-05 | P2 | No se llega a los ejemplos ni a los errores documentados con teclado | El nombre del documento es un enlace a W4 | Tab llega a cada documento y Enter lo abre |
| B-07 | H-07 | P2 | Cuesta encontrar los campos del formulario de publicación | Borde de campo `#64748b` | `contraste.php`: 4.76:1 ≥ 3:1 |
| B-08 | H-08 | P2 | No se sabe si la búsqueda encontró el contrato | Región `aria-live="polite"` con «2 documentos encontrados» | El lector anuncia el número de resultados sin mover el foco |
| B-09 | H-09 | P3 | Pequeña fricción al corregir la versión | Dos botones, «Usar 1.0.1 (corrección)» y «Usar 1.1.0 (cambio menor)» | W5 muestra un botón por cada versión sugerida |

Las correcciones se aplican al prototipo navegable de la semana 11; el criterio verificable de cada una es su prueba de aceptación.

**Bibliografía:** Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design*. Nielsen Norman Group · W3C (2023). *Web Content Accessibility Guidelines (WCAG) 2.2* · W3C (2024). *ARIA Authoring Practices Guide* (`aria-disabled`, `fieldset`).
