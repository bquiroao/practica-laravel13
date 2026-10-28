# Checklist de evaluación — usabilidad y accesibilidad del diseño UX de Notas SOAP

**Estudiante:** Joshua Eduardo García Reyes · **Objeto evaluado:** el diseño de la semana 8: los 4 user flows, los wireframes W1–W5 y la especificación [semana-08-ux-notas-soap.md](../semana-08/semana-08-ux-notas-soap.md) (mensajes M-01..M-30, reglas I-01..I-10, estados y reglas D-1..D-7).

**Resultado:** **C** cumple · **P** cumple en parte · **N** no cumple · **NA** no aplica. Cada P o N tiene un hallazgo (H-xx) en [semana-09-evaluacion-ux-notas-soap.md](semana-09-evaluacion-ux-notas-soap.md).

| Tipo de comprobación | Método |
|---|---|
| Contraste | Medición con [herramientas/contraste.py](herramientas/contraste.py) ([salida](evidencia/contraste-wcag.txt)) con los colores exactos del generador de wireframes |
| Teclado y foco | Recorrido del orden de tabulación (§4) sobre los wireframes y las reglas I-01..I-10 |
| Resto | Inspección del wireframe y del texto de la especificación |

## 1. Heurísticas de usabilidad (Nielsen, 1994)

| # | Heurística | W1 | W2 | W3 | W4 | W5 | Evidencia / hallazgo |
|---|---|---|---|---|---|---|---|
| N1 | Visibilidad del estado del sistema | P | P | P | C | C | Estados vacío, carga, éxito y error en todas. W1: la versión corregida casi no se distingue (H-03). W3: «Borrador» con poco contraste (H-04). W2: «Guardando…» apenas se lee (H-14). |
| N2 | Coincidencia con el mundo real | P | C | C | C | C | W1: la lista muestra «J18.9» sin la descripción del diagnóstico (H-15). Lenguaje clínico correcto en el resto. |
| N3 | Control y libertad del usuario | C | P | C | C | C | W2: cerrar la pestaña o expirar la sesión pierde el borrador sin aviso (H-10, H-11). Hay «Cancelar», «Volver» y `Esc`. |
| N4 | Consistencia y estándares | C | C | C | **N** | C | W4: S, A y P se dibujan como campos de solo lectura, pero la anotación dice que se pueden editar (H-05). |
| N5 | Prevención de errores | C | **N** | **N** | P | C | W2: no muestra a qué paciente se le escribe la nota (H-01); el código CIE-10 es texto libre (H-06). W3: el diálogo de firma no dice qué nota se firma (H-02). W4: no se ve qué cambió respecto a la versión anterior (H-05). |
| N6 | Reconocer antes que recordar | P | P | C | C | C | W2: hay que saberse el código CIE-10 (H-06). W1: hay que recordar qué significa cada código (H-15). |
| N7 | Flexibilidad y eficiencia | C | C | C | C | C | Filtros por estado, «Firmar ahora» después de guardar, datos copiados al corregir. |
| N8 | Diseño estético y minimalista | C | C | C | C | C | Cada pantalla muestra lo necesario para su tarea. |
| N9 | Ayudar a reconocer y recuperarse de errores | C | P | C | C | C | W2: con varios errores al guardar, no hay resumen ni se mueve el foco al primero (H-12). |
| N10 | Ayuda y documentación | C | C | C | C | C | «Ejemplo: J18.9», aviso fijo en W4, siguiente paso en W5. |

## 2. WCAG 2.2, nivel AA

Se listan los criterios ligados a la tarea: teclado, foco, contraste, etiquetas, mensajes y prevención de errores.

### 2.1 Teclado y foco

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 2.1.1 Teclado | C | Todos los controles son nativos: campos, lista, botones y enlaces. |
| 2.1.2 Sin trampas de teclado | C | `Esc` cierra el diálogo de firma (I-05). |
| 2.4.3 Orden del foco | **N** | W5: al llegar a «Acceso denegado» no se define dónde queda el foco (H-07). W2: tras un envío con errores, el foco no va al primer error (H-12). |
| 2.4.7 Foco visible | P | El foco de «Cancelar» solo engrosa el mismo borde gris que tiene sin foco (H-13). |
| 2.4.2 Página con título | **N** | No se especifica el título del documento al navegar a W5 ni a otras rutas de la SPA (H-07). |
| 2.4.4 Propósito de los enlaces | P | W1: tres enlaces «Ver» iguales para un lector de pantalla (H-16). |
| 2.5.8 Tamaño del objetivo (24 × 24 px) | C | Botones de 0.36 in de alto (≈ 35 px); enlaces «Ver» en filas de 0.46 in. |

### 2.2 Contraste y percepción

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 1.4.3 Contraste de texto (4.5:1) | **N** | Fallan 3 pares: encabezados de tabla 4.21:1 y fila corregida 4.26:1 (H-03), etiqueta «Borrador» 2.71:1 (H-04). Pasan texto general (13.10), cabecera y botones (5.92), errores (6.54), avisos (5.45–6.17). |
| 1.4.11 Contraste de no texto (3:1) | **N** | Borde de campo 2.13:1 (H-09). El borde de error (6.54) y el del filtro activo (5.92) pasan; el foco pasa justo (3.01, H-13). |
| 1.4.1 Uso del color | C | Los estados llevan texto («Borrador», «Firmada», «Corregida → v2»), no solo color. |
| 1.3.1 Información y relaciones | C | Tabla con encabezados; campos con etiqueta visible. |
| 1.4.13 Contenido en hover o foco | NA | El diseño no tiene contenido que aparezca al pasar el ratón. |

