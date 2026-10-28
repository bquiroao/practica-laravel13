# Matriz de riesgos — Pacientes

**Proceso:** «registro, búsqueda y actualización segura de un paciente» · **Plan:** [plan-sqa-pacientes.md](plan-sqa-pacientes.md)

## 1. Escalas

| Valor | Probabilidad (P) | Impacto (I) en la ficha del paciente |
|:-:|---|---|
| 1 | Muy improbable: requiere varias fallas simultáneas | Cosmético |
| 2 | Improbable: hay una defensa y una prueba que la cubre | Molestia operativa: hay que repetir o corregir un trámite |
| 3 | Posible: hay evidencia de que puede ocurrir (hallazgo abierto, caso sin prueba) | Datos inconsistentes o evidencia incompleta, sin daño a una persona |
| 4 | Probable: ocurre en uso normal o en un despliegue estándar | Historia clínica partida (duplicado), edición perdida o cambio de identidad sin evidencia |
| 5 | Casi seguro: ya está demostrado | **Exposición** de una paciente protegida, de datos de otro hospital o del documento en claro |

**Nivel = P × I.**

| Nivel | Puntaje | Qué exige |
|---|---|---|
| **Alto** | 12 o más | Tratamiento obligatorio con fecha |
| **Medio** | 6–11 | Control activo y seguimiento semanal |
| **Bajo** | 5 o menos | Se acepta con monitoreo |

La probabilidad se asigna **con evidencia**: un hallazgo abierto o una métrica en ROJO la suben; una prueba y un control activo la bajan.

## 2. Matriz

| ID | Riesgo | Causa / evidencia | P | I | Nivel | Controles preventivos | Controles detectivos | Métrica | Responsable | Tratamiento |
|---|---|---|:-:|:-:|:-:|---|---|---|---|---|
| RS-01 | **Fuga entre hospitales**: un error revela la existencia, el código o el documento de un paciente de otro hospital | AR-02 (crítico) y AR-03, demostrados; M5 en ROJO | 5 | 5 | **25 · Alto** | CP-03, CP-04 | CD-02 (M5), CD-01 | M5, M7 | RM | **Mitigar en S15**: el 409 no devuelve código ni documento de otro hospital; candidatos solo del hospital activo; prueba de caja negra por tenant |
| RS-02 | **Presencia de una paciente protegida confirmada** por quien tiene su DPI | AR-04: 1 resultado frente a 0; RF-13 lo permite | 3 | 5 | **15 · Alto** | CP-03 (decisión de negocio) | CD-04 (consulta 2: búsquedas por documento) | M7 | RM propone · negocio decide | **Mitigar**: auditar la búsqueda por documento de un protegido y alertar; evaluar exigir `leer_protegido` |
| RS-03 | **Tokens de demostración activos** en un despliegue real | AR-01: `crearKernel` siempre usa `AutenticadorTokenDemo` | 3 | 5 | **15 · Alto** | CP-05 (pendiente) | CD-02 (M7) | M7 | RM | **Mitigar en S15**: autenticador por entorno, fail-closed |
| RS-04 | **Dos fichas de la misma persona** (historia partida) | DPI mal digitado (AR-07); homónimo confirmado por error | 2 | 4 | 8 · Medio | CP-01, CP-04 | CD-02 (M4 = 8/8), CD-04 (consulta 4) | M4 | RM; supervisión revisa las confirmaciones | Mantener M4 al 100 %; validar el dígito verificador del DPI (AR-07) |
| RS-05 | **Cambio de la ficha sin evento**, o evento sin cambio | Atomicidad probada en memoria (M6); con PostgreSQL, INT-00 sin ejecutar | 2 | 4 | 8 · Medio | CP-04 | CD-02 (M3, M6), CD-04 (consulta 5) | M3, M6 | RM | Ejecutar INT-00 con PostgreSQL (S15) |
| RS-06 | **Regla nueva fuera de orden** que revela a un protegido (un 422 o un 409 antes del 403) | Orden único en `RevisionDeFicha` (S7); REF-01/02 | 2 | 5 | 10 · Medio | CP-01, CP-03, CP-04 | CD-01, CD-05 (mutaciones) | M1 | RM | Toda regla nueva va a `RevisionDeFicha` con su REF y su mutación |
| RS-07 | **Edición perdida** por concurrencia | `UPDATE … WHERE version` + `If-Match` (PER-05, M1) | 1 | 4 | 4 · Bajo | CP-04 | CD-01 | M1 | RM | Aceptar con monitoreo |
| RS-08 | **Documento en claro** en una respuesta, un registro o un evento | HMAC + cifrado; OBS-02, PER-07, M17 | 1 | 5 | 5 · Bajo | CP-04 | CD-01, CD-05 | M1, M5 | RM | Aceptar con monitoreo |
| RS-09 | **Contrato y código divergen** (un cliente recibe algo no documentado) | API-01..03 y lint en la suite | 2 | 3 | 6 · Medio | CP-02, CP-07 | CD-01, CD-03 (lint + paridad 37/37) | M1 | RM | Mantener los controles |
| RS-10 | **Defectos de persistencia ocultos** por las pruebas omitidas | 2 pruebas (CON-PDO, INT-00) necesitan PostgreSQL | 3 | 3 | 9 · Medio | — | CD-01 (las omitidas se informan) | M1 | RM | Base de pruebas PostgreSQL en S15 |
| RS-11 | **Recursos y dependencias sin control**: claves de idempotencia sin vencimiento, extensiones no declaradas | AR-05, AR-06 | 3 | 2 | 6 · Medio | CP-06 (README de requisitos) | CD-04 (tamaño de `idempotencia_registros`) | M7 (Menor) | RM | Vencimiento de 24 h y sección «Requisitos» (S15) |
| RS-12 | **Cambio de identidad sin justificación** | Motivo obligatorio con la misma normalización (B4, D-28) | 1 | 3 | 3 · Bajo | CP-04 | CD-04 (consulta 3 = 0 filas) | M3 | RM | Aceptar con monitoreo |

**Resumen:** 3 **Altos** (RS-01, RS-02, RS-03), 6 Medios y 3 Bajos. Los tres Altos vienen de hallazgos de la revisión de la semana 13. Sin la revisión, la matriz habría quedado con 0 Altos y la falsa impresión de que el módulo estaba listo.

## 3. Cómo se actualiza

- **Por iteración**, el RSQA y el RM reestiman P con la evidencia nueva: métricas, hallazgos cerrados, pruebas agregadas. El historial queda en git.
- Un riesgo **Alto** no se cierra por decisión: se cierra cuando la métrica asociada está en VERDE **y** el hallazgo está en estado Verificado.
- Un riesgo nuevo entra con el mismo formato y la evidencia que justifica su P.
