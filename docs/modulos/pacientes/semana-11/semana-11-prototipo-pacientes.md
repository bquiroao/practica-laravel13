# Semana 11 — Prototipo navegable desktop y móvil del flujo de Pacientes

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 · Pacientes: registro, edición, búsqueda y detalle

| Entregable | Dónde |
|---|---|
| Archivo del prototipo | [prototipo/index.html](prototipo/index.html): se abre con doble clic, sin servidor ni instalación |
| Mapa de navegación | [diagramas/semana-11-mapa-navegacion.png](diagramas/semana-11-mapa-navegacion.png) (§4) |
| Capturas desktop/móvil del camino feliz | [capturas/escritorio/](capturas/escritorio/) y [capturas/movil/](capturas/movil/), pasos 01–10 (§2) |
| Capturas desktop/móvil del error crítico | Las mismas carpetas, pasos 11–15 (§3) |

## 1. Qué es el prototipo

Una sola aplicación web (HTML, CSS y JavaScript sin dependencias) que se adapta de 320 px a escritorio con los breakpoints de la semana 10. No hay una versión «móvil» y otra «desktop»: son **las mismas rutas, los mismos estados y los mismos mensajes**.

| Pieza | Qué hace |
|---|---|
| [`app.js`](prototipo/app.js) | Rutas con `#/…`, vistas, formularios, diálogo y anuncios para el lector de pantalla. No contiene reglas de la ficha. |
| [`api-simulada.js`](prototipo/api-simulada.js) | La API de Pacientes en el navegador: mismas rutas, mismo orden de reglas, mismos códigos HTTP y de negocio que el Micro-HIS. |
| [`datos.js`](prototipo/datos.js) | Tokens y permisos de `config.php`, más los pacientes de demostración (todos ficticios). |
| [`estilos.css`](prototipo/estilos.css) | La hoja responsive de la semana 10, con los colores corregidos en la semana 9. |

**La API simulada no se inventa respuestas.** `herramientas/paridad.cjs` ejecuta la misma secuencia de 33 peticiones contra la API simulada y contra el **kernel real del Micro-HIS** (PHP), y compara la respuesta de cada paso:

```text
$ node herramientas/paridad.cjs
IGUAL    1. Camino feliz: admisión busca «ramirez» (no existe)   PHP: 200 total=0
IGUAL    3. Camino feliz: reintento con la misma clave           PHP: 201 P-000006 v1 repetida
IGUAL   11. Error crítico: admisión la registra otra vez         PHP: 409 PACIENTE_EXISTENTE
...
Pasos: 33  Iguales: 33  Difieren: 0
```

Salida completa: [evidencia/paridad.txt](evidencia/paridad.txt). Cubre el camino feliz, el error crítico y los errores de validación, permisos, versión, tenant, autenticación y rutas.

### 1.1 Cómo usarlo

1. Abrir `prototipo/index.html` en Chrome, Edge o Firefox. Para ver el móvil: F12 → modo dispositivo (360 × 740).
2. Elegir el rol: **Marta López (Admisión)**, **Luis Ortiz (Enfermería)** o **Rosa Díaz (Supervisión)**.
3. En **Más** se puede simular la red: lenta (3 s), sin conexión, o «perder la próxima respuesta» (el servidor procesa, pero la respuesta no llega).
4. Datos para probar: buscar `ramirez` (no existe), `lopez` (dos resultados), `P-000005` (paciente protegida) o el DPI `2233445560101`.

Los datos viven solo en memoria: al recargar la página, todo vuelve al estado inicial. Nada se guarda en el navegador ni viaja en la URL.

## 2. Camino feliz: buscar, registrar y corregir un teléfono

**Rol:** admisión. **Aspectos:** búsqueda, identidad, datos demográficos y edición.

