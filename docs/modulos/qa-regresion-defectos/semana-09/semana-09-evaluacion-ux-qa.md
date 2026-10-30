# Semana 9 — Evaluación heurística y de accesibilidad (WCAG 2.2 AA) del módulo QA

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Tarea** | Evaluar el diseño con heurísticas y WCAG: teclado, foco, contraste, etiquetas, mensajes y prevención de errores |
| **Entregable** | Checklist completado, al menos 6 hallazgos con evidencia y un backlog priorizado con criterio verificable |

## Entregables

| Pedido | Dónde |
|---|---|
| Checklist completado | [checklist-evaluacion.md](checklist-evaluacion.md): 38 ítems (10 de Nielsen, 22 de WCAG 2.2 AA y 6 propios) y el recorrido de teclado por pantalla |
| ≥ 6 hallazgos con evidencia | **12 hallazgos**, cada uno con su recorte marcado en [evidencia/hallazgos/](evidencia/hallazgos/); el contraste, medido en [contraste-wcag.txt](evidencia/contraste-wcag.txt) |
| Backlog priorizado con criterio verificable | [§5](#5-backlog-priorizado): B-01..B-12 en tres prioridades |

## 1. Resumen

- **Qué se evaluó:** los 5 wireframes, los 4 flujos y el catálogo de mensajes de la semana 8.
- **Checklist:** 21 ✅, 12 ⚠️ y 5 ❌.
- **12 hallazgos.** Los tres más graves:
  1. **H-08:** la bandeja de pendientes contradice dos reglas del propio diseño. El principio «nada se pierde» choca con «cerrar sesión: se perderán 2 resultados». Además, la evidencia **clínica** queda en el equipo si el tester cierra el navegador sin cerrar la sesión: en un equipo compartido del hospital, otra persona podría verla.
  2. **H-01:** «Clasificar» deshabilitado explica por qué solo al pasar el cursor. El documento de la semana 8 promete «al recibir el foco», pero un botón deshabilitado **no recibe el foco**. Quien usa el teclado no puede saber qué le falta.
  3. **H-02:** las etiquetas («Evidencia», «Clasificación») y los títulos de estado tienen **2,85–3,26:1** de contraste, con un mínimo de 4,5:1.
- **Contraste:** el instrumento midió **22 pares** con los colores **tal cual** salen del generador de los wireframes: **12 fallan**. Con la paleta propuesta cumplen los 12 (`contraste.py` termina con 0).

## 2. Método

| Paso | Cómo |
|---|---|
| 1. Heurísticas | Las 10 de Nielsen sobre cada wireframe, sus anotaciones y sus notas |
| 2. WCAG 2.2 AA | Los criterios de la tarea: teclado (2.1.1, 2.1.2, 2.4.1), foco (2.4.3, 2.4.7, 2.4.11, 3.2.1, 3.2.2), contraste (1.4.1, 1.4.3, 1.4.11, 2.5.8), etiquetas (1.1.1, 1.3.1, 3.3.2, 4.1.2), mensajes (3.3.1, 3.3.3, 4.1.3) y prevención de errores (3.3.4, 3.3.7, 3.3.8) |
| 3. Contraste medido | [contraste.py](herramientas/contraste.py): la razón WCAG de los 22 pares de color del generador |
| 4. Teclado | Recorrido simulado en el orden de las anotaciones ([checklist §4](checklist-evaluacion.md#4-recorrido-de-teclado)) |
| 5. Coherencia interna | Cada promesa del documento de la semana 8 se contrastó con su wireframe y con la API 1.1.0 |
| 6. Evidencia | [recortes.py](herramientas/recortes.py) marca en rojo cada hallazgo sobre el PNG del wireframe |

**Severidad (Nielsen):** 0 no es un problema · 1 cosmético · 2 menor · 3 mayor · 4 catástrofe.

**Prioridad:**
- **P1** = severidad 3, o cualquier hallazgo que afecte datos clínicos;
- **P2** = severidad 2;
- **P3** = severidad 1.

## 3. Hallazgos

| Id | Pantalla | Hallazgo | Criterio | Sev. | Evidencia |
|---|---|---|---|---|---|
| H-01 | W4 | «Clasificar» deshabilitado: el motivo solo aparece al pasar el cursor. Un botón deshabilitado no recibe el foco, así que el teclado y el lector de pantalla no llegan al motivo. El documento de la semana 8 prometía lo contrario | 2.1.1, 4.1.2, N-10 | 3 | [h-01](evidencia/hallazgos/h-01-boton-clasificar-sin-motivo.png) |
| H-02 | W2, W4, W1, W3, W5 | Etiquetas, datos de contexto y títulos de estado en #8A8F96: 2,85–3,26:1 (C-01..C-08) | 1.4.3 | 3 | [h-02](evidencia/hallazgos/h-02-contraste-etiquetas.png) |
| H-03 | W2, W1, W4 | Bordes de campos y de las opciones del resultado en #9AA0A6: 2,64:1 (C-11, C-12) | 1.4.11 | 2 | [h-03](evidencia/hallazgos/h-03-contraste-bordes.png) |
| H-04 | W1, W4 | «✓ GO» 4,25:1 y chips PLANIFICADA/NUEVO 3,99:1 (C-09, C-10) | 1.4.3 | 2 | [h-04](evidencia/hallazgos/h-04-contraste-go-chips.png) |
| H-05 | W2 | El resultado son 4 botones sueltos; el elegido se marca con un «●» dibujado. Un lector de pantalla no sabe que es un grupo de opción única ni cuál está elegido | 1.3.1, 4.1.2 | 2 | [h-05](evidencia/hallazgos/h-05-resultado-sin-semantica.png) |
| H-06 | W1 | Los críticos van marcados y deshabilitados con 🔒. El teclado se los salta y el lector no explica que son obligatorios por la matriz | 1.1.1, 4.1.2 | 2 | [h-06](evidencia/hallazgos/h-06-criticos-bloqueados-mudos.png) |
| H-07 | W3 | La bandeja reenvía sola, pero no se define cómo se avisa el resultado (confirmado, rechazado 409). Tampoco se anuncia «adjunto ✓» al validar un archivo | 4.1.3, N-1 | 2 | [h-07](evidencia/hallazgos/h-07-bandeja-sin-aviso.png) |
| H-08 | W3, §8 de la semana 8 | Contradicción. «Nada se pierde» frente a «cerrar sesión: se perderán». «Se cifra y se borra al cerrar la sesión» no cubre cerrar el navegador: la evidencia clínica queda en un equipo compartido | P-4, N-4 | 3 | [h-08](evidencia/hallazgos/h-08-bandeja-contradice-proteccion.png) |
| H-09 | W5 | El diálogo irreversible no define el foco inicial, la tecla Esc, la trampa de foco ni a dónde vuelve el foco | 2.1.2, 2.4.3, 3.3.4 | 2 | [h-09](evidencia/hallazgos/h-09-dialogo-cierre-foco.png) |
| H-10 | W1 | «NO_GO · 2»: un número sin unidad. La anotación dice «2 causas» y la pantalla no lo dice | N-2, N-4 | 1 | [h-10](evidencia/hallazgos/h-10-decision-numero-ambiguo.png) |
| H-11 | W4 | El error del motivo se muestra antes de que el usuario escriba: el rojo permanente se aprende a ignorar | 3.3.1, N-5 | 1 | [h-11](evidencia/hallazgos/h-11-error-antes-de-escribir.png) |
| H-12 | W5, W1, W2 | Se ven códigos de sistema: NO_GO, NO APLICA, «Cerrar con NO_GO» | N-2 | 1 | [h-12](evidencia/hallazgos/h-12-codigos-tecnicos.png) |

## 4. Lo que la evaluación confirmó

- **Sin depender del color:** los estados llevan palabra e icono (1.4.1 ✅).
- **Mensajes que dicen qué hacer:** cada uno indica la acción (3.3.3 ✅). El 422 de alcance incompleto se resuelve con un clic.
- **Reglas del dominio presentes en la UI:** no hay «editar resultado» ni «cerrar defecto» (P-1, P-2).
- **Sin pérdida de lo escrito:** ni ante un 401 ni ante un 422 (3.3.7 ✅).

## 5. Backlog priorizado

Cada ítem tiene un criterio de aceptación que **se puede comprobar**: con un comando, o con una inspección concreta del prototipo de las semanas 10–11.

| Id | Prio. | Corrige | Cambio propuesto | Criterio de aceptación (verificable) |
|---|---|---|---|---|
| B-01 | P1 | H-08 | La bandeja guarda en el equipo solo resultados **sin evidencia clínica**. Un resultado con `DATOS_CLINICOS` sin confirmar se conserva **en memoria**, con el aviso «no cierre esta pestaña». Cerrar sesión con pendientes ofrece [Reenviar ahora] antes de salir | En el prototipo, tras registrar con evidencia clínica sin conexión, el almacenamiento local no contiene bytes de evidencia (inspección de `localStorage`); el mensaje de cierre de sesión ofrece [Reenviar ahora] |
| B-02 | P1 | H-02 | El texto secundario pasa a **#5F6670** | `python herramientas/contraste.py` termina con código 0; C-01..C-08 ≥ 4,5:1 |
| B-03 | P1 | H-01 | Los botones de envío **nunca se deshabilitan**: al activarlos, el foco va al primer campo inválido con su mensaje. Si un botón tiene que estar deshabilitado, el motivo va en un texto visible asociado con `aria-describedby` | En el prototipo, Tab llega a «Clasificar» y Enter con el motivo vacío lleva el foco al motivo con M-14 (recorrido de teclado registrado) |
| B-04 | P2 | H-05 | El resultado es un `radiogroup`: flechas para moverse, `aria-checked`, una sola parada de Tab | El árbol de accesibilidad muestra `role=radiogroup` con 4 `radio` y uno `checked`; axe-core sin violaciones en W2 |
| B-05 | P2 | H-03, H-04 | Bordes **#767C82**; «GO» **#1F6B43**; chips **#8A4A12** | `contraste.py` termina con 0; C-09..C-12 cumplen |
| B-06 | P2 | H-06 | Los críticos se muestran como lista de texto («TC-107 · obligatorio por la matriz de *expediente*»), no como casillas deshabilitadas | El lector lee «obligatorio por la matriz» para cada crítico; ninguna casilla `disabled` en W1 |
| B-07 | P2 | H-07 | Una región `aria-live="polite"` anuncia el adjunto validado y cada reenvío confirmado o rechazado | En el prototipo, el reenvío produce un texto en la región viva («TC-108 registrado») verificable en el DOM |
| B-08 | P2 | H-09 | El diálogo es modal (`role=dialog`, `aria-modal`): foco inicial en **[Volver]**, Esc = Volver, foco atrapado y devuelto a «Cerrar campaña…» | Recorrido de teclado: abrir → el foco está en Volver; Esc cierra; el foco vuelve al botón |
| B-09 | P3 | H-10 | «⛔ NO_GO · 2 causas» | Texto visible con la unidad en W1 |
| B-10 | P3 | H-11 | El error se muestra al salir del campo o al enviar, no antes | El estado inicial de W4 no tiene texto de error |
| B-11 | P3 | H-12 | Etiquetas para personas: «No liberar (NO-GO)», «Liberar (GO)», «No aplica»; el código queda entre paréntesis para soporte | Ningún texto visible con guion bajo en las pantallas del prototipo |
| B-12 | P2 | H-02..H-04 | La paleta corregida se convierte en **tokens** (`--texto-secundario`, `--borde`, `--exito`), y el prototipo usa solo tokens | `grep` de colores hexadecimales en los estilos del prototipo: 0 fuera del bloque de tokens |

**Orden de ejecución:** B-01, B-02 y B-03 (P1) en el prototipo móvil de la semana 10; las P2 y P3 en el navegable de la semana 11, donde se vuelven a medir con axe-core.

## 6. Evidencia

| Archivo | Contenido |
|---|---|
| [contraste-wcag.txt](evidencia/contraste-wcag.txt) | 22 pares: 12 fallan hoy, 0 con la paleta propuesta |
| [evidencia/hallazgos/](evidencia/hallazgos/) | 12 recortes marcados |
| [herramientas/](herramientas/) | `contraste.py` y `recortes.py`, para repetir la medición |

```bash
cd docs/modulos/qa-regresion-defectos/semana-09
python herramientas/contraste.py            # código 0 si las propuestas cumplen
python herramientas/recortes.py <png-de-la-semana-8> evidencia/hallazgos
```
