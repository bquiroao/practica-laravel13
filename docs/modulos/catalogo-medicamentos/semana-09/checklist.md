# Checklist de usabilidad y accesibilidad

**Objeto evaluado:** la entrada web del Micro-HIS (`micro-his/public`, semana 4) y los wireframes
W1–W6 (semana 8). **Fecha:** 2026-10-29. **Evaluador:** autor (evaluación heurística; no hubo
usuarios reales). **Herramientas:** Chromium con Playwright (teclado, foco, reflow), cálculo de
contraste con la fórmula WCAG (`evidencia/calcular-contraste.php`), revisión del HTML.

Resultado: **C** cumple · **P** parcial · **N** no cumple · **NA** no aplica.

## 1. Heurísticas de Nielsen

| # | Heurística | Res. | Evidencia | Hallazgo |
|---|---|---|---|---|
| 1 | Visibilidad del estado del sistema | C | Resultados con «N resultado(s) vigentes»; avisos con `role="status"` | — |
| 2 | Relación con el mundo real | P | Formas y vías se muestran como `SOLUCION_INYECTABLE`; «capsula» no encuentra «cápsula» | H-06, H-07 |
| 3 | Control y libertad del usuario | P | Hay «Volver a la búsqueda», pero eliminar no se puede cancelar | H-03 |
| 4 | Consistencia y estándares | C | Mismo formato de error en web y API; mismos códigos HTTP | — |
| 5 | Prevención de errores | N | Eliminar se ejecuta con un clic | H-03 |
| 6 | Reconocer antes que recordar | C | Listas cerradas en selectores; formulario de versión precargado | — |
| 7 | Flexibilidad y eficiencia | P | Búsqueda por código, nombre o principio; sin búsqueda sin acentos | H-06 |
| 8 | Diseño estético y minimalista | C | Una tarea por pantalla; sin datos ajenos al catálogo | — |
| 9 | Ayudar a reconocer y recuperarse de errores | P | Mensajes con el límite exacto, pero el foco no va al resumen y la baja del mismo día no ofrece salida | H-02, H-09 |
| 10 | Ayuda y documentación | P | Ayudas breves («vacío = hoy»); sin explicación de «versión» ni de «vigencia» | H-09 |

## 2. WCAG 2.1 nivel AA (criterios aplicables)

| Criterio | Res. | Evidencia | Hallazgo |
|---|---|---|---|
| 1.3.1 Información y relaciones | P | `label for` en todos los campos; obligatorios sin marca ni `aria-required` (0 de 7) | H-04 |
| 1.4.3 Contraste (texto) | C | Mínimo medido 6,67:1 (enlaces y botón) — `evidencia/contraste.txt` | — |
| 1.4.10 Reflow | C | Sin desplazamiento horizontal de página a 320, 375 y 430 px (Playwright) | H-11 (tabla) |
| 1.4.11 Contraste de no texto | N | Indicador de foco `#f2b705` sobre blanco = 1,82:1 (< 3:1) | H-01 |
| 2.1.1 Teclado | C | Todo se alcanza con Tab; orden: Buscar, Registrar, texto, forma, fecha | — |
| 2.4.1 Evitar bloques | N | No hay enlace «Saltar al contenido» | H-08 |
| 2.4.3 Orden del foco | P | Tras un 422 el foco queda en `BODY`, no en el resumen de errores | H-02 |
| 2.4.6 Encabezados y etiquetas | C | Un `h1` por página; etiquetas descriptivas | — |
| 2.4.7 Foco visible | P | Visible (3 px) pero con contraste insuficiente | H-01 |
| 3.1.1 Idioma de la página | C | `<html lang="es">` | — |
| 3.3.1 Identificación de errores | P | Error bajo cada campo con `aria-describedby`; un error no se informa (concentración 0 sin unidad) y el resumen no tiene enlaces | H-02, H-05 |
| 3.3.2 Etiquetas o instrucciones | P | Faltan marca de obligatorio y formato de la concentración | H-04 |
| 3.3.3 Sugerencia ante errores | C | «Use entre 3 y 150 caracteres (tiene 1)» | — |
| 3.3.4 Prevención de errores (datos) | N | Eliminar sin confirmación | H-03 |
| 4.1.2 Nombre, función, valor | C | Controles nativos con etiqueta | — |
| 4.1.3 Mensajes de estado | C | `role="status"` y `role="alert"` | — |
| 1.1.1 Contenido no textual | NA | No hay imágenes informativas | — |

## 3. Reglas del dominio vistas desde la UI

| Regla | Res. | Observación | Hallazgo |
|---|---|---|---|
| RN-01 vigencia con versiones | C | Historial claro con «Vigente hoy» | — |
| RN-02 no borrar lo usado | C | 409 con mensaje y acción alternativa en la misma página | (H-03 para lo no usado) |
| RN-03 búsqueda de vigentes | P | Se dice la fecha consultada; sin búsqueda sin acentos | H-06 |
| RN-04 duplicados | C | 409 con el código existente | — |
| RN-05 límites | P | Mensajes correctos salvo un caso no informado | H-05 |
| Trazabilidad para auditoría | P | El historial no muestra quién registró cada versión | H-10 |
