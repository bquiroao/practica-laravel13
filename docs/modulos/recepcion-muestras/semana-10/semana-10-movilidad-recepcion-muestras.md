# Semana 10 — Diseño para movilidad: recepción de muestras y códigos identificadores

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Módulo** | Recepción de muestras y códigos identificadores |
| **Flujo** | «Recepción, identificación inequívoca y rechazo controlado de muestra» |
| **Rango de pantalla** | 320 a 430 px de ancho |
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Fecha** | 27 de octubre de 2026 |

## 1. Alcance y límites

Se adapta a móvil el flujo ya diseñado en la Semana 8 para escritorio: cuatro pantallas, reglas de breakpoint y dos escenarios móviles. Es una **propuesta de diseño**: no hay aplicación, lector de códigos, impresora ni servidor implementados, y los datos son sintéticos.

Las pantallas conservan las reglas del prototipo de la Semana 3 (el sello solo aplica a sangre) y los criterios de la Semana 6 (idempotencia y custodia, que siguen siendo propuestas). No se inventan reglas por examen ni catálogo de motivos: lo define el laboratorio.

## 2. Pantallas

Cada wireframe es un SVG de 360 px con las decisiones de contenido anotadas al lado.

| # | Pantalla | Objetivo único | Archivo |
|---|---|---|---|
| M1 | Buscar orden e ítem | Elegir el ítem de la orden que se recibe | [M1](wireframes/M1-buscar-orden-item.svg) |
| M2 | Verificar vínculo e integridad | Responder etiqueta, recipiente y sello | [M2](wireframes/M2-verificar-muestra.svg) |
| M3 | Decisión: aceptar o rechazar | Confirmar el resultado calculado | [M3](wireframes/M3-decision-rechazo.svg) |
| M4 | Guardado, custodia y conexión limitada | Ver el estado real del guardado | [M4](wireframes/M4-guardado-y-conexion.svg) |

Navegación: M1 → M2 → M3 → M4, con «Volver» en cada paso sin perder lo capturado, y una barra inferior (Inicio, Recepciones, Ayuda) al alcance del pulgar.

## 3. Jerarquía de datos y acciones

| Nivel | Qué se muestra | Dónde |
|---|---|---|
| 1. Contexto | Orden, examen, tipo de muestra, iniciales del paciente | Resumen fijo (M2) |
| 2. Decisión | Etiqueta, recipiente, sello (solo sangre) | Formulario (M2) |
| 3. Resultado | Aceptación o rechazo con motivo | Encabezado de M3 |
| 4. Acción primaria | Un solo botón por pantalla | Parte inferior, fácil de alcanzar |
| 5. Secundario | Observación libre, custodia detallada, ayuda | Bajo la acción o en detalle desplegable |

El código `SP-…` aparece tras el guardado y con función de copiar; no es un dato de entrada.

## 4. Reglas de breakpoint

| Ancho | Diseño | Reglas |
|---|---|---|
| 320 a 359 px | Una columna | Botones Sí/No apilados si no caben dos de 48 px; etiquetas abreviadas pero con texto completo disponible |
| 360 a 429 px | Una columna (diseño base de los wireframes) | Sí/No lado a lado; resumen fijo arriba |
| 430 px | Una columna, márgenes mayores | Sin segunda columna: el flujo es secuencial |
| Mayor de 430 px | Vuelve al diseño de la Semana 8 | Fuera de alcance de esta semana |

Reglas comunes:

1. Área táctil mínima de 48 x 48 px y separación mínima de 8 px; entre confirmar y volver, al menos 16 px.
2. Texto base de 16 px; nunca menor de 12 px, solo para notas.
3. Sin desplazamiento horizontal; las tablas pasan a tarjetas.
4. El zoom del navegador no se bloquea; el diseño se verifica con zoom de 200 %.
5. Los estados se indican con texto y símbolo, no solo con color.

## 5. Tablas, formularios y confirmaciones

