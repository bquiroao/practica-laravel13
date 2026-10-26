# Formato de hallazgos — revisión técnica formal Pacientes

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
| **Ubicación** | ✓ | `archivo:línea` (o la sección del contrato), relativa a `micro-his-pacientes/` | `src/Dominio/Servicios/PoliticaDuplicados.php:32` |
| **Descripción** | ✓ | **Qué** está mal, en una o dos frases, sin proponer la solución | «El 409 por documento devuelve el código de un paciente de otro hospital…» |
| **Evidencia** | ✓ | Cómo se reproduce o se ve: comando con su salida, prueba, o cita del código | `php herramientas/verificar-autorrevision.php` → `HTTP 409 … P-000001` |
| **Regla afectada** | ✓ | R-xx, RF-xx, RNF-xx o decisión D-xx que se incumple | `R-01, D-20` |
| **Severidad** | ✓ | Crítico · Mayor · Menor · Observación (§2) | Mayor |
| **Tipo** | ✓ | Defecto · Riesgo · Mejora · Pregunta (§3) | Riesgo |
| **Propuesta** | | Idea de corrección. **No se discute en la reunión**; la decide el autor en el retrabajo. | «Filtrar el código por hospital en `visibles()`» |
| **Responsable** | ✓ | Quién hace el retrabajo (normalmente el autor) | Autor |
| **Estado** | ✓ | Abierto → Aceptado / Rechazado (con razón) → Corregido → **Verificado** (lo marca el moderador) | Abierto |
| **Verificación** | al cerrar | Cómo comprobó el moderador la corrección: prueba nueva, comando, commit | `TEN-01 en verde, commit abc123` |

**Reglas de redacción:**
- Se describe el **producto**, nunca a la persona («la regla no valida…», no «Boris olvidó…»).
- Un hallazgo es **un** problema. Si dos ítems fallan por la misma causa, se registra uno y se citan los dos ítems.
- Sin evidencia no hay hallazgo: si es una duda, se registra como **Pregunta**.

## 2. Severidad

La severidad se decide por el **impacto en la ficha del paciente**, no por lo difícil que sea corregirlo:

| Severidad | Criterio (basta uno) | Efecto en la salida |
|---|---|---|
| **Crítico** | Permite **acceso indebido** o **salto de tenant**. Una respuesta **filtra** datos de un paciente protegido o de otro hospital, o el documento en claro. Se puede crear un **duplicado** o perder una edición sin aviso. Un cambio de la ficha queda sin evento (o al revés). | Bloquea la salida. Corrección **y** prueba nueva antes de cerrar. |
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
| Ítem | RT-07, RT-10 |
| Ubicación | `src/Dominio/Servicios/PoliticaDuplicados.php:29-37` · `src/Persistencia/EnMemoria/IndiceDuplicadosEnMemoria.php:17-26` · `src/Persistencia/Pdo/PdoIndiceDuplicados.php:17` |
| Descripción | El índice de duplicados busca en todos los hospitales (es intencional: R-02 es «en toda la red»), pero el 409 `PACIENTE_EXISTENTE` devuelve el **código local** y el documento enmascarado de un paciente de **otro** hospital. Ese código no sirve en el hospital activo (da 404) y revela la existencia y los últimos dígitos del documento de un paciente ajeno. |
| Evidencia | `php herramientas/verificar-autorrevision.php` → `admisión de hosp-NORTE … → HTTP 409 {"codigo":"P-000001","identificador":"*********0101"}` y luego `GET P-000001 → 404` |
| Regla afectada | R-01, D-20 (aislamiento por hospital), RT-07 |
| Severidad | Crítico |
| Tipo | Defecto |
| Propuesta | Si el titular es de otro hospital, responder un código neutro («EN_OTRO_HOSPITAL») sin documento, y dejar la conciliación al MPI. |
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
| Eficacia (a posteriori) | hallazgos de la revisión ÷ (los de la revisión + defectos del mismo tipo hallados después) | Se mide en la S15 con las pruebas de caja negra |
