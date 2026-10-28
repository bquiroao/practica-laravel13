# Semana 10 — Diseño móvil de «apertura y consulta autorizada del expediente longitudinal»

| | |
|---|---|
| **Tarea Odoo** | 2512 — Semana 10: Diseño para movilidad |
| **Módulo** | Expediente médico electrónico base |
| **Base** | Flujo y wireframes de la semana 8 (PR #207) y backlog de la semana 9 (PR #224); reglas R-01 a R-10 de la [Semana 1](../semana-01/semana-01-uml-expediente.md) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Jerarquía en móvil (320–430 px)

El expediente se consulta en el teléfono en pasillos y en urgencias, donde otras personas pueden ver la pantalla. Por eso, el expediente longitudinal, los episodios clínicos, el acceso autorizado, la integridad y los datos sensibles se ordenan así:

1. **Acceso autorizado, arriba:** el número de expediente y la atención activa del profesional («Tu atención: E-77»), que es lo que le da derecho a ver (R-05).
2. **Datos sensibles al mínimo:** el nombre aparece abreviado («M. López R.») hasta pulsar «Mostrar nombre», y el documento se muestra como «terminado en 4521», que un lector de pantalla también entiende (B-06).
3. **Episodios clínicos:** una línea de tiempo con el más reciente primero; cada episodio muestra fecha, servicio y resumen, y el detalle se abre aparte. La historia de otros hospitales es un botón al final, no una mezcla con la local.
4. **Integridad:** crear un expediente está dentro de la tarjeta de cada paciente, con su fecha de nacimiento, para no crear el expediente del paciente equivocado (B-01).

Para reducir la carga cognitiva se muestra una sola acción por paciente («Crear expediente» o «Ver expediente HC-000123»), un episodio por línea, y la emergencia en una pantalla propia con su contador de caracteres.

## 2. Pantallas

| M | Pantalla | Navegación | Tabla o formulario | Confirmación y error |
|---|---|---|---|---|
| [M1](diagramas/semana-10-m1-buscar-paciente.png) | Buscar paciente (admisión) | Raíz del módulo para admisión | La tabla de la semana 8 pasa a una tarjeta por paciente, con el botón dentro | «Crear expediente» pide confirmar con nombre, fecha de nacimiento y documento (R-04: no se borra) |
| [M2](diagramas/semana-10-m2-expediente.png) | Expediente | «< Pacientes» vuelve | Línea de tiempo de episodios | Sin atención activa no se abre (E1) |
| [M3](diagramas/semana-10-m3-historia-externa.png) | Otros hospitales | «< HC-000123» vuelve a la historia local | Cada episodio externo con su hospital de origen y su alcance | «Consultando otros hospitales…» y «1 episodio externo» se anuncian (B-07) |
| [M4](diagramas/semana-10-m4-emergencia.png) | Acceso de emergencia | Pantalla completa; «Volver sin acceder» | Justificación con mínimo de 20 caracteres, contador y ejemplo (B-05) | El botón dice la duración: «Confirmar acceso por 1 hora» (R-07) |
| [M5](diagramas/semana-10-m5-conexion-limitada.png) | Conexión limitada | Se abre sobre M2, M3 o M4 | — | Sin copia guardada; «No sabemos si se concedió el acceso»; aviso 5 minutos antes de que termine la emergencia (B-03) |

Cada pantalla completa recibe el foco en su título al abrirse (B-02).

## 3. Reglas de breakpoint

| Ancho | Regla |
|---|---|
| 320–359 px | Una columna. Cada episodio ocupa dos líneas (fecha y episodio; servicio y resumen), y la acción de abrirlo es la fila completa, de 44 px. |
| 360–430 px | Igual, pero la edad y el número de expediente caben junto al nombre. |
| 431 px o más | Fuera del alcance móvil: vuelve la tabla de W2 de la semana 8. |

**Reglas comunes:** sin scroll horizontal a 320 px (WCAG 1.4.10); objetivos táctiles de al menos 44 × 44 px (2.5.8 exige 24 px); texto ampliable al 200 % (1.4.4); funciona en vertical y en horizontal (1.3.4); en ningún ancho se muestran el documento completo ni datos clínicos en la lista de pacientes.

## 4. Escenarios móviles

| | E-1: consultar en el pasillo | E-2: emergencia en urgencias con red débil |
|---|---|---|
| **Quién y dónde** | Médico en el pasillo, con un teléfono de 360 px | Médica de urgencias, con un teléfono de 320 px |
| **Tarea** | Revisar el último episodio de su paciente antes de entrar a la habitación | Ver las alergias de un paciente inconsciente sin consentimiento para este hospital |
| **Decisión de contenido** | Nombre abreviado hasta pulsar «Mostrar nombre», y episodios con solo fecha, servicio y resumen. Lo demás se abre aparte, para no exponer datos sensibles a quien pasa | M4 pide la justificación con contador y un ejemplo, y el botón dice que el acceso dura 1 hora. La historia externa se muestra con su hospital de origen |
| **Error** | Se pierde la conexión al abrir el expediente | La conexión se corta después de pulsar «Confirmar acceso por 1 hora» |
| **Decisión de error** | A diferencia de los otros módulos, aquí no hay copia guardada: «Por seguridad, el expediente no se guarda en el teléfono», con «Reintentar». Un teléfono perdido no debe llevar datos clínicos | «No sabemos si se concedió el acceso» y «Comprobar mi acceso», que consulta si el acceso existe antes de pedir otro. Nunca se reenvía solo, para no abrir dos accesos de emergencia ni duplicar su revisión. Si se concedió, 5 minutos antes de las 11:40 aparece el aviso de cierre |

## 5. Trazabilidad con la semana 9

| Corrección del backlog (PR #224) | Dónde queda en móvil |
|---|---|
| B-01 Crear dentro de la fila del paciente, con datos para reconocerlo | M1 |
| B-02 Foco definido | Pantallas completas con el foco en su título |
| B-03 Aviso antes de que termine la emergencia | M5 |
| B-04 «Ver expediente» frente a «Crear expediente» | M1 |
| B-05 Justificación con mínimo y ejemplos | M4 |
| B-06 Documento enmascarado legible | M1 («Documento terminado en 4521») |
| B-07 Consulta externa anunciada | M3 |

Los nombres, fechas de nacimiento y documentos son ficticios. Fuentes editables en [`diagramas/`](diagramas/) (PlantUML Salt); los PNG se generaron con PlantUML local. Salt no fija el ancho en píxeles, así que los wireframes representan la estructura a 320 px y el ancho real se valida en el prototipo de la semana 11.

**Bibliografía:** W3C (2023). *WCAG 2.2*: 1.3.4, 1.4.4, 1.4.10 y 2.5.8 · Wroblewski, L. (2011). *Mobile First*. A Book Apart.
