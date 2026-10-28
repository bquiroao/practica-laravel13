# Plan de la revisión técnica formal — RBAC

**Módulo:** RBAC: roles, permisos y protección de rutas · **Autor:** Boris Alexander Quiroa Orellana

**Proceso revisado:** «asignación de rol, autorización de una operación y denegación trazable»

## 1. Objetivo y tipo de revisión

**Tipo:** **inspección** (IEEE 1028, revisión formal de producto). Tiene:
- roles separados;
- preparación individual con checklist;
- reunión moderada en la que **solo se registran hallazgos**;
- retrabajo verificado y criterios de entrada y salida explícitos.

Se elige inspección, y no un *walkthrough*, porque lo que se revisa es **control de acceso a datos clínicos**: un defecto tiene costo alto y la revisión debe dejar evidencia.

**Preguntas que la revisión debe responder:**

| # | Pregunta | Ítems del checklist |
|---|---|---|
| P1 | ¿Las **reglas de dominio** deciden lo que dicen R-01..R-08, con una sola definición de vigencia y siempre fail-closed? | RT-01..RT-03, RT-11 |
| P2 | ¿El **contrato API** describe exactamente lo que el código hace, sin filtrar información en los errores? | RT-06, RT-07, RT-12 |
| P3 | ¿**Permisos y tenant** se aplican a toda ruta y toda consulta, con la identidad tomada solo del token? | RT-08..RT-10, RT-15 |
| P4 | ¿Se mantiene la **integridad**: cambio de rol + evento atómicos, y toda denegación trazable? | RT-04, RT-05 |
| P5 | ¿Las **dependencias** son estrictamente las necesarias para el flujo, y la regla de capas se cumple? | RT-13, RT-14 |

**Fuera del objetivo:**
- **Estilo** de código (nombres, formato), salvo que oculte un defecto.
- **Rendimiento:** RNF-09 se mide en la S15.
- **UX del prototipo:** se evaluó en las S9–S12.

## 2. Alcance y paquete de revisión

El paquete se **congela en un commit** (E-1) y se divide en dos sesiones para respetar el límite de tamaño (E-4). La lista exacta de archivos y líneas la genera [herramientas/criterios-entrada.php](herramientas/criterios-entrada.php) ([salida](evidencia/criterios-entrada-2-despues.txt)).

| Sesión | Contenido | Archivos | Líneas efectivas | Preguntas |
|---|---|---:|---:|---|
| **S1** · Dominio, aplicación y persistencia | `src/Dominio/**`, `src/Aplicacion/**`, `database/schema.sql`, `src/Persistencia/Pdo/*`, `RegistroAuditoriaArchivo.php` | 58 | 1512 | P1, P4 |
| **S2** · Contrato, frontera, permisos/tenant y dependencias | `api/openapi.yaml`, `src/Presentacion/Http/**`, `bootstrap/contenedor.php`, `config/config.php`, `public/index.php` | 31 | 1557 | P2, P3, P5 |
| **Total** | | 89 | **3069** | |

**Material de referencia** (se consulta, no se revisa):

| Material | Para qué |
|---|---|
| Reglas R-01..R-08 (S1) y RF/RNF (S2, S6, S12) | Saber qué se exige |
| Matrices de decisión: [S6](../semana-06/matriz-decision-evidencia.md) y [S12](../semana-12/matriz-decision-rf-rnf-evidencia.md) | Saber por qué se decidió así |
| Pruebas (`tests/`) | Evidencia; los revisores **no** revisan las pruebas en sí |
| Persistencia InMemory | Se verifica por la suite de contrato |
| `src/Cliente/*` | Solo para RT-14 |

## 3. Roles y responsabilidades

| Rol | Quién | Responsabilidades | No hace |
|---|---|---|---|
| **Autor** | Boris Alexander Quiroa Orellana | Congela el paquete. Hace la [autorrevisión](autorrevision-autor.md). Responde preguntas en la reunión. Hace el retrabajo. | No modera, no registra, no defiende ni discute severidades. Escucha y aclara. |
| **Moderador** | Compañero/a del grupo, que **no** sea el autor *(nombre por confirmar, E-8)* | Verifica los criterios de entrada. Convoca y envía el material. Dirige la reunión y controla el tiempo. Decide la severidad si no hay consenso. Toma la decisión de salida. Verifica el retrabajo. Firma el acta. | No es revisor principal de ninguna perspectiva. Cubre RT-13 y RT-14 con R3. |
| **Registrador** | Uno de los revisores, por turno *(por confirmar)* | Llena en vivo el [registro de hallazgos](plantillas/registro-hallazgos.csv) y el [acta](plantillas/acta-revision.md) | No debate mientras registra |
| **Revisor R1** · Seguridad | Compañero/a *(por confirmar)* | RT-08, RT-09, RT-10, RT-11, RT-15 | — |
| **Revisor R2** · Contrato API | Compañero/a *(por confirmar)* | RT-06, RT-07, RT-12 | — |
| **Revisor R3** · Dominio e integridad | Compañero/a *(por confirmar)* | RT-01..RT-05 y, con el moderador, RT-13 y RT-14 | — |

