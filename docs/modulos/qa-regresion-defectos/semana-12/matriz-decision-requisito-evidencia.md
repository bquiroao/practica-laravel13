# Matriz decisión → RF/RNF → evidencia (semanas 1–12)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión

La [matriz de la semana 6](../semana-06/matriz-decision-evidencia.md) une 23 decisiones de **arquitectura** (D-01..D-23) con su prueba y su mutación. Esta matriz mira desde el otro lado: las **17 decisiones que llegan al usuario** en el prototipo. Para cada una indica:
- el requisito que la pide;
- la decisión de arquitectura que la sostiene en el servidor;
- la evidencia **en el servidor**: una prueba de `micro-his-qa`;
- la evidencia **en la interfaz**: una captura o comprobación del recorrido de esta semana.

Los requisitos son los de la [semana 2](../semana-02/semana-02-lsp-requerimientos-diseno.md): RF-01..RF-14 y RNF-01..RNF-08. Capturas en [capturas/](capturas/) (`e` = escritorio, `m` = móvil); comprobaciones en [cambio-2-despues-verde.txt](evidencia/cambio-2-despues-verde.txt).

## Matriz

| # | Decisión | Sem. | RF / RNF | Arq. | Evidencia en el servidor | Evidencia en la interfaz |
|---|---|---|---|---|---|---|
| U-01 | Un resultado no se edita: se confirma **antes** de registrar | 1, 8, 11 | RF-12 | D-01, D-09 | INT-03, mutación M8 | Diálogo «¿Registrar?» [a04](capturas/movil/a04-confirmar.png), [b03](capturas/escritorio/b03-confirmar-clinica.png) |
| U-02 | FALLA exige una evidencia verificable; un enlace no cuenta | 2, 11 | RF-05, RNF-01 | D-04, D-05 | DOM-09; suite LSP de la semana 2: 4 → 0 violaciones | «Falta la evidencia» [b02](capturas/movil/b02-falta-evidencia-422.png) |
| U-03 | El error 422 lleva el foco al campo que falta | 9, 11 | RF-05 **·** sin RNF (WCAG 3.3.1) | — | — | `foco=sí` en b02 (los dos anchos) |
| U-04 | Si el almacén no confirma, el resultado **no** se da por registrado | 3, 5 | RF-06, RNF-03 | D-06, D-19 | APP-11, `bin/demo-http.sh` | «Pendiente», nunca «Registrado» [b04](capturas/movil/b04-pendiente-sin-red.png) |
| U-05 | El reenvío usa la misma `Idempotency-Key` | 5, 11 | RF-13 | D-08, D-18 | CLI-01..08, APP-15 | [b05](capturas/movil/b05-pendientes.png) → [b06](capturas/movil/b06-reenviado.png): un solo intento |
| U-06 | La evidencia clínica sin confirmar solo vive en memoria | 9, 10, 11 | RNF-04 | — | — (decisión de cliente, B-01 de la semana 9) | Comprobación B4: nada en `localStorage` ni `sessionStorage` |
| U-07 | Caso, versión y build fijos arriba al ejecutar | 8, 10 | RF-03, RF-04 | D-03 | — | R-P1 de la semana 10 (32/32); [a03](capturas/movil/a03-ejecutar-tc107.png) |
| U-08 | Un MAYOR abierto en un caso crítico bloquea el GO (R-15) | 6 | RF-10 | D-22 | CAM-05, `bin/demo-r15.php`, M32 | «⛔ Bloquea» [b07](capturas/movil/b07-trazabilidad-bloquea.png) |
| U-09 | La previsión dice **todas** las causas antes de cerrar | 6, 11 | RF-10, RF-11 | D-23 | API-03 (`bloquea_go` documentado) | «2 causas» en b07 (H-3 de la semana 11) |
| U-10 | El cierre es irreversible: se muestra la consecuencia y Esc vuelve | 8, 11 | RF-10 | D-10 | APP-14 (doble cierre → 409) | [a09](capturas/escritorio/a09-confirmar-cierre.png), [b08](capturas/escritorio/b08-confirmar-nogo.png); Esc devuelve el foco |
| U-11 | Cada rol ve solo sus acciones: solo el Líder QA cierra | 1, 4, 11, 12 | RF-14 | D-11 | APP-09 (403/404) | [a08](capturas/escritorio/a08-trazabilidad-lider.png) con [Cerrar…] frente a [c01](capturas/escritorio/c01-producto-por-caso.png) sin él |
| U-12 | El fondo queda inerte con un diálogo abierto | 11 | **sin RNF** (WCAG 2.4.3, 2.5.8) | — | — | axe-core: 0 violaciones; 4 Tab no salen del diálogo |
| U-13 | Objetivos de 44 px y sin desplazamiento horizontal en 360 px | 10, 11 | **sin RNF** (WCAG 1.4.10, 2.5.8) | — | — | `desborde=0 toque<44=0` en las 22 vistas móviles |
| U-14 | Contraste AA y todos los colores como tokens | 9, 11 | **sin RNF** (WCAG 1.4.3) | — | — | `contraste.py`: 12 → 0 pares que fallan; 0 colores fuera de `:root` |
| U-15 | Una región viva anuncia el reenvío | 11 | RF-13 **·** sin RNF (WCAG 4.1.3) | — | — | «TC-107 registrado» en `#vivo` (b06) |
| **U-16** | **Trazabilidad por requisito; los que bloquean, primero** | **12** | **RF-11** | D-23 | La trazabilidad ya devolvía `requisitos` (semana 3) | [c02](capturas/movil/c02-por-requisito.png): RF-07 primero; foco en la opción elegida |
| **U-17** | **Responsable de producto: solo lectura, y se dice en la vista** | **12** | **RF-14** | D-11 | APP-09: sin `qa.cerrar` → 403 | [c01](capturas/movil/c01-producto-por-caso.png): sin [Cerrar…] ni [Ejecutar]; «Solo lectura» visible en 360 px |

