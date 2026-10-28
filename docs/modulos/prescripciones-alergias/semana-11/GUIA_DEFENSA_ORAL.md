# Guía de defensa oral — Semana 11 (prototipo navegable)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-11-prototipo-prescripciones.md](semana-11-prototipo-prescripciones.md)

## 1. Resumen en 60 segundos

Construí un prototipo navegable, para escritorio y móvil, del flujo completo: preparar, verificar, emitir, consultar y anular. Se abre con `prototipo/index.html`, sin servidor.

Cada pantalla sale de una respuesta de una **API simulada que reproduce el kernel real del Micro-HIS**. No lo supongo: `paridad.cjs` corre 42 peticiones en los dos y compara las respuestas y el estado final. Da 0 diferencias. Con un defecto inyectado, da 4.

El **error crítico** es el que importa en este módulo: entre la verificación y la emisión, enfermería registra una alergia nueva. La pantalla abierta no se entera, pero el servidor vuelve a verificar al emitir y **bloquea**. La interfaz lo dice claro, conserva el borrador y marca el ítem.

El recorrido automático hace 82 comprobaciones con axe en 25 pasos y en las dos vistas: 0 fallas. Antes encontró un defecto que la semana 10 no había visto: en el teléfono, el último botón quedaba siempre debajo de la barra fija.

## 2. Orden sugerido

1. Abrir el prototipo y hacer en vivo el camino feliz: P-002 con Cefalexina → advertencia → justificar → emitida (§2).
2. El error crítico: P-005, «Más» → registrar la alergia → volver → Emitir → bloqueado (§3).
3. Correr `paridad.cjs` y mostrar el paso 14 (el kernel real hace lo mismo) y el control con el defecto (§1).
4. La recuperación: «Perder la próxima respuesta» → incierto → Reintentar → una sola prescripción (§3.1).
5. Los defectos que encontró la construcción, sobre todo el 2 (§7), y lo que el prototipo enseñó de la API (§9).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué la pantalla no actualiza la franja sola? | Porque así es la realidad: una pantalla abierta no sabe lo que otra persona registra. La seguridad no puede depender de la pantalla. Por eso el servidor reverifica al emitir (R-03). Tras el bloqueo, la franja sí se recarga. |
| ¿Cómo sabe que la API simulada se comporta como la real? | Con `paridad.cjs`: mismos datos (lo comprueba), 42 peticiones y la comparación de cada respuesta, de las prescripciones guardadas y de las trazas. El control demuestra que la herramienta sí detecta diferencias. |
| ¿Qué pasa si la sesión vence justo al emitir? | Se abre un diálogo sobre la página. Al renovar, se repite la **misma** petición con la misma clave: hay una sola prescripción (pasos 08–09). |
| ¿Por qué farmacia abre por número? | La API del módulo no tiene una operación para listar; la cola de farmacia es de otro módulo. Lo dejé escrito (A-4). |
| ¿axe con 0 fallas significa que es accesible? | No. axe solo cubre lo automatizable. El defecto 2 (un botón inalcanzable) pasó axe y las 46 vistas de la semana 10. Lo encontró un recorrido que pulsa como una persona. Tampoco lo probé con un lector de pantalla real. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Que la franja se actualice sola» | Se podría consultar el Expediente al abrir la verificación. Ayuda, pero no reemplaza la reverificación del servidor: la carrera sigue existiendo entre la verificación y el clic. |
| «Mostrar el nombre del alérgeno sin consultar el Expediente» | Es la propuesta A-3 para la semana 12: agregar `alergeno_nombre` al hallazgo, un cambio compatible del contrato. |
| «Agregar otro paso al recorrido» | Se agrega a `recorrer()` con su `comprobar()`: toma la captura, corre axe y verifica en las dos vistas. |
