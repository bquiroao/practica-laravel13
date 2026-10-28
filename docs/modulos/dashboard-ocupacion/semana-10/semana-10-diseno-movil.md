# Semana 10 — Diseño móvil de «consolidación y consulta de ocupación por sala y estado de cama»

| | |
|---|---|
| **Tarea Odoo** | 2497 — Semana 10: Diseño para movilidad |
| **Módulo** | Dashboard de ocupación hospitalaria |
| **Base** | Flujo y wireframes de la semana 8 (PR #206) y backlog de la semana 9 (PR #223); reglas R-01 a R-09 de la [Semana 1](../semana-01/semana-01-uml-ocupacion.md); cálculo de `OcupacionSala::calcular` del Micro-HIS |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Jerarquía en móvil (320–430 px)

La jefatura de enfermería mira la ocupación en el teléfono durante la ronda, para decidir dónde cabe un ingreso. Por eso, la ocupación, las salas, los estados de cama, los indicadores, la actualización y la procedencia de datos se ordenan así:

1. **Procedencia y actualización, arriba:** la hora del corte, su antigüedad y la fuente («catálogo de camas y admisión»). Sin la hora, ninguna cifra sirve para decidir (R-05).
2. **Salas:** una tarjeta por sala, ordenadas por camas libres (las que más tienen, primero), con las disponibles en negrita y el indicador de ocupación en texto y número («54.5 % baja»).
3. **Estados de cama:** el desglose (disponibles, ocupadas, mantenimiento, inactivas) pasa al detalle de cada sala (M3), en lugar de seis columnas.
4. **Acciones:** filtrar es lo más común y está en la primera pantalla; generar un corte, solo para el administrador, va en una pantalla aparte (M4).

Para reducir la carga cognitiva se muestra un solo dato principal por sala, la fórmula del porcentaje solo aparece en el detalle, y «no aplica» se explica con «Sin camas operativas» (R-02).

## 2. Pantallas

| M | Pantalla | Navegación | Tabla o formulario | Confirmación y error |
|---|---|---|---|---|
| [M1](diagramas/semana-10-m1-ocupacion.png) | Ocupación por sala | Raíz del módulo; «Filtrar» abre M2 y «Ver >» abre M3 | La tabla de la semana 8 pasa a una tarjeta por sala | La antigüedad del corte se recalcula cada minuto (B-03) |
| [M2](diagramas/semana-10-m2-filtro.png) | Filtrar | Pantalla completa; «< Ocupación» vuelve sin filtrar | Grupo «Estado de cama» con leyenda | El filtro se aplica con «Ver 1 sala», no con cada flecha, y el resultado se anuncia (B-05) |
| [M3](diagramas/semana-10-m3-detalle-sala.png) | Detalle de sala | «< Ocupación» vuelve | Una línea por estado de cama, con la fórmula y el corte en el título | — |
| [M4](diagramas/semana-10-m4-generar-corte.png) | Generar un corte | Solo para el administrador; «Volver» como alternativa | — | Explica que no cambia estados (R-09); el enlace «2 camas no coinciden» lleva a las inconsistencias |
| [M5](diagramas/semana-10-m5-conexion-limitada.png) | Conexión limitada | Franja fija arriba de M1 | — | Datos viejos, sin conexión y «No sabemos si se generó el corte» |

Cada pantalla completa recibe el foco en su título al abrirse (B-02).

## 3. Reglas de breakpoint

| Ancho | Regla |
|---|---|
| 320–359 px | Una columna, una tarjeta por sala. La primera línea es el nombre de la sala y la segunda, «disponibles de operativas · porcentaje nivel». |
| 360–430 px | Igual, pero el número de mantenimiento e inactivas cabe en la segunda línea de cada tarjeta. |
| 431 px o más | Fuera del alcance móvil: vuelve la tabla de W1 de la semana 8, con las seis columnas. |

**Reglas comunes:** sin scroll horizontal a 320 px (WCAG 1.4.10); objetivos táctiles de al menos 44 × 44 px (2.5.8 exige 24 px); el nivel de ocupación siempre se escribe («baja», «media», «alta»), con los colores medidos en la semana 9, de 5.02:1 o más; texto ampliable al 200 % (1.4.4); funciona en vertical y en horizontal (1.3.4).

## 4. Escenarios móviles

| | E-1: decidir un ingreso con datos viejos | E-2: generar un corte con conexión débil |
|---|---|---|
| **Quién y dónde** | Jefa de enfermería en la ronda, con un teléfono de 360 px | Administrador en una reunión, con un teléfono de 320 px |
| **Tarea** | Elegir la sala para un ingreso que llega de urgencias | Pedir un corte nuevo antes de informar la ocupación |
| **Decisión de contenido** | Las salas se ordenan por camas libres, así que la A, con 5 disponibles, es la primera. El detalle por estado queda en M3 | M4 muestra la hora del último corte y aclara que generar uno no cambia camas, para que el administrador no dude en hacerlo |
| **Error** | La consolidación automática falló y el último corte es de las 10:20 (más de 15 minutos, R-06) | La conexión se corta mientras se genera el corte |
| **Decisión de error** | La franja fija de M5 dice la hora y ofrece «Ver el último corte». Las cifras no se esconden, porque siguen siendo una referencia, pero quedan marcadas como viejas. El teléfono nunca recalcula la ocupación por su cuenta | «No sabemos si se generó el corte», con «Ver el último corte» y «Generar de nuevo». A diferencia de los módulos que escriben datos clínicos, aquí reintentar es seguro: un corte de más solo agrega una lectura y no cambia ninguna cama (R-09) |

## 5. Trazabilidad con la semana 9

| Corrección del backlog (PR #223) | Dónde queda en móvil |
|---|---|
| B-01 Aviso de datos viejos fijo | M5, como franja arriba de M1 |
| B-02 Foco definido | Pantallas completas con el foco en su título |
| B-03 Hora absoluta y antigüedad que se actualiza | M1 |
| B-04 Nivel de ocupación con texto y color | M1 y M3 |
| B-05 Filtro sin aplicar en cada flecha | M2 |
| B-06 «Ver el último corte» y «Generar un corte ahora» | M4 y M5 |
| B-08 Inconsistencias con enlace | M4 |

Las cifras siguen la fórmula de `OcupacionSala::calcular`: 6 ÷ 11 = 54.5 % en la sala A y 6 ÷ 6 = 100.0 % en la B; la C no tiene camas operativas. Fuentes editables en [`diagramas/`](diagramas/) (PlantUML Salt); los PNG se generaron con PlantUML local. Salt no fija el ancho en píxeles, así que los wireframes representan la estructura a 320 px y el ancho real se valida en el prototipo de la semana 11.

**Bibliografía:** W3C (2023). *WCAG 2.2*: 1.3.4, 1.4.4, 1.4.10 y 2.5.8 · Wroblewski, L. (2011). *Mobile First*. A Book Apart.
