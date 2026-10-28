# Semana 14 — Confiabilidad y aseguramiento de calidad (plan SQA) de RBAC

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC: roles, permisos y protección de rutas

**Proceso:** «asignación de rol, autorización de una operación y denegación trazable»

## Entregables

| Pedido | Dónde |
|---|---|
| Plan SQA con 5–7 métricas | [plan-sqa-rbac.md](plan-sqa-rbac.md) §3: **7 métricas** (M1–M7). Cada una con definición, **fuente, umbral, responsable, frecuencia y acción**, y su **línea base medida**. |
| Matriz de riesgos | [matriz-riesgos.md](matriz-riesgos.md): **12 riesgos** con probabilidad × impacto, mapa de calor, controles, métrica, responsable, tratamiento y riesgo residual esperado |
| Controles preventivos y detectivos | [Plan §4–5](plan-sqa-rbac.md#4-controles-preventivos): **7 preventivos** (CP-01..07) y **7 detectivos** (CD-01..07), cada uno con responsable, umbral, fuente, acción, frecuencia y riesgos que cubre |
| Responsables | [Plan §2](plan-sqa-rbac.md#2-organización-y-responsables): responsable del módulo, responsable SQA independiente, revisor de seguridad, moderador e integrador. Regla de independencia: quien corrige no verifica. |
| Calendario de seguimiento | [Plan §8](plan-sqa-rbac.md#8-calendario-de-seguimiento): cadencias por PR, semanal, por iteración y por liberación; hitos S14 → S15 → S16; [tablero](plantillas/tablero-seguimiento.csv) con la línea base |

## 1. Las métricas se miden, no se declaran

[herramientas/medir-sqa.php](herramientas/medir-sqa.php) calcula las 7 métricas sobre el Micro-HIS real y las compara con su umbral:

```text
VERDE  M1  Pruebas automáticas en verde                     100 % (78/78)
ROJO   M2  Cobertura de códigos de decisión                 88.9 % (16/18)  → faltan USUARIO_INACTIVO, USUARIO_NO_ENCONTRADO
VERDE  M3  Conformidad contrato ↔ código                    100 % (7/7 operaciones)
VERDE  M4  Trazabilidad de denegaciones                     100 % (6/6 intentos) · ruido: 0
VERDE  M5  Confiabilidad fail-closed ante fallas inyectadas 100 % (7/7)
ROJO   M6  Hallazgos de revisión Crítico/Mayor sin verificar 3 (Crítico 0 · Mayor 3)
ÁMBAR  M7  Tiempo de decisión p95 (RNF-09)                  0.006 ms (en memoria; falta PostgreSQL)
VERDE: 4  ÁMBAR: 1  ROJO: 2
```

**Hallazgo nuevo de esta semana (M2).** Las pruebas de asignación comprueban el **código HTTP** (422/404), pero no el **código de negocio**. Si un cambio devolviera `ROL_INVALIDO` en lugar de `USUARIO_INACTIVO`, con el mismo 422, ninguna prueba fallaría. Queda en el plan de acción para la S15.

## 2. Los controles detectan defectos: comprobado

Se inyectaron dos defectos en una copia aparte del código (`git archive`, sin tocar el repositorio):

| Defecto inyectado | Métricas que se ponen en rojo |
|---|---|
| Fail-open: una regla que lanza deja pasar | M1 y M5 |
| Denegación sin registro | M4 (0/6), M1 (64/78) y M5 |

Ambos quedan detectados por al menos dos métricas independientes ([evidencia](evidencia/deteccion-mutantes.txt)). Esto se volvió un control detectivo: CD-07.

## 3. Lo que el plan conecta

| Pieza | De dónde viene |
|---|---|
| M6 y los riesgos RS-03, RS-04 y RS-05 | Hallazgos AR-01, AR-04 y AR-02 de la autorrevisión de la **S13** |
| RS-09 | Pruebas CON-PDO e INT omitidas desde la S3, por falta de PostgreSQL |
| M7 | RNF-09 de la **S2**, todavía sin medir con base de datos |
| CD-03 y CD-06 | Paridad y recorrido de las **S11–S12** |
| CP-03 | Revisión formal de la **S13** |

**Resumen de la línea base:**

| Pieza | Estado |
|---|---|
| Riesgos altos | 3: tokens de demostración (RS-03, 15), disponibilidad del PDP (RS-08, 12), persistencia sin probar (RS-09, 12) |
| Plan de acción | 7 acciones con responsable, plazo (S15) y cómo se verifica |

## 4. Honestidad sobre lo pendiente

- **Controles definidos pero no implementados.** CP-05 (configuración por entorno) y CD-05 (alerta de falla doble) se implementan en S15 como retrabajo de la revisión S13.
- **Consulta sin ejecutar.** [revision-bitacora.sql](herramientas/revision-bitacora.sql) (CD-04) requiere PostgreSQL configurado.
- **Medición sin base de datos.** M7 se midió en memoria; por eso queda en ÁMBAR y no en VERDE.
- **Roles sin nombre.** RSQA, RS y MR quedan *por confirmar* con el grupo.
- **Sin cambios al código.** El Micro-HIS no se modificó esta semana: sigue con 80 pruebas, 78 pasan, 0 fallan y 2 se omiten.

## 5. Verificación

```bash
cd docs/modulos/rbac/semana-14
php herramientas/medir-sqa.php                               # línea base: 4 VERDE · 1 ÁMBAR · 2 ROJO (código 1 por los ROJO)
SQA_MICROHIS=<copia con defecto> php herramientas/medir-sqa.php   # comprobar que se detecta
```
