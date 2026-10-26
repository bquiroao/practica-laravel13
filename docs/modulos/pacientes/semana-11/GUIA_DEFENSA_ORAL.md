# Guía de defensa oral — Semana 11

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-11-prototipo-pacientes.md](semana-11-prototipo-pacientes.md) · **Prototipo:** [index.html](prototipo/index.html)

## 1. Resumen en 60 segundos

> «Construí un prototipo navegable de Pacientes que se abre con doble clic y funciona igual en escritorio y en teléfono: es una sola aplicación responsive. Tiene una API simulada que aplica las mismas reglas que el Micro-HIS, y lo demuestro: un script manda las mismas 33 peticiones al prototipo y al kernel real en PHP, y las 33 respuestas coinciden. El camino feliz es buscar, registrar y corregir un teléfono, con un corte de red en el medio que se recupera sin duplicar. El error crítico es intentar registrar otra vez a una paciente con identidad protegida: el sistema lo bloquea sin revelar quién es, supervisión sí la encuentra y el clínico ve la ficha reducida. Otro script recorre todo como una persona, en escritorio y en móvil, y en cada paso verifica el texto, corre axe y mide los objetivos táctiles: 34 comprobaciones sin fallas. Al construir la edición encontré una brecha del contrato, B-4: el PUT exige la ficha completa, pero el detalle no la devuelve.»

## 2. Orden sugerido de la exposición

1. Abrir `prototipo/index.html` y entrar como **Marta (admisión)**: buscar «ramirez» → registrar → en **Más**, «perder la próxima respuesta» → registrar → reintentar.
2. Editar el teléfono y mostrar que no pide motivo; cambiar un nombre y mostrar que sí lo pide.
3. **Error crítico:** buscar «sofia morales» → registrarla → aviso sin código. Cerrar sesión, entrar como **Rosa (supervisión)** y buscar `2233445560101`.
4. Entrar como **Luis (enfermería)** y abrir `P-000005`: ficha reducida.
5. Mostrar el mapa de navegación y la salida de `paridad.cjs`.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Una sola app para escritorio y móvil | Las mismas rutas, estados y mensajes en los dos: la consistencia sale gratis. Los breakpoints cambian la presentación, no el flujo. |
| API simulada con paridad probada | Un prototipo que «inventa» respuestas puede mostrar algo que el backend no hace. La paridad lo impide para 33 situaciones. |
| El error crítico es un duplicado de una protegida | Cruza identidad, búsqueda, duplicados y confidencialidad. Es el caso donde un error de diseño tiene más consecuencias. |
| «Esta persona ya está registrada» sin código | El servidor manda `RESERVADO`. Mostrar «RESERVADO» como código sería críptico; mostrar más revelaría a la paciente. |
| El corte de red en el camino feliz | En un hospital es normal. Demuestra que el reintento con `Idempotency-Key` funciona de punta a punta. |

## 4. Preguntas probables y respuestas

**¿Por qué no usaste Figma?**
Porque un prototipo en HTML es navegable de verdad: se puede probar con teclado y lector de pantalla, y sus respuestas se pueden comparar con el backend. Además, el mismo CSS es el de la propuesta móvil de la semana 10.

**¿Qué es la paridad?**
Ejecutar la misma secuencia de peticiones en dos implementaciones y comparar los resultados. Si la API simulada y el kernel PHP responden igual en los 33 pasos, el prototipo no promete nada que el backend no cumpla.

**¿Qué detecta axe y qué no?**
Detecta problemas que se pueden comprobar automáticamente: contraste, etiquetas, roles ARIA. No detecta si un mensaje se entiende o si el orden del foco tiene sentido; eso lo cubren el recorrido de teclado y las pruebas con usuarios.

**¿Qué es la brecha B-4?**
El `PUT` reemplaza toda la ficha, pero el `GET` devuelve el documento enmascarado y el nombre en un solo campo. Si la interfaz no reenvía el documento, el servidor entiende que se quiere quitar. El prototipo lo resuelve pidiendo confirmar el documento; la solución real es un cambio de contrato.

**¿Por qué el clínico ve «Reservado» y no un campo vacío?**
Un campo vacío parece un dato que falta, y alguien podría «completarlo». «Reservado» dice que el dato existe pero está protegido.

## 5. Recorrido de demostración

Desde `docs/modulos/pacientes/semana-11/`:

1. `node herramientas/paridad.cjs` → `Pasos: 33  Iguales: 33  Difieren: 0`.
2. `node herramientas/recorrido.cjs` → `Comprobaciones: 34  Fallan: 0` (regenera las capturas).
3. Mostrar `capturas/movil/12-ya-registrada-reservado.png` junto a `capturas/escritorio/12-ya-registrada-reservado.png`.
