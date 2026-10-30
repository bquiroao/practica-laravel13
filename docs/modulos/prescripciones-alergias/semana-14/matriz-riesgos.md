# Matriz de riesgos — Prescripciones con validación de alergias

**Parte del** [plan SQA](plan-sqa-prescripciones.md) · **Revisión:** mensual (plan §8) · **Última:** 27/10/2026

## 1. Escalas

| Probabilidad | Criterio |
|:-:|---|
| 1 · Rara | No ocurrió y requiere varias fallas juntas |
| 2 · Posible | Puede ocurrir con un error humano o de configuración |
| 3 · Probable | Ya se observó en las pruebas, en la medición o en la revisión |

| Impacto | Criterio |
|:-:|---|
| 1 · Bajo | Molestia; ningún riesgo clínico ni pérdida de datos |
| 2 · Medio | Una prescripción demorada, una respuesta engañosa o una traza perdida |
| 3 · Alto | **Un paciente puede recibir un medicamento al que es alérgico**, o alguien emite o anula a nombre de otro |

**Nivel = probabilidad × impacto:**

| Nivel | Valor | Exige |
|---|:-:|---|
| **Alto** | 6–9 | Control preventivo **y** detectivo, y una acción con plazo |
| **Medio** | 3–4 | Control detectivo |
| **Bajo** | 1–2 | Monitoreo |

## 2. Matriz

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo | Responsable | Estado |
|---|---|:-:|:-:|:-:|---|---|---|---|
| RK-01 | Una alergia registrada solo por nombre, escrita de otra forma, no bloquea (falso negativo) (AR-03) | 3 | 3 | **9 Alto** | CP-7 (alérgenos con código); criterio de farmacia para nombres que contienen el principio activo | **M3** (corpus); **C-D4** (exposición real) | Farmacéutico · Desarrollador | Abierto: decisión el 03/11 y acción el 10/11 |
| RK-02 | Despliegue con los tokens de demostración: cualquiera emite y anula a nombre de un médico (AR-01) | 2 | 3 | **6 Alto** | CP-6 (fail-closed por configuración) | M7; M4 contra el entorno desplegado | Desarrollador · Seguridad | Abierto: acción el 30/10 |
| RK-03 | Otro médico reusa una clave y recibe «ya emitida» una prescripción ajena, sin traza (AR-02) | 2 | 2 | 4 Medio | Huella con el usuario | **M4** (sonda «clave ajena») | Desarrollador | Abierto: acción el 03/11 |
| RK-04 | Con el Expediente caído, se emite sin verificar (fail-open) | 1 | 3 | 3 Medio | Puerto con excepción obligatoria (D-06); M1/M2 | **M5**; **C-D6** (caídas reales); M2 (M1, M2, M20, M21) | Desarrollador · DBA | Controlado |
| RK-05 | Alguien con acceso a la base modifica o borra una prescripción | 1 | 3 | 3 Medio | CP-5 (solo INSERT/SELECT); triggers | **C-D3**; INT-02 | DBA · Seguridad | Controlado |
| RK-06 | Fuga entre hospitales por un defecto en un repositorio | 1 | 3 | 3 Medio | El hospital sale del token (D-09); CP-4 | **M4** (detectó D2); M16 | Desarrollador · Seguridad | Controlado |
| RK-07 | Uso rutinario del override: justificar por costumbre | 2 | 2 | 4 Medio | Justificación ≥ 20 y permiso; la previa informa `puede_justificar` | **C-D5** | Farmacéutico | Monitoreo |
| RK-08 | Las trazas no llegan a Auditoría (la bandeja no se vacía) | 2 | 2 | 4 Medio | Bandeja en la misma transacción (D-11) | **C-D7**; **C-D2** | DBA | Monitoreo |
| RK-09 | Las pruebas de PostgreSQL no corren en el CI y una regresión de SQL pasa | 2 | 2 | 4 Medio | CI con PostgreSQL (M1 ÁMBAR si hay omitidas) | M1, M2 | Desarrollador · QA | Abierto |
| RK-10 | Una intolerancia leve bloquea igual que una alergia y los médicos dejan de confiar en el sistema (AR-07) | 2 | 1 | 2 Bajo | Criterio de farmacia | C-D5 (si suben los cambios de medicamento) | Farmacéutico | Monitoreo |
| RK-11 | El contrato documenta códigos que nada prueba o que nadie emite (`SERVICIO_NO_DISPONIBLE`) | 3 | 1 | 3 Medio | CP-1 | **M6** (detectó D4) | Desarrollador | Abierto: acción el 10/11 |

**Resumen:**
- **2 riesgos altos:** RK-01 (9) y RK-02 (6). Los dos salen de la autorrevisión de la semana 13 y tienen acción con fecha en el plan §7.
- **RK-01 es el único riesgo con P = 3 e I = 3:** ya se observó (M3 9/12) y su impacto es el que el módulo existe para evitar.
- **Ningún riesgo alto queda sin control detectivo.** Para RK-01 hay dos: M3 lo mide contra un corpus y C-D4 mide la exposición en la base real.

## 3. Cómo se actualiza

1. **Cada mes** (plan §8), el líder técnico, QA y farmacia recalculan P × I con los datos del tablero y de los controles detectivos. Por ejemplo, C-D4 calibra RK-01 y C-D6, RK-04.
2. **Un incidente** (plan §6) sube a 3 la probabilidad del riesgo asociado, hasta que su control se demuestre efectivo.
3. **Un riesgo se cierra** cuando su control preventivo está implementado **y** su control detectivo da 0 durante un mes.
