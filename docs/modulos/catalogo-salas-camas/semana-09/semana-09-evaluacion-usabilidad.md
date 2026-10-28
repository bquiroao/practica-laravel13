# Semana 9 — Evaluación de usabilidad y accesibilidad del flujo UX del Catálogo de salas, wards y camas

| | |
|---|---|
| **Tarea Odoo** | 2451 — Semana 9: Evaluación del diseño, usabilidad y accesibilidad |
| **Módulo** | Catálogo de salas, wards y camas |
| **Objeto evaluado** | User flow y wireframes W1–W5 de la semana 8 (commit `54f336a`, PR #202) y los colores reales del frontend compartido (`AppLayout.vue`, `LoginPage.vue`) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Método

- **Heurísticas:** las 10 de Nielsen, revisadas pantalla por pantalla.
- **WCAG 2.2 nivel AA:** los criterios del enunciado (teclado, foco, contraste, etiquetas, mensajes y prevención de errores) y el uso del color.
- **Evidencia:** cada hallazgo cita la línea exacta del wireframe ([lineas-wireframes-s8.txt](evidencia/lineas-wireframes-s8.txt)) o una medición. El contraste se mide con [contraste.php](evidencia/contraste.php) (fórmula de luminancia relativa de WCAG, con autoprueba 21:1 y 1:1); su salida está en [salida-contraste.txt](evidencia/salida-contraste.txt).
- **Gravedad** (escala de Nielsen, 0–4) e **impacto en el catálogo**: la prioridad sube si el problema puede duplicar códigos, quitar capacidad por error o impedir la tarea a quien usa teclado.
- **Limitación:** hubo un solo evaluador, y Nielsen recomienda de 3 a 5. Los wireframes son de baja fidelidad, así que el contraste se mide sobre los colores reales del layout donde vivirán estas pantallas.

## 2. Checklist

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | Parcial | W1 y W2 muestran carga, vacío y envío; el resultado del filtro no se anuncia (H-05) |
| 2 | Relación con el mundo real | Cumple | Sala, cama, mantenimiento y motivo son términos del personal |
| 3 | Control y libertad | Cumple | «Cancelar» en W2–W4; «Recargar» y «Volver a la lista» en W5 |
| 4 | Consistencia y estándares | No cumple | El error de W2 queda bajo «Nombre», aunque la anotación dice «junto al campo» (H-01) |
| 5 | Prevención de errores | No cumple | «Inactiva» y «Mantenimiento» tienen el mismo botón y no muestran el impacto en la capacidad (H-04) |
| 6 | Reconocer antes que recordar | Parcial | Se muestra el estado actual; el asterisco no tiene leyenda (H-02) |
| 7 | Flexibilidad y eficiencia | Cumple | Buscador por código de sala o cama y filtro por estado |
| 8 | Diseño estético y minimalista | Cumple | Una tarea por pantalla, sin datos de pacientes |
| 9 | Reconocer y corregir errores | Parcial | W5 conserva el motivo; «unos segundos» no dice cuándo reintentar (H-08) |
| 10 | Ayuda y documentación | Cumple | «[?] Ayuda» y textos junto a cada campo |
| 11 | Teclado (2.1.1, 2.1.2) | No cumple | No se define cómo se cierra W4 con teclado ni dónde queda el foco (H-06) |
| 12 | Foco (2.4.3, 2.4.7, 2.4.11) | No cumple | 0 menciones de «foco» en los 8 archivos de la semana 8 (H-06) |
| 13 | Contraste de texto (1.4.3) | Cumple | Texto 17.06:1, enlace activo 5.17:1, error 6.47:1, etiqueta 10.35:1 |
| 14 | Contraste de componentes (1.4.11) | No cumple | Borde de campo `#cbd5f5` a 1.46:1, cuando el mínimo es 3:1 (H-07) |
| 15 | Uso del color (1.4.1) | No cumple | El error de W2 solo se distingue por el rojo (H-01); los estados no tienen paleta definida (H-09) |
| 16 | Etiquetas e instrucciones (1.3.1, 3.3.2, 4.1.2) | Parcial | Los radios de W4 no tienen un nombre de grupo (H-03); asterisco sin leyenda (H-02) |
| 17 | Mensajes de error y de estado (3.3.1, 3.3.3, 4.1.3) | Parcial | Los textos sugieren qué hacer, pero el error no está asociado al campo (H-01) y el filtro no se anuncia (H-05) |
| 18 | Prevención de errores (3.3.4) | No cumple | El cambio a «Inactiva» se confirma igual que uno reversible (H-04) |

## 3. Hallazgos

| ID | Dónde | Problema | Criterio | Gravedad | Evidencia |
|---|---|---|---|---|---|
| H-01 | W2, línea 7 | El error «Ya existe una sala con el código A» solo es rojo, aparece bajo «Nombre» y no está asociado al campo «Código» | 1.4.1, 3.3.1; heurísticas 4 y 9 | 3 | `<color:red>` en la fila siguiente a «Nombre *» |
| H-02 | W2–W4, campos con «*» | El asterisco marca lo obligatorio, pero ninguna pantalla lo explica | 3.3.2; heurística 6 | 1 | Líneas 5 y 6 de W3 |
| H-03 | W4, líneas 5 y 6 | Los radios «Mantenimiento» e «Inactiva» no tienen un nombre de grupo: un lector de pantalla los anuncia sin decir que son el nuevo estado | 1.3.1, 4.1.2 | 2 | La etiqueta está en otra celda, solo en la primera fila |
| H-04 | W4, línea 9 | «Confirmar cambio» es igual para mantenimiento que para inactivar, y no dice cuánta capacidad se pierde | 3.3.4; heurística 5 | 3 | Un único botón para ambos destinos |
| H-05 | W1, anotación de la semana 8 | El filtro cambia la tabla sin anunciar cuántas salas quedan | 4.1.3; heurística 1 | 2 | «Enter en el buscador filtra sin recargar» |
| H-06 | W2–W5 | No se define el foco: ni el inicial de W4 y W5, ni el retorno al cerrar, ni el orden de tabulación, ni si Esc cierra W4 | 2.1.2, 2.4.3, 2.4.7 | 4 | 0 menciones de «foco» en la semana 8 |
| H-07 | Campos de W2–W4 (estilo compartido) | Si los formularios reutilizan el estilo del login, el borde del campo no se distingue del fondo | 1.4.11 | 2 | `#cbd5f5` sobre `#ffffff`: 1.46:1 |
| H-08 | W5, línea 10 | «Intenta de nuevo en unos segundos» no dice cuántos ni avisa cuando ya se puede | Heurística 9 | 1 | Texto de la línea 10 |
| H-09 | W1 y W4, estados de cama | No se define cómo se distinguen los estados; el amarillo habitual para «mantenimiento» no se lee | 1.4.1, 1.4.3 | 2 | `#f59e0b` sobre blanco: 2.15:1 |

## 4. Backlog priorizado

La prioridad sale del impacto en salas, wards, camas, estados operativos, capacidad y consistencia del catálogo. P1 = puede duplicar códigos, quitar capacidad por error o impedir la tarea con teclado · P2 = dificulta la tarea · P3 = mejora menor.

| Orden | Hallazgo | Prioridad | Impacto en el catálogo | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|---|
| B-01 | H-06 | P1 | Sin teclado no se puede cambiar ningún estado operativo | Al abrir W4 o W5, el foco va al título; Tab recorre los controles en orden visual sin salir del diálogo; Esc cierra W4 y el foco vuelve a la fila de la cama; anillo de foco de 2 px en `#2563eb` | Recorrido solo con teclado de W1 a W5 sin ratón; anillo de foco ≥ 3:1 (medido: 5.17:1) |
| B-02 | H-04 | P1 | Inactivar por error quita una cama de la capacidad de la sala | Al elegir «Inactiva», W4 muestra «La sala A pasará de 5 a 4 camas disponibles» y el botón dice «Inactivar cama»; con mantenimiento dice «Pasar a mantenimiento» | El texto del botón y el aviso de capacidad cambian según el destino |
| B-03 | H-01 | P1 | Un cÃ³digo duplicado que no se ve rompe la consistencia del catÃ¡logo | Mover el error bajo «Código», empezar con «Error:» y un icono, marcar el campo con `aria-invalid="true"` y `aria-describedby`, y devolverle el foco | Un lector de pantalla anuncia «Código, inválido, Error: ya existe una sala con el código A» |
| B-04 | H-03 | P2 | Con lector de pantalla no se sabe quÃ© estado operativo se elige | Agrupar los radios en `fieldset` con la leyenda «Nuevo estado» | El lector anuncia «Nuevo estado, grupo»; las flechas mueven entre opciones |
| B-05 | H-07 | P2 | Cuesta encontrar los campos de sala y cama | Borde de campo `#64748b` | `contraste.php`: 4.76:1 ≥ 3:1 |
| B-06 | H-09 | P2 | Estados operativos que no se leen en la lista de camas | Cada estado siempre con su texto, más color: disponible `#15803d` y mantenimiento `#b45309`; ocupada e inactiva en `#334155` | `contraste.php`: 5.02:1 y 10.35:1 ≥ 4.5:1; en escala de grises cada estado se sigue leyendo por su texto |
| B-07 | H-05 | P2 | No se sabe cuÃ¡ntas salas o wards quedan al filtrar | Región `aria-live="polite"` con «3 salas encontradas» al filtrar | El lector anuncia el número de resultados sin mover el foco |
| B-08 | H-02 | P3 | Dudas sobre quÃ© datos de la sala o cama son obligatorios | Leyenda «* Obligatorio» al inicio de cada formulario | La leyenda aparece en W2, W3 y W4 antes del primer campo |
| B-09 | H-08 | P3 | Reintentos a ciegas al guardar camas | «Reintenta en 10 s» con cuenta regresiva, y el botón se habilita al terminar | El botón está deshabilitado durante 10 s y luego se habilita solo |

Las correcciones se aplican al prototipo navegable de la semana 11; el criterio verificable de cada una es su prueba de aceptación.

**Bibliografía:** Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design*. Nielsen Norman Group · W3C (2023). *Web Content Accessibility Guidelines (WCAG) 2.2* · W3C (2023). *Understanding SC 1.4.11 Non-text Contrast*.
