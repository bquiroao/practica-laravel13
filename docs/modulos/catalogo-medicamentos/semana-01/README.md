# Semana 1 — Catálogo de medicamentos: UML y narrativa de alcance

**Proceso modelado:** alta, búsqueda y control de vigencia de un medicamento.
**Estudiante:** Erick Rolando Ramazzini Muralles (@ErickRamazzini)

## 1. Objetivo del módulo

El catálogo de medicamentos es la lista oficial de productos que un hospital puede usar.
Cada medicamento se registra **una vez**, se puede **buscar** por varios criterios y tiene una
**vigencia**: desde qué fecha se puede usar y, si deja de usarse o cambia, hasta qué fecha.
Los demás módulos del HIS (prescripciones, farmacia, dispensación) **solo leen** el catálogo.

El catálogo **describe el producto**; no recomienda su uso, no fija dosis y no evalúa interacciones.

## 2. Narrativa de alcance

> El químico farmacéutico responsable del catálogo de un hospital busca un medicamento antes de
> registrarlo. Si no existe una versión vigente equivalente, lo registra con su nombre, principio
> activo, concentración, forma farmacéutica, vía de administración, presentación y fecha de inicio
> de vigencia. El sistema valida los datos, impide duplicados y asigna un código. Cuando cambia la
> concentración o la presentación, no se edita el registro: se crea una **versión nueva** que
> cierra la anterior, de modo que en ninguna fecha hay dos versiones vigentes del mismo código.
> Cuando el medicamento deja de usarse, se da de **baja** cerrando su vigencia; nunca se borra si
> ya fue usado en una prescripción. El personal clínico y el módulo de prescripciones buscan solo
> los medicamentos vigentes a una fecha. El auditor de calidad consulta el historial de versiones.

### Incluido
- Alta de un medicamento con validación de datos y control de duplicados.
- Búsqueda de versiones **vigentes a una fecha** (por defecto hoy) por nombre, principio activo, código o forma farmacéutica.
- Control de vigencia: nueva versión, baja y consulta de historial.
- Eliminación **solo** de un registro erróneo que nunca se usó en prescripciones.
- Aislamiento por hospital (tenant): cada hospital ve y modifica solo su catálogo.

### Fuera de alcance
- Existencias, lotes de bodega, fechas de caducidad de lotes y precios.
- Prescripciones, dispensación y administración al paciente.
- Alergias del paciente y alertas clínicas de interacción.
- Recomendaciones de dosis o indicaciones clínicas.

## 3. Actores

| Actor | Tipo | Objetivo |
|---|---|---|
| Químico farmacéutico del catálogo (`QF_CATALOGO`) | Primario | Mantener el catálogo correcto: alta, versiones, bajas y corrección de errores. |
| Personal clínico de consulta (`CONSULTA_CLINICA`) | Primario | Encontrar rápido un medicamento vigente para usarlo en su trabajo. |
| Auditor de calidad (`AUDITOR_CALIDAD`) | Secundario | Revisar qué versión estuvo vigente en una fecha y quién la registró. |
| Módulo `prescripciones-alergias` | Sistema externo | Leer medicamentos vigentes por código o búsqueda; informar si un código fue usado. |

## 4. Casos de uso

| ID | Caso de uso | Actor principal | Reglas |
|---|---|---|---|
| CU-01 | Registrar medicamento (alta) | QF_CATALOGO | RN-04, RN-05, RN-06 |
| CU-02 | Buscar medicamentos vigentes a una fecha | Todos | RN-03 |
| CU-03 | Registrar nueva versión | QF_CATALOGO | RN-01, RN-04, RN-05 |
| CU-04 | Dar de baja (cerrar vigencia) | QF_CATALOGO | RN-01, RN-02 |
| CU-05 | Consultar historial de versiones | AUDITOR_CALIDAD | — |
| CU-06 | Eliminar registro erróneo sin uso | QF_CATALOGO | RN-02 |

## 5. Reglas de negocio (versión inicial; se detallan en la semana 2)

