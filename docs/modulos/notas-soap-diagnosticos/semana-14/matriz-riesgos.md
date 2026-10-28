# Matriz de riesgos — Notas SOAP y diagnósticos

**Plan:** [plan-sqa-notas-soap.md](plan-sqa-notas-soap.md) · **Nivel = Probabilidad × Impacto** (1 a 5). **Alto** ≥ 12: tratamiento obligatorio con fecha · **Medio** 6–11: control activo y seguimiento semanal · **Bajo** ≤ 5: se acepta con monitoreo.

| Valor | Probabilidad (con evidencia) | Impacto en el paciente y sus datos |
|:-:|---|---|
| 1 | Requiere varias fallas a la vez | Cosmético |
| 2 | Hay una defensa y una prueba que la cubre | Molestia operativa |
| 3 | Hay evidencia de que puede pasar (hallazgo abierto, caso sin prueba) | Dato inconsistente o evidencia incompleta |
| 4 | Pasa en uso normal o en un despliegue estándar | Nota duplicada o trabajo clínico bloqueado |
| 5 | Casi seguro | Datos clínicos de otro hospital expuestos o nota firmada alterada |

| ID | Riesgo | Causa / evidencia | P | I | Nivel | Preventivos | Detectivos | Métrica | Resp. | Tratamiento |
|---|---|---|:-:|:-:|:-:|---|---|---|---|---|
| RN-01 | Una regla defectuosa deja escribir a quien no debe | Cambio en un caso de uso sin prueba; el mutante 1 lo simula y se detecta | 2 | 5 | **10 Medio** | CP-01, CP-02 | CD-01, CD-04 | M1, M5 | RM | Mitigar: mutantes detectados |
| RN-02 | **Lectura entre hospitales** | AR-01, reproducido: M5 = 60 % | 4 | 5 | **20 Alto** | CP-03, CP-04 (pendiente) | CD-02, CD-06 | M5, M6 | RM, RS | **Corregir en S15** |
| RN-03 | **Autenticación de demostración en un despliegue** | AR-04: `crearKernel()` usa tokens fijos | 3 | 5 | **15 Alto** | CP-05 (pendiente) | CD-02 (M6) | M6 | RM | **Corregir en S15** |
| RN-04 | Nota sin diagnóstico o firma a medias | Falla de base dentro de la transacción; el mutante 2 lo simula | 1 | 5 | **5 Bajo** | Transacción (R-06) | CD-01, CD-04 | M4 | RM | Aceptar con monitoreo: M4 = 100 % |
| RN-05 | **Nota duplicada por reintento** | B-6 / AR-03: sin clave, el reintento duplica (M7 = 75 %) | 3 | 4 | **12 Alto** | CP-01; la app siempre manda la clave (R-M5) | CD-02 (M7) | M7 | RM | Mitigar en S16: clave obligatoria |
| RN-06 | Contrato divergente para otros módulos | Cambio en `Rutas` o en una vista sin actualizar `openapi.yaml` | 2 | 4 | **8 Medio** | CP-02, CP-06 | CD-01, CD-03 | M3 | RM | Mitigar: M3 = 100 % y lint |
| RN-07 | Pruebas débiles: afirman el código HTTP y no el de negocio | M2 = 66.7 %: 6 códigos sin afirmar | 3 | 3 | **9 Medio** | CP-01 | CD-02 (M2) | M2 | RM | Mitigar en S15 |
| RN-08 | Regresión en persistencia real sin detectar | INT-06 (PostgreSQL) omitida en la suite | 3 | 3 | **9 Medio** | CP-02; INT con SQLite | CD-01 | M1 | RM | Mitigar en S15: correr INT-06 con PostgreSQL |
| RN-09 | Interfaz que pierde o duplica una nota con mala señal | Prototipo sin servidor real | 2 | 4 | **8 Medio** | Reglas R-M1..R-M6 (S10) | CD-05 | — | RM | Mitigar: recorrido en cada PR del prototipo |
| RN-10 | Firma después del cierre del expediente | AR-02: firmar no exige expediente abierto | 3 | 2 | **6 Medio** | CP-03 (decisión en la revisión) | CD-02 (M6) | M6 | RM, MR | Decidir en la revisión S13 |

## Mapa de calor (línea base)

| P \ I | 1 | 2 | 3 | 4 | 5 |
|:-:|:-:|:-:|:-:|:-:|:-:|
| **4** | | | | | **RN-02** |
| **3** | | RN-10 | RN-07, RN-08 | **RN-05** | **RN-03** |
| **2** | | | | RN-06, RN-09 | RN-01 |
| **1** | | | | | RN-04 |

**Alto:** RN-02, RN-03, RN-05 · **Medio:** 6 riesgos, todos con control activo o corrección planificada · **Bajo:** RN-04.

## Riesgo residual esperado

| ID | Hoy | Después | Por qué baja |
|---|:-:|:-:|---|
| RN-02 | 20 | 5 (P1 × I5) | El hospital del expediente se compara al leer; M5 = 100 % con prueba (S15) |
| RN-03 | 15 | 5 (P1 × I5) | Autenticador por entorno con prueba (S15) |
| RN-05 | 12 | 4 (P1 × I4) | Clave obligatoria; M7 = 100 % (S16) |
| RN-07 | 9 | 3 (P1 × I3) | M2 = 100 % (S15) |
