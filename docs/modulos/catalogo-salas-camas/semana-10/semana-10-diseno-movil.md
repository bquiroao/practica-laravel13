# Semana 10 — Diseño móvil de «creación de sala y cama con control de estado operativo»

| | |
|---|---|
| **Tarea Odoo** | 2452 — Semana 10: Diseño para movilidad |
| **Módulo** | Catálogo de salas, wards y camas |
| **Base** | Flujo y wireframes de la semana 8 (PR #202) y backlog de la semana 9 (PR #208); reglas R-01 a R-09 de la [Semana 1](../semana-01/semana-01-uml-catalogo-camas.md) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Jerarquía en móvil (320–430 px)

En el piso, el supervisor de enfermería necesita saber, primero, cuántas camas puede usar cada sala; después, el estado de una cama concreta, y por último, cambiarlo. El administrador crea salas y camas con menos frecuencia. Por eso:

1. **Arriba:** las camas disponibles de cada sala frente a las operativas («5 disponibles de 11 operativas»), que es el dato de capacidad.
2. **Después:** las no operativas y las ocupadas, en una línea de resumen; el detalle de los estados operativos de cada cama se ve al entrar a la sala.
3. **Al final:** las acciones de creación, solo para el administrador, en un único botón «+ Nueva sala o cama».

En el esquema, *ward* y sala son lo mismo (tabla `wards`), así que en móvil se dice siempre «sala». Para proteger la consistencia del catálogo, cada cama se nombra completa, con su sala (A/101), y no se confunden camas de salas distintas.

Para reducir la carga cognitiva hay una tarea por pantalla, los estados se escriben siempre con texto (no solo con color), el estado inicial de una cama nueva se muestra fijo en vez de pedirlo, y no hay tablas de seis columnas.

## 2. Pantallas

| M | Pantalla | Navegación | Tabla o formulario | Confirmación y error |
|---|---|---|---|---|
| [M1](diagramas/semana-10-m1-salas.png) | Salas | Raíz del módulo; «≡ Menú» abre los demás módulos | La tabla de la semana 8 pasa a una tarjeta por sala | Sin conexión: muestra los datos guardados con su hora y «Reintentar» |
| [M2](diagramas/semana-10-m2-camas-sala.png) | Camas de una sala | «< Salas» vuelve a M1 | Una fila por cama con código y estado; filtro por estado | Una cama ocupada no tiene botón: «La gestiona admisión» (R-06) |
| [M3](diagramas/semana-10-m3-cambiar-estado.png) | Cambiar estado | Pantalla completa, no un modal; «< Sala A» vuelve sin guardar | Grupo «Nuevo estado» con leyenda, motivo y el impacto en la capacidad | El botón dice lo que hace («Pasar a mantenimiento» o «Inactivar cama») |
| [M4](diagramas/semana-10-m4-nueva-cama.png) | Nueva cama | «< Salas» vuelve sin guardar | Una columna, la leyenda «* Obligatorio» y el estado inicial fijo | El error va bajo su campo y empieza con «Error:» (E3) |
| [M5](diagramas/semana-10-m5-conexion-limitada.png) | Conexión limitada | Se abre sobre M3 o M4 | — | «No sabemos si se guardó» con «Comprobar», o «Sin conexión» con «Reintentar» |

Cada pantalla completa recibe el foco en su título al abrirse, lo que resuelve en móvil el foco de los diálogos de la semana 9 (B-01).

## 3. Reglas de breakpoint

| Ancho | Regla |
|---|---|
| 320–359 px | Una columna. Tarjetas en lugar de tablas: cada sala muestra solo sus disponibles, sus operativas y una línea de resumen. Botón principal a todo el ancho, al final de la pantalla. |
| 360–430 px | Igual, pero dos botones caben lado a lado cuando cada uno mide al menos 140 px (M5). |
| 431 px o más | Fuera del alcance móvil: vuelve la tabla de W1 de la semana 8, con las seis columnas. |

**Reglas comunes:** sin scroll horizontal a 320 px (WCAG 1.4.10); cada objetivo táctil mide al menos 44 × 44 px (2.5.8 exige 24 px; se usan 44 porque en el piso se trabaja de pie y a veces con guantes); el texto de los campos es de 16 px para que el teléfono no haga zoom; el texto se puede ampliar al 200 % (1.4.4); funciona en vertical y en horizontal (1.3.4); la acción principal queda al alcance del pulgar.

## 4. Escenarios móviles

| | E-1: cambio de estado con 3G intermitente | E-2: crear una cama y perder la conexión |
|---|---|---|
| **Quién y dónde** | Supervisora de enfermería en el piso, con un teléfono de 360 px | Administrador en un pasillo, con un teléfono de 320 px |
| **Tarea** | Pasar A/101 a mantenimiento por limpieza profunda | Crear la cama A/105 en la sala A |
| **Decisión de contenido** | M2 muestra solo el código y el estado de cada cama. M3 muestra el impacto («la sala A pasará de 5 a 4 disponibles»), porque es lo que afecta la capacidad | M4 pide solo la sala y el código; el estado inicial es fijo (R-04) y no ocupa un campo |
| **Error** | La conexión se corta al enviar: M5 «No sabemos si se guardó», con el motivo conservado | Se reintenta después del corte y el servidor responde E3 («ya existe A/105») |
| **Decisión de error** | «Comprobar» consulta el estado de A/101: si ya está en mantenimiento, muestra el éxito; si no, ofrece reenviar con el mismo motivo. El cambio **no** se guarda para enviarlo después, porque admisión podría ocupar la cama mientras tanto (E7) y se rompería la consistencia del catálogo | La pantalla no lo presenta como un error del usuario: «A/105 ya existe: probablemente se creó en el intento anterior» y «Ver A/105». Así no se crea un duplicado ni se confunde al usuario |

## 5. Trazabilidad con la semana 9

| Corrección del backlog (PR #208) | Dónde queda en móvil |
|---|---|
| B-01 Foco definido | Pantallas completas con el foco en su título |
| B-02 Impacto en la capacidad y botón con verbo | M3 |
| B-03 Error junto al campo, con «Error:» | M4 |
| B-04 Grupo «Nuevo estado» con leyenda | M3 |
| B-06 Estados con texto | M1, M2 y M3 |
| B-08 Leyenda «* Obligatorio» | M4 |

Fuentes editables en [`diagramas/`](diagramas/) (PlantUML Salt); los PNG se generaron con PlantUML local. Salt no fija el ancho en píxeles, así que los wireframes representan la estructura a 320 px y el ancho real se valida en el prototipo de la semana 11.

**Bibliografía:** W3C (2023). *WCAG 2.2*: 1.3.4, 1.4.4, 1.4.10 y 2.5.8 · Wroblewski, L. (2011). *Mobile First*. A Book Apart.
