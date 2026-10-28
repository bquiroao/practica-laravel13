# Checklist de evaluación — usabilidad y accesibilidad del flujo UX de Pacientes

**Estudiante:** Boris Alexander Quiroa Orellana · **Objeto evaluado:** diseño de la semana 8, que comprende:

- los [user flows](../semana-08/diagramas/);
- los [wireframes W1–W6](../semana-08/wireframes/semana-08-wireframes-pacientes.pdf);
- [semana-08-ux-pacientes.md](../semana-08/semana-08-ux-pacientes.md) (mensajes M-01..M-22, reglas I-1..I-8, estados).

**Resultado:** **C** cumple · **P** cumple en parte · **N** no cumple. Cada P o N tiene un hallazgo (H-xx) en [semana-09-evaluacion-ux-pacientes.md](semana-09-evaluacion-ux-pacientes.md).

**Cómo se evaluó cada fila:**

| Tipo de comprobación | Método |
|---|---|
| Contraste | Medición con [herramientas/contraste.py](herramientas/contraste.py) ([salida](evidencia/contraste-wcag.txt)) |
| Teclado y foco | Recorrido del orden de tabulación (§4) sobre los wireframes y las reglas I-1..I-8 |
| Coherencia con el backend | Comparación de cada pantalla con el código del Micro-HIS (`Texto::normalizar`, `PoliticaDuplicados`, `Paciente::SEXOS`) |
| Resto | Inspección del wireframe y del texto de la especificación |

## 1. Heurísticas de usabilidad (Nielsen, 1994)

| # | Heurística | W1 | W2 | W3 | W4 | W5 | W6 | Evidencia / hallazgo |
|---|---|---|---|---|---|---|---|---|
| N1 | Visibilidad del estado del sistema | P | C | C | C | C | C | Estados vacío/carga/éxito/error en todas. W1: el número de resultados no se anuncia (H-09). |
| N2 | Coincidencia con el mundo real | C | P | C | C | C | C | W2: en una emergencia no se puede registrar el sexo como desconocido, aunque el backend lo admite (H-07). |
| N3 | Control y libertad del usuario | C | C | P | C | C | P | W3: admisión no tiene salida ante una coincidencia (H-03). W6: el diálogo no dice cómo deshacer la protección (H-10). |
| N4 | Consistencia y estándares | P | P | **N** | C | **N** | C | W1/W2: fecha en AAAA-MM-DD al escribir y dd/mm/aaaa al mostrar (H-08). W3: el supervisor ve «Reservado», pero el backend le devuelve el código real (H-01). W5: pide motivo por una tilde, aunque el backend no lo considera cambio de identidad (H-02). |
| N5 | Prevención de errores | C | P | P | C | C | C | W2: obligar a elegir un sexo produce datos falsos (H-07). W3: sin salida, admisión puede alterar el nombre para evitar el aviso y crear un duplicado (H-03). |
| N6 | Reconocer antes que recordar | C | C | C | C | C | C | El registro precarga lo buscado; los candidatos muestran nombre y fecha. |
| N7 | Flexibilidad y eficiencia | C | C | C | C | C | C | Teclado en resultados (I-6); búsqueda por código, documento o nombre. |
| N8 | Diseño estético y minimalista | C | C | C | C | C | C | Cada pantalla muestra solo lo necesario para su decisión. |
| N9 | Ayudar a reconocer y recuperarse de errores | C | C | P | C | P | C | W3: «Corregir» no dice qué corregir (H-12). W5: la recuperación del conflicto saca datos al portapapeles (H-04) y el motivo aparece ya en error (H-11). |
| N10 | Ayuda y documentación | P | C | C | C | C | C | W1: la ayuda «?» no está especificada para teclado ni tiene nombre accesible (H-14). |

## 2. WCAG 2.2, nivel AA

Se listan los criterios ligados a lo que pide la tarea: teclado, foco, contraste, etiquetas, mensajes y prevención de errores.

