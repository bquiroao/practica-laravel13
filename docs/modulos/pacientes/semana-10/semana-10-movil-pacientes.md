# Semana 10 — Diseño para movilidad del flujo de Pacientes

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 · Pacientes: registro, edición, búsqueda y detalle

**Proceso:** «registro, búsqueda y actualización segura de un paciente» en teléfonos de 320 a 430 px

| Entregable | Dónde |
|---|---|
| Propuesta responsive de 3–5 pantallas | 5 pantallas en HTML/CSS, abribles en cualquier navegador: [prototipo/m1-buscar.html](prototipo/m1-buscar.html) (§8), con [capturas](capturas/) |
| Reglas de breakpoint | §4, verificadas automáticamente en 39 vistas ([verificacion-responsive.txt](evidencia/verificacion-responsive.txt)) |
| Dos escenarios móviles con decisiones de contenido y error | §9 y §10, con [tiras de capturas](escenarios/) |

## 1. Punto de partida

| Origen | Qué se reutiliza |
|---|---|
| Semana 7 | Componentes Vue: `useBusquedaPacientes`, `useFichaPaciente`, `useGuardarPaciente`, `FichaPaciente`, `MotivoDialog`, `ConflictoVersionDialog` |
| Semana 8 | Flujos por rol, wireframes W1–W6 de escritorio y catálogo de mensajes M-01..M-22 |
| Semana 9 | Backlog BL-01..BL-14: esta propuesta ya los aplica (§12) |
| Semana 5 | `Idempotency-Key` en el registro y `If-Match` en la edición: la base de la recuperación ante cortes (§7) |
| Prototipo de RBAC (semana 10) | La hoja de estilos base y el verificador, para que los dos módulos se vean y se comporten igual en el teléfono |

Cómo abrir el prototipo: doble clic en `prototipo/m1-buscar.html`. Cada pantalla muestra sus estados con `?estado=…`; por ejemplo, `m2-registrar.html?estado=incierto`. Es un prototipo estático: no llama a la API.

## 2. Jerarquía de contenido

En un teléfono cabe poco. Cada pantalla decide qué se ve primero según la tarea del rol.

| Pantalla | Prioridad 1 (siempre visible) | Prioridad 2 (visible sin desplazar en 360 px) | Prioridad 3 (desde 431 px o bajo demanda) |
|---|---|---|---|
| M1 Buscar | Un solo campo «Código, documento o nombre» y «Buscar» | Resultados: nombre y código | Fecha de nacimiento como criterio («Más criterios», plegado) |
| M2 Registrar | El paso actual (uno de tres) y su acción | Lo ya escrito en la revisión | Explicación de por qué se pide el documento |
| M3 Ficha | Nombre (o «Identidad protegida»), estado y código | Identidad: nacimiento, sexo, documento enmascarado | Contacto y «cada cambio queda en la bitácora» |
| M4 Editar | Los campos y «Revisar cambios» | El motivo, **solo** si cambia la identidad | La versión que se está editando |
| M5 Proteger | Las consecuencias y el motivo | La casilla de confirmación | Cómo deshacerlo |

**Por aspecto de la tarea:**

| Aspecto | Decisión en el teléfono |
|---|---|
| Identidad | El código del paciente acompaña al nombre en todas las pantallas: es lo que se dicta y lo que está en la pulsera. |
| Datos demográficos | Fecha con selector nativo (dd/mm/aaaa al ver, ISO al enviar) y sexo con «Desconocido» para emergencias. |
| Búsqueda | Un solo campo; la fecha se pliega. Resultados en tarjetas, no en tabla. |
| Edición | El motivo aparece solo cuando hace falta; la confirmación muestra solo lo que cambió. |
| Duplicados | El aviso ofrece primero la salida segura (abrir la ficha existente) y luego corregir. |
| Confidencialidad | Nada en la URL ni guardado en el teléfono; un protegido se muestra con «Reservado» y la etiqueta «Protegida». |

## 3. Reducción de carga cognitiva

