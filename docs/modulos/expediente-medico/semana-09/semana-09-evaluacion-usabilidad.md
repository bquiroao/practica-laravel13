# Semana 9 — Evaluación de usabilidad y accesibilidad del flujo UX del Expediente médico electrónico base

| | |
|---|---|
| **Tarea Odoo** | 2511 — Semana 9: Evaluación del diseño, usabilidad y accesibilidad |
| **Módulo** | Expediente médico electrónico base |
| **Objeto evaluado** | User flow y wireframes W1–W5 de la semana 8 (commit `46b98c4`, PR #207) y los colores reales del frontend compartido (`AppLayout.vue`, `LoginPage.vue`) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Método

- **Heurísticas:** las 10 de Nielsen, revisadas pantalla por pantalla.
- **WCAG 2.2 nivel AA:** los criterios del enunciado (teclado, foco, contraste, etiquetas, mensajes y prevención de errores), el uso del color y el tiempo límite del acceso de emergencia.
- **Evidencia:** cada hallazgo cita la línea exacta del wireframe ([lineas-wireframes-s8.txt](evidencia/lineas-wireframes-s8.txt)) o una medición. El contraste se mide con [contraste.php](evidencia/contraste.php) (fórmula de luminancia relativa de WCAG, con autoprueba 21:1 y 1:1); su salida está en [salida-contraste.txt](evidencia/salida-contraste.txt).
- **Gravedad** (escala de Nielsen, 0–4) e **impacto clínico**: la prioridad sube si el problema puede abrir o mostrar el expediente de otro paciente, cortar un acceso autorizado durante una emergencia o impedir la tarea a quien usa teclado.
- **Limitación:** hubo un solo evaluador, y Nielsen recomienda de 3 a 5. Los nombres de los wireframes son ficticios. Los problemas del layout compartido (`lang`, título y foco del router) se evaluaron en la semana 9 de Dashboard base y no se repiten aquí.

## 2. Checklist

| # | Criterio | Resultado | Evidencia |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | Parcial | W2 muestra la atención activa y avisa que el acceso se audita; la consulta a otros hospitales no anuncia su estado (H-07) |
| 2 | Relación con el mundo real | Cumple | Expediente, episodio, consentimiento y emergencia son términos clínicos |
| 3 | Control y libertad | Cumple | «Cancelar» en las confirmaciones; «Volver al expediente local» en W3 |
| 4 | Consistencia y estándares | No cumple | «Abrir» abre un expediente existente en la fila y crea uno nuevo en el diálogo (H-02) |
| 5 | Prevención de errores | Parcial | El diálogo avisa que el expediente no se borra (R-04); dos pacientes con nombres parecidos se distinguen solo por 4 dígitos (H-01) |
| 6 | Reconocer antes que recordar | Cumple | La fila muestra si el paciente ya tiene expediente (E3) |
| 7 | Flexibilidad y eficiencia | Cumple | Búsqueda por nombre en W1 y acceso directo a la historia externa desde W2 |
| 8 | Diseño estético y minimalista | Cumple | Solo el alcance autorizado (R-09) |
| 9 | Reconocer y corregir errores | Cumple | W5 dice qué hacer ante E1 y E3, y que la historia local sigue disponible |
| 10 | Ayuda y documentación | Parcial | «Ayuda» en el menú de W1 y W2; W4 no da ejemplos de justificación, aunque la semana 8 los proponía (H-04) |
| 11 | Teclado (2.1.1, 2.2.1) | No cumple | El acceso de emergencia se corta sin aviso ni ajuste del tiempo (H-03) |
| 12 | Foco (2.4.3, 2.4.7, 2.4.11) | No cumple | 0 menciones de «foco» en los 8 archivos de la semana 8 (H-06) |
| 13 | Contraste de texto (1.4.3) | Cumple | Texto 17.06:1, error 6.47:1 y etiqueta 10.35:1 |
| 14 | Contraste de componentes (1.4.11) | No cumple | Borde de campo `#cbd5f5` a 1.46:1, cuando el mínimo es 3:1 (H-08) |
| 15 | Uso del color (1.4.1) | Cumple | Los wireframes no usan el color para transmitir información |
| 16 | Etiquetas e instrucciones (1.1.1, 1.3.1, 3.3.2) | No cumple | El documento enmascarado se lee como «punto, punto…» (H-05); el botón de crear está fuera de la fila del paciente (H-01) |
| 17 | Mensajes de error y de estado (3.3.1, 3.3.3, 4.1.3) | Parcial | Los errores de W5 son claros; la justificación de W4 no sugiere cómo completarla (H-04) |
| 18 | Prevención de errores (3.3.4) | Parcial | Abrir y entrar en emergencia piden confirmación; la confirmación no muestra datos para reconocer al paciente (H-01) |

## 3. Hallazgos

| ID | Dónde | Problema | Criterio | Gravedad | Evidencia |
|---|---|---|---|---|---|
| H-01 | W1, líneas 8 a 12 | «M. López» y «M. López Ruiz» solo se distinguen por los últimos 4 dígitos, y el botón «Abrir expediente para M. López» está fuera de la tabla: se puede crear el expediente del paciente equivocado | 1.3.1, 3.3.4; heurística 5 | 3 | Botón de la línea 11, separado de su fila |
| H-02 | W1, líneas 9 y 12 | «[Abrir]» abre un expediente que ya existe; «[Abrir]» del diálogo crea uno nuevo que no se puede borrar | 3.2.4; heurística 4 | 2 | Misma etiqueta con efectos distintos |
| H-03 | W4, línea 7 | El acceso de emergencia «se cierra solo» a la hora, sin aviso previo: el médico puede perder la historia externa a mitad de la atención | 2.2.1; heurística 1 | 3 | «Acceso por 1 hora como máximo; luego se cierra solo» |
| H-04 | W4, línea 6 | La justificación solo es «obligatoria»: no tiene un mínimo ni ejemplos, y «x» bastaría para abrir un acceso sin consentimiento | 3.3.3; heurística 5 | 2 | Campo libre con «*» |
| H-05 | W1, líneas 8 y 9 | El documento enmascarado «••••-••••-4521» se lee carácter por carácter, sin decir que está oculto | 1.1.1, 1.3.1 | 2 | Viñetas sin texto alternativo |
| H-06 | W1–W5 | No se define el foco: ni el inicial del diálogo de W1 y de W4, ni el retorno al cerrar, ni el anillo de foco | 2.4.3, 2.4.7 | 3 | 0 menciones de «foco» en la semana 8 |
| H-07 | W2, línea 5 y W5, línea 9 | «Ver otros hospitales» consulta la base CENTRAL sin anunciar que está cargando ni el resultado | 4.1.3; heurística 1 | 2 | Botón y error sin estado intermedio |
| H-08 | Campos de W1 y W4 (estilo compartido) | Si los formularios reutilizan el estilo del login, el borde del campo no se distingue del fondo | 1.4.11 | 2 | `#cbd5f5` sobre `#ffffff`: 1.46:1 |

## 4. Backlog priorizado

La prioridad sale del impacto en expediente longitudinal, episodios clínicos, acceso autorizado, integridad y datos sensibles. P1 = puede crear o mostrar el expediente de otro paciente, cortar un acceso autorizado o impedir la tarea con teclado · P2 = dificulta la tarea · P3 = mejora menor.

| Orden | Hallazgo | Prioridad | Impacto clínico | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|---|
| B-01 | H-01 | P1 | Integridad: un expediente creado para el paciente equivocado no se puede borrar (R-04) | Botón «Crear expediente» dentro de cada fila; el diálogo repite nombre, fecha de nacimiento y documento enmascarado | Ningún botón de crear queda fuera de su fila; el diálogo muestra los tres datos |
| B-02 | H-06 | P1 | Sin teclado no se abre el expediente ni se pide un acceso de emergencia | Foco inicial en el título de cada diálogo, Tab sin salir de él, Esc lo cierra y el foco vuelve al botón de origen; anillo de foco de 2 px en `#2563eb` | Recorrido solo con teclado de W1 a W5; anillo ≥ 3:1 (medido: 5.17:1) |
| B-03 | H-03 | P1 | Acceso autorizado: se pierde la historia externa durante una emergencia | Hora de cierre visible y aviso 5 minutos antes con `role="alertdialog"`; para seguir hace falta una nueva justificación, porque R-07 limita cada acceso a 1 hora | Con un reloj fijo en el minuto 55 aparece el aviso; al minuto 60 el acceso se cierra con un mensaje, no en silencio |
| B-04 | H-02 | P2 | Integridad del expediente longitudinal | «Ver expediente» para el existente y «Crear expediente» en el diálogo | Ningún botón dice solo «Abrir»; crear y ver tienen textos distintos |
| B-05 | H-04 | P2 | Acceso autorizado y auditoría de la emergencia | Mínimo de 20 caracteres, contador visible y ejemplos («paciente inconsciente, se necesitan alergias») | Con menos de 20 caracteres no se confirma y el mensaje dice cuántos faltan |
| B-06 | H-05 | P2 | Datos sensibles | `aria-label="Documento terminado en 4521"` y viñetas con `aria-hidden` | El lector anuncia «Documento terminado en 4521» |
| B-07 | H-07 | P2 | Expediente longitudinal y episodios externos | Región `aria-live="polite"` con «Consultando otros hospitales…» y luego «1 episodio externo» o el error de W5 | El lector anuncia el inicio y el resultado sin mover el foco |
| B-08 | H-08 | P2 | Formularios de apertura y emergencia | Borde de campo `#64748b` | `contraste.php`: 4.76:1 ≥ 3:1 |

Las correcciones se aplican al prototipo navegable de la semana 11; el criterio verificable de cada una es su prueba de aceptación.

**Bibliografía:** Nielsen, J. (1994). *10 Usability Heuristics for User Interface Design*. Nielsen Norman Group · W3C (2023). *Web Content Accessibility Guidelines (WCAG) 2.2* · W3C (2023). *Understanding SC 2.2.1 Timing Adjustable*.