### 2.1 Teclado y foco

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 2.1.1 Teclado | P | Todo es operable con teclado según I-1 e I-6, salvo la ayuda «?» de W1, que no está definida (H-14). |
| 2.1.2 Sin trampa para el foco del teclado | C | Los diálogos (W3, W6) se cierran con `Esc` (I-1). |
| 2.4.3 Orden del foco | C | Orden visual; foco inicial definido en los diálogos sensibles (I-3). |
| 2.4.7 Foco visible | C | Anillo de 2 pt `#3D4F6B` (C-20: 8.30:1), aplicable a todos los controles. |
| 2.4.11 Foco no oculto (mínimo) | C | Ningún elemento fijo tapa los controles; los diálogos tienen velo. |
| 3.2.1 Al recibir el foco | C | Recibir el foco no envía ni navega. |
| 3.2.2 Al introducir datos | C | Escribir no cambia de contexto; buscar y guardar requieren una acción explícita. |

### 2.2 Contraste y percepción

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 1.4.1 Uso del color | C | Estados y etiquetas con texto, no solo color (ACTIVO, PROTEGIDA, errores). |
| 1.4.3 Contraste mínimo | **N** | 7 pares de texto fallan: el gris `#8A8F96` (3.26:1 y 2.93:1), incluido «Reservado» (H-05). |
| 1.4.11 Contraste no textual | **N** | Bordes de campos y casillas `#9AA0A6`: 2.64:1 (H-06). |
| 1.3.1 Información y relaciones | C | Tablas con encabezados; etiquetas visibles asociadas a cada campo. |
| 1.4.13 Contenido con *hover* o foco | P | La ayuda «?» de W1 no define cómo se descarta ni si persiste (H-14). |

### 2.3 Etiquetas, mensajes y prevención de errores

| Criterio | Resultado | Evidencia / hallazgo |
|---|---|---|
| 2.4.6 Encabezados y etiquetas | C | Títulos de pantalla y de diálogo descriptivos («Proteger la identidad de Sofía Morales Díaz»). |
| 2.5.3 Etiqueta en el nombre | C | El texto visible de cada botón es su nombre. |
| 3.3.1 Identificación de errores | P | Errores en texto junto al campo; pero en W5 el motivo aparece directamente en estado de error, antes de que el usuario escriba (H-11). |
| 3.3.2 Etiquetas o instrucciones | P | Falta la leyenda de «*» (H-13); el selector de sexo no lista sus opciones (H-07). |
| 3.3.3 Sugerencia ante errores | C | Cada mensaje dice qué hacer (catálogo M-01..M-22). |
| 3.3.4 Prevención de errores (datos) | C | Confirmación antes/después (W5), confirmación con motivo (W3, W6) y conflicto de versión sin sobrescribir. |
| 3.3.7 Entrada redundante | C | W2 precarga lo buscado en W1. |
| 4.1.2 Nombre, función, valor | P | La ayuda «?» no tiene nombre accesible definido (H-14). |
| 4.1.3 Mensajes de estado | **N** | El número de resultados y el estado «sin resultados» no se anuncian (H-09). |

## 3. Checklist específico de Pacientes

Dos comprobaciones por cada aspecto que nombra la tarea.

| ID | Aspecto | Comprobación | Resultado | Hallazgo |
|---|---|---|---|---|
| PA-01 | Identidad | La UI decide «cambio de identidad» con la misma regla que el backend | **N** | H-02 |
| PA-02 | Identidad | Se puede registrar una identidad provisional sin inventar datos | P | H-07 |
| PA-03 | Demografía | Las fechas se escriben y se muestran en el mismo formato | P | H-08 |
| PA-04 | Demografía | Las validaciones del cliente dicen lo mismo que el servidor (M-04..M-07) | C | — |
| PA-05 | Búsqueda | Criterios fuera de la URL y datos mínimos en la lista | C | — |
| PA-06 | Búsqueda | Resultados y «sin resultados» son perceptibles para todos y accionables según el rol | P | H-09 |
| PA-07 | Edición | Un conflicto de versión nunca sobrescribe | C | — |
| PA-08 | Edición | El trabajo se recupera tras un conflicto sin sacar datos de la aplicación | **N** | H-04 |
| PA-09 | Duplicados | Lo que ve cada rol en el 409 coincide con lo que devuelve el backend | **N** | H-01 |
| PA-10 | Duplicados | Cada rol tiene una salida segura ante una coincidencia probable | P | H-03 |
| PA-11 | Confidencialidad | Vista reducida: `null` = «Reservado», sin completar con datos previos | C | — |
| PA-12 | Confidencialidad | Proteger explica las consecuencias, exige motivo y dice cómo revertir | P | H-10 |

