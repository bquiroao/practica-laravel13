# Checklist de evaluación — usabilidad y accesibilidad del flujo UX de Prescripciones

**Estudiante:** Boris Alexander Quiroa Orellana · **Objeto evaluado:** flujos y wireframes W1–W6 de la [semana 8](../semana-08/semana-08-ux-prescripciones.md) · **Fecha:** 27/10/2026

**Leyenda:**
- ✅ cumple · ⚠️ cumple en parte · ❌ no cumple · N/A no aplica.
- La columna «Hallazgo» enlaza al detalle en el [documento de evaluación](semana-09-evaluacion-ux-prescripciones.md#4-hallazgos).

**Método:**
- Cada ítem se revisó contra los PNG de los wireframes, sus 38 anotaciones y el catálogo de 22 mensajes.
- El contraste no se estimó a ojo: se **midió** con [`herramientas/contraste.py`](herramientas/contraste.py) sobre los colores del generador de los wireframes.

## 1. Heurísticas de usabilidad (Nielsen, 1994)

| # | Heurística | Resultado | Evidencia | Hallazgo |
|---|---|:-:|---|---|
| N1 | Visibilidad del estado del sistema | ⚠️ | Carga, éxito y errores definidos en W2 y W4. «Verificando…» se anuncia (M-04), pero el **resultado** de la verificación no. | H-12 |
| N2 | Correspondencia con el mundo real | ⚠️ | Mensajes en lenguaje clínico («por seguridad no se emitió»). Pero «Alergias no evaluadas» es jerga de la API, y la severidad usa «severa» y «grave» para lo mismo. | H-09 |
| N3 | Control y libertad del usuario | ✅ | «Volver», «Cancelar», «Cambiar medicamento» y la confirmación al salir sin emitir (W1). | — |
| N4 | Consistencia y estándares | ❌ | Severidad con dos nombres. Además, W4 dice que al volver se consultan «las prescripciones del paciente», cosa que la API no ofrece y que el documento de la semana 8 resuelve de otra forma. | H-09, H-10 |
| N5 | Prevención de errores | ⚠️ | Se valida antes de emitir, sin doble envío y con la confirmación «sin alergias» desmarcada. Pero el médico sin override escribe la justificación antes de enterarse de que no puede, y «✕» quita un ítem sin confirmar. | H-08, H-11 |
| N6 | Reconocer antes que recordar | ✅ | Las alergias activas están arriba (W1) y el diálogo repite el hallazgo completo (W3). | — |
| N7 | Flexibilidad y eficiencia | ✅ | «+ Agregar medicamento»; los selectores ofrecen solo medicamentos activos; «Reintentar» conserva todo. | — |
| N8 | Diseño estético y minimalista | ✅ | Un panel por resultado; el detalle del hallazgo está siempre visible, sin tooltips. | — |
| N9 | Reconocer, diagnosticar y recuperarse de errores | ⚠️ | Los errores dicen qué hacer y llevan código de soporte. Faltan dos cosas: «Emitir» deshabilitado no dice por qué a quien usa el teclado, y el motivo se marca en rojo mientras se escribe. | H-01, H-13 |
| N10 | Ayuda y documentación | ⚠️ | Hay ayuda contextual (M-01, quién verá la justificación). El «?» no está definido como control accesible. | H-15 |

## 2. WCAG 2.2, nivel AA

### 2.1 Teclado y foco

| Criterio | Resultado | Evidencia | Hallazgo |
|---|:-:|---|---|
| 2.1.1 Teclado | ❌ | La razón de «Emitir» deshabilitado (M-09) aparece solo al pasar el cursor, y el «?» no está definido para el teclado. | H-01, H-15 |
| 2.1.2 Sin trampas para el foco | ✅ | Los diálogos atrapan el foco a propósito y Esc los cierra (§7 de la semana 8). | — |
| 2.4.3 Orden del foco | ✅ | Sigue el orden visual; al abrir un diálogo, el foco va al campo de texto, no al botón que confirma. | — |
| 2.4.7 Foco visible | ⚠️ | No está dibujado en los wireframes. Se fija como token en B-04. | H-04 |
| 2.4.11 Foco no oculto | ✅ | No hay cabeceras fijas ni paneles que tapen el control con foco. | — |
| 3.2.1 Al recibir el foco | ✅ | Ningún control cambia el contexto al recibir el foco. | — |
| 3.2.2 Al introducir datos | ✅ | Cambiar un ítem invalida la verificación, pero no navega ni abre nada. | — |

### 2.2 Contraste y percepción

| Criterio | Resultado | Evidencia | Hallazgo |
|---|:-:|---|---|
| 1.4.1 Uso del color | ✅ | Cada estado lleva un ícono y un texto (✓ ⚠ ⛔ ? ✕); el campo con error tiene un borde y un mensaje. | — |
| 1.4.3 Contraste mínimo | ❌ | 11 pares de texto fallan: el gris `#8A8F96` (2,85–3,26:1) y el verde `#2E8B57` (3,75 y 4,25:1). | H-04, H-05 |
| 1.4.11 Contraste sin texto | ❌ | Los bordes de los campos y de la casilla «sin alergias conocidas» tienen 2,64:1. | H-03, H-06 |
| 1.4.13 Contenido al pasar el cursor o con el foco | ❌ | M-09 vive en un tooltip de un botón deshabilitado: no se puede descartar ni alcanzar. | H-01 |
| 2.5.8 Tamaño del objetivo (mínimo) | ❌ | La casilla de la confirmación mide 0,22 in (≈ 21 px CSS) y su texto no está definido como etiqueta clicable. | H-03 |

### 2.3 Etiquetas, mensajes y prevención de errores

| Criterio | Resultado | Evidencia | Hallazgo |
|---|:-:|---|---|
| 1.3.1 Información y relaciones | ❌ | Los campos de cada ítem están en una grilla cuyas columnas son texto suelto: «0» no se asocia con «Días» ni con el ítem 2. | H-07 |
| 3.3.1 Identificación de errores | ⚠️ | Los 422 marcan el campo con texto (W1, W6). Pero «Emitir» deshabilitado no identifica qué falta, y el motivo se marca como error antes de que el usuario termine. | H-01, H-13 |
| 3.3.2 Etiquetas o instrucciones | ⚠️ | Hay etiquetas visibles, pero no hay leyenda del «*» y los campos de los ítems no tienen nombre propio. | H-07, H-14 |
| 3.3.3 Sugerencia ante errores | ✅ | M-03 da las cifras y qué hacer; M-08 ofrece cambiar el medicamento; M-20 dice cuántos caracteres faltan. | — |
| 3.3.4 Prevención de errores (datos importantes) | ✅ | La verificación es obligatoria, «sin alergias» se confirma explícitamente, «Anular» pide un motivo y la idempotencia evita duplicados. | — |
| 3.3.7 Entrada redundante | ⚠️ | El 409 conserva la justificación (M-15). El 401, en cambio, promete conservar el borrador, y con la regla de «solo en memoria» se pierde. | H-02 |
| 3.3.8 Autenticación accesible | ⚠️ | El 401 lleva a iniciar sesión, pero la promesa de M-18 no se puede cumplir. | H-02 |
| 4.1.2 Nombre, función y valor | ❌ | «✕» (quitar el ítem) y «?» no tienen nombre ni función definidos. | H-08, H-15 |
| 4.1.3 Mensajes de estado | ⚠️ | M-04 y W4-1 se anuncian; el resultado de la verificación previa, no. | H-12 |

## 3. Específicos de Prescripciones

| # | Ítem | Resultado | Evidencia | Hallazgo |
|---|---|:-:|---|---|
| P-01 | Las alergias activas se ven antes de elegir el medicamento | ✅ | Cabecera del paciente en W1 | — |
| P-02 | «Sin registros» no se presenta como «sin alergias» | ✅ | M-10, casilla desmarcada y quién confirmó (W3) | — |
| P-03 | Un bloqueo no tiene ninguna forma de forzarse | ✅ | W2-3: solo «Cambiar medicamento» | — |
| P-04 | Quien no puede justificar una advertencia lo sabe antes de escribir | ❌ | M-07 aparece debajo de un «Emitir con justificación» activo | H-11 |
| P-05 | Fail-closed explicado en lenguaje clínico | ✅ | M-11 y M-14 | — |
| P-06 | El resultado incierto no se presenta como éxito ni como fracaso, y el reintento es seguro | ⚠️ | M-13 es correcto, pero la regla de W4 contradice la API y el código de soporte aparece truncado | H-10 |
| P-07 | La severidad y el tipo de hallazgo se entienden sin conocer el esquema | ⚠️ | En palabras, pero con dos nombres para «severe» | H-09 |
| P-08 | La inmutabilidad se ve: sin editar y con la anulada visible | ✅ | W5 (sin acciones) y W6 | — |
| P-09 | Nada clínico en la URL ni en el almacenamiento del navegador | ⚠️ | Se cumple, pero la promesa de M-18 empuja a guardar el borrador en el navegador | H-02 |

## 4. Recorrido de teclado

Se simuló Tab, Shift+Tab, Enter, Espacio, Esc y las flechas, en el orden que definen las anotaciones.

### W1 · Preparar
Ver expediente → `?` (**no definido: H-15**) → Medicamento 1 → Dosis → Unidad → Cada → Vía → Días → `✕` (**sin nombre: H-08**) → ítem 2… → + Agregar → Cancelar → Verificar alergias → **«Emitir» deshabilitado: Tab lo salta y su explicación es solo con el ratón (H-01)**.
- El foco va al primer campo inválido cuando hay un error. ✅
- Un lector de pantalla anuncia «0, campo de edición» sin decir «Días» ni el ítem (H-07). ❌

### W2 · Verificación previa
Verificar → (carga) → panel de resultado → Emitir / Cambiar medicamento / Reintentar.
- El resultado aparece, pero no se anuncia (H-12). ❌

### W3 · Justificar / confirmar
Justificación (foco inicial) → Volver → Emitir con justificación. Casilla → Registrar alergia → Emitir.
- Espacio marca la casilla. ✅ El objetivo es pequeño y no se puede marcar haciendo clic en el texto (H-03). ❌
- Esc cierra los dos diálogos y el foco vuelve a «Verificar». ✅

### W4 · Resultado
El foco va al mensaje de resultado (W4-1). ✅ Luego: Ver prescripción → Nueva prescripción, o Reintentar.

### W5 · Consultar
Volver → tabla de ítems → bloque de verificación. Solo lectura; no hay controles que no se alcancen. ✅

### W6 · Anular
Motivo (foco inicial) → Cancelar → **«Anular» deshabilitado: Tab lo salta (H-01)**.
- Esc cancela. ✅

## 5. Resumen

| Bloque | Ítems | ✅ | ⚠️ | ❌ | N/A |
|---|---:|---:|---:|---:|---:|
| Heurísticas | 10 | 4 | 5 | 1 | 0 |
| WCAG: teclado y foco | 7 | 5 | 1 | 1 | 0 |
| WCAG: contraste y percepción | 5 | 1 | 0 | 4 | 0 |
| WCAG: etiquetas y mensajes | 9 | 2 | 5 | 2 | 0 |
| Específicos de Prescripciones | 9 | 5 | 3 | 1 | 0 |
| **Total** | **40** | **17** | **14** | **9** | **0** |

Los 23 ítems ⚠️ o ❌ se explican con **15 hallazgos** (H-01..H-15). Todos tienen una corrección en el [backlog](semana-09-evaluacion-ux-prescripciones.md#6-backlog-priorizado).