## Lo que mostró la matriz

1. **Cinco decisiones sostienen accesibilidad sin un requisito que las pida:** U-03, U-12, U-13, U-14 y U-15. En U-03 y U-15 el RF cubre el error y el reenvío, pero no el foco ni el anuncio. Las cinco existen porque las pidió la evaluación de la semana 9, no un requisito. **Si mañana se discute quitar el `inert` «porque complica», no hay un requisito que lo defienda.** Propuesta:

   > **RNF-09 · Accesibilidad.** Toda pantalla cumple WCAG 2.2 AA. Se verifica con axe-core en cada paso del recorrido (0 violaciones) y con la navegación solo con teclado.
   >
   > **RNF-10 · Uso en móvil.** De 320 a 430 px: sin desplazamiento horizontal, objetivos ≥ 44 px y fuente ≥ 16 px. Se verifica con `verificar-responsive.cjs`.

2. **RF-11 se cumplía en el servidor y no en la interfaz.** RF-11 pide la trazabilidad «requisito ↔ caso ↔ intento ↔ evidencia ↔ defecto, y los requisitos sin caso». La API devolvía `requisitos` desde la semana 3, pero ninguna pantalla los usaba: la trazabilidad solo se veía **por caso**. La matriz lo mostró al mismo tiempo que llegó el pedido de cambio (U-16). **Sigue abierta** la segunda mitad: los requisitos **sin** caso. El prototipo arma los requisitos a partir de los casos de la campaña, así que nunca puede mostrar uno sin caso; eso pide que la API simulada exponga el alcance de la matriz. Queda como pendiente para la revisión técnica de la semana 13.

3. **Las decisiones de cliente no tienen prueba en el servidor:** U-06, U-07 y U-12..U-15. Es correcto, porque viven en el navegador. Pero su única evidencia es el recorrido de este prototipo, y el prototipo no es el producto. Se anota como riesgo para el plan de SQA de la semana 14.

## Cobertura

| | Cantidad |
|---|---|
| Decisiones | 17 |
| Con RF o RNF que las pida enteras | 12 (U-01, U-02, U-04..U-11, U-16, U-17) |
| Cuya parte de accesibilidad no tiene requisito | 5 (U-03, U-12..U-15; U-03 y U-15 tienen un RF para el resto) |
| Con una prueba del servidor con nombre | 9 (U-01, U-02, U-04, U-05, U-08..U-11, U-17) |
| Con evidencia en la interfaz | 17 (todas) |
| RF del módulo que ninguna decisión de interfaz toca | RF-01, RF-02, RF-08, RF-09: diseñar y aprobar casos, clasificar y corregir defectos no están en el prototipo (sí en el servidor, semana 6, y en los wireframes W1 y W4 de la semana 8) |