| Técnica | Dónde |
|---|---|
| **Un campo en lugar de seis.** La búsqueda acepta código, documento o nombre en el mismo campo, como ya lo permite `CriterioBusqueda`. | M1 |
| **Divulgación progresiva.** «Más criterios» plegado; datos de prioridad 3 solo desde 431 px. | M1, M3 |
| **Una decisión por pantalla.** El registro se parte en 3 pasos: quién es, documento y contacto, revisión. | M2 |
| **Preguntar solo lo necesario.** El motivo aparece solo si cambia un dato de identidad, con la misma regla del backend. | M4 |
| **Mostrar solo lo que cambia.** La confirmación lista los campos modificados, no la ficha entera. | M4 |
| **Instrucción, no error.** Lo que falta se dice en gris antes de intentar; el rojo, solo después. | M4, M5 |

## 4. Reglas de breakpoint

Mobile-first: la base es 320 px y cada breakpoint **agrega**. Implementadas en [prototipo/estilos.css](prototipo/estilos.css).

| Nombre | Ancho | Cambios |
|---|---|---|
| **XS** | 320–359 px | Nombre corto del hospital («H. R. Oriente»). Todo en una columna. Acción principal fija abajo y navegación inferior. |
| **S** | 360–430 px | Nombre completo del hospital. Es el ancho de diseño de referencia. |
| **M** | 431–767 px | Aparecen los datos de prioridad 3 y el rol en la cabecera. La confirmación «antes → después» pasa a tres columnas. Botones de la hoja en fila. |
| **L** | 768–1023 px | Navegación lateral. La hoja inferior pasa a diálogo centrado. La acción principal deja de estar fija. |
| **XL** | ≥ 1024 px | Escritorio (W1–W6 de la semana 8): los resultados vuelven a ser tabla y la ficha usa dos columnas. |

### 4.1 Reglas transversales (verificadas)

| ID | Regla | Cómo se verifica |
|---|---|---|
| R-T1 | Todo objetivo táctil mide al menos 44 × 44 px (los botones, 48). | `verificar-responsive.cjs` mide cada botón, enlace y campo visible. |
| R-T2 | Los campos usan letra de 16 px o más: iOS no hace zoom al enfocarlos. | Ídem, con `getComputedStyle`. |
| R-T3 | El hospital activo se ve siempre. | Ídem: la cabecera con el hospital tiene altura > 0 en todas las vistas. |
| R-T4 | No hay desplazamiento horizontal. | `scrollWidth - innerWidth ≤ 0`. |
| R-D1 | Ningún enlace lleva datos personales en la URL (`nombre=`, `documento=`, `telefono=`…). | Ídem, revisando todos los `href` (regla propia de Pacientes). |

```text
$ node herramientas/verificar-responsive.cjs prototipo capturas
OK    m1-buscar-base-320.png    desborde=0px  toque<44=0  fuente<16=0  hospital=sí  datos-en-url=0
...
Vistas: 39  Fallan: 0
```

La primera ejecución dio **38 de 39**: en escritorio, los códigos de la tabla de resultados eran enlaces de 72 × 21 px. Se corrigió en el CSS (área de 44 px) y se volvió a medir. La salida final está en [evidencia/verificacion-responsive.txt](evidencia/verificacion-responsive.txt).

## 5. Navegación

| Ancho | Patrón | Por qué |
|---|---|---|
| < 768 px | Barra inferior con **solo las secciones permitidas al rol**: admisión ve Pacientes · Registrar · Más; el clínico, Pacientes · Más. | El pulgar alcanza la parte baja de la pantalla; mostrar lo que el rol no puede usar solo agrega ruido. |
| < 768 px | «‹» en la cabecera para volver; en el registro y la edición, la barra inferior se oculta. | Durante una tarea con datos a medio escribir, no conviene salir con un toque accidental. |
| ≥ 768 px | Navegación lateral. | Hay espacio y se trabaja con teclado. |

El hospital activo es un botón en la cabecera. Cambiarlo limpia la búsqueda, la ficha y cualquier formulario (regla I-8 de la semana 8). Con un formulario a medio llenar conviene además pedir confirmación, como propuso la evaluación de RBAC; queda anotado para el prototipo de la semana 11.

## 6. Tablas, formularios y confirmaciones

