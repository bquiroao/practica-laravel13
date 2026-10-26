# Guía de defensa oral — Semana 1

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría de movimientos del sistema · **Documento:** [semana-01-uml-proceso-auditoria.md](semana-01-uml-proceso-auditoria.md)

## 1. Resumen en 60 segundos

> «Modelé la bitácora de movimientos del HIS: quién hizo qué, en qué módulo, en qué hospital y cuándo. Tiene dos lados. Al **registrar**, cada módulo envía su movimiento con una cuenta de servicio. El sistema verifica que sea de su propio hospital, que esté completo y que no traiga contraseñas ni datos clínicos. Si es un reintento con el mismo identificador, no lo duplica. Después lo sella encadenándolo con el anterior y lo inserta en una tabla que la base no deja modificar ni borrar. Al **consultar**, el super administrador o el auditor del hospital deben indicar un rango de fechas, y pueden filtrar por usuario y módulo. El auditor solo ve su hospital, y la red completa es solo para el super administrador. Toda consulta queda, a su vez, en la bitácora. Además, se puede verificar la cadena de sellos para demostrar que nadie la alteró. Los tres diagramas comparten identificadores, así que cada regla se sigue de uno a otro.»

## 2. Orden sugerido de la exposición

1. **Narrativa de alcance:** por qué una bitácora editable no es evidencia, y qué queda fuera (exportación, analytics, la tabla de acceso entre hospitales).
2. **Casos de uso:** que **no** haya «editar» ni «borrar» es una decisión de diseño; explicar `include` (CU-05, CU-06) y `extend` (CU-03, CU-07).
3. **Actividad:** las cuatro ramas de D-03, y el orden D-04 → D-05 → D-06.
4. **Secuencia:** el bloque 2, la consulta con el 403 de alcance y la meta-auditoría (S-30).
5. **Matriz:** elegir R-05 (tenant) y ubicarla en los tres diagramas.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| `audit_logs` en la **base central** | Los usuarios (`central_users`) y los hospitales viven allí, y el super administrador consulta entre hospitales. En la base de cada hospital, la consulta de red tendría que unir N bases. |
| Solo inserción, con trigger | La base ya usa ese patrón en `cross_hospital_access_audits`. El trigger protege incluso de quien escribe SQL directo con el usuario de la aplicación. |
| Sello encadenado (R-03) | El trigger se puede desactivar con un usuario administrador de la base. El sello no lo impide, pero lo **detecta**: cambiar un movimiento rompe todos los sellos siguientes. |
| Idempotencia por `event_id` (R-04) | El emisor reintenta ante un corte de red; sin esto, el mismo movimiento quedaría dos veces. |
| Rango de fechas **obligatorio** (R-06) | Lo pide el README, y además protege el rendimiento: la tabla crece siempre. |
| Consultar también se audita (R-08) | Mirar la bitácora puede ser en sí un abuso (buscar qué hizo una persona concreta). Quien vigila también queda vigilado. |
| 404 idéntico entre hospitales (E-09) | No revelar que un movimiento existe en otro tenant. |
| El tenant se revisa antes que el contenido (D-04) | Un emisor de otro hospital no recibe pistas de validación. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre «inmutable» y «a prueba de alteraciones»?**
Inmutable significa que el sistema no permite cambiarlo (sin endpoint, con trigger). A prueba de alteraciones (*tamper-evident*) significa que, si alguien lo cambia por fuera, se nota. Se necesitan las dos cosas: la primera evita el error común, y la segunda descubre el abuso del administrador.

**Si nada se edita, ¿cómo se corrige un movimiento mal registrado?**
Con **otro** movimiento que lo referencia (`corrige_a`). El original queda tal cual y el detalle muestra la corrección. Así se ve tanto el error como su arreglo.

**¿Por qué el emisor es un actor si es un sistema?**
Porque inicia un caso de uso (CU-01) con un objetivo propio: dejar evidencia. RBAC e identidad son secundarios: solo responden cuando el módulo les pregunta.

**¿Por qué no se guardan los datos clínicos en el movimiento?**
Minimización (R-07). La bitácora la ven auditores que no deben leer historias clínicas. Basta con la referencia al recurso (tipo e id).

**¿Qué pasa con la zona horaria?**
Se guarda en UTC. El usuario filtra en la hora de Guatemala, y `hasta` incluye el día entero. Si `hasta` se tomara como medianoche del día indicado, ese día quedaría fuera, que es el error clásico.

## 5. Recorrido de demostración

1. Abrir el diagrama de casos de uso y señalar la nota «append-only».
2. Seguir R-04 (idempotencia): D-06 y D-07 en actividad, S-09 a S-12 en secuencia.
3. Seguir R-08: A-08 en actividad y S-30 en secuencia.