- **Ítems de la orden:** tarjetas, una por ítem; cada tarjeta muestra examen, tipo y estado.
- **Custodia:** tarjetas con hora, lugar y persona, en lugar de una tabla de columnas.
- **Formulario:** una pregunta por fila, etiqueta encima del campo y botones Sí/No grandes en lugar de casillas pequeñas.
- **Confirmaciones:** antes de guardar, un resumen de lo que se registrará. Rechazar usa un color y una etiqueta distintos y queda separado de «Volver».
- **Validación:** el foco pasa al primer campo pendiente y el mensaje se asocia a ese campo.

## 6. Dos escenarios móviles

### Escenario A — Rechazo de sangre con sello roto (pantallas M1 a M3)

| Paso | Qué ve y decide la persona | Decisión de contenido o error |
|---|---|---|
| 1 | Escanea o escribe ORD-0000 y elige el ítem de sangre | Si la orden no existe: mensaje específico, sin crear nada |
| 2 | Responde etiqueta Sí, recipiente Sí, sello No | El sello solo se pregunta porque es sangre |
| 3 | El sistema calcula: rechazo por sello no íntegro | El motivo del sistema no se edita; la observación es opcional |
| 4 | Confirma el rechazo tras ver el resumen | Rechazar y volver están separados |

Error de este escenario: si falta responder un campo, «Continuar» queda deshabilitado, el mensaje dice cuál falta y el foco se mueve a él.

### Escenario B — Conexión limitada al confirmar (pantalla M4)

| Paso | Qué ocurre | Decisión de contenido o error |
|---|---|---|
| 1 | La persona confirma y la conexión falla | Se muestra «Sin conexión estable» y **no se informa éxito** |
| 2 | La decisión queda «Pendiente de envío», con sus datos conservados | No aparece como aceptada ni rechazada |
| 3 | La persona mantiene la muestra aparte y pulsa «Reintentar envío» | El reintento usa la misma clave de envío |
| 4 | El servidor confirma | Se muestra «Guardado», el código `SP-…` y la custodia |
| 5 | Si la etiqueta falla | Se distinguen «guardado» e «impreso»; no se genera un rechazo clínico por una falla técnica |

Reglas ante conexión limitada:

1. Un resultado solo se muestra como guardado cuando el servidor lo confirma.
2. Los datos capturados se conservan durante la sesión; no hay trabajo sin conexión a largo plazo.
3. La clave de envío evita duplicar la recepción (criterios CHG-CA-04 y CHG-CA-05 de la Semana 6).
4. Una muestra con envío pendiente no pasa a procesamiento.

## 7. Reducción de carga cognitiva

- Un objetivo por pantalla y un paso numerado («Paso 2 de 3»).
- El sistema calcula el resultado; la persona lo confirma.
- El contexto (orden, examen, paciente) permanece visible.
- Sin términos nuevos: se usan los de las Semanas 8 y 9.
- El error dice qué pasó y qué hacer, y distingue «no encontrada», «otro hospital» y «sin conexión».

## 8. Trazabilidad

| Decisión | Origen |
|---|---|
| Sello solo en sangre | Regla de `SpecimenAcceptancePolicy`, Semana 3 |
| Observación libre separada del motivo | Hallazgo 4, Semana 9 |
| Estados con texto y símbolo | Hallazgo 2, Semana 9 |
| Guardado distinto de impreso | Hallazgo 6, Semana 9 |
| Clave de envío y rechazo sin procesamiento | Semana 6 (CHG-CA-04, CHG-CA-05, CHG-CA-07) |

## 9. Limitaciones

- No se probó en dispositivos reales ni con usuarios; los wireframes son SVG dibujados a mano.
- La clave de envío, la custodia, la impresión y el escáner no existen en el prototipo.
- Los motivos de rechazo dependen del catálogo del laboratorio, que no se define aquí.
- La conexión limitada se trata solo durante la sesión; el trabajo sin conexión duradero queda fuera de alcance.
- Los tamaños y breakpoints son una propuesta sin medición de contraste ni lector de pantalla.