**Notas sobre los roles:**
- Las **perspectivas** reparten la atención, pero cada revisor lee el paquete de su sesión completo. Un hallazgo fuera de la propia perspectiva también se registra.
- Si el grupo no alcanza para cinco personas, el **registrador** puede ser el mismo moderador. El **autor nunca** puede ser moderador ni registrador.
- Los nombres se dejan por confirmar a propósito: los acuerda el grupo, y el moderador los anota en el acta (E-8).

## 4. Proceso y calendario

![Proceso de la revisión](diagramas/semana-13-proceso-revision.png)

Día D = reunión de la sesión S1. La S2 se hace en D+1, con la misma preparación.

| Fase | Cuándo | Duración | Quién | Entrada → salida |
|---|---|---|---|---|
| 1. Planificación | D−4 | 30 min | Autor + moderador | Paquete congelado → plan, roles y fechas |
| 2. Verificar la entrada | D−3 | 5 min | Moderador | `criterios-entrada.php` → E-1..E-6 PASA |
| 3. Apertura | D−3 | 15 min | Todos | Objetivos, checklist y formato explicados; material enviado (E-7: ≥ 48 h antes) |
| 4. Preparación individual | D−3 a D−1 | ≤ 2 h por revisor y sesión (≈ 150–200 LOC/h en lo propio, lectura rápida del resto) | Revisores | Checklist marcado + hallazgos preliminares en el CSV + tiempo en [registro-preparacion.csv](plantillas/registro-preparacion.csv) |
| 5. Reunión S1 | D | ≤ 90 min | Todos | Hallazgos registrados + severidad + decisión |
| 6. Reunión S2 | D+1 | ≤ 90 min | Todos | Ídem |
| 7. Retrabajo | D+1 a D+5 | Según hallazgos | Autor | Cada hallazgo aceptado se corrige con una prueba que lo demuestre, o se rechaza con razón |
| 8. Seguimiento | D+6 | 30–45 min | Moderador | Estados → Verificado; suite y lint en verde |
| 9. Cierre | D+6 | 15 min | Moderador | Criterios de salida S-1..S-5, métricas, acta firmada |

**Por qué estos límites.** Más de ~200 LOC/h en la preparación o más de 90–120 min de reunión bajan claramente la detección: el revisor deja de leer y empieza a hojear. Por eso el paquete de 3069 líneas va en **dos** sesiones.

## 5. Reglas de la reunión

