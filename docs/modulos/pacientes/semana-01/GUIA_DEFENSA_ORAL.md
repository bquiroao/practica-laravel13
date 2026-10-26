# Guía de defensa oral — Semana 1

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Pacientes · **Documento:** [semana-01-uml-proceso-pacientes.md](semana-01-uml-proceso-pacientes.md)

## 1. Resumen en 60 segundos

> «Modelé cómo el HIS registra, encuentra y corrige a un paciente sin crear duplicados ni perder información. Al **registrar**, el sistema valida los datos y consulta el índice maestro (MPI): si el identificador ya existe, bloquea; si solo se parece el nombre y la fecha, advierte, y únicamente un supervisor con motivo puede registrar igual. La **búsqueda** exige un criterio mínimo, solo mira el hospital activo y devuelve como máximo 20 resultados con el identificador enmascarado. La **actualización segura** usa tres mecanismos: la versión del registro, para no sobrescribir el cambio de otra persona; un motivo obligatorio para tocar la identidad; y auditoría con valores antes y después en la misma transacción. Los permisos los decide el módulo RBAC. Los tres diagramas comparten identificadores, así que cada regla se puede seguir de uno a otro.»

## 2. Orden sugerido de la exposición

1. **Narrativa de alcance:** el riesgo del duplicado (historia clínica partida en dos) y qué queda fuera, como la fusión de fichas en el MPI.
2. **Casos de uso:** actores, CU-01 a CU-08, y la diferencia entre `include` (CU-05, CU-07, CU-08) y `extend` (CU-06, CU-03).
3. **Actividad:** las tres ramas del `switch` D-03, y en cada una 1 o 2 decisiones y excepciones.
4. **Secuencia:** la actualización con conflicto de versión, de S-46 a S-59.
5. **Matriz de trazabilidad:** escoger una regla, por ejemplo R-06, y ubicarla en los tres diagramas.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| CU-01 **incluye** a CU-05 | Verificar duplicados es obligatorio en todo registro, no opcional. |
| CU-06 **extiende** a CU-01 | Confirmar pese a una coincidencia ocurre **solo** si hay coincidencia probable. La flecha va del caso que extiende al caso base. |
| Identificador igual **bloquea**; nombre parecido **advierte** | Dos personas pueden llamarse igual y nacer el mismo día, pero no compartir DPI. La base ya lo exige con el índice único `mpi_identifiers_active_identity_unique`. |
| El MPI y RBAC son actores secundarios | Son sistemas **externos** al módulo que le prestan un servicio. El repositorio y la bitácora son internos: por eso aparecen en la secuencia y no en casos de uso. |
| Control de versión (R-06) | Si dos usuarios abren la misma ficha, el segundo que guarda recibe 409 en lugar de borrar sin saberlo el cambio del primero. Se envía con la cabecera estándar `If-Match`. |
| La versión se valida antes que los cambios (D-13) | Si la ficha cambió, corregir campos no sirve: hay que recargar primero. |
| Motivo para datos de identidad (R-07) | Cambiar nombre, fecha o DPI puede «convertir» la ficha en otra persona. El motivo deja la justificación en la auditoría. |
| Guardar y auditar en **una** transacción (R-09) | Sin evidencia no hay cambio: si la auditoría falla, se revierte todo (E-06, E-14). |
| 404 idéntico para un paciente de otro hospital (E-08) | No revelar que el paciente existe en otro tenant. |
| Búsqueda acotada y datos mínimos (R-10, R-04) | Minimización de datos: quien busca no necesita ver el DPI completo de 20 personas. |

## 4. Preguntas probables y respuestas

**¿Qué es el MPI y por qué existe?**
El *Master Patient Index* es el índice central que asigna una identidad global (`global_id`) a cada persona, aunque tenga fichas en varios hospitales. Cada hospital guarda su `local_patients` y se vincula con el MPI.

**¿Por qué el identificador se guarda cifrado si hay que buscarlo?**
Se guarda cifrado (`value_encrypted`) y además un *hash* (`search_hash`). Para buscar se calcula el hash del valor ingresado y se compara: así se encuentra la coincidencia exacta sin descifrar toda la tabla.

