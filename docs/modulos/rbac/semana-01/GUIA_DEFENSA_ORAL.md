# Guía de defensa oral — Semana 1

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-01-uml-proceso-rbac.md](semana-01-uml-proceso-rbac.md)

## 1. Resumen en 60 segundos

> «Modelé cómo el HIS decide si alguien puede hacer algo. Primero, un administrador le asigna un rol a un usuario dentro de un hospital; eso crea una **membresía** con estado y vigencia. Después, cada vez que el usuario intenta una operación, el sistema valida el hospital (tenant), el token, que la membresía esté vigente y que el rol tenga el permiso que pide la operación. Si todo cumple, se permite. Si algo falla, se **deniega con trazabilidad**: se registra el evento en una bitácora inmutable y se responde 403 con un `correlation_id`, para que un auditor lo encuentre después. Los tres diagramas comparten identificadores, así que cualquier elemento se puede seguir de uno a otro.»

## 2. Orden sugerido de la exposición

1. **Narrativa de alcance:** el ejemplo de Ana López, enfermera que intenta crear una prescripción.
2. **Casos de uso:** actores, CU-01 a CU-06, y la diferencia entre `include` y `extend`.
3. **Actividad:** recorrer las tres fases y señalar 2 o 3 decisiones y excepciones.
4. **Secuencia:** seguir el caso denegado de S-25 a S-43.
5. **Matriz de trazabilidad:** escoger una regla, por ejemplo R-06, y ubicarla en los tres diagramas.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| CU-01 y CU-02 **incluyen** a CU-03 | La autorización ocurre **siempre**: no hay operación protegida sin ella. `include` modela un comportamiento obligatorio y reutilizado. |
| CU-04 **extiende** a CU-03 | La denegación ocurre **solo bajo una condición**: decisión negativa. `extend` modela un comportamiento opcional que se activa en un punto de extensión. La flecha va del caso que extiende (CU-04) al caso base (CU-03). |
| La bitácora y el repositorio no son actores | Un actor está **fuera** del sistema. La bitácora es un componente interno, por eso aparece en los diagramas de actividad y secuencia pero no en el de casos de uso. |
| Asignar roles también pasa por la autorización | Si cualquiera pudiera asignar roles, alguien podría darse permisos de más. Por eso se exige `roles.asignar` y se prohíbe la autoasignación (R-04). |
| *Fail-closed* (R-05) | En salud es preferible bloquear una operación legítima por un momento que permitir una indebida. Si la bitácora falla, la operación sigue denegada (E-07). |
| Asignación y auditoría en una sola transacción (R-07) | Evita roles «fantasma» sin evidencia de quién los asignó. Si la auditoría falla, se revierte todo (E-04). |
| El 403 no da detalles (R-08) | Decir «te falta el permiso X» le ayuda a un atacante a explorar el sistema. El `correlation_id` basta para que soporte o el auditor investiguen. |
| Membresía con vigencia | Ya está en la base de datos real: `hospital_memberships` tiene `status`, `valid_from` y `valid_to`, y un índice único que impide duplicados (R-03). |
| Tenant = hospital | Lo exige el middleware existente `TenantMiddleware`, que valida la cabecera `X-Tenant-ID`: un rol solo vale dentro de su hospital. |

## 4. Preguntas probables y respuestas

**¿Qué es RBAC y en qué se diferencia de asignar permisos directamente a cada usuario?**
En RBAC, los permisos se asignan a **roles** y los roles a usuarios. Cuando cambia la función de alguien, se le cambia el rol y no hay que tocar cada permiso. Escala mejor y es más fácil de auditar.

**¿Por qué tres diagramas y no uno?**
Cada uno responde a una pregunta distinta. Casos de uso: *qué* hace el sistema y *para quién*. Actividad: *cómo fluye* el proceso, con sus decisiones. Secuencia: *quién le habla a quién* y en qué orden.