| # | Paso | Escritorio (1280 px) | Móvil (360 px) |
|---|---|---|---|
| 02 | Busca «ramirez»: no existe | ![](capturas/escritorio/02-busqueda-vacia.png) | ![](capturas/movil/02-busqueda-vacia.png) |
| 03 | Paso 1 · ¿Quién es? | ![](capturas/escritorio/03-paso1.png) | ![](capturas/movil/03-paso1.png) |
| 05 | Paso 3 · Revisa | ![](capturas/escritorio/05-revision.png) | ![](capturas/movil/05-revision.png) |
| 06 | Se pierde la respuesta: «No sabemos si se registró» | ![](capturas/escritorio/06-registro-incierto.png) | ![](capturas/movil/06-registro-incierto.png) |
| 07 | Reintenta con la misma clave: el mismo código, sin duplicado | ![](capturas/escritorio/07-ficha-registrada.png) | ![](capturas/movil/07-ficha-registrada.png) |
| 08 | Corrige el teléfono: no pide motivo | ![](capturas/escritorio/08-editar.png) | ![](capturas/movil/08-editar.png) |
| 09 | Confirma antes → después | ![](capturas/escritorio/09-confirmar.png) | ![](capturas/movil/09-confirmar.png) |
| 10 | «Datos actualizados», versión 2 | ![](capturas/escritorio/10-ficha-actualizada.png) | ![](capturas/movil/10-ficha-actualizada.png) |

El corte de red del paso 06 es parte del camino feliz a propósito. Así se ve que la recuperación medida en la semana 10 funciona de punta a punta: el reintento devuelve el mismo 201 con `Idempotent-Replayed` y la UI lo dice («Ya se había registrado: no se creó otro»).

## 3. Error crítico: registrar otra vez a una paciente con identidad protegida

**Por qué es crítico:** cruza **cuatro** de los seis aspectos de la tarea.

- **Identidad:** una misma persona con dos fichas.
- **Búsqueda:** por nombre no aparece, a propósito.
- **Duplicados:** el sistema debe detectarla.
- **Confidencialidad:** al detectarla, no debe revelar quién es. Es una víctima con orden de protección.

| # | Paso | Escritorio | Móvil |
|---|---|---|---|
| 11 | Admisión busca «sofia morales»: 0 resultados (protegida) | ![](capturas/escritorio/11-protegida-no-aparece.png) | ![](capturas/movil/11-protegida-no-aparece.png) |
| 12 | La registra con su DPI → 409 `PACIENTE_EXISTENTE` con código `RESERVADO`: «Esta persona ya está registrada… no podemos mostrar quién es… llama a supervisión» | ![](capturas/escritorio/12-ya-registrada-reservado.png) | ![](capturas/movil/12-ya-registrada-reservado.png) |
| 13 | Supervisión la busca por documento: la ve, con la etiqueta «Protegida» | ![](capturas/escritorio/13-supervision-la-encuentra.png) | ![](capturas/movil/13-supervision-la-encuentra.png) |
| 14 | Supervisión abre la ficha completa | ![](capturas/escritorio/14-ficha-supervision.png) | ![](capturas/movil/14-ficha-supervision.png) |
| 15 | El clínico abre la misma ficha por código: reducida, sin «Editar» | ![](capturas/escritorio/15-ficha-clinico-reducida.png) | ![](capturas/movil/15-ficha-clinico-reducida.png) |

**Qué comprueba el recorrido en el paso 12:** que la pantalla **no** contiene `P-000005` ni el teléfono de la paciente. En el paso 15, que no contiene «Sofía», «Morales», el teléfono ni el botón «Editar datos». Y en todos los pasos, que la URL no lleva nombre, documento ni teléfono.

**Decisiones de la pantalla 12:**
- El código `RESERVADO` no se muestra como si fuera un código. Se traduce a una explicación: «ya está registrada, por confidencialidad no podemos mostrar quién es».
- No hay «Abrir ficha» ni «Revisar el documento», porque el documento está bien. Hay una salida segura y la instrucción explícita de **no cambiar los datos para evitar el aviso**: es la forma más común de crear un duplicado (hallazgo H-03 de la semana 9).

## 4. Mapa de navegación

![Mapa de navegación](diagramas/semana-11-mapa-navegacion.png)

Fuente: [`diagramas/semana-11-mapa-navegacion.puml`](diagramas/semana-11-mapa-navegacion.puml). En rojo, los estados de error que detienen el flujo; en amarillo, los recuperables. Las rutas protegidas por permiso llevan entre corchetes el permiso que exigen.