**¿Qué diferencia hay entre una decisión (D) y una excepción (E)?**
Una decisión es una pregunta del flujo, como ¿datos válidos? o ¿versión vigente?. Una excepción es el final anticipado por error, como 409 o 422. Los resultados (F) son los finales exitosos.

**¿Por qué los finales exitosos se llaman F y no R?**
Porque R ya identifica las reglas de negocio (R-01 a R-10). Usar la misma letra haría ambigua la matriz.

**¿Qué pasa si dos usuarios editan al mismo paciente a la vez?**
Ambos leen la versión 7. El primero guarda y queda la versión 8. El segundo envía `If-Match: 7`, el sistema ve que la vigente es 8 y responde 409 (S-57). Además, el `UPDATE … WHERE versión = vigente` lo protege también en la base de datos.

**¿Por qué no se elimina un paciente?**
Porque otros módulos (admisiones, notas, laboratorio) lo referencian y las tablas usan `ON DELETE RESTRICT`. Se pasa a «inactivo».

**¿La búsqueda también se audita? ¿No es solo lectura?**
Sí. Consultar datos de salud es un acceso que debe quedar registrado. Pero los criterios se guardan resumidos, sin datos personales en claro.

**¿Qué diferencia hay entre 403, 404, 409 y 422 en este modelo?**
403: no tienes permiso. 404: no existe en tu hospital. 409: choca con el estado actual (duplicado, versión, identificador en uso). 422: los datos que enviaste no son válidos.

**¿Qué concepto de orientación a objetos se aplica?**
- **Encapsulamiento:** las reglas del paciente viven en el caso de uso, no en el controlador ni en la interfaz.
- **Responsabilidad única:** el controlador recibe, RBAC autoriza, el caso de uso decide, el repositorio persiste y la bitácora registra.
- **Abstracción:** el caso de uso habla con el MPI y el repositorio por operaciones (`buscarCoincidencias`, `cargar`) sin conocer su implementación.

## 5. Recorrido de demostración

1. Abrir [semana-01-uml-proceso-pacientes.md](semana-01-uml-proceso-pacientes.md) en GitHub o en VS Code con vista previa.
2. Mostrar los casos de uso y señalar el `include` de CU-05 y el `extend` de CU-06.
3. En la actividad, seguir este camino: A-01 → D-01 sí → A-02 → D-02 sí → D-03 Actualizar → A-13 → D-11 sí → D-12 sí → D-13 no → E-10.
4. En la secuencia, mostrar ese mismo camino de S-46 a S-59.
5. En la matriz, ubicar R-06 en D-13/E-10 y en S-47, S-56 a S-59 y S-64.
6. Abrir un `.puml` para demostrar que las fuentes son editables.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| Agregar «Inactivar paciente» | En casos de uso: `usecase "CU-09\nInactivar paciente" as CU09`, `Supervisor --> CU09`, `CU09 ..> CU07 : <<include>>` y `CU09 ..> CU08 : <<include>>`. Agregar su regla y su fila en la matriz. |
| Agregar un actor «Paciente (portal)» que actualiza su teléfono | Actor principal conectado a CU-04 con una restricción: solo datos de contacto, nunca de identidad. |
| Agregar una excepción «MPI no disponible» al registrar | En la actividad, después de A-05: `if (¿MPI responde?) then (no)` → registrar con vínculo pendiente y marcar la verificación para después, o bloquear (decisión a justificar). En la secuencia, un `alt` tras S-09. |
| Buscar también por teléfono | Agregarlo a A-09 y a R-10, y al mensaje S-29. |
| Mostrar el caso 404 en la secuencia | En el bloque 3, un `alt` después de S-54 con `REPO --> CU : no existe` y `404`. |

Para regenerar las imágenes después de un cambio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/pacientes/semana-01/diagramas/*.puml
```

Si no hay Java disponible, se puede pegar el `.puml` en https://www.plantuml.com/plantuml para verlo al momento.
