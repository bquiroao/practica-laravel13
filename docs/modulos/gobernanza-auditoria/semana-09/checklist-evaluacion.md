# Checklist de evaluación — usabilidad y accesibilidad del flujo UX de Auditoría

**Estudiante:** Boris Alexander Quiroa Orellana · **Objeto evaluado:** flujos y wireframes W1–W6 de la [semana 8](../semana-08/semana-08-ux-auditoria.md) · **Fecha:** 26/10/2026

**Leyenda:**
- ✅ cumple · ⚠️ cumple en parte · ❌ no cumple · N/A no aplica.
- La columna «Hallazgo» enlaza al detalle en el [documento de evaluación](semana-09-evaluacion-ux-auditoria.md#4-hallazgos).

**Método:**
- Cada ítem se revisó contra los PNG de los wireframes, las anotaciones de cada pantalla y el catálogo de mensajes.
- El contraste no se estimó a ojo: se **midió** con [`herramientas/contraste.py`](herramientas/contraste.py).

## 1. Heurísticas de usabilidad (Nielsen, 1994)

| # | Heurística | Resultado | Evidencia | Hallazgo |
|---|---|:-:|---|---|
| N1 | Visibilidad del estado del sistema | ⚠️ | Estados de carga, vacío y error definidos (W1, W2). El número de resultados y «Cargar más» no se anuncian. | H-12 |
| N2 | Correspondencia con el mundo real | ⚠️ | Mensajes en lenguaje claro. Pero las acciones se muestran como códigos técnicos y la hora va sin fecha. | H-10, H-11 |
| N3 | Control y libertad del usuario | ❌ | Marcar «Toda la red» abre un diálogo sin que el usuario lo pida; «Limpiar» borra el rango obligatorio. | H-01, H-13 |
| N4 | Consistencia y estándares | ⚠️ | Mismos mensajes en todas las pantallas. El formato de fecha mostrado (dd/mm/aaaa) no coincide con el que pide el mensaje del servidor (AAAA-MM-DD). | H-09 |
| N5 | Prevención de errores | ✅ | Rango precargado, confirmación de red y de verificación, sin doble envío. | — |
| N6 | Reconocer antes que recordar | ❌ | Las insignias citan «#1» y «#2», pero la lista no muestra ningún id. | H-03 |
| N7 | Flexibilidad y eficiencia | ⚠️ | Enter busca y la paginación es simple. Las filas no son controles alcanzables con teclado. | H-04 |
| N8 | Diseño estético y minimalista | ✅ | Columnas mínimas; el detalle técnico está colapsado. | — |
| N9 | Reconocer, diagnosticar y recuperarse de errores | ⚠️ | Los errores recuperables tienen acción y código de soporte. Faltan dos cosas: «Verificar» deshabilitado no explica por qué, y ALTERADA no dice a quién avisar ni da el informe. | H-07, H-08 |
| N10 | Ayuda y documentación | ⚠️ | Hay ayuda contextual, pero el «?» no está definido como control accesible. | H-15 |

## 2. WCAG 2.2, nivel AA

### 2.1 Teclado y foco

| Criterio | Resultado | Evidencia | Hallazgo |
|---|:-:|---|---|
| 2.1.1 Teclado | ❌ | Filas «clicables» sin control enfocable; el «?» no está definido para teclado. | H-04, H-15 |
| 2.1.2 Sin trampas de teclado | ✅ | Los paneles y diálogos se cierran con Esc y devuelven el foco (W3, W4). | — |
| 2.4.3 Orden del foco | ✅ | El orden visual coincide con el lógico ([§4](#4-recorrido-de-teclado)). | — |
| 2.4.7 Foco visible | ✅ | Anillo de 2 pt en el foco inicial (W4); contraste de 8,30:1 (C-22). | — |
| 2.4.11 Foco no oculto | ✅ | El panel de detalle no tapa el elemento enfocado de la lista: la lista queda atenuada detrás, sin foco. | — |
| 3.2.1 Al recibir el foco | ✅ | Ningún control cambia el contexto al recibir el foco. | — |
| 3.2.2 Al introducir datos | ❌ | Seleccionar el radio «Toda la red» (también con las flechas) abre un diálogo modal. | H-01 |

### 2.2 Contraste y percepción

| Criterio | Resultado | Evidencia | Hallazgo |
|---|:-:|---|---|
| 1.4.3 Contraste del texto | ❌ | 7 pares fallan, entre 2,85:1 y 3,26:1: etiquetas, encabezados, pistas, títulos de estado, contador y «?». | H-05 |
| 1.4.11 Contraste de no texto | ❌ | Bordes de campos y del radio: 2,64:1 (mínimo 3:1). | H-06 |
| 1.4.1 Uso del color | ✅ | «denegado» se escribe además de colorearse; las insignias tienen texto; ALTERADA lleva «✕» y texto. | — |
| 1.3.1 Información y relaciones | ⚠️ | La tabla tiene encabezados, pero la relación corrección ↔ original solo se expresa como texto «#n», sin vínculo. | H-03 |
| 1.4.4 / 1.4.10 Cambio de tamaño y reajuste | N/A | Se evalúa en la propuesta móvil (semana 10). | — |

### 2.3 Etiquetas, mensajes y prevención de errores

| Criterio | Resultado | Evidencia | Hallazgo |
|---|:-:|---|---|
| 3.3.1 Identificación de errores | ⚠️ | Los 422 marcan el campo con texto. «Verificar» deshabilitado, en cambio, no identifica el error para quien no ve el campo. | H-07 |
| 3.3.2 Etiquetas o instrucciones | ⚠️ | Hay etiquetas visibles en todos los campos. Faltan la leyenda del «*» y el formato de fecha. | H-09, H-14 |
| 3.3.3 Sugerencia ante errores | ✅ | M-02, M-03 y M-20 dicen qué hacer. | — |
| 3.3.4 Prevención de errores (datos importantes) | ✅ | Confirmación antes de consultar la red y de verificar; nada modifica datos. | — |
| 3.3.7 Entrada redundante | ✅ | Los reintentos conservan los filtros; «Cambiar filtros» los trae puestos. | — |
| 3.3.8 Autenticación accesible | ⚠️ | El 401 lleva al inicio de sesión, pero la promesa de conservar los filtros no se puede cumplir. | H-02 |
| 4.1.2 Nombre, función y valor | ❌ | Filas y «?» sin nombre ni función definidos. | H-04, H-15 |
| 4.1.3 Mensajes de estado | ⚠️ | «Buscando…» se anuncia; el número de resultados y la carga de más filas, no. | H-12 |

## 3. Checklist específico de Auditoría

| # | Ítem | Resultado | Evidencia |
|---|---|:-:|---|
| A-01 | No existe ninguna acción de editar, borrar o «reparar» (R-02) | ✅ | W2 sin columna de acciones; W5 ALTERADA sin «reparar» |
| A-02 | El original corregido sigue visible en su fecha (semana 6) | ✅ | W2 «Corregido por #2» |
| A-03 | La UI avisa que leer deja rastro (R-08) | ✅ | M-06 permanente en W1 |
| A-04 | Ninguna lectura se dispara sin acción del usuario | ✅ | Sin carga automática; cerrar el detalle no consulta |
| A-05 | El rango es obligatorio y claro (R-06) | ⚠️ | Precargado y explicado, pero con el formato inconsistente (H-09) y borrable con «Limpiar» (H-13) |
| A-06 | El 404 no revela otros tenants (R-05) | ✅ | M-12 único |
| A-07 | El alcance de red está siempre a la vista y es deliberado | ⚠️ | La cabecera lo muestra; la confirmación aparece en el momento equivocado (H-01) |
| A-08 | Ningún dato de la bitácora persiste en el navegador | ⚠️ | Regla definida en W6, pero contradice la promesa de M-09 (H-02) |
| A-09 | Los resultados de verificación permiten actuar | ⚠️ | ÍNTEGRA es citable; ALTERADA y TRUNCADA no dicen a quién avisar ni dan el informe (H-08) |
| A-10 | Las relaciones corrección ↔ original se pueden seguir | ❌ | Los ids no se ven en la lista (H-03) |

## 4. Recorrido de teclado

Se recorrió cada pantalla con Tab, Shift+Tab, Enter, Esc y las flechas, siguiendo las anotaciones de interacción.

### W1 · Consultar
`?` (**no definido: H-15**) → Desde → Hasta → Usuario → Módulo → Acción → Resultado → Limpiar → Buscar.
- Enter en cualquier campo busca. ✅
- El foco no se pierde al mostrar un error 422: va al campo marcado. ✅

### W2 · Resultados
Cambiar filtros → tabla → **las filas no son alcanzables (H-04)** → Cargar 50 más.
- Tras «Cargar más», el foco va a la primera fila nueva (definido), pero esa fila no es un control. ❌

### W3 · Detalle
Cerrar → Ver #2 → Copiar → Detalle técnico.
- Esc cierra y devuelve el foco a la fila que lo abrió, que hoy no puede recibir foco (depende de H-04). ⚠️

### W4 · Consulta de red
Radio «Un hospital» → **flecha abajo selecciona «Toda la red» y abre el diálogo (H-01)** → Cancelar (foco inicial) → Consultar la red.
- Esc cancela. ✅

### W5 · Verificar
Cadena → Ancla: secuencia → Ancla: sello → **«Verificar» deshabilitado: el Tab lo salta (H-07)**.

### W6 · Errores
Cada estado tiene un único botón de acción («Iniciar sesión», «Reintentar» o «Buscar de nuevo»), alcanzable con Tab. ✅

## 5. Resumen

| Bloque | Ítems | ✅ | ⚠️ | ❌ | N/A |
|---|---:|---:|---:|---:|---:|
| Heurísticas | 10 | 2 | 6 | 2 | 0 |
| WCAG: teclado y foco | 7 | 5 | 0 | 2 | 0 |
| WCAG: contraste y percepción | 5 | 1 | 1 | 2 | 1 |
| WCAG: etiquetas y mensajes | 8 | 3 | 4 | 1 | 0 |
| Específico de Auditoría | 10 | 5 | 4 | 1 | 0 |
| **Total** | **40** | **16** | **15** | **8** | **1** |

Los 23 ítems ⚠️ o ❌ se explican con **15 hallazgos** (H-01..H-15). Todos tienen una corrección en el [backlog](semana-09-evaluacion-ux-auditoria.md#6-backlog-priorizado).