| Ruta | Pantalla | Roles |
|---|---|---|
| `#/ingresar` | Elegir rol | Todos |
| `#/pacientes` | Buscar (inicio) | Todos |
| `#/pacientes/nuevo` | Registrar en 3 pasos | Admisión, supervisión |
| `#/pacientes/P-…` | Ficha completa o reducida | Todos |
| `#/pacientes/P-…/editar` | Editar + confirmación en diálogo | Admisión, supervisión |
| `#/mas` | Sesión y red simulada | Todos |

## 5. Consistencia de roles, estados y mensajes

| Elemento | Admisión | Clínico | Supervisión |
|---|---|---|---|
| Navegación | Pacientes · Registrar · Más | Pacientes · Más | Pacientes · Registrar · Más |
| Vacío de la búsqueda | Ofrece «Registrar paciente nuevo» | Sugiere buscar por código o documento | Igual que admisión |
| Ficha de un protegido | No la ve por nombre; por código, reducida | Reducida | Completa, con la etiqueta «Protegida» |
| `PACIENTE_EXISTENTE` de un protegido | «Ya está registrada», sin código | (no registra) | El código real y «Abrir ficha» |
| `POSIBLE_DUPLICADO` | Salida segura y supervisión de turno | (no registra) | Motivo y «Registrar como otra persona» |
| Editar | Si la ficha no está reducida ni restringida | Nunca | También fichas restringidas |

**Estados presentes en todas las pantallas:** carga (esqueleto + `aria-busy`), vacío, éxito (aviso enfocado y anunciado), error recuperable (con la acción siguiente), sin conexión (franja y acciones deshabilitadas) y resultado incierto.

**Mensajes:** los mismos textos del catálogo de la semana 8, con las correcciones de la semana 9. Por ejemplo: el motivo como instrucción y no como error, «Revisar el número de documento» y la explicación de cómo encontrar a un protegido.

## 6. Accesibilidad

`herramientas/recorrido.cjs` recorre los dos flujos con Chrome headless, como lo haría una persona (clics, escritura y teclado), en escritorio y en móvil. En cada uno de los 15 pasos:
- comprueba el texto esperado y el prohibido;
- corre **axe-core** (WCAG 2.2 A/AA);
- en móvil, mide el desborde y los objetivos táctiles de al menos 44 px.

```text
$ node herramientas/recorrido.cjs
OK    escritorio 01-ingresar                    axe=0
...
OK    movil      15-ficha-clinico-reducida      axe=0 desborde=0 toque<44=0
Comprobaciones: 34  Fallan: 0
```

Salida completa: [evidencia/recorrido.txt](evidencia/recorrido.txt).

| Criterio | Cómo lo cumple el prototipo |
|---|---|
| 2.4.1 Saltar bloques | «Saltar al contenido», primer elemento enfocable. |
| 2.4.2 Título de página | `document.title` cambia en cada ruta. |
| 2.4.3 Orden del foco | Al navegar, el foco va al `<h1>` de la pantalla nueva. |
| 2.4.7 Foco visible | Anillo de 3 px `#3D4F6B` (blanco sobre la barra). |
| 2.1.2 / diálogo modal | El resto de la página queda `inert`. El recorrido comprueba que 8 `Tab` seguidos no salen del diálogo. `Esc` lo cierra y devuelve el foco. |
| 3.3.1 / 3.3.3 Errores | `aria-invalid` y el mensaje asociado con `aria-describedby`; el foco va al primer campo con error. |
| 4.1.3 Mensajes de estado | Regiones `aria-live` para resultados, éxito y errores (BL-08 de la semana 9). |
| 1.4.3 Contraste | 0 infracciones de axe. |

**Lo que encontró el recorrido y se corrigió:**
1. **Contraste del avatar de admisión:** texto blanco sobre `#2E8B87` = 4.07:1, por debajo de 4.5:1. Se oscureció a `#1F6B66` (6.26:1).
2. **Una condición de carrera:** si el usuario cambiaba de pantalla mientras la búsqueda cargaba, la respuesta tardía escribía en un elemento que ya no existía y la página lanzaba un error. Ahora la respuesta se descarta si la pantalla cambió.
3. **Capturas tomadas antes de tiempo:** una captura móvil salió con el esqueleto de carga. El recorrido ahora espera a que no quede ningún `aria-busy` antes de comprobar.

