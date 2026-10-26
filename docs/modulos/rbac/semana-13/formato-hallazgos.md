# Formato de hallazgos — revisión técnica formal RBAC

Todos los hallazgos usan este formato:
- los del autor en su autorrevisión;
- los de los revisores durante la preparación;
- los que se registran en la reunión.

Así se pueden sumar, comparar y seguir hasta el cierre.

- Registro en hoja de cálculo: [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv), una fila por hallazgo.
- Ejemplo completo: [autorrevision-autor.md](autorrevision-autor.md).

## 1. Campos

| Campo | Obligatorio | Contenido | Ejemplo |
|---|:-:|---|---|
| **ID** | ✓ | Prefijo según quién lo encontró + número: `AR` autor, `R1`/`R2`/`R3` revisores, `RE` reunión | `R1-03` |
| **Ítem** | ✓ | Ítem del [checklist](checklist-revision.md) que falla (`RT-xx`), o `FUERA` si no encaja en ninguno | `RT-09` |
| **Ubicación** | ✓ | `archivo:línea` (o la sección del contrato), relativa a `micro-his-rbac/` | `src/Presentacion/Http/Kernel.php:62` |
| **Descripción** | ✓ | **Qué** está mal, en una o dos frases, sin proponer la solución | «El 404 de ruta inexistente se responde antes de validar el tenant…» |
| **Evidencia** | ✓ | Cómo se reproduce o se ve: comando con su salida, prueba, o cita del código | `php herramientas/verificar-autorrevision.php` → `HTTP 201 y 201` |
| **Regla afectada** | ✓ | R-xx, RF-xx, RNF-xx o decisión D-xx que se incumple | `R-01, RNF-08` |
| **Severidad** | ✓ | Crítico · Mayor · Menor · Observación (§2) | Mayor |
| **Tipo** | ✓ | Defecto · Riesgo · Mejora · Pregunta (§3) | Riesgo |
| **Propuesta** | | Idea de corrección. **No se discute en la reunión**; la decide el autor en el retrabajo. | «Ampliar `existeActiva()` a membresías con fin que se solapen» |
| **Responsable** | ✓ | Quién hace el retrabajo (normalmente el autor) | Autor |
| **Estado** | ✓ | Abierto → Aceptado / Rechazado (con razón) → Corregido → **Verificado** (lo marca el moderador) | Abierto |
| **Verificación** | al cerrar | Cómo comprobó el moderador la corrección: prueba nueva, comando, commit | `PEF-11 en verde, commit abc123` |

**Reglas de redacción:**
- Se describe el **producto**, nunca a la persona («la regla no valida…», no «Boris olvidó…»).
- Un hallazgo es **un** problema. Si dos ítems fallan por la misma causa, se registra uno y se citan los dos ítems.
- Sin evidencia no hay hallazgo: si es una duda, se registra como **Pregunta**.

## 2. Severidad

La severidad se decide por el **impacto en RBAC**, no por lo difícil que sea corregirlo:

| Severidad | Criterio (basta uno) | Efecto en la salida |
|---|---|---|
| **Crítico** | Permite **acceso indebido** o **salto de tenant**. Una denegación queda **sin evidencia**. Una respuesta **filtra** motivo, rol, permiso o datos clínicos. Un cambio de rol queda sin evento (o al revés). | Bloquea la salida. Corrección **y** prueba nueva antes de cerrar. |
| **Mayor** | Incumple una regla (R-xx, RF/RNF) o el contrato en un caso alcanzable, sin explotación directa. Riesgo de despliegue que la configuración no impide. | Se corrige, o tiene decisión documentada aprobada por el moderador más un issue con fecha. |
| **Menor** | No incumple reglas, pero dificulta mantener, verificar o desplegar: documentación, dependencias no declaradas, nombres engañosos. | Se corrige o se registra en el backlog. |
| **Observación** | Mejora o comentario sin defecto | Se registra; no condiciona la salida. |

## 3. Tipo

| Tipo | Significa |
|---|---|
| **Defecto** | El producto hace algo distinto de lo que dicen la regla o el contrato. Se puede reproducir. |
| **Riesgo** | Hoy funciona, pero una condición plausible lo rompe: despliegue, falla de infraestructura, uso por otro módulo. |
| **Mejora** | Funciona y cumple, pero hay una forma claramente mejor. |
| **Pregunta** | El revisor no puede decidir si es un defecto. El autor responde en la reunión; si la respuesta revela un defecto, la pregunta se convierte en hallazgo. |

## 4. Ejemplo de un hallazgo completo

| Campo | Valor |
|---|---|
| ID | AR-02 |
| Ítem | RT-03, RT-11 |
| Ubicación | `src/Dominio/Reglas/Asignacion/ReglaSinMembresiaDuplicada.php:21` · `database/schema.sql:45-47` |
| Descripción | La regla de no duplicar (R-03) solo considera membresías **abiertas** (`valido_hasta IS NULL`). Dos asignaciones idénticas con fecha de fin se aceptan, y la persona queda con dos membresías activas del mismo rol. |
| Evidencia | `php herramientas/verificar-autorrevision.php` → `misma asignación dos veces (con fin): HTTP 201 y 201 → membresías activas de laboratorio: 2` |
| Regla afectada | R-03, RT-03 (definición única de vigencia) |
| Severidad | Mayor |
| Tipo | Defecto |
| Propuesta | Que la regla y el índice consideren membresías activas cuyo periodo se **solape** con el nuevo, o que la API exija `Idempotency-Key` (B-5). |
| Responsable | Autor |
| Estado | Abierto |
| Verificación | — |

## 5. Métricas que se calculan con el registro

| Métrica | Fórmula | Para qué |
|---|---|---|
| Densidad de defectos | hallazgos Crítico + Mayor + Menor ÷ KLOC del paquete | Comparar con revisiones futuras del módulo |
| Tasa de preparación | líneas del paquete ÷ horas de preparación de cada revisor | Si supera 300 LOC/h, la preparación fue superficial |
| Rendimiento de la reunión | hallazgos nuevos en la reunión ÷ total | Si es ~0, la reunión solo repasó; si es alto, faltó preparación |
| Distribución por ítem | hallazgos por RT-xx | Mejorar el checklist: un ítem que nunca encuentra nada se revisa o se fusiona |
| Eficacia (a posteriori) | hallazgos de la revisión ÷ (los de la revisión + defectos del mismo tipo hallados después) | Se mide en la S15 con las pruebas de integración |
