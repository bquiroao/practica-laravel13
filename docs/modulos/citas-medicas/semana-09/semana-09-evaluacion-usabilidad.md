# Semana 9 — Evaluación de usabilidad y accesibilidad del flujo UX de Citas médicas y agenda

| | |
|---|---|
| **Tarea Odoo** | 2466 — Semana 9: Evaluación del diseño, usabilidad y accesibilidad |
| **Módulo** | Citas médicas y agenda |
| **Objeto evaluado** | User flow y wireframes W1–W5 de la semana 8 (commit `7bef826`, PR #203) y los colores reales del frontend compartido (`AppLayout.vue`, `LoginPage.vue`, `HomePage.vue`) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Método

- **Heurísticas:** las 10 de Nielsen, revisadas pantalla por pantalla.
- **WCAG 2.2 nivel AA:** los criterios del enunciado (teclado, foco, contraste, etiquetas, mensajes y prevención de errores) y el uso del color.
- **Evidencia:** cada hallazgo cita la línea exacta del wireframe ([lineas-wireframes-s8.txt](evidencia/lineas-wireframes-s8.txt)) o una medición. El contraste se mide con [contraste.php](evidencia/contraste.php) (fórmula de luminancia relativa de WCAG, con autoprueba 21:1 y 1:1); su salida está en [salida-contraste.txt](evidencia/salida-contraste.txt).
- **Gravedad** (escala de Nielsen, 0–4) e **impacto en la agenda**: la prioridad sube si el problema puede cancelar o confirmar la cita equivocada, ocultar la disponibilidad o impedir la tarea a quien usa teclado.
- **Limitación:** hubo un solo evaluador, y Nielsen recomienda de 3 a 5. Los wireframes son de baja fidelidad, así que el contraste se mide sobre los colores reales del layout donde vivirán estas pantallas.

## 2. Checklist

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | Parcial | W1 muestra carga, vacío y el estado de cada cita; la búsqueda de horarios y el éxito de W3 no se anuncian (H-01) |
| 2 | Relación con el mundo real | Cumple | Agenda, horario, especialidad y motivo son términos del personal |
| 3 | Control y libertad | Cumple | «Volver y editar» en W3 conserva lo llenado; «No cancelar» en W4 |
| 4 | Consistencia y estándares | No cumple | «Cancelar» significa salir en W2 y W5, pero cancelar la cita en W1 y W4 (H-02) |
| 5 | Prevención de errores | Parcial | W3 resume antes de guardar y vuelve a validar los cruces; los botones de fila no dicen de qué cita son (H-03) |
| 6 | Reconocer antes que recordar | Parcial | Solo se ofrecen horarios libres; solo «Motivo» explica que es obligatorio (H-07) |
| 7 | Flexibilidad y eficiencia | Cumple | Filtros por día y médico; búsqueda de paciente por código o nombre |
| 8 | Diseño estético y minimalista | Cumple | Solo las acciones válidas para cada estado (R-07) |
| 9 | Reconocer y corregir errores | Parcial | W5 propone los siguientes horarios libres; «unos segundos» no dice cuándo reintentar (H-08) |
| 10 | Ayuda y documentación | Cumple | «[?] Ayuda» y textos junto a cada campo |
| 11 | Teclado (2.1.1, 2.1.2) | No cumple | No se define cómo se usa con teclado el buscador de paciente (H-04) ni cómo se cierran W3 y W4 (H-05) |
| 12 | Foco (2.4.3, 2.4.7, 2.4.11) | No cumple | 0 menciones de «foco» en los 8 archivos de la semana 8 (H-05) |
| 13 | Contraste de texto (1.4.3) | Cumple | Texto 17.06:1, error 6.47:1, éxito de W3 `#008000` 5.14:1, estados propuestos 5.02–7.58:1 |
| 14 | Contraste de componentes (1.4.11) | No cumple | Borde de campo `#cbd5f5` a 1.46:1, cuando el mínimo es 3:1 (H-06) |
| 15 | Uso del color (1.4.1) | Cumple | Los estados se escriben («Programada», «Confirmada», «Cancelada») y el éxito lleva «✓» y texto |
| 16 | Etiquetas e instrucciones (1.3.1, 2.4.6, 3.3.2, 4.1.2) | No cumple | Botones de fila sin la cita en su nombre (H-03); asterisco sin leyenda (H-07) |
| 17 | Mensajes de error y de estado (3.3.1, 3.3.3, 4.1.3) | Parcial | Los errores sugieren qué hacer (W5), pero los mensajes de estado no se anuncian (H-01) |
| 18 | Prevención de errores (3.3.4) | Cumple | Revisión en W3 antes de agendar; en W4, «No cancelar» es la opción por defecto y el motivo es obligatorio |

## 3. Hallazgos

| ID | Dónde | Problema | Criterio | Gravedad | Evidencia |
|---|---|---|---|---|---|
| H-01 | W2 y W3 | «Buscando horarios» y «Cita programada» cambian la pantalla sin anunciarse a quien usa lector de pantalla | 4.1.3; heurística 1 | 2 | Anotación de W2 (línea 31 del documento) y línea 12 de W3 |
| H-02 | W1, W2, W4 y W5 | «Cancelar» sale del formulario en W2 y W5, pero cancela la cita en W1 y W4: es fácil confundirlos | 3.2.4; heurística 4 | 3 | Línea 11 de W2 frente a la línea 9 de W4 |
| H-03 | W1, líneas 8 y 9 | Cada fila repite «[Confirmar] [Cancelar]»; el lector anuncia «Cancelar, botón» sin decir de qué cita | 2.4.6, 4.1.2; heurística 5 | 3 | Mismo nombre de botón en todas las filas |
| H-04 | W2, línea 5 | El buscador de paciente sugiere resultados, pero no se define cómo se recorren con flechas ni cómo se anuncian | 2.1.1, 4.1.2 | 2 | «Busca por código o nombre (mín. 3 letras)» |
| H-05 | W2–W5 | No se define el foco: ni el inicial de W3, W4 y W5, ni el retorno a la fila de la agenda al cerrar, ni el orden de tabulación | 2.1.2, 2.4.3, 2.4.7 | 4 | 0 menciones de «foco» en la semana 8 |
| H-06 | Campos de W2 y W4 (estilo compartido) | Si los formularios reutilizan el estilo del login, el borde del campo no se distingue del fondo | 1.4.11 | 2 | `#cbd5f5` sobre `#ffffff`: 1.46:1 |
| H-07 | W2 y W4, campos con «*» | Solo «Motivo» explica que es obligatorio; ninguna pantalla explica el asterisco | 3.3.2; heurística 6 | 1 | Líneas 5 y 10 de W2 |
| H-08 | W5, línea 10 | «Intenta de nuevo en unos segundos» no dice cuántos ni avisa cuando ya se puede | Heurística 9 | 1 | Texto de la línea 10 |

## 4. Backlog priorizado

La prioridad sale del impacto en citas, agenda médica, disponibilidad, confirmación, cancelación y concurrencia. P1 = puede confirmar o cancelar la cita equivocada o impedir la tarea con teclado · P2 = dificulta la tarea · P3 = mejora menor.

| Orden | Hallazgo | Prioridad | Impacto en la agenda | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|---|
| B-01 | H-05 | P1 | Sin teclado no se puede agendar, confirmar ni cancelar | Al abrir W3, W4 o W5, el foco va al título; Tab no sale del diálogo; Esc lo cierra y el foco vuelve a la fila de la agenda; anillo de foco de 2 px en `#2563eb` | Recorrido solo con teclado de W1 a W5 sin ratón; anillo de foco ≥ 3:1 (medido: 5.17:1) |
| B-02 | H-02 | P1 | Una cancelación por confusión libera un horario que el paciente sí usaría | Para salir de un formulario, «Volver»; «Cancelar la cita» solo para la acción destructiva, con estilo de peligro | Ningún botón de W2, W3 ni W5 dice «Cancelar»; en W1 y W4, «Cancelar» siempre va seguido de «la cita» |
| B-03 | H-03 | P1 | Confirmar o cancelar la cita de otro paciente | `aria-label` con la cita: «Cancelar la cita de las 08:00 de P-0012» | El lector anuncia la hora y el paciente en cada botón de fila |
| B-04 | H-01 | P2 | No se sabe si la disponibilidad terminó de cargar o si la cita quedó confirmada | Región `aria-live="polite"` con «Buscando horarios…», «2 horarios libres» y «Cita programada para el 28/10 a las 08:30» | El lector anuncia cada mensaje sin mover el foco |
| B-05 | H-04 | P2 | Elegir al paciente equivocado al agendar | Buscador con el patrón *combobox* de WAI-ARIA: flechas para recorrer, Enter para elegir y «3 resultados» anunciado | Con teclado se elige un paciente de la lista sin ratón |
| B-06 | H-06 | P2 | Cuesta encontrar los campos del formulario de cita | Borde de campo `#64748b` | `contraste.php`: 4.76:1 ≥ 3:1 |
| B-07 | H-07 | P3 | Dudas sobre qué datos de la cita son obligatorios | Leyenda «* Obligatorio» al inicio de W2 y W4 | La leyenda aparece antes del primer campo |
| B-08 | H-08 | P3 | Reintentos a ciegas cuando la agenda no responde | «Reintenta en 10 s» con cuenta regresiva, y el botón se habilita al terminar | El botón está deshabilitado durante 10 s y luego se habilita solo |

La concurrencia ya está bien resuelta en la semana 8: W3 vuelve a validar los cruces y W5 ofrece los siguientes horarios libres sin perder lo llenado. Por eso no tiene una corrección propia. Las correcciones se aplican al prototipo navegable de la semana 11; el criterio verificable de cada una es su prueba de aceptación.

**Bibliografía:** Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design*. Nielsen Norman Group · W3C (2023). *Web Content Accessibility Guidelines (WCAG) 2.2* · W3C (2024). *ARIA Authoring Practices Guide: Combobox Pattern*.
