# Guía de defensa oral — Semana 8

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-08-ux-pacientes.md](semana-08-ux-pacientes.md) · **Wireframes:** [semana-08-wireframes-pacientes.pptx](wireframes/semana-08-wireframes-pacientes.pptx)

## 1. Resumen en 60 segundos

> «Diseñé la experiencia de "registro, búsqueda y actualización segura de un paciente" para los tres roles autorizados: admisión, clínico y supervisor. El principio central es buscar antes de registrar: todos los flujos empiezan en la búsqueda, y solo si el paciente no aparece se ofrece registrarlo, con lo buscado ya escrito. Hice un user flow por rol y seis wireframes anotados: búsqueda, registro, duplicados, ficha, edición e identidad protegida. Cada anotación dice si es un estado, un mensaje, una validación, una regla de interacción, ayuda o protección de datos. Cada mensaje sale de un código de error real del backend, así que la interfaz no promete nada que el servidor no haga. Para la confidencialidad, la búsqueda no va en la URL, la ficha no se guarda en el navegador y un paciente protegido aparece como "Reservado" porque así lo decide el servidor. Al diseñar encontré tres brechas del contrato, que dejé documentadas.»

## 2. Orden sugerido de la exposición

1. **Roles** (§2): quién ve qué, diseñado por permiso.
2. **Flujo de admisión — registrar** (§4.1): buscar → vacío → registrar → cinco salidas.
3. **W1 y W2**: búsqueda con sus estados; formulario con identidad provisional y reintento seguro.
4. **W3**: la decisión más delicada, duplicados.
5. **W5**: motivo, antes/después y conflicto de versión.
6. **W6**: proteger y lo que ve el clínico.
7. **Brechas** (§11): lo que el contrato todavía no permite.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Buscar antes de registrar | La causa más común de duplicados es registrar sin buscar. Precargar lo buscado en W2 hace que el camino correcto sea también el más corto. |
| La búsqueda no va en la URL | `?nombre=Ana López` quedaría en el historial, en marcadores y en enlaces copiados. Es una fuga de datos personales. |
| La acción segura es la principal en W3 | Ante la duda, usar la ficha existente no hace daño; crear un duplicado sí. Por eso tiene el foco inicial. |
| El motivo solo aparece si cambia la identidad | Pedir motivo para cambiar un teléfono sería fricción inútil. R-07 lo exige solo para los datos de identidad. |
| Nunca sobrescribir ante un 409 | Ofrecer «guardar de todas formas» destruiría el trabajo de otra persona. Se ofrece ver la versión nueva y copiar lo escrito. |
| «Reservado» en lugar de un campo vacío | Un campo vacío parece un dato faltante y alguien podría «completarlo». «Reservado» explica que existe pero está protegido. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre un user flow y un wireframe?**
El user flow muestra el **recorrido**: pasos, decisiones y salidas. El wireframe muestra **una pantalla** de ese recorrido: qué elementos tiene y cómo se comportan. Aquí cada nodo del flujo dice qué wireframe le corresponde (W1…W6).

**¿Por qué baja fidelidad?**
Porque en esta etapa importa validar la estructura, los estados y los mensajes, no los colores ni la tipografía. Además se cambia rápido.

**¿Qué es un error recuperable?**
Uno del que el usuario puede salir sin perder su trabajo: un dato mal escrito, un conflicto de versión o una caída de red. En el diagrama aparecen con `detach`: el flujo vuelve al usuario para reintentar.

**Si la UI oculta el botón «Editar», ¿ya está protegido?**
No. Ocultar es comodidad. El servidor vuelve a verificar el permiso en cada operación, así que un usuario que fuerce la petición recibe 403 igual.

**¿Qué pasa si se cae la red al registrar?**
La UI no sabe si se guardó. Como el `POST` lleva `Idempotency-Key`, reintentar con la misma clave devuelve el mismo resultado sin duplicar (semana 5). Por eso M-13 dice «puedes reintentar sin riesgo».

**¿Y al editar?**
El `PUT` no se reintenta solo: si se aplicó, el reintento con la versión vieja daría 409 y confundiría. Se recarga la ficha para ver qué quedó (M-14).

## 5. Recorrido de demostración

1. Abrir el `.pptx` en modo presentación y recorrer W1 a W6, leyendo una anotación de cada tipo.
2. Abrir el user flow de admisión-registrar y seguir el camino «sin resultados → registrar → 409 POSIBLE_DUPLICADO → W3».
3. Mostrar en el catálogo (§6) que M-08 y M-09 salen de `PACIENTE_EXISTENTE` y `POSIBLE_DUPLICADO`, y abrir `PoliticaDuplicados.php` para mostrar que esos códigos existen.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| «Agrega un estado a W4» (por ejemplo, ficha en modo solo lectura por mantenimiento) | Un mini estado más en W4, su mensaje en §6 y su fila en la matriz de §8. |
| «¿Cómo diseñarías marcar a un paciente como fallecido?» | Es la brecha B-1: hace falta un campo `estado` en el contrato. La pantalla sería un diálogo como W6: consecuencias, motivo y confirmación. |
| «Haz que admisión pueda pedir revisión de un duplicado» | Es la brecha B-2: hace falta una operación nueva (una solicitud que vea el supervisor). En W3, el texto de ayuda se convertiría en un botón. |
