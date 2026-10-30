# Semana 9 — Evaluación heurística y de accesibilidad (WCAG 2.2 AA) de la consola de release

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana (@bquiroao) |
| **Módulo** | QA, pruebas E2E, CI y guía de despliegue final |
| **Adaptación** | Gestión de ejecuciones automatizadas y calidad de versiones |
| **Objeto evaluado** | Wireframes W1–W6, flujos y catálogo de mensajes de la [semana 8](../semana-08/semana-08-ux-qa-e2e.md), contrastados con el contrato 1.1.0 y el código del servidor |

## Entregables

| Entregable | Archivo |
|---|---|
| Checklist completo (41 ítems) | [checklist-evaluacion.md](checklist-evaluacion.md) |
| 14 hallazgos con evidencia | Sección 3 y `evidencia/hallazgos/` |
| Backlog priorizado con criterios verificables | Sección 5 |
| Contraste medido | [contraste.py](herramientas/contraste.py) → [contraste-wcag.txt](evidencia/contraste-wcag.txt) |

## 1. Resumen

El diseño de la semana 8 resuelve bien lo que más importa en este módulo: no se puede aprobar «a mano», el digest se valida y cada error dice qué hacer. La evaluación encontró **14 problemas**:

| Severidad | Cantidad | Hallazgos |
|---|---|---|
| 3 | 3 | H-01, H-02, H-09 |
| 2 | 9 | — |
| 1 | 2 | — |

**Los dos hallazgos más relevantes no son de accesibilidad:**
1. **H-09.** El botón de W4 nombra la decisión de la vista previa («Registrar RECHAZADO»), pero quien decide es el servidor, al registrar. Si entre la vista previa y el clic la ejecución supera las 24 h de antigüedad o cambia la política, se registra otra cosa que la que el botón prometía.
2. **H-10.** Contrastar el catálogo con el código mostró que un formato inválido no es 400 sino **422 `DATOS_INVALIDOS`**, el error más frecuente de W1, y no tiene mensaje.

**En accesibilidad**, 15 de los 28 pares de color no cumplen (todos se arreglan con 5 colores, verificado). Además:
- W3 se refresca sola sin pausa ni anuncio;
- el icono de copiar no tiene nombre y mide la mitad del mínimo.

## 2. Método

