# Semana 9 — Evaluación del diseño, usabilidad y accesibilidad

Módulo: **Alergias clínicas y alerta visual en expediente**

Se evaluaron dos cosas:
- el **diseño** de la semana 8 (user flows y W1 a W5);
- las **vistas reales** del Micro-HIS (MVC de la semana 4), que es donde los hallazgos se pueden reproducir.

El checklist completo está en [checklist-evaluacion.md](checklist-evaluacion.md), con 18 criterios.

## 1. Método

| Técnica | Cómo | Reproducir |
|---|---|---|
| Heurísticas de Nielsen | Recorrido de los flujos del médico y de enfermería | Leer W1 a W5 y las vistas |
| Teclado y foco | Navegar con Tab y Mayús+Tab, enviar un formulario con error | `php -S 127.0.0.1:8042 -t public` → `/pacientes/PAC-00451/alergias/nueva` |
| Contraste (calculado, no a ojo) | Fórmula de luminancia relativa de WCAG 2.2 | `python herramientas/contraste.py` → `RESULTADO: 12 pares, 0 fallas` |
| Revisión automática | axe-core 4.10 en Chromium sin interfaz | En tu equipo: extensión **axe DevTools** en Chrome sobre las dos páginas |

## 2. Hallazgos

| ID | Hallazgo | Evidencia | Criterio | Impacto en seguridad clínica |
|---|---|---|---|---|
| H-01 | La severidad se muestra en inglés: «Ibuprofeno (moderate)» | `alerta.php` imprime `severidad->value`. Se ve abriendo `/pacientes/PAC-00451/alerta` | Nielsen 2 | **Alto:** «life_threatening» puede no entenderse en una urgencia |
| H-02 | Tras un error 422 el foco queda en `BODY`. El campo con error no tiene `aria-invalid` ni `aria-describedby`; el mensaje solo aparece arriba | Enviar fecha 2099-01-01. Comprobado con Chromium: `document.activeElement` = `BODY` y `aria-invalid` = null | WCAG 3.3.1, 2.4.3 | **Alto:** quien usa lector de pantalla no sabe qué campo corregir |
| H-03 | Se guarda una alergia `life_threatening` sin pantalla de confirmación | `ControladorAlergia::registrar` guarda en el primer POST | Nielsen 5 | **Alto:** un clic equivocado en la severidad cambia el nivel de alerta |
| H-04 | El formulario no dice que basta el nombre **o** el código; se descubre al recibir el error | `formulario.php` no tiene ninguna ayuda previa | Nielsen 6 | Medio |
| H-05 | `role="alert"` envuelve toda la sección. Al cargar, el lector de pantalla lee la lista y el formulario completos | `alerta.php` | WCAG 4.1.3 | Medio: ruido que hace saltar el anuncio |
| H-06 | La página de error usa `javascript:history.back()` | `error.php` | Nielsen 3 | Bajo |
| H-07 | No hay ayuda contextual para «reconocer» ni para «sin registro» | `alerta.php` | Nielsen 10 | Medio |
| H-08 | No hay estado de carga; con base lenta la página queda en blanco | Render en servidor | Nielsen 1 | Bajo en MVC; **alto** en el cliente Vue (W2-4) |
| H-09 | La ayuda dice «AAAA-MM-DD», pero el selector nativo muestra dd/mm/aaaa | `formulario.php` + Chrome en español | WCAG 3.3.2 | Bajo |
| H-10 | La entrada web no tiene autenticación: usa un usuario demo fijo (DEC-07), así que cualquiera ve «Registrar alergia» | `bootstrap.php`, `armarWeb()` | Protección de datos | **Alto** si se desplegara; la API sí exige token |

Lo que **cumple** con evidencia: contraste contraste (12/12; texto mínimo 7.75:1, no textual mínimo 5.20:1), color no exclusivo, etiquetas `<label for>`, foco visible, orden de tabulación y axe-core con 0 violaciones.

## 3. Backlog priorizado

Prioridad = impacto en alergias, severidad, alerta y seguridad clínica × facilidad.

| ID | Prioridad | Hallazgo | Corrección propuesta | Criterio verificable |
|---|---|---|---|---|
| B-01 | P1 | H-01 | Mostrar la severidad con su etiqueta en español (`PresentacionAlerta::severidadVisible`, semana 7) | `/pacientes/PAC-00451/alerta` no contiene `moderate` ni `life_threatening`; una prueba nueva lo busca en el HTML |
| B-02 | P1 | H-02 | Al volver con 422: `aria-invalid="true"` y `aria-describedby` en el campo, mensaje junto a él y `autofocus` en el primero | En Chromium, tras enviar fecha 2099-01-01: `activeElement.id === 'inicio'` y `aria-invalid === 'true'` |
| B-03 | P1 | H-03 | Paso de confirmación (W4) cuando la severidad es `severe`, `life_threatening` o `unknown` | Un POST sin `confirmado=1` con esas severidades responde la vista de confirmación y no inserta (0 filas nuevas) |
| B-04 | P1 | H-10 | Exigir sesión o token también en la entrada web; enfermería no recibe el formulario | GET `/alergias/nueva` sin sesión → 401; con rol enfermería → 403 |
| B-05 | P2 | H-05 | `role="alert"` solo en el título de la franja; la lista sin rol | El árbol de accesibilidad tiene un solo nodo `alert` con el texto del título |
| B-06 | P2 | H-04 | Ayuda visible «Llene al menos uno: nombre o código» (W3-1) | El texto aparece en el formulario antes de enviar |
| B-07 | P2 | H-07 | Enlaces «¿Por qué debo confirmar?» y «¿Qué significa?» (W1-4, W2-1) | Los dos enlaces existen y abren texto de ayuda |
| B-08 | P3 | H-08 | Esqueleto de carga en el cliente Vue (W2-4) | Con la red lenta del navegador, nunca se ve el estado vacío antes de la respuesta |
| B-09 | P3 | H-06 | Enlace real al expediente del paciente en lugar de `history.back()` | `error.php` no contiene `javascript:` |
| B-10 | P3 | H-09 | Quitar «AAAA-MM-DD» de la etiqueta del campo `type=date` | La etiqueta dice solo «Fecha de inicio» |

Los P1 tocan directamente la seguridad clínica: lenguaje de la severidad, corrección de errores, confirmación del riesgo vital y control de acceso. Se toman en cuenta en el prototipo de la semana 11.