**¿Qué significa «trazabilidad» entre los diagramas?**
Que cada elemento se puede seguir de uno a otro. Ejemplo: CU-04 → A-12 a A-15 en actividad → S-36 a S-43 en secuencia. Está resumido en la matriz de la sección 8.

**¿Cuál es la diferencia entre una decisión (D) y una excepción (E)?**
Las decisiones son ramas normales del negocio: ¿tiene permiso?, ¿la membresía está vigente? Las excepciones son condiciones de error o de entrada inválida que terminan el flujo antes de tiempo, como un token inválido o una falla de la bitácora.

**¿Qué pasa si el token es inválido? ¿Se registra como denegación?**
No. Responde 401 (E-06) antes de evaluar permisos, porque todavía no se sabe *quién* es el usuario. La denegación trazable aplica a usuarios identificados que no tienen autorización.

**¿Qué diferencia hay entre 401 y 403?**
401 significa que no se sabe quién eres: no autenticado. 403 significa que sí se sabe quién eres, pero no tienes permiso: no autorizado.

**¿Qué datos lleva el evento de denegación y por qué?**
Actor, hospital, permiso, recurso, motivo, IP, fecha y `correlation_id`. Con esos datos se responde quién, dónde, qué intentó, por qué se le negó y cuándo, y se puede cruzar con lo que vio el usuario.

**¿Por qué la bitácora es *append-only*?**
Si se pudiera editar o borrar, no serviría como evidencia. El esquema del proyecto ya sigue ese patrón; por ejemplo, `cross_hospital_access_audits` tiene un trigger que impide UPDATE y DELETE.

**¿Dónde se guarda esto en la base de datos real?**
Las membresías, en `hospital_memberships` (base CENTRAL). La bitácora será `local_access_audit` (base HOSPITAL), que está planificada en el esquema federado.

**¿Qué patrón o concepto de orientación a objetos se aplica?**
- **Encapsulamiento:** la regla de autorización vive en un solo componente, el servicio de autorización.
- **Responsabilidad única:** el middleware intercepta, el servicio decide, el repositorio persiste y la bitácora registra.

Esto anticipa SOLID, que es el tema de la semana 2.

## 5. Recorrido de demostración

1. Abrir [semana-01-uml-proceso-rbac.md](semana-01-uml-proceso-rbac.md) en GitHub o en VS Code con vista previa.
2. Mostrar el diagrama de casos de uso y señalar el `extend` de CU-04.
3. En el diagrama de actividad, seguir este camino: A-08 → D-05 sí → D-06 sí → A-09 → D-07 sí → D-08 no → A-12 → A-13 → A-14 → A-15.
4. En el diagrama de secuencia, mostrar ese mismo camino de S-25 a S-43.
5. Abrir un `.puml` para demostrar que las fuentes son editables.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| Agregar «Revocar rol» | En `semana-01-casos-de-uso.puml` agregar `usecase "CU-07\nRevocar rol" as CU07`, la línea `Admin --> CU07` y las relaciones `CU07 ..> CU03 : <<include>>` y `CU07 ..> CU05 : <<include>>`. |
| Agregar un motivo de denegación nuevo, por ejemplo `USUARIO_INACTIVO` | En `semana-01-actividad.puml` agregar un `elseif` antes de D-08 con su motivo; en la narrativa, agregarlo a la lista de motivos tipificados. |
| Agregar un actor «Sistema de notificaciones» | Actor secundario conectado a CU-04 para alertar al auditor. Agregarlo también como participante en la secuencia, después de S-38. |
| Mostrar el caso permitido con más detalle | En `semana-01-secuencia.puml`, dentro de `alt Permiso concedido`, agregar el controlador como participante y el mensaje de persistencia. |
| Cambiar el ejemplo (otro rol u otra operación) | Reemplazar `enfermeria` y `prescripciones.crear` en los tres `.puml` y en la narrativa. La estructura no cambia. |

Para regenerar las imágenes después de un cambio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/rbac/semana-01/diagramas/*.puml
```

Si no hay Java disponible, se puede pegar el `.puml` en https://www.plantuml.com/plantuml para verlo al momento.