| Paso | Cómo |
|---|---|
| 1. Heurísticas | Las 10 de Nielsen sobre cada wireframe, sus anotaciones y sus notas |
| 2. WCAG 2.2 AA | 25 criterios de 7 temas: teclado, foco, tiempo, contraste, etiquetas, mensajes y prevención de errores |
| 3. Contraste medido | [contraste.py](herramientas/contraste.py) calcula la razón WCAG de los 28 pares de color del generador de wireframes |
| 4. Teclado | Recorrido simulado en el orden de las anotaciones ([checklist §4](checklist-evaluacion.md#4-recorrido-de-teclado)) |
| 5. Coherencia con el servidor | Cada mensaje y validación de la semana 8 se contrastó con `openapi.yaml` y con el código (`FormularioEscenario`, `EscenarioE2E::registrar`, `EvaluarGate`) |
| 6. Evidencia | [recortes.py](herramientas/recortes.py) marca en rojo cada hallazgo sobre el PNG del wireframe |

**Severidad (Nielsen):** 0 no es un problema · 1 cosmético · 2 menor · 3 mayor · 4 catástrofe.

**Prioridad:**
- **P1** = severidad 3, o cualquier hallazgo que pueda llevar a registrar una decisión o un despliegue distinto del que el usuario cree;
- **P2** = severidad 2;
- **P3** = severidad 1.

## 3. Hallazgos

| Id | Pantalla | Hallazgo | Criterio | Sev. | Evidencia |
|---|---|---|---|---|---|
| H-01 | W1, W5, W6 | «Registrar escenario», «Registrar despliegue» y «Desplegar» quedan **deshabilitados**. Un botón deshabilitado no recibe foco: con teclado o lector de pantalla no se llega a él ni al motivo | 2.1.1, 4.1.2, N-10 | 3 | [h-01](evidencia/hallazgos/h-01-boton-registrar-deshabilitado.png) |
| H-02 | Todas | Etiquetas, encabezados, datos de contexto, pistas y títulos de estado en #8A8F96: 2,85–3,26:1 (C-01…C-09, C-15) | 1.4.3 | 3 | [h-02](evidencia/hallazgos/h-02-contraste-texto-secundario.png) |
| H-03 | W1, W2, W5 | Placeholder del buscador 2,51:1 (C-10); bordes de campos y casillas 2,64:1 (C-14) | 1.4.3, 1.4.11 | 2 | [h-03](evidencia/hallazgos/h-03-contraste-placeholder-bordes.png) |
| H-04 | W3, W4, W5 | Chips PASA y APROBADO 3,75:1; «✓» 4,25:1; chips EN CURSO, SIN PLAN e INESTABLE 3,99:1 (C-11…C-13) | 1.4.3 | 2 | [h-04](evidencia/hallazgos/h-04-contraste-chips-estado.png) |
| H-05 | W3 | La tabla se reescribe cada 15 s mientras la ejecución está EN_CURSO. No hay forma de pausar, y los cambios («E2E-106 FALLA») no se anuncian. Para un lector de pantalla, la tabla cambia bajo el foco | 2.2.2, 4.1.3, N-1 | 2 | [h-05](evidencia/hallazgos/h-05-refresco-automatico.png) |
| H-06 | W2, W3, W5 | El icono para copiar el digest o la huella no tiene nombre accesible y mide ≈ 14 px, contra un mínimo de 24 px | 1.1.1, 4.1.2, 2.5.8 | 2 | [h-06](evidencia/hallazgos/h-06-iconos-copiar.png) |
| H-07 | W5, W2, W6 | El digest (71 caracteres) se muestra abreviado con «…», también dentro del campo donde se pega. El operador depende del mensaje «difiere en el último carácter» y no puede verificar a ojo **cuál** digest pegó | N-6, P-2 | 2 | [h-07](evidencia/hallazgos/h-07-digest-abreviado.png) |
| H-08 | W5 | La aprobación vence (72 h) mientras el operador instala. No hay aviso previo (por ejemplo, a 2 h) y no está definido si lo cargado (guía, digest, smoke) se conserva tras el 409 `APROBACION_VENCIDA` | 2.2.1, 3.3.7 | 2 | [h-08](evidencia/hallazgos/h-08-vencimiento-durante-instalacion.png) |
| H-09 | W4 | La vista previa calcula la decisión y el botón la nombra. Pero el contrato no tiene vista previa (brecha B-3 de la semana 8): `EvaluarGate` calcula **al registrar**. Si entre la vista y el clic la ejecución cruza las 24 h de antigüedad o cambia la política vigente, se registra otra decisión que la del botón, y la pantalla no define qué muestra entonces | N-1, N-5, 3.3.4 | 3 | [h-09](evidencia/hallazgos/h-09-vista-previa-vs-decision.png) |
| H-10 | W1, W5 | El catálogo dice que un formato inválido es «400 en el servidor». El código muestra que el formulario solo valida tipos, y que el dominio responde **422 `DATOS_INVALIDOS`** con la lista de campos. Ese código, el más frecuente de W1 y W5, no tiene mensaje | P-4, 3.3.3, N-4 | 2 | [h-10](evidencia/hallazgos/h-10-datos-invalidos-sin-mensaje.png) |
| H-11 | W4, W2, W3 | Iconos con significado sin texto: el «✓» de W4 (el «✕» sí dice «no cumple»), el 🔒 de los críticos y el ★ de W3 | 1.1.1, N-4 | 2 | [h-11](evidencia/hallazgos/h-11-iconos-sin-texto.png) |
| H-12 | W6, flujo del operador | El flujo promete «aviso al responsable de release» cuando un smoke falla (M-17), pero ninguna pantalla lo muestra. W6 no tiene el estado REVERTIDO en la línea de tiempo | N-1, P-5 | 2 | [h-12](evidencia/hallazgos/h-12-revertido-sin-pantalla.png) |
| H-13 | W4, W6 | Se ven códigos de sistema: «sigue EN_CURSO», «(TENANT_AJENO)» y «e2e.consultar» | N-2 | 1 | [h-13](evidencia/hallazgos/h-13-codigos-tecnicos.png) |
| H-14 | W3 | Los enlaces «traza», «log» y «[Descargar]» se repiten en cada fila. Un lector de pantalla los lista sin el escenario | 2.4.4 | 1 | [h-14](evidencia/hallazgos/h-14-enlaces-ambiguos.png) |

## 4. Lo que la evaluación confirmó

- **El gate no se negocia en la UI.** No hay «aprobar igual» ni «editar decisión» (P-1).
- **La UI anticipa lo que el servidor exige.** Hay un par para cada regla: críticos 🔒 ↔ `PLAN_INCOMPLETO`, digest en vivo ↔ `DIGEST_DISTINTO`, datos `SINT-` ↔ `DATOS_NO_SINTETICOS` (P-3).
- **No depende del color.** Cada estado lleva palabra (1.4.1).
- **Los mensajes dicen qué hacer** (3.3.3), con la excepción de H-10.
- **Los botones cumplen el tamaño** (≈ 33 px) y el foco está definido.

## 5. Backlog priorizado

Cada ítem tiene un criterio de aceptación que **se puede comprobar**: con un comando, con el código o con una inspección concreta del prototipo de las semanas 10–11.

| Id | Prio. | Corrige | Cambio propuesto | Criterio de aceptación (verificable) |
|---|---|---|---|---|
| B-01 | P1 | H-09 | Agregar al contrato una **vista previa sin efectos**: `GET /versiones/{v}/gate?ejecucion=…`, que usa la misma función pura `QualityGate::evaluar`. El registro devuelve la decisión real; si difiere de la vista previa, la UI la muestra con «La decisión cambió: …» y no la presenta como la elegida. El botón pasa a decir «Registrar la decisión del gate» | Una prueba de aplicación muestra que la vista previa no persiste nada (0 filas nuevas en `evaluaciones`) y coincide con el registro hecho a la misma hora. API-01 la encuentra documentada |
| B-02 | P1 | H-02 | El texto secundario pasa a **#5F6670** | `python herramientas/contraste.py` termina con código 0; C-01…C-09 y C-15 ≥ 4,5:1 |
| B-03 | P1 | H-01 | Los botones de envío **no se deshabilitan**. Al activarlos, el foco va al primer campo inválido con su mensaje. Una acción que la regla no permite (Desplegar sin gate) se muestra activa y responde con el motivo, o como texto asociado por `aria-describedby` | En el prototipo, Tab llega a «Registrar despliegue». Enter con el digest distinto lleva el foco al campo con M-15 (recorrido de teclado registrado) |
| B-04 | P2 | H-10 | Agregar M-22 para 422 `DATOS_INVALIDOS`: «Revise: Id, Requisitos» (con los `campos` de la respuesta), y foco en el primero. Corregir M-02: el formato lo valida el dominio, no el formulario | Cada código que puede devolver una operación de W1–W6 aparece en el catálogo (lista cruzada con `openapi.yaml`). El prototipo muestra M-22 ante la respuesta real del servidor |
| B-05 | P2 | H-03, H-04 | Bordes **#767C82**, placeholder **#6B7178**, verde **#1F6B43** y ámbar **#8A4A12** | `contraste.py` termina con 0; C-10…C-14 cumplen |
| B-06 | P2 | H-05 | W3 se refresca con un botón [Pausar actualización] visible. Una región `aria-live="polite"` anuncia **solo** los cambios («E2E-106: FALLA»), no la tabla completa. La fila enfocada no se reemplaza | En el prototipo, pausar detiene las peticiones (sin tráfico en 30 s). Un resultado nuevo produce un texto en la región viva |
| B-07 | P2 | H-06 | «Copiar» es un botón con texto visible o `aria-label="Copiar digest"`, de 24 × 24 px como mínimo | axe-core sin `button-name` en W2, W3 y W5. La caja del botón mide ≥ 24 px (medida en el prototipo) |
| B-08 | P2 | H-07 | El digest se muestra completo, en monoespaciada y en bloques de 8, con el bloque que difiere resaltado (texto + borde, no solo color) | En el prototipo, el campo muestra los 71 caracteres sin «…» y el bloque distinto lleva el texto «distinto» |
| B-09 | P2 | H-08 | Aviso a 2 h del vencimiento. Ante 409 `APROBACION_VENCIDA`, guía, digest y smoke se conservan, con [Pedir nueva evaluación] | En el prototipo, simulando el 409, los tres campos siguen cargados |
| B-10 | P2 | H-11 | «✓ cumple» con texto; el 🔒 dice «obligatorio (crítico)»; el ★ dice «crítico» | Ningún icono con significado sin texto visible o `aria-label` (inspección del árbol de accesibilidad) |
| B-11 | P2 | H-12 | W6 muestra REVERTIDO en la línea de tiempo («Smoke fallido; producción volvió a 2.4.0») y la lista de versiones lo marca para el responsable de release | En el prototipo, con un despliegue REVERTIDO, W6 lo muestra con su causa |
| B-12 | P3 | H-13 | Textos para personas: «en curso», «Ese recurso es de otro hospital», «todos los que pueden consultar». El código queda solo para soporte | Ningún texto visible con guion bajo ni con `e2e.` en el prototipo (búsqueda en el DOM) |
| B-13 | P3 | H-14 | Enlaces con contexto: «Descargar traza de E2E-102» (o `aria-label` equivalente) | axe-core sin `link-name` ni enlaces de igual nombre con destinos distintos en W3 |
| B-14 | P2 | H-02…H-04 | La paleta corregida se convierte en **tokens** (`--texto-secundario`, `--borde`, `--exito`, `--aviso`) y el prototipo usa solo tokens | `grep` de colores hexadecimales en los estilos del prototipo: 0 fuera del bloque de tokens |

**Orden de ejecución:**
- **P1:** B-02 y B-03 en el prototipo móvil de la semana 10. B-01 necesita un cambio de contrato, así que entra como el cambio solicitado de la semana 12.
- **P2 y P3:** en el navegable de la semana 11, donde se vuelven a medir con axe-core.

## 6. Evidencia

| Archivo | Contenido |
|---|---|
| [contraste-wcag.txt](evidencia/contraste-wcag.txt) | 28 pares: 15 fallan hoy, 0 con la paleta propuesta |
| `evidencia/hallazgos/h-01…h-14` | Recorte de cada hallazgo, marcado en rojo sobre su wireframe |
| [checklist-evaluacion.md](checklist-evaluacion.md) | 41 ítems: 19 ✅, 14 ⚠️ y 8 ❌ |
