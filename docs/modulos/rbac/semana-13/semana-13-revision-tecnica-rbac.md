# Semana 13 — Revisión técnica formal de RBAC

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC: roles, permisos y protección de rutas

**Proceso:** «asignación de rol, autorización de una operación y denegación trazable»

## Entregables

| Pedido | Dónde |
|---|---|
| Plan de revisión | [plan-revision-tecnica.md](plan-revision-tecnica.md): objetivo, preguntas, paquete en 2 sesiones, calendario, reglas de la reunión, métricas y riesgos. Incluye el [diagrama del proceso](diagramas/semana-13-proceso-revision.png). |
| Checklist de 12–15 ítems | [checklist-revision.md](checklist-revision.md): **15 ítems** (RT-01..RT-15) en 4 perspectivas. Cada uno dice dónde mirar, cómo se verifica, en qué regla se basa y qué severidad tiene si falla. |
| Roles de autor, moderador y revisores | [Plan §3](plan-revision-tecnica.md#3-roles-y-responsabilidades): autor, moderador, registrador y revisores R1 (seguridad), R2 (contrato) y R3 (dominio e integridad). Cada rol dice qué hace y qué **no** hace. |
| Criterios de entrada y salida | [Plan §6–7](plan-revision-tecnica.md#6-criterios-de-entrada): **E-1..E-8** (6 automatizados en [criterios-entrada.php](herramientas/criterios-entrada.php)) y **S-1..S-5** con reglas de decisión |
| Formato de hallazgos | [formato-hallazgos.md](formato-hallazgos.md): 12 campos, severidad por impacto RBAC, tipo y métricas. Plantillas: [registro-hallazgos.csv](plantillas/registro-hallazgos.csv), [registro-preparacion.csv](plantillas/registro-preparacion.csv) y [acta-revision.md](plantillas/acta-revision.md). |

## 1. Enfoque

La tarea pide revisar seis temas. Cada uno quedó cubierto por ítems concretos del checklist:

| Tema | Ítems |
|---|---|
| Reglas de dominio | RT-01, RT-02, RT-03, RT-11 |
| Contrato API | RT-06, RT-07, RT-12 |
| Permisos y tenant | RT-08, RT-09, RT-10, RT-15 |
| Integridad de roles, permisos, rutas, decisiones y denegaciones | RT-04, RT-05 |
| Dependencias estrictamente necesarias | RT-13, RT-14 |

**Tres decisiones del plan:**

1. **Inspección formal** y no *walkthrough*, porque el código controla el acceso a datos clínicos.
2. **Dos sesiones** de unas 1500 líneas cada una, por el límite de preparación (≈ 150–200 LOC/h) y de reunión (≤ 90 min).
3. **Verificación objetiva:** cada ítem trae su forma de comprobarse (prueba, comando o búsqueda). El revisor lee además buscando lo que la prueba no cubre.

## 2. La preparación ya se ejecutó: puerta de entrada y autorrevisión

**Criterios de entrada automatizados.** Se corrieron dos veces:

| Corrida | Resultado | Evidencia |
|---|---|---|
| Antes de escribir el checklist y la autorrevisión | **FALLA** en E-5 y E-6 | [criterios-entrada-1-antes.txt](evidencia/criterios-entrada-1-antes.txt) |
| Ahora | **6 de 6 PASA**: commit congelado, 80 pruebas sin fallas, 11 guardas en verde, S1 = 1512 y S2 = 1557 líneas, checklist de 15 ítems, 7 hallazgos del autor | [criterios-entrada-2-despues.txt](evidencia/criterios-entrada-2-despues.txt) |

E-7 (envío ≥ 48 h antes) y E-8 (roles confirmados) los verifica el moderador a mano.

**Autorrevisión del autor** ([autorrevision-autor.md](autorrevision-autor.md)). Apliqué el checklist a mi propio código antes de convocar. Encontré **7 hallazgos reales**:

| ID | Severidad | Hallazgo |
|---|---|---|
| AR-01 | Mayor | La autenticación de demostración está siempre activa en el punto de entrada real |
| AR-02 | Mayor | R-03 deja duplicar membresías con fecha de fin |
| AR-04 | Mayor | La falla doble de la bitácora es silenciosa |
| AR-03 | Menor | Un sondeo entre hospitales por permisos efectivos no deja rastro: lo introdujo el cambio de la S12 |
| AR-05 | Menor | `mbstring` no está declarada como requisito |
| AR-06 | Observación | La librería cliente se despliega con el servidor |
| AR-07 | Observación (pregunta) | ¿Revocar una membresía futura es intencional? |

Tres de ellos (AR-01, AR-02 y AR-03) se reproducen con [verificar-autorrevision.php](herramientas/verificar-autorrevision.php) ([salida](evidencia/autorrevision-verificaciones.txt)).

Estos hallazgos llegan **abiertos** a la reunión: el equipo los confirma o los ajusta. **No se corrigieron esta semana**, porque la tarea es preparar la revisión. Corregirlos antes de la reunión vaciaría la revisión de contenido y rompería E-1 (paquete congelado).

## 3. Qué falta para ejecutar la revisión

| Pendiente | Quién |
|---|---|
| Confirmar los nombres de moderador, registrador y revisores R1–R3 (E-8) | El grupo |
| Fijar el día D y enviar el material ≥ 48 h antes (E-7) | Moderador |
| Preparación individual y reuniones S1 y S2 | Revisores |
| Retrabajo de los hallazgos aceptados, cada uno con una prueba | Autor |
| Seguimiento, criterios de salida y acta | Moderador |

## 4. Verificación

```bash
cd docs/modulos/rbac/semana-13
php herramientas/criterios-entrada.php        # 6/6 PASA (E-7 y E-8 manuales)
php herramientas/verificar-autorrevision.php  # reproduce AR-01, AR-02 y AR-03
```

Esta semana no se modificó el Micro-HIS: 80 pruebas, 78 pasan, 0 fallan y 2 se omiten (PostgreSQL), igual que en la S12.
