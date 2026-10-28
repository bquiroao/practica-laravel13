# Checklist de evaluación — usabilidad y accesibilidad del flujo UX de RBAC

**Estudiante:** Boris Alexander Quiroa Orellana · **Objeto evaluado:** diseño de la semana 8, que comprende:

- los user flows;
- los wireframes W1–W6;
- [semana-08-ux-rbac.md](../semana-08/semana-08-ux-rbac.md) (mensajes, reglas I-01..I-11, estados).

**Resultado:** **C** cumple · **P** cumple en parte · **N** no cumple · **NA** no aplica. Cada P o N tiene un hallazgo (H-xx) en [semana-09-evaluacion-ux-rbac.md](semana-09-evaluacion-ux-rbac.md).

**Cómo se evaluó cada fila:**

| Tipo de comprobación | Método |
|---|---|
| Contraste | Medición con [herramientas/contraste.py](herramientas/contraste.py) ([salida](evidencia/contraste-wcag.txt)) |
| Teclado y foco | Recorrido del orden de tabulación (§4) sobre los wireframes y las reglas I-01..I-11 |
| Resto | Inspección del wireframe y del texto de la especificación |

## 1. Heurísticas de usabilidad (Nielsen, 1994)

| # | Heurística | W1 | W2 | W3 | W4 | W5 | W6 | Evidencia / hallazgo |
|---|---|---|---|---|---|---|---|---|
| N1 | Visibilidad del estado del sistema | P | C | P | C | C | C | Estados vacío/carga/éxito/error en todas. W1: «—» en Acción sin explicar (H-14). W3: botón deshabilitado sin decir por qué (H-05). |
| N2 | Coincidencia con el mundo real | C | P | C | C | P | C | W2: fecha en AAAA-MM-DD; el resto del sistema usa dd/mm/aaaa (H-07). W5: referencia de 36 caracteres difícil de dictar (H-09). |
| N3 | Control y libertad del usuario | C | P | C | C | C | C | W2: cambiar de hospital descarta el formulario sin avisar (H-06). «Volver», «Cancelar» y `Esc` existen. |
| N4 | Consistencia y estándares | C | P | P | P | C | C | Formato de fecha (H-07). W3 muestra el error mientras se escribe, contra la regla I-03 (H-11). W4: pestaña activa sin indicador estándar (H-16). |
| N5 | Prevención de errores | C | **N** | P | C | C | C | W2: el usuario destino se escribe a mano y la confirmación solo repite el identificador (H-01). W3: confirmación fuerte, pero el modal deja operar la cabecera (H-08). |
| N6 | Reconocer antes que recordar | C | **N** | C | C | C | C | W2: hay que recordar el identificador exacto del usuario (H-01). El rol sí se elige de una lista. |
| N7 | Flexibilidad y eficiencia | C | C | C | C | P | P | Copiar la referencia con un clic. Si se comparte por teléfono, no hay alternativa corta (H-09). |
| N8 | Diseño estético y minimalista | C | C | C | C | C | C | Cada pantalla muestra solo lo necesario para su decisión. |
| N9 | Ayudar a reconocer y recuperarse de errores | C | C | P | C | C | P | Los mensajes dicen qué hacer. W6: M-31 no sugiere cambiar de hospital (H-10). |
| N10 | Ayuda y documentación | P | C | C | C | C | C | W1: la ayuda «?» no está especificada para teclado (H-13). |

## 2. WCAG 2.2, nivel AA

Se listan los criterios ligados a lo que pide la tarea: teclado, foco, contraste, etiquetas, mensajes y prevención de errores.

### 2.1 Teclado y foco

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 2.1.1 Teclado | P | Todos los controles son nativos (botón, campo, select, casilla). Excepciones: la ayuda «?» de W1 solo se describe al pasar el ratón (H-13), y «Revocar rol» deshabilitado no recibe foco (H-05). |
| 2.1.2 Sin trampas de teclado | C | `Esc` cierra el diálogo de W3 (I-05). |
| 2.4.3 Orden del foco | **N** | W3: el foco puede salir del diálogo hacia la cabecera, que sigue activa (H-08). W5: al llegar a la página no se define dónde queda el foco (H-04). |
| 2.4.7 Foco visible | C | W3 dibuja el anillo de foco (2 pt, 8.30:1, C-19). |
| 2.4.11 Foco no oculto | P | El diálogo de W3 cubre el contenido de fondo; si el foco se escapa (H-08), queda debajo del velo. |
| 2.4.2 Página con título | **N** | No se especifica el título del documento al navegar a W5 ni a otras rutas de la SPA (H-04). |
| 2.5.8 Tamaño del objetivo (24 × 24 px) | C | Botones de 0.34 in de alto (≈ 33 px). |