| Elemento | En el teléfono | En escritorio |
|---|---|---|
| **Resultados de búsqueda** | Tarjetas: nombre (prioridad 1), código y fecha, documento enmascarado o la etiqueta «Sin documento». Toda la tarjeta es el enlace. | Tabla con encabezados (`<th scope="col">`) y título oculto para el lector. |
| **Ficha** | Tarjetas por bloque: Identidad, Contacto. | Dos columnas. |
| **Formulario de registro** | 3 pasos con barra de progreso; teclado numérico para el documento (`inputmode="numeric"`) y de teléfono (`type="tel"`); fecha con selector nativo; leyenda «Los campos con * son obligatorios». | Un solo formulario (W2). |
| **Confirmación de edición** | Hoja inferior: «Guardar» arriba y «Volver a editar» abajo, donde cae el pulgar. | Diálogo centrado. |
| **Acción sensible (proteger)** | Hoja inferior con velo; el resto de la página inerte. «Proteger» deshabilitado hasta tener motivo y casilla, con el motivo explicado al lado; foco inicial en «Cancelar». | Diálogo centrado (W6). |

## 7. Recuperación ante conexión limitada

### 7.1 Estados de la red

| Estado | Cuándo | Qué ve el usuario |
|---|---|---|
| En línea | Normal | Nada. |
| Lenta | Una petición tarda más de 2 s | Franja amarilla: «Conexión lenta. Seguimos intentando; no cierres esta pantalla.» |
| Sin conexión | El navegador reporta `offline` o fallan 2 peticiones seguidas | Franja oscura con lo que **sí** se puede hacer. Las acciones que escriben se deshabilitan con «· requiere conexión». |
| Resultado incierto | Se envió una escritura y no llegó la respuesta | Aviso específico por operación (§7.3). |

### 7.2 Tiempos y reintentos

| Operación | Aviso «lenta» | Corte | Reintento |
|---|---|---|---|
| Buscar, abrir ficha (GET) | 2 s | 10 s | Botón «Reintentar»; es seguro. |
| Registrar (POST) | 2 s | 20 s | Botón «Reintentar» con la **misma** `Idempotency-Key`. |
| Editar, proteger (PUT) | 2 s | 20 s | **No se reintenta.** Se recarga la ficha y se compara. |

### 7.3 ¿Es seguro reintentar? Medido, no supuesto

`php herramientas/reintento.php` envía dos veces la misma petición al kernel real del Micro-HIS (adaptadores en memoria), como un teléfono que no recibió la primera respuesta:

```text
Operación                       1.er env. Reintento   Respuesta del reintento                    Pacientes
Registrar con Idempotency-Key    201       201         misma respuesta (Idempotent-Replayed)      1
Registrar sin Idempotency-Key    201       409         PACIENTE_EXISTENTE                         1
Registrar provisional sin clave  201       409         POSIBLE_DUPLICADO                          1
Editar (PUT con If-Match)        200       409         VERSION_DESACTUALIZADA                     —
```

Conclusiones que pasan al diseño:

- **El registro siempre lleva `Idempotency-Key`.** Sin la clave tampoco se duplica, gracias a R-02 y R-03, pero el reintento responde con un 409 **engañoso**: el usuario cree que hay otro paciente, cuando es el que acaba de registrar. Con la clave, el reintento devuelve el mismo 201 y el mismo código.
- **Una clave por intento, no por pantalla.** Se genera al pulsar «Registrar paciente» y se reutiliza solo en «Reintentar». Si el usuario corrige los datos, es otro intento y otra clave.
- **El `PUT` nunca se reintenta solo.** El reintento choca con su propio cambio (409). Lo correcto es recargar la ficha: si el cambio está, terminó; si no, se aplica otra vez.

### 7.4 Lo que el diseño **no** hace sin conexión, a propósito

| Lo que no hace | Por qué |
|---|---|
| Guardar fichas o resultados en el teléfono (`localStorage`, IndexedDB o la caché de un *service worker*) | Un teléfono se pierde o se comparte. Los datos de pacientes, y más aún los de un protegido, no deben quedar en el dispositivo (P-3 de la semana 8). Lo que está en pantalla se conserva solo mientras la página esté abierta, marcado con la hora de carga. |
| Encolar registros o ediciones para enviarlos «cuando vuelva la señal» | Un registro diferido no puede verificar duplicados en el momento, y una edición diferida chocaría con la versión. Es mejor decir «requiere conexión» que crear un duplicado más tarde. |
| Buscar con la última lista guardada | Sería buscar en datos viejos y, otra vez, guardarlos en el teléfono. |

