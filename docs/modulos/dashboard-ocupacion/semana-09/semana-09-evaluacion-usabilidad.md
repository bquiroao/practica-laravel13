# Semana 9 — Evaluación de usabilidad y accesibilidad del flujo UX del Dashboard de ocupación hospitalaria

| | |
|---|---|
| **Tarea Odoo** | 2496 — Semana 9: Evaluación del diseño, usabilidad y accesibilidad |
| **Módulo** | Dashboard de ocupación hospitalaria |
| **Objeto evaluado** | User flow y wireframes W1–W5 de la semana 8 (commit `273a165`, PR #206) y los colores reales del layout compartido (`AppLayout.vue`) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Método

- **Heurísticas:** las 10 de Nielsen, revisadas pantalla por pantalla.
- **WCAG 2.2 nivel AA:** los criterios del enunciado (teclado, foco, contraste, etiquetas, mensajes y prevención de errores) y el uso del color.
- **Evidencia:** cada hallazgo cita la línea exacta del wireframe ([lineas-wireframes-s8.txt](evidencia/lineas-wireframes-s8.txt)) o una medición. El contraste se mide con [contraste.php](evidencia/contraste.php) (fórmula de luminancia relativa de WCAG, con autoprueba 21:1 y 1:1); su salida está en [salida-contraste.txt](evidencia/salida-contraste.txt).
- **Gravedad** (escala de Nielsen, 0–4) e **impacto en la decisión clínica**: la prioridad sube si el problema puede llevar a decidir ingresos o traslados con datos viejos o impedir la tarea a quien usa teclado.
- **Limitación:** hubo un solo evaluador, y Nielsen recomienda de 3 a 5. Los problemas del layout compartido (`lang`, título y foco del router) se evaluaron en la semana 9 de Dashboard base y no se repiten aquí.

## 2. Checklist

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | Parcial | W1 muestra la hora del corte y su antigüedad, pero «hace 4 min» es fijo (H-01); el aviso de datos viejos solo está en W5 (H-05) |
| 2 | Relación con el mundo real | Cumple | Sala, cama, mantenimiento y porcentaje de ocupación son términos de enfermería |
| 3 | Control y libertad | Cumple | «Quitar filtro» en W2; «Cancelar» en W4 |
| 4 | Consistencia y estándares | Parcial | «Actualizar» y «Consolidar» parecen la misma acción y hacen cosas distintas (H-03) |
| 5 | Prevención de errores | Cumple | W4 confirma y explica que consolidar no cambia estados (R-09) |
| 6 | Reconocer antes que recordar | Cumple | La fórmula del porcentaje y la fuente se ven al pie de W1 |
| 7 | Flexibilidad y eficiencia | Parcial | Filtro por estado en W2; W3 dice dónde se corrige, pero sin un enlace (H-04) |
| 8 | Diseño estético y minimalista | Cumple | Solo cifras por sala, sin pacientes (R-07) |
| 9 | Reconocer y corregir errores | Cumple | W5 conserva el corte anterior con su hora y ofrece «Reintentar» |
| 10 | Ayuda y documentación | Cumple | «Ayuda» explica el cálculo, el corte y «no aplica» |
| 11 | Teclado (2.1.1, 3.2.2) | Parcial | Los radios del filtro aplican el cambio con cada flecha (H-02) |
| 12 | Foco (2.4.3, 2.4.7, 2.4.11) | No cumple | 0 menciones de «foco» en los 8 archivos de la semana 8 (H-06); «Consolidar» pierde el foco al deshabilitarse (H-08) |
| 13 | Contraste de texto (1.4.3) | Cumple | Texto 17.06:1 y enlace activo 5.17:1; la escala propuesta, entre 5.02:1 y 6.47:1 |
| 14 | Contraste de componentes (1.4.11) | Cumple | No hay campos de texto; anillo de foco propuesto a 5.17:1 |
| 15 | Uso del color (1.4.1) | Parcial | Hoy los porcentajes solo son números; la escala no está definida y un semáforo habitual no se lee: 1.92:1 y 2.28:1 (H-07) |
| 16 | Etiquetas e instrucciones (1.3.1, 3.3.2) | Cumple | Columnas con encabezado y el filtro con la etiqueta «Estado de cama» |
| 17 | Mensajes de error y de estado (3.3.1, 4.1.3) | No cumple | «1 de 3 salas» y el aviso de datos viejos no se anuncian (H-02, H-05) |
| 18 | Prevención de errores (3.3.4) | Cumple | Solo lectura: ninguna acción cambia datos; consolidar pide confirmación |

## 3. Hallazgos

| ID | Dónde | Problema | Criterio | Gravedad | Evidencia |
|---|---|---|---|---|---|
| H-01 | W1, línea 5 | «hace 4 min» es un texto fijo: si la pantalla queda abierta, sigue diciendo 4 minutos aunque pase una hora | Heurística 1 | 2 | `Corte: 10:35 (hace 4 min)` |
| H-02 | W2, líneas 5 y 10 | Con teclado, cada flecha sobre los radios cambia el filtro y la tabla, y «1 de 3 salas» no se anuncia | 3.2.2, 4.1.3 | 2 | Radios y texto de resultado sin región viva |
| H-03 | W1, línea 5 y W4, línea 7 | «Actualizar» vuelve a leer el último corte y «Consolidar» genera uno nuevo, pero los nombres no lo dejan claro | Heurísticas 2 y 4 | 2 | Dos botones en pantallas distintas |
| H-04 | W3, líneas 6 y 7 | «Dónde se corrige» es texto sin enlace: el administrador tiene que buscar la cama a mano en admisión o en el catálogo | Heurística 7 | 1 | «Admisión: registrar o cerrar la estancia» |
| H-05 | W5, línea 7 | El aviso de datos viejos solo está en W5: no se ve fijo en W1 ni se anuncia cuando el corte pasa de 15 minutos con la pantalla abierta | 4.1.3; heurística 1 | 3 | «Datos de las 10:20: pueden estar desactualizados» |
| H-06 | W2–W5 | No se define el foco: ni el inicial de W4, ni el retorno al cerrar, ni el anillo de foco | 2.4.3, 2.4.7 | 3 | 0 menciones de «foco» en la semana 8 |
| H-07 | W1, líneas 8 y 9 | El porcentaje no tiene escala: no se distingue «54.5 %» de «100.0 %», y si se agrega un semáforo de solo color no se lee | 1.4.1, 1.4.3 | 2 | `#eab308` 1.92:1 y `#22c55e` 2.28:1 sobre blanco |
| H-08 | W4, línea 9 | «Consolidar» se deshabilita mientras consolida: el foco se pierde y quien usa lector no sabe cuándo termina | 2.4.3, 4.1.3 | 2 | «el botón deshabilitado» |

## 4. Backlog priorizado

La prioridad sale del impacto en ocupación, salas, estados de cama, indicadores, actualización y procedencia de datos. P1 = puede llevar a decidir ingresos con datos viejos o impedir la tarea con teclado · P2 = dificulta la tarea · P3 = mejora menor.

| Orden | Hallazgo | Prioridad | Impacto en la ocupación | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|---|
| B-01 | H-05 | P1 | Decidir ingresos o traslados con una ocupación vieja | Franja fija en W1 cuando el corte pasa de 15 minutos, con su hora, su procedencia y «Actualizar», anunciada una vez con `role="status"` | Con un reloj fijo en el minuto 16, W1 muestra la franja y el lector la anuncia una sola vez |
| B-02 | H-06 | P1 | Sin teclado no se consolida ni se filtra | Foco inicial en el título de W4, Tab sin salir del diálogo, Esc lo cierra y el foco vuelve a «Consolidar ahora»; anillo de foco de 2 px en `#2563eb` | Recorrido solo con teclado de W1 a W5; anillo ≥ 3:1 (medido: 5.17:1) |
| B-03 | H-01 | P2 | Procedencia de datos: la antigüedad del corte | Hora absoluta siempre visible («Corte de las 10:35») y antigüedad recalculada cada minuto | Con un reloj fijo, la antigüedad pasa de «hace 4 min» a «hace 5 min» sin recargar |
| B-04 | H-07 | P2 | Indicadores de ocupación por sala | Texto y color: «baja» `#15803d` (menos de 70 %), «media» `#b45309` (70–89 %) y «alta» `#b91c1c` (90 % o más) | `contraste.php`: todos ≥ 4.5:1; en escala de grises cada nivel se lee por su texto |
| B-05 | H-02 | P2 | Filtro por estado de cama | Las flechas mueven la selección y la tabla se filtra 300 ms después de la última, con región `aria-live="polite"` para «1 de 3 salas» | Tres flechas seguidas producen un solo filtrado y un solo anuncio |
| B-06 | H-03 | P2 | Actualización de datos | «Ver el último corte» y «Generar un corte ahora», cada uno con una ayuda corta | Ningún botón dice «Actualizar»; cada texto describe su efecto |
| B-07 | H-08 | P2 | Actualización de datos | «Consolidar» con `aria-disabled` mientras consolida, y al terminar el foco va al mensaje de éxito o de error | El foco nunca termina en `body`; el lector anuncia «Corte de las 10:40 guardado» |
| B-08 | H-04 | P3 | Salas y estados de cama inconsistentes | «Dónde se corrige» como enlace a la cama en admisión o en el catálogo | Cada fila de W3 tiene un enlace que abre la cama indicada |

Las correcciones se aplican al prototipo navegable de la semana 11; el criterio verificable de cada una es su prueba de aceptación.

**Bibliografía:** Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design*. Nielsen Norman Group · W3C (2023). *Web Content Accessibility Guidelines (WCAG) 2.2* · W3C (2024). *ARIA Authoring Practices Guide: Radio Group Pattern*.
