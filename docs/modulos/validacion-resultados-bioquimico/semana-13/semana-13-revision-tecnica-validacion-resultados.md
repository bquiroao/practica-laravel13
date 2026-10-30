# Semana 13 — Revisión técnica formal de validación de resultados por bioquímico

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Validación de resultados por bioquímico |
| **Proceso revisado** | Revisión, validación o rechazo de un resultado por bioquímico |
| **Tema** | Calidad del software y revisiones técnicas formales |
| **Autora** | Lopez Perez, Mercedes Azucena |
| **Fecha** | 27 de octubre de 2026 |

## 1. Propósito y dictamen

Esta revisión inspecciona los requisitos y el prototipo de las semanas 1 y 2 con foco en reglas de dominio, contrato API, permisos y tenant, integridad de resultados, revisión bioquímica, validación, rechazo, firma y trazabilidad, y en las dependencias estrictamente necesarias para «revisión, validación o rechazo de un resultado por bioquímico».

**Dictamen de escritorio:** el caso de uso `ReviewPendingLabResult` depende solo de tres contratos (autorización, reloj y repositorio), distingue nueve resultados, exige motivo para rechazar y verifica estado y versión también en la escritura atómica. El runner de la Semana 2 reporta 33 aserciones que pasan (13 criterios CA-01 a CA-13). La evidencia es adecuada para demostrar reglas de aplicación con adaptadores en memoria. **No** acredita un contrato API, un tenant tomado de la sesión autenticada, separación de funciones entre quien captura y quien valida, firma o sello de integridad de la decisión, ni persistencia o auditoría reales. REV13-01 a REV13-03 son bloqueadores antes de cualquier integración multi-tenant o persistente; REV13-04 a REV13-07 requieren decisión de diseño o de los responsables clínicos.

## 2. Alcance y referencias inspeccionadas

### Incluido

- Requerimientos RF-01 a RF-08, RNF-01 a RNF-07 y criterios CA-01 a CA-13 de la Semana 2.
- Código del ejemplo: `despues/ReviewWorkflow.php` e `InMemoryLabResultDecisionRepository.php`, y las pruebas de `verificar.php`.
- Modelo UML y reglas de la Semana 1.
- Dependencias necesarias: autorización por hospital, reloj, repositorio de decisión, y los datos de orden, examen y muestra asociados al resultado.

### Excluido

- Captura y corrección del valor (módulo de ingreso de resultados), reglas bioquímicas, intervalos de referencia y valores críticos.
- Interfaz, endpoints reales, esquema productivo, MFA, publicación y entrega del resultado.
- Calidad de las pruebas de la Semana 15 y cualquier implementación fuera de los artefactos listados.

### Evidencia de referencia

| Artefacto | Resultado de esta inspección |
|---|---|
| `php -l` sobre los 4 archivos PHP del ejemplo | Sin errores de sintaxis |
| `php verificar.php` | 33 aserciones, todas pasan ([evidencia/validacion-semana-13.txt](evidencia/validacion-semana-13.txt)) |
| Búsqueda de `critical`, `range`, `referencia`, `normal` en el código | Sin coincidencias: no hay reglas clínicas codificadas |

## 3. Plan de revisión

| Etapa | Actividad | Producto |
|---|---|---|
| 1. Planificación | Fijar alcance, material, roles y criterios | Este documento |
| 2. Preparación individual | Cada revisor aplica el checklist a su ámbito sin comentarios cruzados | Checklist con estado por ítem |
| 3. Reunión de inspección | El moderador conduce la lectura por requisito; se registran hallazgos sin discutir soluciones | Lista de hallazgos con formato obligatorio |
| 4. Retrabajo | La autora corrige o justifica cada hallazgo | Hallazgos con resolución |
| 5. Seguimiento | El moderador verifica el cierre según criterios de salida | Dictamen final |

Esfuerzo recomendado: lectura individual previa y una reunión de unos 90 minutos como máximo; el material por revisión es de 13 criterios y alrededor de 280 líneas de código.

## 4. Roles y responsabilidades

Este es un ejercicio individual: los roles se describen para una revisión formal real y la autora los ejerce de forma provisional. Una revisión real exige que el autor no modere ni revise su propio trabajo.

| Rol | Responsabilidad | Ámbito |
|---|---|---|
| Autora | Presenta el material, aclara dudas y corrige los hallazgos; no modera ni decide el dictamen | Todo el material |
| Moderador | Conduce la reunión, controla el tiempo, verifica los criterios de entrada y salida y emite el dictamen | Proceso de la revisión |
| Revisor de dominio y bioquímica | Contrasta las reglas de decisión y de rechazo con el criterio profesional; confirma que no se codifican reglas clínicas | Checklist 1–5 |
| Revisor de seguridad y tenant | Revisa permisos, ámbito hospitalario, firma y trazabilidad | Checklist 6–10 |
| Revisor de integración y calidad | Revisa contrato API, dependencias, integridad, pruebas y atomicidad | Checklist 11–15 |
| Escriba | Registra los hallazgos con el formato obligatorio | Reunión |

