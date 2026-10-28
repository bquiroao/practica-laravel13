# Guía de defensa oral — Semana 10

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-10-movil-pacientes.md](semana-10-movil-pacientes.md) · **Prototipo:** [m1-buscar.html](prototipo/m1-buscar.html)

## 1. Resumen en 60 segundos

> «Adapté el flujo de pacientes a teléfonos de 320 a 430 píxeles con cinco pantallas que se abren en cualquier navegador: buscar, registrar, ficha, editar y proteger identidad. En el teléfono cada pantalla tiene una prioridad: la búsqueda es un solo campo, el registro se parte en tres pasos y el motivo aparece solo cuando cambia la identidad. Definí cinco breakpoints mobile-first y cinco reglas que verifico con un script en 39 vistas: objetivos táctiles de 44 píxeles, letra de 16 en los campos, hospital siempre visible, sin desplazamiento horizontal y ningún dato personal en la URL. Para la conexión limitada no supuse nada: medí contra el Micro-HIS qué pasa al reintentar. El registro con clave de idempotencia se puede reintentar sin duplicar; la edición no, porque choca con su propio cambio, así que se recarga. Y a propósito no guardo pacientes en el teléfono: si se pierde, no se lleva datos. Los dos escenarios muestran esas decisiones con capturas.»

## 2. Orden sugerido de la exposición

1. Abrir `m1-buscar.html` en el navegador con el modo teléfono (F12 → dispositivo) y recorrer M1 → M3 → M4.
2. **Breakpoints** (§4) y la salida del verificador.
3. **Conexión limitada** (§7): la tabla medida de `reintento.php`.
4. **Escenarios** (§9, §10).
5. **Lo que no hace sin conexión, a propósito** (§7.4).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Un solo campo de búsqueda | Menos decisiones en una pantalla chica; el backend ya distingue código, documento y nombre. |
| Registro en 3 pasos | Una decisión por pantalla; el teclado del teléfono tapa la mitad de un formulario largo. |
| No guardar pacientes en el teléfono | Un teléfono se pierde o se comparte. Lo que se ve dura mientras la página está abierta. |
| No encolar escrituras sin conexión | Un registro diferido no puede comprobar duplicados en el momento. Es mejor «requiere conexión» que un duplicado más tarde. |
| `POST` sí, `PUT` no | Medido: el `POST` con clave devuelve la misma respuesta; el `PUT` repetido da 409 contra su propio cambio. |
| 44 px y no 24 | WCAG 2.5.8 pide 24 px, pero en un hospital se usa el teléfono con guantes y caminando. |

## 4. Preguntas probables y respuestas

**¿Qué significa mobile-first?**
Que el CSS base es para el ancho más chico (320 px) y cada `@media (min-width: …)` agrega cosas. Así el teléfono no descarga ni oculta lo que solo sirve en escritorio.

**¿Por qué 16 px en los campos?**
Porque iOS hace zoom automático al enfocar un campo con letra menor a 16 px, y la pantalla se desacomoda.

**Si se corta la red al guardar, ¿cómo sabe el usuario qué pasó?**
No lo sabe, y la pantalla lo dice: «No sabemos si se guardó». En el registro, reintentar es seguro por la clave de idempotencia. En la edición, se recarga la ficha y se ve si el cambio está.

**¿Por qué la búsqueda no queda en la URL?**
Porque la URL queda en el historial del navegador y en los enlaces que se comparten. El verificador comprueba que ningún enlace lleve nombre, documento ni teléfono (regla R-D1).

**¿Qué detectó el verificador?**
En escritorio, los enlaces de la tabla medían 21 píxeles de alto. Se corrigió y se volvió a medir.

## 5. Recorrido de demostración

Desde `docs/modulos/pacientes/semana-10/`:

1. `php herramientas/reintento.php` → la tabla de §7.3.
2. Abrir `prototipo/m2-registrar.html?estado=incierto` en modo teléfono.
3. Abrir `prototipo/m3-ficha.html?estado=reducida` (vista del clínico de una paciente protegida).
4. Si hay Node y Chrome: `node herramientas/verificar-responsive.cjs prototipo capturas` → `Vistas: 39  Fallan: 0`.
