# Guía de defensa oral — Semana 11

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-11-prototipo-rbac.md](semana-11-prototipo-rbac.md)

## 1. Resumen en 60 segundos

> «Construí un prototipo navegable del flujo de RBAC. Es una sola aplicación web que se abre con doble clic y se adapta a escritorio y móvil.
>
> No es un dibujo: tiene rutas protegidas, un guard que pregunta al PDP, una caché de decisiones y un servidor simulado. Comprobé que ese servidor responde igual que nuestro Micro-HIS en PHP: misma secuencia de 33 solicitudes, 33 respuestas iguales.
>
> El camino feliz: Carlos asigna Enfermería a Luis, eligiéndolo por nombre, y Luis registra signos vitales.
>
> El error crítico: a Ana le revocan el rol mientras tiene la ficha abierta. La pantalla todavía le muestra «Guardar», pero el servidor vuelve a autorizar y lo niega. Ana recibe una referencia sin ningún motivo, y Carlos la pega en Auditoría y ve «rol no vigente».
>
> Las 32 capturas las tomó un script que recorre el prototipo con clics y teclado y corre axe en cada pantalla. La primera vez encontró 24 fallas de accesibilidad; las corregí y ahora da cero.»

## 2. Orden sugerido de la exposición

1. **Mapa de navegación (§4):** rutas, permisos y guard.
2. **Demostración en vivo:** el camino feliz y luego el error crítico (§5 de esta guía).
3. **Láminas móvil y escritorio:** la misma app en los dos tamaños.
4. **Consistencia (§5 del documento):** `datos.js` como fuente única.
5. **Accesibilidad (§6):** axe 0, teclado y las 4 correcciones.
6. **Paridad (§1):** por qué confiar en el servidor simulado.
7. **Hallazgo B-6 (§8):** la interfaz llena la auditoría de ruido.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Prototipo en código, no en Figma | Se puede navegar de verdad: guard, caché, foco y teclado. Además permite probarlo automáticamente y medir accesibilidad. |
| Servidor simulado en el navegador | Se abre sin instalar nada. La paridad 33/33 demuestra que se comporta como el Micro-HIS. |
| Este error crítico, y no un simple 403 | Es el caso donde la UI y el servidor discrepan. Prueba que la UI no decide, que el 403 no revela el motivo y que la denegación es trazable. |
| Herramientas de demostración en el menú | El error crítico necesita dos personas a la vez. El menú lo simula y está rotulado «Demostración». |
| Tras el 403 se borra solo esa decisión de la caché | Si se borrara todo, el guard sacaría a Ana de la ficha antes de que viera la referencia. |
| Una sola fuente para nombres, estados y mensajes | Es la forma de garantizar consistencia entre escritorio y móvil: no hay dos lugares que mantener. |
| B-1 y B-4 simulados con etiqueta visible | Sin ellos la pantalla de roles no funciona. La etiqueta deja claro que no están en el contrato. |

## 4. Preguntas probables y respuestas

**¿Por qué Ana ve «Guardar» si ya no tiene el rol?**
La pantalla consultó al PDP antes de la revocación y guarda esa decisión 30 s. Por eso lo importante es que **al guardar** el servidor vuelve a autorizar, y esta vez niega. Mostrar u ocultar es experiencia de uso; la seguridad está en el servidor.

**¿Qué ve Ana y qué ve el auditor?**
- **Ana:** «No se guardó: acceso denegado», la referencia y cómo compartirla. Nada sobre el motivo, el rol ni el permiso (R-08).
- **El auditor:** con esa referencia ve persona, acción, motivo en lenguaje claro y código técnico (`MEMBRESIA_NO_VIGENTE`).

**¿Cómo sabes que el servidor simulado es fiel?**
`paridad.cjs` manda la misma secuencia de 33 solicitudes al kernel PHP real y a la simulación, y compara estado, código, decisión, campos inválidos, tipo de evento y motivo. Las 33 respuestas son iguales.

**¿Qué es una ruta protegida en el prototipo?**
Toda ruta que declara un permiso pasa por el guard, que pregunta al PDP. Si la respuesta es DENEGADO, la persona va a «Acceso denegado» con la referencia de **esa** denegación, que también queda auditada (EC-1).

**¿Cómo mediste la accesibilidad?**
- axe-core en las 32 pantallas (WCAG 2.2 A/AA).
- En móvil, además, objetivos de 44 px o más y sin desplazamiento horizontal.
- Una prueba de teclado del diálogo: foco inicial en «Cancelar», 12 `Tab` sin salir, `Esc` devuelve el foco, y una revocación completa solo con teclado.

**¿axe es suficiente?**
No. Detecta alrededor de un tercio de los problemas. Por eso hay pruebas de teclado y foco aparte. Falta una prueba con lector de pantalla real, y lo digo en las limitaciones.

**¿Qué es B-6?**
El PDP audita cada DENEGADO, y eso está bien para operaciones reales. Pero la interfaz pregunta al PDP solo para decidir qué mostrar, y eso genera de 1 a 5 «denegaciones» por persona sin que nadie intente nada. Propongo un endpoint de permisos efectivos que no audite, solo para la interfaz.

## 5. Demostración en vivo (5 minutos)

1. Abrir `prototipo/index.html`. Entrar como **Carlos** → Roles → buscar «Luis» → «＋ Asignar rol» → Enfermería → Revisar → Asignar. Señalar la referencia y que el aviso no desaparece.
2. Avatar → «Cambiar de usuario» → **Luis** → la tarjeta Pacientes → Guardar → «Signos vitales registrados».
3. Cambiar a **Ana** → escribir `#/roles` en la URL → «No tienes acceso…».
4. Volver a Inicio → Pacientes → avatar → «Otra sesión: Carlos revoca Enfermería de Ana» → Guardar → 403 con referencia → Copiar.
5. Cambiar a **Carlos** → Auditoría → pegar → «Motivo: rol no vigente».
6. F12 → modo dispositivo 360 px → repetir un paso para mostrar la versión móvil.
7. Opcional: abrir «Revocar rol» y usar solo teclado (Tab, Espacio, Enter, Esc).

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Cambia el nombre de un rol» | Una línea en `DATOS.roles` (`datos.js`). Cambia en escritorio, en móvil y en la auditoría a la vez. |
| «Agrega una persona de demostración» | Agregar a `semilla.usuarios`, `personas` y `tokens`, y a la lista `demo` de `vIngresar`. |
| «Muestra la falta de conexión» | Avatar → «Simular sin conexión»: aparece la barra oscura y las operaciones no se envían. |
| «Muestra el resultado incierto de la semana 10» | Avatar → «Perder la próxima respuesta» → asignar → «No sabemos si se asignó» → Reintentar → «Ya estaba asignado». |
| «Corre las pruebas» | `node herramientas/paridad.cjs` y `node herramientas/recorrido.cjs` |
