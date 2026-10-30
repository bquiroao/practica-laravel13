# Checklist de evaluación heurística y WCAG 2.2 AA — módulo QA (wireframes W1–W5 y flujos de la semana 8)

**Estudiante:** Boris Alexander Quiroa Orellana · **Objeto evaluado:** [wireframes](../semana-08/wireframes/semana-08-wireframes-qa-regresion.pdf), [flujos](../semana-08/semana-08-ux-qa-regresion.md#4-user-flows-por-rol) y catálogo de mensajes de la semana 8

**Leyenda:** ✅ cumple · ⚠️ cumple en parte · ❌ no cumple. Cada ⚠️ y ❌ remite a un hallazgo H-xx.

## 1. Heurísticas de Nielsen

| # | Heurística | Estado | Evidencia en el diseño |
|---|---|---|---|
| N-1 | Visibilidad del estado del sistema | ⚠️ | Estados de carga, PENDIENTE y decisión anticipada (`bloquea_go`). Pero la bandeja que reenvía sola no avisa del resultado → **H-07** |
| N-2 | Coincidencia con el mundo real | ⚠️ | Mensajes en lenguaje natural (M-01..M-23), pero se ven códigos como NO_GO o NO APLICA, y un «· 2» sin unidad → **H-10, H-12** |
| N-3 | Control y libertad | ✅ | [Volver] y [Cancelar] en cada confirmación; la bandeja se puede reenviar a pedido |
| N-4 | Consistencia y estándares | ⚠️ | La anotación de W1 dice «2 causas» y la pantalla dice «· 2»; el principio «nada se pierde» choca con «se perderán 2 resultados» → **H-08, H-10** |
| N-5 | Prevención de errores | ⚠️ | Confirmación con resumen y críticos bloqueados. Pero el error del motivo aparece antes de escribir (ruido que acostumbra a ignorar errores) → **H-11** |
| N-6 | Reconocer antes que recordar | ✅ | La versión y el build se ven en W2; la matriz propone los casos; la sugerencia de `verifica_defecto` (M-07) |
| N-7 | Flexibilidad y eficiencia | ✅ | [Siguiente caso →] tras registrar; reenvío automático; filtros por severidad (flujo del Desarrollador) |
| N-8 | Diseño estético y minimalista | ✅ | Una acción principal por pantalla; las acciones de otros roles no se muestran |
| N-9 | Ayudar a reconocer, diagnosticar y recuperarse de errores | ✅ | 422 con `detalle` y foco al campo; ALCANCE_INCOMPLETO con [Agregar los que faltan]; 5xx con código de soporte |
| N-10 | Ayuda y documentación | ⚠️ | Las «?» explican la matriz y la severidad. Pero el motivo de un botón deshabilitado solo aparece al pasar el cursor → **H-01** |

## 2. WCAG 2.2 nivel AA (los criterios que pide la tarea)

| Tema | Criterio | Estado | Evidencia |
|---|---|---|---|
| **Teclado** | 2.1.1 Teclado | ⚠️ | Todo tiene acción de teclado, salvo el motivo de los botones deshabilitados (solo cursor) → **H-01** |
| | 2.1.2 Sin trampa de teclado | ⚠️ | El diálogo de cierre (W5) no define cómo se sale (Esc) ni a dónde vuelve el foco → **H-09** |
| | 2.4.1 Evitar bloques | ✅ | La cabecera es corta; se define un «Saltar al contenido» |
| **Foco** | 2.4.3 Orden del foco | ⚠️ | El orden sigue la lectura, pero el foco inicial del diálogo irreversible no está definido → **H-09** |
| | 2.4.7 Foco visible | ✅ | El estilo de foco se define en la paleta (contorno de 2 px #3D4F6B) |
| | 2.4.11 Foco no oculto | ✅ | Sin barras fijas que tapen contenido |
| | 3.2.1 Al recibir el foco | ✅ | Nada cambia de contexto al enfocar |
| | 3.2.2 Al introducir datos | ✅ | Elegir FALLA muestra el formulario del defecto en la misma página, sin navegar |
| **Contraste** | 1.4.1 Uso del color | ✅ | Estados con palabra + icono: ✓ GO, ⛔ NO_GO, ●/○, 🔒 |
| | 1.4.3 Contraste del texto | ❌ | 10 pares de texto fallan: etiquetas y títulos de estado (2,85–3,26:1), «✓ GO» (4,25:1), chips (3,99:1) → **H-02, H-04** ([contraste-wcag.txt](evidencia/contraste-wcag.txt)) |
| | 1.4.11 Contraste de no-texto | ❌ | Bordes de campos y de las opciones del resultado: 2,64:1 → **H-03** |
| | 2.5.8 Tamaño del objetivo | ✅ | Botones de 0,34 in (≈ 33 px) de alto; las «?», de 0,26 in (≈ 25 px) ≥ 24 px |
| **Etiquetas** | 1.1.1 Contenido no textual | ⚠️ | El 🔒 de los críticos no tiene alternativa de texto → **H-06** |
| | 1.3.1 Información y relaciones | ⚠️ | Las cuatro opciones del resultado no se agrupan como un único control → **H-05** |
| | 3.3.2 Etiquetas o instrucciones | ✅ | Todo campo tiene etiqueta visible; «Evidencia (obligatoria para FALLA y BLOQUEADO)» |
| | 4.1.2 Nombre, función, valor | ❌ | El resultado elegido se marca con «●» dibujado, sin estado expuesto; el crítico deshabilitado no dice por qué → **H-05, H-06** |
| **Mensajes** | 3.3.1 Identificación de errores | ⚠️ | Errores con texto junto al campo, pero mostrados antes de la interacción → **H-11** |
| | 3.3.3 Sugerencias ante errores | ✅ | Cada mensaje dice qué hacer (M-03, M-09, M-10, M-14, M-15) |
| | 4.1.3 Mensajes de estado | ❌ | El reenvío automático de la bandeja y el «adjunto ✓» no se anuncian → **H-07** |
| **Prevención de errores** | 3.3.4 Prevención (legal, financiera, datos) | ⚠️ | Hay confirmación con consecuencia para registrar y cerrar, pero el foco inicial del diálogo cae en lo destructivo → **H-09** |
| | 3.3.7 Entrada redundante | ✅ | El 401 conserva lo escrito; el 422 no borra los archivos adjuntos |
| | 3.3.8 Autenticación accesible | ✅ | La identidad la da el HIS (fuera del módulo), sin pruebas cognitivas |

## 3. Criterios propios del módulo

| # | Criterio | Estado | Evidencia |
|---|---|---|---|
| P-1 | Un resultado no se puede editar desde ninguna pantalla | ✅ | No hay acción; lo advierte la confirmación (W2-7) |
| P-2 | El Desarrollador nunca ve «cerrar defecto» | ✅ | W4-6 y su flujo |
| P-3 | La evidencia clínica no se ve sin permiso, ni en la URL | ✅ | W4-2; URLs solo con ids |
| P-4 | Un resultado sin confirmar no se pierde **ni se filtra** | ❌ | La bandeja guarda evidencia clínica en el equipo; cerrar sesión «pierde» y cerrar el navegador **deja** los datos → **H-08** |
| P-5 | La decisión GO / NO-GO se entiende antes de cerrar | ✅ | W5-1, `bloquea_go` |
| P-6 | Cada respuesta del contrato tiene su mensaje | ✅ | M-01..M-23 cubren los estados de la API 1.1.0 |

**Resultado:** 38 ítems (10 heurísticas, 22 criterios WCAG y 6 propios). **21 ✅, 12 ⚠️ y 5 ❌.**

## 4. Recorrido de teclado

Se simuló Tab, Shift+Tab, Enter, Espacio, Esc y las flechas en el orden que definen las anotaciones.

| Pantalla | Recorrido | Problema |
|---|---|---|
| W1 | Planificar → filas (Enter abre) → funcionalidades (Supr quita) → casos opcionales (Espacio) → Build → Planificar e iniciar | Los críticos deshabilitados se saltan: el usuario de teclado no sabe que están ni por qué (**H-06**) |
| W2 | Pasos (Espacio marca ✓/✕) → Resultado → Adjuntar → Clasificación → Defecto → Registrar… | El resultado son 4 paradas de Tab sin flechas ni estado anunciado (**H-05**) |
| W3 | El foco va al mensaje (éxito/error) → [Siguiente caso] o al campo con error → bandeja → Reenviar ahora | El reenvío automático cambia la bandeja sin aviso (**H-07**) |
| W4 | Severidad → Prioridad → Motivo → Clasificar | «Clasificar» deshabilitado no recibe el foco; su motivo no se puede leer (**H-01**) |
| W5 | Tabla (cada defecto) → Cerrar campaña… → diálogo → ¿? | Sin foco inicial ni Esc definidos en el diálogo (**H-09**) |
