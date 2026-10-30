# Matriz de riesgos — Gobernanza y auditoría

**Parte del** [plan SQA](plan-sqa-auditoria.md) · **Revisión:** mensual (plan §8) · **Última:** 26/10/2026

## 1. Escalas

| Probabilidad | Criterio |
|:-:|---|
| 1 · Rara | No ocurrió y requiere varias fallas juntas |
| 2 · Posible | Puede ocurrir con un error humano o de configuración |
| 3 · Probable | Ya se observó en las pruebas o en la revisión |

| Impacto | Criterio |
|:-:|---|
| 1 · Bajo | Molestia; no se pierde evidencia ni datos |
| 2 · Medio | Una investigación da una respuesta incompleta o tardía |
| 3 · Alto | Se pierde o se invalida la evidencia, o se expone información de otro hospital |

**Nivel = probabilidad × impacto:**

| Nivel | Valor | Exige |
|---|:-:|---|
| **Alto** | 6–9 | Control preventivo **y** detectivo, y una acción con plazo |
| **Medio** | 3–4 | Control detectivo |
| **Bajo** | 1–2 | Monitoreo |

## 2. Matriz

| ID | Riesgo | P | I | Nivel | Control preventivo | Control detectivo | Responsable | Estado |
|---|---|:-:|:-:|:-:|---|---|---|---|
| RK-01 | Despliegue con los tokens de demostración: lectura de toda la red con un token público (AR-01) | 2 | 3 | **6 Alto** | CP-6 (fail-closed por configuración) | M6; M4 en el entorno de despliegue | Desarrollador · Seguridad | Abierto: acción el 29/10 |
| RK-02 | La verificación deja de funcionar al crecer la cadena (AR-02) | 3 | 3 | **9 Alto** | Verificación por lotes | M7; C-D5 (volumen real) | Desarrollador · DBA | Abierto: acción el 02/11 |
| RK-03 | Alguien con acceso a la base borra o modifica filas, saltándose la aplicación | 1 | 3 | 3 Medio | CP-5 (solo INSERT/SELECT); trigger | C-D2, C-D3, C-D7 (ancla) y M3 | DBA · Seguridad | Controlado |
| RK-04 | Fuga entre hospitales por un defecto en el alcance | 1 | 3 | 3 Medio | `Alcance` como único dueño (D-22); CP-4 | M4 (12 sondas), M22 | Desarrollador · Seguridad | Controlado |
| RK-05 | «¿Quién vio la ficha?» incompleto por recursos con otro formato (AR-04) | 3 | 2 | **6 Alto** | Regla de formato del recurso | C-D6 | Desarrollador · QA | Abierto: acción el 02/11 |
| RK-06 | Uso de correcciones para encubrir un acceso indebido | 2 | 2 | 4 Medio | Una sola corrección por original; motivo obligatorio; el original sigue visible | C-D4 | Seguridad | Controlado |
| RK-07 | La meta-auditoría satura la bitácora (lecturas masivas) (AR-05) | 2 | 2 | 4 Medio | Sin reintentos automáticos en la UI (semana 10) | C-D5 | DBA | Monitoreo |
| RK-08 | Un emisor pierde movimientos por reutilizar `event_id` en otro hospital (AR-06) | 2 | 2 | 4 Medio | Contrato que documente la unicidad global | Bandeja local del emisor (409 visibles) | Desarrollador · emisores | Abierto: documentar |
| RK-09 | Las pruebas de PostgreSQL no corren en el CI y una regresión de SQL pasa | 2 | 2 | 4 Medio | CI con PostgreSQL (M1 ÁMBAR si hay omitidas) | M1, M2 | Desarrollador · QA | Abierto |
| RK-10 | El contrato documenta códigos que nada prueba, y los clientes los manejan mal | 2 | 1 | 2 Bajo | CP-1 | M5 (detectó D3) | Desarrollador | Controlado |

**Resumen:**
- **3 riesgos altos** (RK-01, RK-02 y RK-05). Todos vienen de la autorrevisión de la semana 13 y tienen acción con fecha en el plan §7.
- **Ningún riesgo alto queda sin control detectivo.**

## 3. Cómo se actualiza

1. **Cada mes** (plan §8), el líder técnico y QA recalculan P × I con los datos del tablero y de los controles detectivos. Por ejemplo, C-D5 calibra RK-02 y RK-07.
2. **Un incidente** (plan §6) sube la probabilidad del riesgo asociado a 3, hasta que su control se demuestre efectivo.
3. **Un riesgo se cierra** cuando su control preventivo está implementado **y** su control detectivo da 0 durante un mes.
