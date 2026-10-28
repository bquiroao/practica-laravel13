# Semana 14 — Confiabilidad y aseguramiento de la calidad (plan SQA) de Notas SOAP

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73-svg`) · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Proceso:** «registro de nota SOAP y asociación de diagnóstico clínico»

| Pedido | Dónde |
|---|---|
| Plan SQA con 5–7 métricas | [plan-sqa-notas-soap.md §3](plan-sqa-notas-soap.md#3-métricas-7): **7 métricas** con fórmula, fuente, umbral, responsable, frecuencia, acción y **línea base medida** |
| Matriz de riesgos | [matriz-riesgos.md](matriz-riesgos.md): **10 riesgos** con probabilidad × impacto, mapa de calor, controles, métrica, responsable, tratamiento y riesgo residual |
| Controles preventivos y detectivos | [Plan §4–5](plan-sqa-notas-soap.md#4-controles-preventivos): **6 preventivos** (CP-01..06) y **6 detectivos** (CD-01..06), cada uno con responsable, umbral, fuente, acción, frecuencia y riesgos |
| Responsables | [Plan §2](plan-sqa-notas-soap.md#2-responsables): responsable del módulo, responsable SQA independiente, revisor de seguridad, moderador e integrador |
| Calendario de seguimiento | [Plan §8](plan-sqa-notas-soap.md#8-calendario-de-seguimiento): por PR, semanal, por iteración y por liberación; hitos S14 → S15 → S16 |

## 1. Las métricas se miden, no se declaran

[herramientas/medir-sqa.php](herramientas/medir-sqa.php) calcula las 7 métricas sobre el Micro-HIS real:

```text
VERDE  M1  Pruebas automáticas en verde              100.0 % (123/123), 1 omitida(s)
ROJO   M2  Códigos de error afirmados por pruebas    66.7 % (12/18)  → faltan tenant_requerido, token_invalido, ruta_no_encontrada, metodo_no_permitido, nota_firmada, servicio_no_disponible
VERDE  M3  Conformidad contrato ↔ código           100.0 % (5/5) operaciones
VERDE  M4  Integridad ante fallas inyectadas (R-06)   100.0 % (5/5) escenarios
ROJO   M5  Aislamiento por hospital (R-01)            60.0 % (3/5) intentos rechazados
ROJO   M6  Hallazgos Crítico/Mayor sin verificar     2 (Crítico 1 · Mayor 1)
ROJO   M7  Escrituras con reintento seguro            75.0 % (3/4)  → no seguro: registrar sin clave
VERDE: 3  ÁMBAR: 0  ROJO: 4
```

- **Hallazgo nuevo (M2).** Las pruebas comprueban el código HTTP pero no siempre el código de negocio. Si un cambio devolviera `nota_ya_corregida` en lugar de `nota_firmada` (los dos son 409), ninguna prueba lo notaría: pasa con 6 de los 18 códigos.
- **M5 y M6** miden en el código lo que la revisión S13 encontró a mano (AR-01: un médico de otro hospital lee y lista las notas).
- **M7** mide la brecha B-6: registrar sin clave duplica la nota si se reintenta.

## 2. Los controles detectan defectos: comprobado

Se inyectaron dos defectos en copias aparte del Micro-HIS (`git archive`, sin tocar el repositorio) y se midieron con `SQA_MICROHIS=<copia>`:

| Mutante | Qué rompe | Métricas que bajan |
|---|---|---|
| 1 · `FirmarNota` deja de verificar que firme el autor | R-02, R-07 | **M1** 99.2 % (122/123) y **M5** 40 % (2/5) |
| 2 · La transacción en memoria deja de revertir | R-06 | **M1** 96.7 % (119/123) y **M4** 40 % (2/5) |

Cada defecto lo detectan **dos métricas independientes**. Esto se volvió el control detectivo CD-04: si un mutante sobrevive, falta una prueba.

## 3. Lo que el plan conecta

| Pieza | De dónde viene |
|---|---|
| M5, M6, RN-02, RN-03, RN-10 | Hallazgos AR-01, AR-04 y AR-02 de la autorrevisión (S13) |
| M7, RN-05 | Medición de reintentos (S10) y brecha B-6 |
| M3, CP-06 | Contrato verificado (S5) y cambio con guarda API-05 (S12) |
| CP-03 | Revisión técnica formal (S13) |
| CD-05, RN-09 | Recorrido automático del prototipo (S11) |

**Riesgos altos:** lectura entre hospitales (RN-02, 20), autenticación de demostración (RN-03, 15) y nota duplicada por reintento (RN-05, 12). Los tres tienen acción con fecha en el [plan §7](plan-sqa-notas-soap.md#7-plan-de-acción-de-la-línea-base).

## 4. Lo que queda pendiente, dicho claro

- **Controles definidos pero no implementados:** CP-04 (hospital en cada dato), CP-05 (configuración por entorno) y CD-06 (revisión de la bitácora, requiere PostgreSQL). Se implementan en la S15 como retrabajo de la revisión.
- **Roles sin nombre:** RSQA, RS y MR quedan *por confirmar* con el grupo.
- **Sin cambios en el código:** el Micro-HIS sigue con 124 pruebas (123 pasan, 1 omitida).
- **M6 lee el registro de la S13:** si ese PR aún no está en `develop`, M6 sale en ÁMBAR («sin registro»).

## 5. Verificación y defensa oral

```bash
cd docs/modulos/notas-soap-diagnosticos/semana-14
php herramientas/medir-sqa.php                                  # línea base: 3 VERDE · 4 ROJO (código 1 por los ROJO)
SQA_MICROHIS=<copia con un defecto> php herramientas/medir-sqa.php   # el defecto pone métricas en ROJO
```

**En 2 minutos:** «El plan SQA tiene 7 métricas y ninguna es una opinión: un script las mide sobre el código. Hoy dan 3 en verde y 4 en rojo, y cada rojo tiene responsable, acción y fecha. Para probar que las métricas sirven, metí dos defectos a propósito en una copia: cada uno lo detectaron dos métricas. El riesgo más alto es el que encontró la revisión de la semana 13: un médico de otro hospital puede leer las notas. Se corrige en la semana 15.»

**Pregunta probable:** *¿Por qué presentar un plan con 4 métricas en rojo?* Porque la línea base debe ser la real. Un plan que solo muestra verdes no ayuda a decidir; estos rojos dicen exactamente qué corregir primero.