## 4. Recorrido de teclado

Se recorre cada pantalla con `Tab`, `Shift+Tab`, `Enter`, `Espacio`, flechas y `Esc`, siguiendo el orden visual del wireframe y las reglas I-1..I-8. ✗ marca el paso donde el diseño no define o rompe el comportamiento.

### W1 · Buscar paciente

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | `Tab` ×2 | Selector de hospital → menú de usuario | ✓ |
| 2 | `Tab` | Ayuda «?»: no se sabe si es un botón, qué nombre tiene ni cómo se cierra | ✗ H-14 |
| 3 | `Tab` | «+ Registrar paciente» (solo con `pacientes.registrar`) | ✓ |
| 4 | `Tab` | Campo «Código, documento o nombre» | ✓ |
| 5 | `Tab` | «Fecha de nacimiento»: se escribe en AAAA-MM-DD, pero la lista la muestra en dd/mm/aaaa | ✗ H-08 |
| 6 | `Enter` | Busca; el foco pasa a la primera fila (I-6). El lector no anuncia «2 resultados» | ✗ H-09 |
| 7 | `↓` + `Enter` | Abre la ficha P-000004 (W4) | ✓ |

### W2 · Registrar paciente

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | `Tab` | «Nombres *»: el «*» no tiene leyenda | ✗ H-13 |
| 2 | `Tab` ×2 | «Apellidos *» → «Fecha de nacimiento *» | ✓ |
| 3 | `Tab` | «Sexo *» (`select`): no hay opción para «desconocido» en una emergencia | ✗ H-07 |
| 4 | `Tab` ×3 | Tipo de documento → número → teléfono; la pista del DPI está asociada al número | ✓ |
| 5 | `Espacio` | Casilla «No tiene documento ahora» | ✓ |
| 6 | `Tab` + `Enter` | «Registrar» → «Registrando…» → M-20 con el foco en el aviso (I-4) | ✓ |

### W3 · Duplicados

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | — | 409 por documento: foco en «Abrir ficha P-000001» | ✓ |
| 2 | `Tab` + `Enter` | «Corregir»: no dice qué campo corregir ni a dónde va el foco | ✗ H-12 |
| 3 | — | Coincidencia probable (supervisor): el candidato protegido aparece como «Reservado» y no se puede abrir | ✗ H-01 |
| 4 | `Tab` | Motivo → «Es la misma: usar la ficha» (foco inicial, I-3) | ✓ |
| 5 | — | Admisión: solo «Usar la ficha» o salir; «Pedir revisión» no existe | ✗ H-03 |

### W5 · Editar paciente

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | — | Cambiar «Lucía» por «Lucia»: aparece el campo de motivo ya en rojo | ✗ H-02, H-11 |
| 2 | `Tab` | Motivo → «Revisar cambios» → tarjeta de confirmación | ✓ |
| 3 | `Enter` en «Guardar» | 409: aviso M-11 con «Ver la versión nueva» | ✓ |
| 4 | `Tab` + `Enter` | «Copiar mis cambios»: los datos del paciente salen al portapapeles del sistema | ✗ H-04 |

### W6 · Identidad protegida

| Paso | Tecla | Foco / efecto esperado | Resultado |
|---|---|---|---|
| 1 | `Enter` en «Proteger identidad» (W4) | Abre el diálogo con el foco en «Cancelar» (I-3) | ✓ |
| 2 | `Shift+Tab` | Casilla → motivo | ✓ |
| 3 | — | El diálogo no dice cómo se revierte ni quién puede hacerlo | ✗ H-10 |
| 4 | `Esc` | Cierra; el foco vuelve a «Proteger identidad» | ✓ |

## 5. Resumen

| Bloque | Ítems | C | P | N |
|---|---:|---:|---:|---:|
| Heurísticas (10 × 6 pantallas) | 60 | 47 | 11 | 2 |
| WCAG 2.2 AA | 21 | 13 | 5 | 3 |
| Pacientes específico | 12 | 4 | 5 | 3 |
| **Total** | **93** | **64** | **21** | **8** |

Los conteos salen de las tablas anteriores.