## 8. Las 5 pantallas

### M1 · Buscar paciente (todos los roles)

| 360 px | Sin resultados | Sin conexión | Escritorio (1024 px) |
|---|---|---|---|
| ![M1](capturas/m1-buscar-base-360.png) | ![M1 vacío](capturas/m1-buscar-vacio-360.png) | ![M1 sin conexión](capturas/m1-buscar-sin-conexion-360.png) | ![M1 escritorio](capturas/m1-buscar-base-1024.png) |

Un solo campo; el número de resultados se anuncia (`aria-live`) sin mover el foco; «Registrar» fijo abajo solo para admisión.

### M2 · Registrar en 3 pasos (admisión)

| Paso 1 | Paso 3: revisión | Resultado incierto | Duplicado por documento | Coincidencia probable |
|---|---|---|---|---|
| ![](capturas/m2-registrar-paso1-360.png) | ![](capturas/m2-registrar-base-360.png) | ![](capturas/m2-registrar-incierto-360.png) | ![](capturas/m2-registrar-duplicado-360.png) | ![](capturas/m2-registrar-coincidencia-360.png) |

Ante una coincidencia, admisión no puede confirmar. La pantalla da la salida segura y el contacto de supervisión, y advierte que cambiar los datos para evitar el aviso crearía un duplicado (BL-04 de la semana 9, mientras exista la brecha B-2).

### M3 · Ficha (admisión y clínico)

| Completa (admisión) | Reducida (clínico, protegida, 320 px) | Sin conexión | Escritorio |
|---|---|---|---|
| ![](capturas/m3-ficha-base-360.png) | ![](capturas/m3-ficha-reducida-320.png) | ![](capturas/m3-ficha-sin-conexion-360.png) | ![](capturas/m3-ficha-base-1024.png) |

Sin conexión, la ficha que ya estaba abierta sigue visible con la hora de carga; «Editar» se deshabilita con su motivo.

### M4 · Editar (admisión)

| Formulario | Motivo (cambio de nombre) | Confirmación | Conflicto de versión |
|---|---|---|---|
| ![](capturas/m4-editar-base-320.png) | ![](capturas/m4-editar-motivo-360.png) | ![](capturas/m4-editar-confirmar-320.png) | ![](capturas/m4-editar-conflicto-320.png) |

### M5 · Proteger identidad (supervisor)

| Hoja inferior (360 px) | Tableta (768 px) | Sin conexión | Éxito |
|---|---|---|---|
| ![](capturas/m5-proteger-base-360.png) | ![](capturas/m5-proteger-base-768.png) | ![](capturas/m5-proteger-sin-conexion-360.png) | ![](capturas/m5-proteger-exito-360.png) |

## 9. Escenario 1 · Admisión en emergencias registra con señal débil (360 px)

![Escenario 1](escenarios/escenario-1-admision-emergencias-360.png)

**Contexto:** Marta, de admisión de emergencias, registra a una paciente desde un teléfono en la sala de espera. La señal va y viene.

| Paso | Decisión de contenido | Decisión ante el error |
|---|---|---|
| 1. Busca por nombre y no aparece | El vacío ofrece «Registrar» solo después de buscar (P-1). | — |
| 2. Paso 1: quién es | Solo 4 campos; la fecha con selector nativo; «Desconocido» disponible si no lo sabe. | Los errores van junto al campo y no se pierde lo escrito. |
| 3. Paso 3: revisa | Resumen con el documento enmascarado. | «Corregir datos» vuelve al paso 1 sin borrar nada. |
| 4. Red lenta | Franja «Conexión lenta… no cierres esta pantalla»; botón «Registrando…» deshabilitado. | Evita el doble toque. |
| 5. No llega la respuesta | «No sabemos si se registró. Puedes reintentar sin riesgo.» | Reintento con la **misma** `Idempotency-Key` (medido en §7.3: 201 repetido, 1 paciente). |
| 6. Éxito | El mismo código P-000123, con «Abrir ficha». | Si la primera petición ya había llegado, el usuario no lo nota. |

## 10. Escenario 2 · Admisión corrige un teléfono con red inestable (320 px)

![Escenario 2](escenarios/escenario-2-admision-edicion-320.png)

