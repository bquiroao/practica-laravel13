# Semana 10 — Catálogo de medicamentos: diseño para movilidad (320–430 px)

| Entregable | Archivo |
|---|---|
| Propuesta responsive de 5 pantallas | `wireframes/m1-buscar-tarjetas.png` … `m5-sin-conexion.png` |
| Reglas de breakpoint | Sección 2 |
| Dos escenarios móviles con decisiones de contenido y error | Sección 4 |

## 1. Jerarquía de datos y acciones en móvil

| Prioridad | Qué se muestra | Por qué |
|---|---|---|
| 1 | Nombre, concentración, forma, vía | Es lo que se compara para no confundir productos (RN-04) |
| 2 | «Vigente al …» y versión | Evita usar una versión cerrada (RN-01, RN-03) |
| 3 | Código `MED-…` | Para copiarlo o dictarlo |
| 4 | Presentación y registro sanitario | Bajo demanda, en el detalle |
| 5 | Historial | Plegado; se abre si se necesita |

Acciones: búsqueda siempre arriba; las de escritura (QF) en el detalle, con «Más acciones» para baja y
eliminar, para no tocarlas por error.

## 2. Reglas de breakpoint

| Ancho | Diseño | Lista | Formulario | Acciones |
|---|---|---|---|---|
| 320–430 px (objetivo) | 1 columna, márgenes de 12 px | **Tarjetas** (sin tabla) | Alta en 2 pasos (M3) | Botones de ancho completo, mínimo 44×44 px; confirmaciones en hoja inferior (M4) |
| 431–767 px | 1 columna, márgenes de 16 px | Tarjetas en 2 columnas | Un paso, campos a ancho completo | Igual que móvil |
| ≥ 768 px | Contenido de hasta 64 rem | Tabla (semana 4) | Un paso, etiquetas arriba | Botones en línea |

Reglas comunes:
- Texto base 16 px (evita el zoom automático en iOS); sin desplazamiento horizontal a 320 px (WCAG 1.4.10, ya medido en la semana 9 para la web actual).
- `inputmode="decimal"` en la concentración; selectores nativos para forma, vía y unidad.
- Las fechas se muestran siempre `AAAA-MM-DD` con la etiqueta «vigente al».

## 3. Pantallas

| Pantalla | Decisiones |
|---|---|
| M1 Buscar y resultados | Búsqueda y botón en una fila; filtros plegados; cada resultado es una tarjeta con la información de prioridad 1–3. Corrige H-11. |
| M2 Detalle | La versión vigente va primero en una caja; el historial queda plegado; las acciones de QF no aparecen para otros roles. |
| M3 Alta en 2 pasos | Paso 1 = producto (lo que define RN-04), paso 2 = presentación y vigencia. El duplicado (409) se detecta al terminar el paso 1 con una búsqueda previa, antes de escribir el resto. |
| M4 Confirmar baja | Hoja inferior con motivo obligatorio y consecuencia explicada; «Cancelar» del mismo tamaño que «Confirmar». |
| M5 Conexión limitada | Muestra la última búsqueda guardada con su hora; ante un envío sin respuesta, **consulta el estado antes de reenviar**. |

## 4. Escenarios móviles

### E1 — Enfermería en piso con señal débil (lectura)

`enf.norte` (CONSULTA_CLINICA) necesita confirmar que «Amoxicilina 250 mg/5 mL» sigue vigente.

| Paso | Decisión de contenido | Error y recuperación |
|---|---|---|
| Escribe «amoxi» | Tarjetas con concentración y forma grandes (M1) | — |
| La red tarda | Indicador «Buscando…» y el botón se deshabilita | A los 8 s: «La red está lenta. Reintentar» |
| No hay red | Muestra la última búsqueda guardada **con su fecha y hora** (M5) | Aviso «Puede estar desactualizada»; no se ofrece como vigente sin esa advertencia |
| Abre el detalle | «Vigente hoy: versión 1» arriba (M2) | Si el código ya no está vigente: «No vigente desde …» en rojo y sin acciones |

### E2 — QF registra una baja urgente con conexión intermitente (escritura)

`qf.norte` debe dar de baja MED-000101 desde su teléfono.

| Paso | Decisión de contenido | Error y recuperación |
|---|---|---|
| Abre «Más acciones > Dar de baja» | Hoja inferior (M4) con motivo obligatorio | 422 si el motivo es corto: mensaje bajo el campo, sin perder lo escrito |
| Confirma y se corta la red | «No sabemos si la baja se guardó» (M5) | La app **no reenvía**: hace `GET /api/v1/medicamentos/MED-000101` y muestra el estado real |
| El GET dice que sigue vigente | «La baja no se guardó. ¿Enviar de nuevo?» | Reenvío manual con el mismo motivo |
| El GET dice que ya no está vigente | «La baja se guardó» | Si reintentara, el servidor respondería 409 `YA_DADO_DE_BAJA`, que la app muestra como éxito ya aplicado |
| Responde 503 | «El catálogo no responde (código de soporte …)» | Reintento después de `Retry-After` (5 s) |

## 5. Relación con el backlog de la semana 9

B-11 (tarjetas en móvil) se resuelve en M1; B-01 (confirmación) en M4; B-03 (foco en errores) se
mantiene en M3. El prototipo de la semana 11 implementa M1, M2 y M4.