1. Se revisa el **producto**, no a la persona. Los hallazgos se redactan sobre el código («la regla no considera…»).
2. **No se discuten soluciones.** Se registra la propuesta en una frase y se sigue; la decide el autor en el retrabajo.
3. El moderador recorre los ítems **en orden** (RT-01 → RT-15). En cada uno, cada revisor dice sus hallazgos preliminares y se registran.
4. Severidad por consenso con el criterio del [formato](formato-hallazgos.md#2-severidad). Si no hay consenso en 2 minutos, decide el moderador.
5. Las preguntas al autor tienen respuesta corta. Si la respuesta revela un defecto, se registra.
6. Tiempo máximo: 90 minutos. Lo que quede pendiente se registra como «no revisado» y va al seguimiento.
7. Los hallazgos de la [autorrevisión](autorrevision-autor.md) se tratan **igual** que los de los revisores: se confirman, se ajustan o se rechazan.

## 6. Criterios de entrada

Automatizados en [herramientas/criterios-entrada.php](herramientas/criterios-entrada.php). Si alguno falla, la reunión **no** se hace.

| ID | Criterio | Cómo se verifica |
|---|---|---|
| E-1 | Paquete congelado en un commit, sin cambios locales en `micro-his-rbac/` | `git status --porcelain` vacío; se anota el commit |
| E-2 | Suite automatizada sin fallas | `php tests/ejecutar.php` → `Fallan: 0` |
| E-3 | Guardas de contrato (API-01..03) y de capas (ARQ-01..08) en verde | Se leen de la salida de la suite |
| E-4 | Cada sesión ≤ 1600 líneas efectivas | Conteo automático por sesión |
| E-5 | Checklist de 12–15 ítems y formato de hallazgos publicados | Cuenta las filas `RT-xx`; verifica la plantilla CSV |
| E-6 | Autorrevisión del autor registrada con el mismo formato | Cuenta las filas `AR-xx` |
| E-7 | Material enviado ≥ 48 h antes | Manual: el moderador anota la fecha en el acta |
| E-8 | Roles asignados y revisores confirmados | Manual: nombres en el acta |

**La puerta funciona.** La primera corrida se hizo **antes** de escribir el checklist y la autorrevisión, y falló en E-5 y E-6 ([evidencia](evidencia/criterios-entrada-1-antes.txt)). La corrida actual pasa los 6 criterios automáticos ([evidencia](evidencia/criterios-entrada-2-despues.txt)).

## 7. Criterios de salida y decisión

| ID | Criterio de salida | Quién lo verifica |
|---|---|---|
| S-1 | **Cero** hallazgos **críticos** abiertos | Moderador |
| S-2 | Cada **mayor** está corregido y verificado, **o** tiene una decisión documentada aprobada por el moderador más un issue con fecha | Moderador |
| S-3 | Tras el retrabajo: suite sin fallas, `Fallan: 0`, y lint del contrato válido | Moderador (vuelve a correr los comandos) |
| S-4 | Cada hallazgo corregido tiene su **verificación** anotada: prueba nueva o comando | Moderador |
| S-5 | Métricas calculadas y acta firmada y archivada en `semana-13/` | Moderador |

**Decisión al final de cada reunión:**

| Decisión | Cuándo |
|---|---|
| **Aceptado** | Sin críticos ni mayores |
| **Aceptado con condiciones** | Hay mayores, pero ningún crítico, y no más de 3 mayores. El moderador verifica el retrabajo sin otra reunión. |
| **Re-inspección** | Al menos un crítico, **o** más de 3 mayores, **o** densidad Crítico + Mayor > 2 por KLOC. Nueva reunión solo sobre lo corregido. |

## 8. Formato de hallazgos

Definido en [formato-hallazgos.md](formato-hallazgos.md). Cada hallazgo registra:
- ID, ítem RT y ubicación `archivo:línea`;
- descripción y evidencia reproducible;
- regla afectada;
- severidad (Crítico · Mayor · Menor · Observación) y tipo (Defecto · Riesgo · Mejora · Pregunta);
- propuesta, responsable, estado y verificación.

Registro en [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv). Ya contiene los 7 hallazgos de la autorrevisión (AR-01..AR-07) como ejemplo real.

## 9. Métricas

| Métrica | Se registra en | Uso |
|---|---|---|
| Minutos de preparación por revisor | `registro-preparacion.csv` | Tasa de preparación (LOC/h); si supera 300, la preparación fue superficial |
| Hallazgos por severidad, tipo, ítem y origen (preparación / reunión) | `registro-hallazgos.csv` | Densidad por KLOC; utilidad de cada ítem del checklist |
| Duración de cada reunión | Acta | Respetar el límite de 90 min |
| Hallazgos corregidos y verificados / aceptados | CSV (estado) | Criterio S-2 |

**Línea base:** la autorrevisión encontró **7 hallazgos en 3069 líneas**:
- 3 mayores, 2 menores y 2 observaciones;
- **1.6 Crítico + Mayor + Menor por KLOC**.

La revisión del equipo se compara contra esa cifra: si encuentra lo mismo, el checklist cumplió y el autor ya lo había visto; si encuentra más, eso mide el valor de la revisión.

## 10. Riesgos de la propia revisión

| Riesgo | Mitigación |
|---|---|
| Los revisores no conocen PHP ni el código del módulo | La apertura incluye un recorrido de 10 minutos por la [matriz de la S12](../semana-12/matriz-decision-rf-rnf-evidencia.md); cada ítem dice **dónde mirar**. |
| RT-04 (transacción) no se puede comprobar de punta a punta sin PostgreSQL (INT-05 omitida) | Se revisa por lectura de `PdoTransaccion` y `RegistradorDeCambios`. Queda anotado en el acta y se cierra en la S15. |
| El autor sesga la reunión defendiendo el diseño | Regla 2 (no se discuten soluciones) y el moderador controla los turnos. |
| Hallazgos de la autorrevisión aceptados sin mirar | Regla 7: se confirman o se ajustan como cualquier otro. |
| El paquete cambia entre la preparación y la reunión | E-1: el commit queda anotado; si cambia, se vuelve a correr la verificación de entrada. |