### 2.2 Contraste y percepción

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 1.4.3 Contraste de texto (4.5:1) | **N** | 6 pares fallan: etiquetas, encabezados de tabla, pistas, títulos de estado, «?» (2.93–3.26:1, H-02) y «Copiado ✓» (4.25:1, H-15). Pasan 9 pares: texto, cabecera, botones, errores, aviso 403, éxito. |
| 1.4.11 Contraste de no texto (3:1) | **N** | Bordes de campo y de casilla 2.64:1 (H-03); indicador de pestaña activa 1.11:1 (H-16). Borde de error y anillo de foco pasan. |
| 1.4.1 Uso del color | C | Estados con icono y texto (● Activo, ◌ Vencido, ⊘ Revocado). Los errores llevan texto, no solo borde rojo. |
| 1.4.13 Contenido en hover o foco | P | La ayuda «?» de W1 no define si se puede cerrar ni si se mantiene visible (H-13). |
| 1.3.1 Información y relaciones | P | La tabla de W1 tiene encabezados. La celda «—» no tiene equivalente textual (H-14). |

### 2.3 Etiquetas, mensajes y prevención de errores

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 2.4.6 Encabezados y etiquetas | C | Cada campo tiene etiqueta visible. Los diálogos tienen título específico («Revocar el rol Enfermería de ana»). |
| 3.3.2 Etiquetas o instrucciones | P | La fecha no anuncia su formato antes del error (H-07). Las condiciones para habilitar «Revocar rol» no se dicen antes (H-05). |
| 4.1.2 Nombre, función, valor | P | El botón «?» no tiene nombre accesible (H-13). El estado de la pestaña activa no está especificado (`aria-selected`, H-16). |
| 3.3.1 Identificación de errores | C | Mensaje junto al campo, en texto, con borde de error de 6.51:1. |
| 3.3.3 Sugerencias ante errores | C | Cada mensaje del catálogo M-01..M-31 dice cómo corregir. |
| 3.3.4 Prevención de errores (datos) | P | Asignar y revocar piden confirmación, pero la de W2 no identifica a la persona (H-01). |
| 3.3.7 Entrada redundante | P | Ante un 401 (W5) se vuelve a la ruta, pero no se dice si se conservan los datos del formulario. Ante un cambio de hospital se pierden (H-06). |
| 4.1.3 Mensajes de estado | P | I-06 define `aria-live` para éxito y error y `role="alert"` para el 403. Faltan dos cosas: el anuncio de la página W5 tras la navigación (H-04) y la persistencia del aviso de éxito con la referencia (H-12). |
| 2.2.1 Tiempo ajustable | P | La duración del aviso de éxito (M-20/M-21), que lleva la referencia y «Copiar», no está definida (H-12). |

## 3. Checklist específico de RBAC

Son reglas propias del módulo: el diseño debe cumplirlas además de ser usable.

| # | Comprobación | Resultado | Evidencia |
|---|---|---|---|
| R-UX-01 | El 403 no revela rol, permiso ni motivo (R-08). | C | W4, W5: mensaje genérico + referencia. |
| R-UX-02 | Toda denegación ofrece una referencia copiable y un siguiente paso. | C | W4 (M-10, M-11), W5. |
| R-UX-03 | Un usuario con lector de pantalla se entera de la denegación y encuentra la referencia. | **N** | H-04. |
| R-UX-04 | La UI es fail-closed: sin decisión del PDP no se muestran acciones. | C | W4: estados CARGA y M-13. |
| R-UX-05 | El hospital activo está siempre visible. | C | Cabecera de todas las pantallas. |
| R-UX-06 | Cambiar de hospital no envía datos de un hospital a otro ni los pierde sin avisar. | **N** | H-06. |
| R-UX-07 | Un cambio de permisos (asignar o revocar) se confirma identificando sin ambigüedad a la persona y el efecto. | P | El efecto sí se muestra: los permisos que gana o pierde. La persona solo aparece por su identificador (H-01). |
| R-UX-08 | La acción destructiva (revocar) no se puede ejecutar por accidente. | P | Motivo + casilla + foco en «Cancelar». Pero la cabecera queda operable (H-08). |
| R-UX-09 | La vigencia del rol (que decide PERMITIDO/DENEGADO) no se puede malinterpretar. | P | H-07. |
| R-UX-10 | El auditor puede llegar desde la referencia al evento y entender el motivo. | P | W6 + glosario. Si la referencia se transcribe mal (H-09) o es de otro hospital (H-10), el auditor no tiene pista. |
| R-UX-11 | Los datos de auditoría no incluyen datos clínicos. | C | W6: solo metadatos (D-4). |
| R-UX-12 | La caché de decisiones no sobrevive a un cambio de hospital ni al cierre de sesión. | C | D-5, I-10. |

