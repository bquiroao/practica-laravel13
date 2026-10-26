# Semana 14 — Confiabilidad y aseguramiento de la calidad (plan SQA) de Pacientes

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 · Pacientes: registro, edición, búsqueda y detalle

**Proceso:** «registro, búsqueda y actualización segura de un paciente»

## Entregables

| Entregable pedido | Dónde |
|---|---|
| Plan SQA con 5–7 métricas | [plan-sqa-pacientes.md](plan-sqa-pacientes.md) §3: **7 métricas**, cada una con definición, fuente, umbral, responsable, frecuencia, acción y línea base medida |
| Matriz de riesgos | [matriz-riesgos.md](matriz-riesgos.md): 12 riesgos con P × I, controles, métrica, responsable y tratamiento |
| Controles preventivos y detectivos | Plan §4 (CP-01..CP-07) y §5 (CD-01..CD-07), cada uno con responsable, umbral, fuente y acción |
| Responsables | Plan §2: RM, RSQA, RS, MR, supervisión e Integrador, con la regla de independencia |
| Calendario de seguimiento | Plan §8: cadencias (cada PR, semanal, por iteración, por liberación) e hitos S14–S16 |

## 1. Las 7 métricas y su línea base

```text
$ php herramientas/medir-sqa.php
VERDE  M1  Pruebas automáticas en verde                     100 % (85/85)
VERDE  M2  Cobertura de códigos de error del dominio        100 % (13/13)
VERDE  M3  Trazabilidad de escrituras y consultas           100 % (5/5 escenarios)
VERDE  M4  Integridad de la identidad (anti-duplicados)     100 % (8/8 intentos bloqueados)
ROJO   M5  Respuestas sin datos de protegidos ni de otro hospital  75 % (6/8 respuestas limpias)
       fugas: otro hospital registra su DPI (409) (P-000001, 0101); otro hospital registra a su homónima (409) (P-000001)
VERDE  M6  Confiabilidad ante fallas inyectadas             100 % (6/6)
ROJO   M7  Hallazgos de revisión Crítico/Mayor sin verificar  4 (Crítico 1 · Mayor 3) · Menor 3
VERDE: 5  ÁMBAR: 0  ROJO: 2
```

| Atributo que pide la tarea | Métricas |
|---|---|
| Calidad del flujo | M1, M2 |
| Confiabilidad | M6 |
| Seguridad | M5, M7 |
| Trazabilidad | M3 |
| Riesgos de identidad, demografía, búsqueda, edición, duplicados y confidencialidad | M4 (identidad y duplicados), M5 (confidencialidad y búsqueda), M3 (edición), y la matriz de riesgos |

**Por qué la línea base tiene dos ROJOS y eso es bueno.** M5 y M7 no se inventaron para quedar en rojo: miden **automáticamente** los defectos que encontró la revisión de la semana 13 (AR-02 y AR-03). Un plan SQA que arranca todo en VERDE con defectos conocidos estaría midiendo lo que no importa.

## 2. Las métricas detectan defectos: comprobado

En copias aparte del Micro-HIS se inyectaron tres defectos ([evidencia](evidencia/deteccion-mutantes.txt)):

| Mutante | Lo detectan |
|---|---|
| 1 · Deja de reservar el código de un protegido en el 409 | M1 + **M5** (fuga nueva «P-000002») |
| 2 · Guarda la ficha fuera de la transacción | M1 + **M6** (bitácora caída deja la ficha) |
| 3 · El evento de edición pierde el «antes» | M1 + **M3** |

Cada uno lo detectan dos métricas independientes: la suite y la métrica del atributo afectado.

## 3. Riesgos

| Nivel | Riesgos |
|---|---|
| **Alto** (≥ 12) | RS-01 fuga entre hospitales (25) · RS-02 presencia de una protegida confirmada por DPI (15) · RS-03 tokens de demostración en producción (15) |
| Medio | RS-04 duplicado de una persona · RS-05 cambio sin evento · RS-06 regla fuera de orden · RS-09 contrato y código divergen · RS-10 pruebas omitidas · RS-11 recursos sin control |
| Bajo | RS-07 edición perdida · RS-08 documento en claro · RS-12 cambio de identidad sin motivo |

Los tres Altos salen de la revisión de la semana 13. Cada uno tiene tratamiento con fecha (S15) y una métrica que dirá si se cerró.

## 4. Cómo se verifica

```bash
# desde docs/modulos/pacientes/semana-14
php herramientas/medir-sqa.php                                 # línea base: 5 VERDE · 2 ROJO (código de salida 1)
SQA_MICROHIS=<copia con un defecto> php herramientas/medir-sqa.php   # comprobación de detección (CD-07)
```

## 5. Limitaciones

- **Medido en memoria.** M3, M5 y M6 usan los adaptadores en memoria. Con PostgreSQL, la atomicidad (M6) y la bitácora (CD-04) se confirman en la S15.
- **Roles por confirmar.** RSQA, RS y MR deben ser compañeros del grupo; el plan lo exige por independencia.
- **Sin métrica de rendimiento.** El flujo no tiene un RNF de tiempo propio; la frontera medida en la semana 5 (0,003 ms en proceso) no justifica una métrica semanal. Si el HIS fija un SLA, entraría como M8.