## 5. Dependencias estrictamente necesarias

| Dependencia | ¿Por qué es necesaria? | Estado en el prototipo |
|---|---|---|
| Autorización de revisión por hospital (`ReviewerAuthorization`) | Solo un bioquímico autorizado decide | Contrato con adaptador de prueba |
| Reloj (`Clock`) | Momento de la decisión reproducible | Contrato |
| Repositorio de decisión (`LabResultDecisionRepository`) | Leer la versión y escribir estado y auditoría de forma atómica | Contrato con adaptador en memoria |
| Datos de orden, examen y muestra del resultado | Contexto mínimo para decidir | Campos del resultado, validados como no vacíos |
| Identidad y tenant de la sesión | Origen confiable del actor y del hospital | **Solo supuesta**; el comando los recibe como datos |

No son necesarias, y no se revisan, las integraciones con entrega, facturación o reportes.

## 6. Checklist de revisión técnica (15 ítems)

Estados: **Cumple**, **Parcial**, **No cumple**, **No verificable** (con el material disponible).

| # | Ítem | Evidencia revisada | Estado | Hallazgo |
|---:|---|---|---|---|
| 1 | Las transiciones permitidas son solo pendiente → validado o rechazado | `ReviewPendingLabResult`, CA-07 | Cumple | — |
| 2 | Rechazar exige motivo no vacío; validar no lo exige | CA-02, CA-03 | Cumple | — |
| 3 | El caso de uso no codifica límites bioquímicos ni juzga normalidad | Búsqueda en el código, RNF-07 | Cumple | — |
| 4 | El valor y la unidad no cambian al decidir | CA-01, CA-02 | Cumple | — |
| 5 | El motivo de rechazo tiene límites y tratamiento definidos | Código | No cumple | REV13-06 |
| 6 | El hospital procede del contexto autenticado, no del cliente | `ReviewCommand::hospitalId` | No cumple | REV13-01 |
| 7 | Existe separación de funciones entre quien captura y quien valida | `LabResult`, sin autor de la captura | No cumple | REV13-02 |
| 8 | El permiso distingue validar de rechazar y define el rol | `canReview(actor, hospital)` | Parcial | REV13-04 |
| 9 | Un resultado de otro hospital no se revela (404 lógico) | CA-11 | Cumple | — |
| 10 | La decisión está firmada o sellada y ligada al valor revisado | `ReviewDecisionRecord` | No cumple | REV13-03 |
| 11 | Hay un contrato API con códigos, errores y esquema | No existe; 9 estados de resultado | Parcial | REV13-05 |
| 12 | Estado y auditoría se confirman juntos y el adaptador revalida versión y estado | CA-09, CA-10, adaptador en memoria | Parcial | REV13-07 |
| 13 | Los errores distinguen denegación, entrada inválida, conflicto y falla | CA-04, CA-08, CA-10, CA-12 | Cumple | — |
| 14 | Las dependencias son las mínimas y se inyectan como contratos | CA-13, RNF-01 | Cumple | — |
| 15 | Las pruebas cubren los requisitos y las trazan a RF/RNF | 33 aserciones, 13 CA | Parcial | REV13-07 |

Resumen: 8 cumplen, 3 parciales, 4 no cumplen. Ningún ítem quedó como no verificable.

### Regla de aceptación del checklist

La revisión se considera **aprobada** cuando no quedan hallazgos bloqueadores abiertos y todo ítem «No cumple» tiene una resolución aceptada o una decisión de alcance firmada por el moderador. «Parcial» exige un plan con responsable.

## 7. Hallazgos de esta inspección

| ID | Severidad | Ítem | Resumen |
|---|---|---|---|
| REV13-01 | Alta (bloqueador) | 6 | El hospital llega en el comando; el tenant no sale de la sesión |
| REV13-02 | Alta (bloqueador) | 7 | No hay separación entre quien captura y quien valida |
| REV13-03 | Alta (bloqueador) | 10 | La decisión no queda firmada ni ligada al valor revisado |
| REV13-04 | Media | 8 | El permiso es un booleano por hospital |
| REV13-05 | Media | 11 | No hay contrato API ni correspondencia de errores |
| REV13-06 | Baja | 5 | El motivo de rechazo no tiene límite ni tratamiento |
| REV13-07 | Media | 12, 15 | La atomicidad y la auditoría solo se demuestran en memoria |

Detalle:

**REV13-01 — Tenant tomado del comando.** `ReviewCommand` incluye `hospitalId` y el caso de uso lo compara con el del resultado. El diseño supone que el valor es confiable, pero no define quién lo calcula. Si lo envía el cliente, la autorización por hospital se puede eludir. *Acción sugerida:* derivar actor y hospital del token de sesión del módulo de autenticación y retirar el hospital de la entrada del cliente.