| ID | Regla |
|---|---|
| RN-01 | **Vigencia con versiones.** Un cambio crea la versión N+1 del mismo código; la anterior se cierra con `vigente_hasta` = inicio de la nueva. Nunca hay dos versiones vigentes del mismo código en una misma fecha. |
| RN-02 | **No se borra lo usado.** Si un código aparece en prescripciones, no se elimina: se da de baja cerrando su vigencia. |
| RN-03 | **Búsqueda de vigentes.** Solo aparecen versiones vigentes a la fecha consultada; los caracteres `%` y `_` se buscan como texto literal. |
| RN-04 | **Sin duplicados.** No puede haber dos medicamentos vigentes con el mismo principio activo, concentración, forma farmacéutica y vía. |
| RN-05 | **Límites del dominio = columnas de la base.** Un dato inválido se rechaza con 422 antes de llegar a la base. |
| RN-06 | **Código propio del hospital.** El código `MED-NNNNNN` se asigna en el alta, no cambia entre versiones y solo es válido dentro de su hospital. |

La vigencia se maneja como intervalo **[desde, hasta)**: el día `hasta` ya no está vigente.

## 6. Diagramas

| Diagrama | Archivo | Qué muestra |
|---|---|---|
| Casos de uso | `diagramas/casos-de-uso.png` | Actores, objetivo de cada uno, límite del sistema, `include`/`extend` y frontera con prescripciones. |
| Actividad | `diagramas/actividad-alta-busqueda-vigencia.png` | Búsqueda, alta, nueva versión y baja con decisiones, excepciones E1–E4 y resultados R1–R3. |
| Secuencia | `diagramas/secuencia-alta-medicamento.png` | CU-01: participantes, mensajes, validaciones (rol, datos, duplicado, base) y respuestas 201/403/409/422/503. |

## 7. Trazabilidad entre los tres diagramas

| Caso de uso | Actividad | Secuencia | Resultado o excepción |
|---|---|---|---|
| CU-02 | "Filtra solo versiones vigentes" | (búsqueda previa al alta) | Rama "¿Hay resultados?" |
| CU-01 + RN-05 | "Valida formato y límites" | Mensajes 8–13 | E1 → 422 |
| CU-01 + RN-04 | "¿Otro medicamento vigente con la misma clave?" | Mensajes 15–21 | E2 → 409 |
| CU-01 | "Asigna código" / "¿Falla la base?" | Mensajes 22–35 | E3 → 503 · R1 → 201 |
| CU-03 + RN-01 | "¿Inicio posterior…?" / "Cierra la versión anterior" | Se modela en semana 3 (mismo patrón) | E4 → 409 · R2 |
| CU-04 + RN-02 | "Cierra la vigencia sin borrar" | Se modela en semana 3 | R3 |
| Permisos | "Inicia sesión en su hospital" | Mensajes 4–6 | 403 |

Los nombres de la secuencia (`ControladorMedicamentos`, `RegistrarMedicamento`, `Medicamento`,
`RepositorioMedicamentos`) son los que se usarán en el Micro-HIS (semanas 3–5).

## 8. Frontera con módulos vecinos

| Módulo | Relación con el catálogo | Estado |
|---|---|---|
| `prescripciones-alergias` | Lee medicamentos vigentes y debe informar si un código fue usado (RN-02). | **Pendiente de confirmar** con su dueño el formato de esa consulta. |
| Farmacia / dispensación / inventario (si existen) | Leen el catálogo por código; existencias, lotes y precios son suyos. | **Pendiente de confirmar** si existen en el repo y quién es el dueño. |
| Alergias clínicas | Puede usar el principio activo como referencia; las alertas no son de este módulo. | **Pendiente de confirmar.** |

No se tuvo acceso de lectura al repositorio del grupo al preparar esta entrega, así que los
nombres de módulos y tablas reales de HOSPITAL/CENTRAL no se pudieron confirmar. La tabla
`medicamentos` en HOSPITAL es una **propuesta del Micro-HIS**.

## 9. Datos de ejemplo

Los medicamentos son genéricos conocidos (paracetamol, amoxicilina, ibuprofeno, omeprazol,
cloruro de sodio), pero **los códigos `MED-NNNNNN`, los registros sanitarios, las fechas y los
hospitales ("Hospital Norte" y "Hospital Sur") son ficticios**. No son información clínica verificada.