### 2.3 Etiquetas, mensajes y prevención de errores

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 3.3.1 Identificación de errores | C | Cada error va en texto junto al campo (M-01..M-07). |
| 3.3.2 Etiquetas o instrucciones | P | Etiquetas visibles con «*». Falta una nota «* obligatorio» al inicio del formulario (se agrega en BL-12). |
| 3.3.3 Sugerencia ante errores | C | Los mensajes dicen cómo corregir («Ejemplo: J18.9», «al menos 10 caracteres»). |
| 3.3.4 Prevención de errores (datos) | P | La firma tiene confirmación, pero no identifica la nota (H-02). El registro no muestra el paciente (H-01). |
| 3.3.7 Entrada redundante | C | La corrección copia los datos de la versión anterior. |
| 4.1.3 Mensajes de estado | P | Se definen `aria-live` y `role="alert"` (I-09). No se define cuánto dura el aviso de éxito (H-08) ni se anuncia «Guardando…» (H-14). |
| 2.2.1 Tiempo ajustable | **N** | La sesión puede expirar mientras el médico escribe, sin aviso ni forma de extenderla (H-10). |

## 3. Checklist propio de Notas SOAP

Derivado de las reglas R-01..R-08, RNF-03, RNF-06 y de las reglas D-1..D-7 de la semana 8.

| # | Comprobación | Resultado | Evidencia / hallazgo |
|---|---|---|---|
| NS-01 | El hospital activo se ve en todas las pantallas (R-01) | C | Cabecera de W1–W5. |
| NS-02 | Las pantallas para escribir muestran a qué paciente, expediente y episodio va la nota | **N** | W2 y W4 no lo muestran (H-01). |
| NS-03 | Solo el autor ve «Firmar» y «Corregir»; enfermería, solo lectura (R-02) | C | Anotaciones de W1 y W3. |
| NS-04 | Las 4 secciones son obligatorias antes de enviar (R-04) | C | W2, M-01. |
| NS-05 | El diagnóstico CIE-10 se elige de forma segura (R-05) | P | Se valida el formato, pero se escribe a mano (H-06). |
| NS-06 | El mensaje de éxito deja claro que nota y diagnóstico quedaron juntos (R-06) | C | M-20 y M-22. |
| NS-07 | Una nota firmada no tiene «Editar»; se corrige con una versión nueva (R-07) | C | W3 y W4. |
| NS-08 | La versión reemplazada se distingue claramente de la vigente | P | Existe el aviso de W3 y la marca de W1, pero con poco contraste (H-03). |
| NS-09 | El estado «Borrador» o «Firmada» se lee de inmediato | P | Etiqueta con 2.71:1 (H-04). |
| NS-10 | No hay botón «Eliminar» en ninguna pantalla (R-08) | C | W1–W5. |
| NS-11 | La lista y la URL no muestran texto clínico (RNF-03, D-2, D-4) | C | W1 muestra el resumen; la URL lleva identificadores. |
| NS-12 | No se pierde lo escrito ante errores, cierres o fin de sesión (RNF-06) | P | Se conserva ante errores del servidor; no ante cierre de pestaña ni sesión expirada (H-10, H-11). |
| NS-13 | El 403 no revela por qué se negó y deja un siguiente paso | C | W5, M-11. |

## 4. Recorrido de teclado

### W2 · Nueva nota

| Paso | Tecla | Foco en | Observación |
|---|---|---|---|
| 1 | `Tab` | S · Subjetivo | Etiqueta visible. No hay contexto del paciente arriba del formulario (H-01). |
| 2–4 | `Tab` | O, A, P | Orden lógico. |
| 5–7 | `Tab` | Código CIE-10, Tipo, Descripción | El código se escribe a mano (H-06). |
| 8 | `Tab` | Cancelar | — |
| 9 | `Tab` + `Enter` | Guardar borrador | Si P está vacía, aparece M-01, pero el foco se queda en el botón (H-12). Mientras guarda, «Guardando…» no se anuncia (H-14). |

### W3 · Firmar (diálogo)

| Paso | Tecla | Foco en | Observación |
|---|---|---|---|
| 1 | `Enter` en «Firmar» | Se abre el diálogo; foco en «Cancelar» (I-05) | El diálogo no dice qué nota ni qué paciente (H-02). |
| 2 | `Tab` | Firmar nota | El foco solo se ve por un borde gris más grueso (H-13). |
| 3 | `Esc` | Vuelve a «Firmar» | Correcto. |

### W4 · Corregir

| Paso | Tecla | Foco en | Observación |
|---|---|---|---|
| 1 | `Tab` | Motivo | Contador «n / 10» visible. |
| 2 | `Tab` | O · Objetivo | S, A y P parecen de solo lectura: no queda claro si reciben foco (H-05). |
| 3 | `Tab` + `Enter` | Guardar corrección | No hay forma de ver qué cambió antes de guardar (H-05). |

### W5 · Acceso denegado

| Paso | Tecla | Foco en | Observación |
|---|---|---|---|
| 1 | — | (sin definir) | Al llegar, el foco y el título de la página no están especificados (H-07). |
| 2 | `Tab` | Copiar | Funciona si el foco empezó arriba. |
| 3 | `Tab` | Volver al expediente | — |

## 5. Resumen

| Bloque | Ítems | C | P | N | NA |
|---|---:|---:|---:|---:|---:|
| Heurísticas (10 × 5 pantallas) | 50 | 38 | 9 | 3 | 0 |
| WCAG 2.2 AA | 19 | 8 | 5 | 5 | 1 |
| Checklist de Notas SOAP | 13 | 8 | 4 | 1 | 0 |
| **Total** | **82** | **54** | **18** | **9** | **1** |