**Contexto:** Marta actualiza el teléfono de una paciente desde un teléfono pequeño. Al mismo tiempo, un compañero en escritorio corrige otro dato de la misma ficha.

| Paso | Decisión de contenido | Decisión ante el error |
|---|---|---|
| 1. Abre la ficha | Nombre, estado y código arriba; la versión leída (3) se guarda en memoria. | — |
| 2. Cambia el teléfono | La pista dice que el teléfono no pide motivo; no aparece el campo de motivo. | — |
| 3. Confirma | La hoja muestra solo el teléfono, tachado → nuevo. | — |
| 4. No llega la respuesta | «No sabemos si se guardó. Recarga la ficha para comprobarlo.» | **No** se reintenta el `PUT`: el reintento daría 409 contra su propio cambio (§7.3). |
| 5. El compañero guardó antes | «Otra persona modificó esta ficha.» | «Ver la versión nueva y reaplicar mis cambios»: se carga la versión 4 y el teléfono queda marcado para revisar, en memoria, sin portapapeles (BL-02). |

## 11. Trazabilidad

| Decisión móvil | Regla / requisito | Respaldo |
|---|---|---|
| Un solo campo de búsqueda | R-10, CU-02 | `CriterioBusqueda::desde()` acepta código, documento o nombre |
| Nada en la URL ni en el teléfono | R-04, RNF-10 | Regla R-D1 verificada; P-3 de la semana 8 |
| Reintento seguro del registro | R-02, RNF de disponibilidad (semana 5) | `Idempotency-Key`; medido en §7.3 |
| Sin reintento del `PUT` | R-06 | `If-Match`; medido en §7.3 |
| Motivo solo si cambia la identidad | R-07 | `Texto::normalizar` (BL-01) |
| Vista reducida y «Reservado» | R-11, RF-13, RF-14 | `Paciente::detalle()` |
| Hoja de proteger con motivo y casilla | R-11, RF-12 | `PoliticaConfidencialidad` |

## 12. Backlog de la semana 9 aplicado

| BL | Corrección | Dónde se ve |
|---|---|---|
| BL-01 | Motivo con la misma regla del backend | M4: «Lucía» → «María» pide motivo; el teléfono, no |
| BL-02 | Reaplicar en memoria, sin portapapeles | M4, estado conflicto |
| BL-04 | Salida segura de admisión ante una coincidencia | M2, estado coincidencia |
| BL-05, BL-06 | Contraste: `#5F6670` y `#7D838A` | `estilos.css` |
| BL-07 | `type="date"` | M1, M2 |
| BL-08 | Resultados anunciados | M1 (`aria-live`) |
| BL-09 | Sexo «Desconocido» | M2, paso 1 |
| BL-10 | Cómo deshacer la protección | M5 |
| BL-11 | Motivo como instrucción, no como error | M4 |
| BL-12 | «Revisar el número de documento» | M2, estado duplicado |
| BL-13 | Ayuda «?» con nombre accesible | M1 |
| BL-14 | Leyenda de «*» | M2, paso 1 |
| BL-03 | El supervisor ve el código real del candidato | **No aplica en estas pantallas**: el flujo móvil de coincidencias es el de admisión. Queda para el prototipo de la semana 11. |

## 13. Brechas

Siguen abiertas las de la semana 8: B-1 (cambiar el estado), B-2 (pedir revisión de una coincidencia) y B-3 (historial de la ficha). El móvil no agrega brechas nuevas: todo lo que muestra el prototipo usa operaciones del contrato 1.1.0.

## 14. Verificación y limitaciones

```bash
# desde docs/modulos/pacientes/semana-10
node herramientas/verificar-responsive.cjs prototipo capturas   # → Vistas: 39  Fallan: 0 (requiere puppeteer-core y Chrome)
php herramientas/reintento.php                                  # → la tabla de §7.3
```

- **Prototipo estático.** Las pantallas muestran estados fijos (`?estado=`); no llaman a la API. La lógica real, como la clave de idempotencia o el `If-Match`, se midió contra el Micro-HIS con `reintento.php`.
- **Medido en un navegador de escritorio** con viewport de teléfono (Chrome headless), no en dispositivos reales. Falta probar en iOS Safari y en Android con teclado en pantalla.
- **Un solo evaluador**, sin usuarios reales de admisión o enfermería.