**REV13-02 — Sin separación de funciones.** `LabResult` no guarda quién capturó la versión, así que el caso de uso no puede impedir que la misma persona capture y valide. *Acción sugerida:* añadir el autor de la captura y decidir con los responsables si se prohíbe la autovalidación.

**REV13-03 — Firma y trazabilidad.** El registro guarda actor y momento, pero no incluye el valor ni su resumen criptográfico, ni una firma. La decisión solo queda ligada por identificador y versión; si el valor cambiara sin cambiar la versión, la decisión no lo detectaría. Además, el estado pasa a validado o rechazado sin cambiar el número de versión. *Acción sugerida:* guardar un resumen del valor y la unidad revisados, y definir qué es la «firma» (sello de sistema o firma electrónica) con un responsable normativo.

**REV13-04 — Permiso demasiado grueso.** `canReview` no distingue validar de rechazar ni expresa un rol. *Acción sugerida:* definir permisos por acción con el módulo RBAC.

**REV13-05 — Contrato API ausente.** Hay nueve resultados internos, pero no una correspondencia con respuestas del servicio ni un esquema de petición. *Acción sugerida:* proponer un contrato que, por ejemplo, correlacione entrada inválida, denegación, no disponible, conflicto y falla con respuestas distintas, y definir una clave de idempotencia.

**REV13-06 — Motivo de rechazo.** Solo se recorta el texto y se exige que no esté vacío; no hay longitud máxima ni se define si puede contener datos del paciente. *Acción sugerida:* fijar un límite y una política de contenido.

**REV13-07 — Atomicidad y pruebas.** La atomicidad se demuestra en un adaptador en memoria; no se probó con una base de datos real ni con concurrencia entre procesos. Las pruebas no cubren, por ejemplo, un reintento después de una escritura exitosa cuya respuesta se perdió. *Acción sugerida:* ejecutar el contrato contra el adaptador persistente y añadir un caso de reintento.

Observación menor, sin identificador: si se pide rechazar sin motivo un resultado que ya está validado, el sistema responde estado no permitido antes que entrada inválida; es coherente, pero conviene documentar el orden de las comprobaciones.

## 8. Criterios de entrada y salida

### Entrada

1. Documento de requisitos y criterios de aceptación aprobados por la autora.
2. El ejemplo compila (`php -l`) y su runner pasa.
3. Checklist y formato de hallazgos disponibles.
4. Roles asignados; el autor no modera.
5. Material entregado con antelación a los revisores.

### Salida

1. Todos los ítems del checklist tienen un estado.
2. Cada hallazgo tiene severidad, responsable y resolución o decisión de aplazamiento.
3. No quedan bloqueadores abiertos (REV13-01 a REV13-03) sin resolución o decisión firmada.
4. El moderador verifica los cambios del retrabajo.
5. Se emite el dictamen: aprobado, aprobado con condiciones o no aprobado.

**Estado de esta revisión:** **aprobado con condiciones** como material académico; **no aprobado** para una integración persistente o multi-tenant mientras sigan abiertos los tres bloqueadores.

## 9. Formato obligatorio de hallazgo

```text
ID:             REV13-NN
Ítem checklist: n.º
Severidad:      Alta (bloqueador) | Media | Baja
Ubicación:      archivo / requisito / criterio
Descripción:    qué se observó, con evidencia
Impacto:        riesgo concreto
Acción sugerida: sin imponer implementación
Responsable:    rol
Estado:         Abierto | En retrabajo | Cerrado | Aplazado con decisión
Resolución:     texto y fecha
```

Ejemplo diligenciado:

```text
ID:             REV13-01
Ítem checklist: 6
Severidad:      Alta (bloqueador)
Ubicación:      ReviewCommand::hospitalId; ReviewPendingLabResult::execute
Descripción:    el hospital se recibe como dato del comando
Impacto:        autorización por hospital eludible si el cliente lo controla
Acción sugerida: derivar el hospital de la sesión autenticada
Responsable:    Autora, con revisor de seguridad
Estado:         Abierto
Resolución:     —
```

## 10. Validación y límites

- Revisión de escritorio de un prototipo académico con adaptador en memoria; no se ejecutó una reunión con otros revisores ni se midieron defectos de forma estadística.
- Los roles son propuestos; al ser un trabajo individual, la autora los ejerce de forma provisional.
- No se evaluó criterio clínico, intervalos ni valores críticos; deben definirlos los responsables bioquímicos.
- «Firma» se usa como requisito de trazabilidad; no se asume una norma ni un mecanismo concretos.
- El dictamen no certifica el sistema ni la Semana 15.

## 11. Referencias

- [Semana 1 — UML de validación de resultados](../semana-01/semana-01-uml-validacion-resultados.md)
- [Semana 2 — DIP aplicado a la validación](../semana-02/semana-02-dip-validacion-resultados.md)
- [Ejemplo DIP](../semana-02/ejemplo-dip/README.md)
- [Evidencia de esta revisión](evidencia/validacion-semana-13.txt)