## 4. Recorrido de teclado

Se recorre cada pantalla con `Tab`, `Shift+Tab`, `Enter`, `Espacio` y `Esc`, siguiendo el orden visual del wireframe y las reglas I-01..I-11. ✗ marca el paso donde el diseño no define o rompe el comportamiento.

### W2 · Asignar rol

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | `Tab` | Selector de hospital (cabecera) | ✓ |
| 2 | `Tab` | Menú de usuario | ✓ |
| 3 | `Tab` | Campo «Usuario *» | ✓ |
| 4 | — | Escribir «nuevo-01». No hay sugerencias: hay que saberse el identificador. | ✗ H-01 |
| 5 | `Tab` | «Rol *» (`select`; flechas para elegir) | ✓ |
| 6 | `Tab` | «Válido desde *». El formato no se anuncia hasta que hay error. | ✗ H-07 |
| 7 | `Tab` ×2 | «Válido hasta» → «Cancelar» | ✓ |
| 8 | `Tab` + `Enter` | «Revisar asignación» → la tarjeta de confirmación | ✓ (el foco debe ir al título de la tarjeta; está implícito en I-04) |
| 9 | `Enter` en «Asignar rol» | Aviso M-20 con referencia y «Copiar» | ✗ H-12: no se sabe si el aviso sigue cuando el usuario llega a «Copiar» |
| 10 | `Shift+Tab` hasta la cabecera y cambio de hospital | Se recarga la pantalla y se pierde lo escrito | ✗ H-06 |

### W3 · Revocar rol (diálogo)

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | `Enter` en «Revocar» (W1) | Abre el diálogo con el foco en «Cancelar» | ✓ (I-05) |
| 2 | `Shift+Tab` | Casilla → motivo | ✓ |
| 3 | — | Escribir «Traslado» (8 caracteres). El error rojo aparece mientras se escribe. | ✗ H-11 |
| 4 | `Tab` ×2 | Casilla → «Cancelar». **«Revocar rol» se salta** porque está deshabilitado: no se descubre ni se anuncia por qué. | ✗ H-05 |
| 5 | `Tab` desde el último control | Debería volver al primer control del diálogo. El diseño no lo define y la cabecera queda activa. | ✗ H-08 |
| 6 | `Esc` | Cierra; el foco vuelve a «Revocar» en W1 | ✓ |

### W4 → W5 · Denegación

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | `Enter` en «Registrar signos vitales» | El servidor responde 403 y aparece el aviso `role="alert"` | ✓ (I-06) |
| 2 | `Tab` | El siguiente control en el orden del documento. El aviso está **debajo** del botón, así que llega bien a «Copiar». | ✓ |
| 3 | Navegación a una ruta prohibida | `permisoGuard` redirige a W5. No se define título del documento, foco inicial ni anuncio. | ✗ H-04 |
| 4 | `Tab` en W5 | Campo de referencia (solo lectura) → «Copiar» → «Volver al inicio» → «¿Cómo solicito acceso?» | ✓ |
| 5 | `Enter` en «Copiar» | «Copiado ✓» se anuncia con `aria-live` (I-07), pero su contraste es 4.25:1 | ✗ H-15 |

### W6 · Auditoría

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | `Tab` | Campo «Referencia» | ✓ |
| 2 | `Enter` | Envía la búsqueda (el formulario tiene botón de envío) | ✓ |
| 3 | — | 200: tarjeta del evento; el foco debe ir a su título. | ✓ (I-11 lo define para W1; para W6 se debe escribir igual) |
| 4 | — | 404: M-31 junto al campo; no sugiere cambiar de hospital | ✗ H-10 |

## 5. Resumen

| Bloque | Ítems | C | P | N | NA |
|---|---:|---:|---:|---:|---:|
| Heurísticas (10 × 6 pantallas) | 60 | 44 | 14 | 2 | 0 |
| WCAG 2.2 AA | 21 | 7 | 10 | 4 | 0 |
| RBAC específico | 12 | 6 | 4 | 2 | 0 |
| **Total** | **93** | **57** | **28** | **8** | **0** |

Los conteos salen de las tablas anteriores.