## 7. Mejores prácticas aplicadas

| Práctica | Dónde |
|---|---|
| Mobile-first y una sola base de código | `estilos.css`: 320 px de base y 5 breakpoints (semana 10) |
| Buscar antes de registrar | El vacío de la búsqueda lleva al registro; el registro recuerda lo buscado |
| Formularios cortos por pasos, teclado adecuado (`inputmode`) y fecha nativa | Registro |
| Confirmación solo de lo que cambia | Diálogo de edición |
| La acción segura como principal | «Volver a Pacientes», «Es la misma: buscar su ficha» |
| Reintento seguro e idempotente; nunca reintentar el `PUT` solo | Registro y edición |
| Privacidad por diseño: nada en la URL ni en el navegador; documento enmascarado | Todo el prototipo; comprobado por el recorrido |
| La interfaz no decide reglas; la paridad prueba que la API simulada es fiel | `paridad.cjs` |

## 8. Hallazgo: editar exige datos que el detalle no devuelve (brecha B-4)

Al construir la edición apareció un problema del **contrato**, no de la interfaz:

- `PUT /api/v1/pacientes/{código}` **reemplaza la ficha completa**: hay que enviar nombres, apellidos y documento.
- `GET /api/v1/pacientes/{código}` devuelve el nombre **en un solo campo** y el documento **enmascarado**.

Consecuencias, comprobadas en el paso 8 de la paridad:
- **Si el `PUT` no envía el documento**, el servidor entiende «quitar el documento». Como eso cambia la identidad, responde 422 `MOTIVO_REQUERIDO`. Si además llegara un motivo, la ficha **perdería su documento**.
- **La UI no puede separar nombres y apellidos con certeza.** El prototipo supone que las dos últimas palabras son los apellidos. Para el backend el nombre completo normalizado no cambia, pero los campos guardados sí podrían quedar mal partidos.

**Solución provisional en el prototipo:** pedir que se confirme el número de documento con el documento del paciente («Termina en 0105…»). Funciona, pero agrega fricción.

**Corrección propuesta:** que en el `PUT` un campo **ausente** signifique «sin cambio» (como ya ocurre con `identidad_protegida` desde la semana 6) y que el detalle devuelva `nombres` y `apellidos` por separado. Queda como candidata al cambio solicitado de la semana 12.

## 9. Brechas del contrato

| ID | Brecha | Estado |
|---|---|---|
| B-1 | No se puede cambiar el estado (inactivo, fallecido) | Abierta (semana 8) |
| B-2 | No hay flujo para que admisión pida revisión de una coincidencia | Abierta; el prototipo da el contacto de supervisión |
| B-3 | El historial de una ficha no se expone en la API | Abierta |
| **B-4** | **El `PUT` exige la ficha completa, pero el detalle no la devuelve** | **Nueva (§8)** |

## 10. Verificación y limitaciones

```bash
# desde docs/modulos/pacientes/semana-11 (requiere PHP 8.2+, Node, puppeteer-core, axe-core y Chrome)
node herramientas/paridad.cjs     # → Pasos: 33  Iguales: 33  Difieren: 0
node herramientas/recorrido.cjs   # → Comprobaciones: 34  Fallan: 0 (regenera las capturas)
```

- **La API es simulada.** Su fidelidad está probada con la paridad, pero solo para las 33 situaciones de la secuencia. Una regla no cubierta podría diferir.
- **Un solo hospital por usuario.** Cambiar de hospital no está en el prototipo; la regla I-8 queda descrita en la semana 8.
- **No incluye proteger una identidad.** Esa pantalla está en el prototipo estático de la semana 10 (M5). En este prototipo la paciente ya está protegida desde la semana 6.
- **axe-core detecta cerca de un tercio de los problemas de accesibilidad.** Faltan pruebas con NVDA o TalkBack y con usuarios reales.
