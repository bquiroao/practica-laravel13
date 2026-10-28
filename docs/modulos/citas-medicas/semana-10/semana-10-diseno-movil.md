# Semana 10 — Diseño móvil de «solicitud, validación de disponibilidad y confirmación o cancelación de cita»

| | |
|---|---|
| **Tarea Odoo** | 2467 — Semana 10: Diseño para movilidad |
| **Módulo** | Citas médicas y agenda |
| **Base** | Flujo y wireframes de la semana 8 (PR #203) y backlog de la semana 9 (PR #209); reglas R-01 a R-10 de la [Semana 1](../semana-01/semana-01-uml-citas.md) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Jerarquía en móvil (320–430 px)

En la recepción o en el consultorio, la agenda médica se mira para saber qué cita viene. Las acciones (confirmar, cancelar o agendar) son menos frecuentes y más delicadas. Por eso:

1. **Arriba:** la hora y el estado de cada cita activa (programada o confirmada), y después el paciente y el médico.
2. **Después:** las citas canceladas se agrupan al final en una línea plegada («1 cita cancelada»), porque ya no ocupan horario (R-08).
3. **Acciones:** no van en la lista. La confirmación y la cancelación están dentro del detalle de cada cita, para no tocar la cita equivocada, y «+ Nueva cita» va al final. La concurrencia no se le muestra al usuario mientras llena el formulario: se resuelve al guardar, y solo si hay conflicto aparece M5 con alternativas.

Para reducir la carga cognitiva, la solicitud se divide en 3 pasos (paciente; médico y horario; motivo y revisión). La disponibilidad se muestra como botones con los horarios libres, que ya excluyen los cruces del médico y del paciente (R-02, R-03), así que el usuario no tiene que adivinar ni recibir un error después.

## 2. Pantallas

| M | Pantalla | Navegación | Tabla o formulario | Confirmación y error |
|---|---|---|---|---|
| [M1](diagramas/semana-10-m1-agenda.png) | Agenda del día | Raíz del módulo; «< 27/10» y «29/10 >» cambian de día; «≡ Menú» abre los demás módulos | La tabla de la semana 8 pasa a una tarjeta por cita | Sin conexión: muestra la agenda guardada con su hora y «Reintentar» |
| [M2](diagramas/semana-10-m2-nueva-cita-horario.png) | Nueva cita, paso 2 | «< Paso 1» vuelve sin perder lo elegido | Especialidad, médico y horarios libres como botones de 44 px | «2 horarios libres» se anuncia al cargar; sin horarios: «No hay horarios libres esta semana» |
| [M3](diagramas/semana-10-m3-revisar.png) | Nueva cita, paso 3 | «< Paso 2» para corregir | Motivo y resumen de la cita (R-06) | «Agendar cita» vuelve a validar el horario antes de guardar |
| [M4](diagramas/semana-10-m4-cancelar.png) | Cancelar la cita | Pantalla completa; «< Cita» vuelve sin cambiar | Motivo obligatorio (R-10) | El botón destructivo nombra al paciente; «Volver sin cancelar» es la alternativa |
| [M5](diagramas/semana-10-m5-conexion-limitada.png) | Conexión limitada y concurrencia | Se abre sobre M3 o M4 | — | «No sabemos si se agendó» con «Comprobar»; horario ocupado con alternativas; cita ya cancelada por otra persona |

Cada pantalla completa recibe el foco en su título al abrirse, lo que resuelve en móvil el foco de los diálogos de la semana 9 (B-01).

## 3. Reglas de breakpoint

| Ancho | Regla |
|---|---|
| 320–359 px | Una columna. Una tarjeta por cita, con la hora en negrita en la primera línea. Los horarios libres van en una lista vertical de botones a todo el ancho. |
| 360–430 px | Igual, pero dos horarios alternativos caben lado a lado cuando cada botón mide al menos 140 px (M5). |
| 431 px o más | Fuera del alcance móvil: vuelve la tabla de W1 de la semana 8, con las acciones en cada fila. |

**Reglas comunes:** sin scroll horizontal a 320 px (WCAG 1.4.10); objetivos táctiles de al menos 44 × 44 px (2.5.8 exige 24 px); texto de los campos de 16 px para que el teléfono no haga zoom; texto ampliable al 200 % (1.4.4); funciona en vertical y en horizontal (1.3.4); la fecha siempre lleva el día de la semana («Mié 28/10»), para no confundirse al cambiar de día.

## 4. Escenarios móviles

| | E-1: agendar con wifi inestable | E-2: el médico cancela una cita que ya cambió |
|---|---|---|
| **Quién y dónde** | Recepcionista atendiendo por teléfono, con un celular de 360 px | Médica en su consultorio, con un celular de 320 px |
| **Tarea** | Agendar a A. Pérez con la Dra. Ruiz el 28/10 a las 08:30 | Cancelar la cita de L. Gómez de las 09:00 |
| **Decisión de contenido** | Tres pasos cortos y la revisión de M3 con paciente, médico y horario; los horarios se eligen de una lista de libres, sin escribir horas | La médica solo ve sus citas (R-09). Cancelar está dentro del detalle y su botón nombra al paciente («Cancelar la cita de L. Gómez») |
| **Error** | La conexión se corta al pulsar «Agendar cita»: M5 «No sabemos si se agendó» | Recepción ya la canceló a las 08:50; el servidor responde que la cita cambió (E7) |
| **Decisión de error** | «Comprobar» consulta las citas de A. Pérez ese día. Si la cita existe, muestra el éxito; si no, ofrece reenviar. Reenviar no puede duplicarla, porque R-03 impide dos citas activas del paciente en el mismo horario. La cita **no** se guarda para enviarla después: otra persona podría tomar el horario (concurrencia) | No se presenta como un error de la médica: «Esta cita ya fue cancelada. Recepción la canceló a las 08:50» y «Volver a la agenda». El horario ya está libre |

## 5. Trazabilidad con la semana 9

| Corrección del backlog (PR #209) | Dónde queda en móvil |
|---|---|
| B-01 Foco definido | Pantallas completas con el foco en su título |
| B-02 «Cancelar la cita» frente a «Volver» | M4: «Cancelar la cita de L. Gómez» y «Volver sin cancelar»; ningún otro botón dice «Cancelar» |
| B-03 Botones con la cita en su nombre | Las acciones salen de la lista y quedan en el detalle, con el nombre del paciente |
| B-04 Mensajes de estado anunciados | M2: «2 horarios libres» |
| B-07 Leyenda de obligatorio | Los campos de M2 a M4 llevan «*»; la leyenda va en el paso 1 |

Fuentes editables en [`diagramas/`](diagramas/) (PlantUML Salt); los PNG se generaron con PlantUML local. Salt no fija el ancho en píxeles, así que los wireframes representan la estructura a 320 px y el ancho real se valida en el prototipo de la semana 11.

**Bibliografía:** W3C (2023). *WCAG 2.2*: 1.3.4, 1.4.4, 1.4.10 y 2.5.8 · Wroblewski, L. (2011). *Mobile First*. A Book Apart.
