# Guía de defensa oral — Semana 10

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-10-movil-rbac.md](semana-10-movil-rbac.md)

## 1. Resumen en 60 segundos

> «Adapté el flujo de RBAC a teléfonos de 320 a 430 px con un prototipo HTML que se puede abrir en el navegador: 5 pantallas, varios estados cada una, capturadas en 5 anchos.
>
> En el teléfono se hace una cosa a la vez. Asignar un rol pasó de un formulario de 4 campos a 3 pasos de una decisión cada uno, y la persona se elige por nombre, no por identificador. Lo que nunca se recorta es lo que sostiene la autorización: el hospital activo, la referencia de una denegación y los permisos que se ganan o se pierden.
>
> Las reglas de breakpoint no son solo texto: un script verifica en las 31 vistas que no haya desplazamiento horizontal, que todo botón mida al menos 44 px, que los campos usen 16 px y que el hospital siempre se vea.
>
> Para la conexión limitada medí, en vez de suponer, si es seguro reintentar: revocar sí, asignar sin fecha de fin sí, pero asignar con fecha de fin duplica la membresía. Por eso en ese caso el teléfono pide verificar antes de reintentar.»

## 2. Orden sugerido de la exposición

1. **De W a M (§1):** la tabla de equivalencias y quién usa el teléfono.
2. **Jerarquía P1/P2/P3 (§2):** «lo que nunca se recorta».
3. **Breakpoints (§4):** mostrar M1 a 320, 360 y 1024 px, y correr el verificador.
4. **Conexión limitada (§7):** correr `reintento.php` y explicar R-C1.
5. **Escenario 1:** Ana, la denegación y la referencia compartida sin datos del paciente.
6. **Escenario 2:** Carlos, el resultado incierto y el 409 interpretado como éxito.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Mobile-first (base 320 px) | Lo esencial se diseña con el menor espacio; los breakpoints solo agregan. Así no se olvida nada en el teléfono pequeño. |
| 3 pasos para asignar | Una decisión por pantalla reduce la carga cognitiva. El paso 3 completo es la confirmación, que es la última barrera contra dar permisos a otra persona. |
| Hoja inferior para revocar, con «Cancelar» abajo | El pulgar cae abajo. El camino seguro debe ser el más fácil de tocar. |
| Navegación solo con secciones permitidas; ninguna si hay una sola | Menos opciones visibles. Además no se anuncia que existen secciones prohibidas. |
| No guardar en cola cambios de permisos sin conexión | Un permiso debe cambiar cuando el administrador lo decide y con el contexto que ve. Si se envía más tarde, puede caer en otro hospital o en otro momento. |
| No guardar datos clínicos en el teléfono | Un teléfono se pierde o se presta. Se conservan en memoria mientras la pantalla siga abierta, y se dice. |
| El 409 tras reintentar se muestra como éxito | La UI sabe que es un reintento de lo mismo: «ya estaba asignado» es la verdad para el usuario. |
| «Compartir referencia» sin datos del paciente | El teléfono comparte por canales fuera del HIS. Solo viajan la referencia y el hospital. |

## 4. Preguntas probables y respuestas

**¿Por qué los breakpoints son 360, 431, 768 y 1024?**
- 360 px es el teléfono más común; debajo de 360 el nombre del hospital no cabe y se abrevia.
- 430 px es el teléfono grande que pide la tarea; desde 431 cabe una línea más de datos sin empujar la acción.
- 768 px es la tableta.
- 1024 px es el escritorio que ya teníamos.

**¿Cómo verificas que las reglas se cumplen?**
Con `verificar-responsive.cjs`. Abre cada pantalla en Chrome con viewport móvil emulado y mide: desbordamiento horizontal, tamaño de cada objetivo táctil, tamaño de letra de los campos y que el hospital sea visible. En la primera corrida encontró un botón de 40 px.

**¿Qué pasa si se corta la red justo después de asignar?**
La pantalla dice «No sabemos si se asignó» y muestra lo que se envió.
- Sin fecha de fin, reintentar es seguro: si ya estaba, el servidor responde 409 y lo muestro como «ya estaba asignado».
- Con fecha de fin no es seguro, porque se crearía otra membresía. Ahí el botón principal es «Ver sus roles». La solución de fondo es la cabecera `Idempotency-Key` (brecha B-5).

**¿El teléfono puede autorizar sin conexión con la caché?**
No. La caché de 30 s solo sirve para mostrar u ocultar botones. Sin red no se ejecuta nada, y las acciones dicen «requiere conexión».

**¿Por qué Ana vio «Guardar» si ya le habían revocado el rol?**
Porque la decisión estaba en la caché de 30 s, pedida antes de la revocación. No importa: al guardar, el servidor vuelve a autorizar, deniega y registra. La interfaz nunca es la que decide.

**¿Qué hiciste con la tabla en el teléfono?**
La convertí en una tarjeta por membresía. No usé `display: block` sobre `<table>` porque algunos navegadores pierden la semántica de tabla para el lector de pantalla. Desde 1024 px vuelve la tabla.

**¿Qué limitaciones tiene?**
- Capturas de un navegador emulado, no de teléfonos reales.
- El prototipo es estático: los estados de red se simulan con `?estado=`.
- No probé con VoiceOver ni TalkBack.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/semana-10
php herramientas/reintento.php          # asignar con fin → NO SEGURO
node herramientas/verificar-responsive.cjs prototipo capturas   # Vistas: 31  Fallan: 0
```

En el navegador, abrir `prototipo/m1-roles.html`, pulsar F12 y activar el modo de dispositivo:
- pasar de 320 a 1024 px y ver cómo las tarjetas se convierten en tabla;
- abrir `m4-operacion.html?estado=denegado` y `m2-asignar.html?estado=incierto`.

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Muestra la vista a 375 px» | Agregar `['m1-roles', 'base', 375, 812]` a `VISTAS` y volver a correr el verificador. |
| «Haz más grande el objetivo táctil» | Cambiar `--toque` en `estilos.css` y `MIN_TOQUE` en el verificador. |
| «Agrega un estado “sin roles”» | Un bloque con `data-estado="vacio"` en `m1-roles.html` y su captura. |
| «¿Y si el reintento de revocar da 404 porque nunca existió?» | M-07 dice «Este rol ya no está activo» y recarga la lista. Es correcto en ambos casos: el rol no está activo. |
