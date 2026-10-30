# Semana 14 — Plan de aseguramiento de calidad (SQA) del módulo QA E2E, CI y despliegue

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana (@bquiroao) |
| **Alcance** | `micro-his-e2e` (API 1.2.0) y la consola de release |
| **Objetivo** | Que ninguna versión del módulo llegue a `develop` sin cumplir sus reglas, y que se note **antes** cuando la calidad baje |

## 1. Métricas (7)

| # | Métrica | Dueño | Umbral | Fuente (comando) | Valor hoy | Acción si no cumple |
|---|---|---|---|---|---|---|
| Q-1 | Pruebas en verde contra PostgreSQL | Autor del cambio | 100 % (0 fallas, 0 omitidas) | `E2E_DB_DSN=… php tests/ejecutar.php` | 89/89 | No se abre el PR |
| Q-2 | Mutaciones detectadas | Autor + revisor técnico | 100 % | `php tests/mutaciones.php` | 34/34 | Escribir la prueba que falta antes de mergear (como DOM-11 en la semana 7) |
| Q-3 | Deriva del contrato (operaciones, permisos y códigos sin documentar) | Revisor técnico | 0 | API-01…04 | 0 | Documentar o quitar la operación; sube la versión del contrato |
| Q-4 | Reglas de negocio fuera del dominio (Dr en Aplicación + Presentación) | Revisor técnico | ≤ 2 (solo transporte) | `php bin/acoplamiento.php` | 2 | Mover la regla al dominio con caracterización previa (semana 7) |
| Q-5 | Violaciones de accesibilidad en los recorridos | Responsable UX | 0 (axe-core, WCAG 2.2 AA) y 43/43 reglas de breakpoint | `recorrido.cjs`, `verificar-responsive.cjs` | 0 · 88/88 · 43/43 | Bloquea el PR de interfaz |
| Q-6 | Tiempo de evaluación del gate (RNF-08) | Responsable de release | p95 < 1 000 ms con 300 escenarios | `php bin/medir-gate.php` | 0,41 ms ([evidencia](evidencia/rnf08-gate-300.txt)) | Perfilar `QualityGate::evaluar` antes de agregar reglas |
| Q-7 | Hallazgos mayores abiertos de la revisión técnica | Moderador | 0 al cierre de cada iteración | Tabla de hallazgos (semana 13) | 1 (RT-01) | No se libera la iteración; se corrige con su prueba (semana 15) |

## 2. Matriz de riesgos

**Escalas:** probabilidad (P) e impacto (I) van de 1 (bajo) a 3 (alto). **Nivel = P × I:**
- 1–2 bajo;
- 3–4 medio;
- 6–9 alto.

| # | Riesgo | P | I | Nivel | Control principal |
|---|---|---|---|---|---|
| RK-1 | Se despliega un artefacto distinto del evaluado | 1 | 3 | 3 · medio | PV-2, DT-2 |
| RK-2 | Un reintento duplica un registro (RT-01) | 3 | 2 | **6 · alto** | DT-3; corrección en la semana 15 |
| RK-3 | La vista previa del gate y el registro divergen (tiempo o política) | 2 | 2 | 4 · medio | PV-4 |
| RK-4 | Una regla se copia en el cliente y diverge (como las 5/6 reglas de la semana 11) | 2 | 2 | 4 · medio | PV-3, DT-4 |
| RK-5 | Una prueba en verde no prueba lo importante (pruebas vacías) | 2 | 3 | **6 · alto** | DT-1 |
| RK-6 | Datos reales de pacientes en escenarios o evidencias | 1 | 3 | 3 · medio | PV-1 |
| RK-7 | Fuga de tokens de demostración a producción (RT-04) | 1 | 3 | 3 · medio | PV-5 |

## 3. Controles

| Id | Tipo | Control | Cubre | Cuándo |
|---|---|---|---|---|
| PV-1 | Preventivo | El dominio rechaza datos que no sean `SINT-…` (R-01), y la base lo vuelve a exigir | RK-6 | Siempre |
| PV-2 | Preventivo | El despliegue exige el último gate APROBADO, vigente y el mismo digest; también por trigger | RK-1 | Siempre |
| PV-3 | Preventivo | La consola usa la API real; ninguna regla del gate vive en el cliente | RK-4 | Diseño |
| PV-4 | Preventivo | La vista previa y el registro comparten `calcular()`, y la consola muestra «La decisión cambió» | RK-3 | Siempre |
| PV-5 | Preventivo | Identidad por JWT del HIS fuera de demo; `tokens_demo` solo con `E2E_ENTORNO=demo` | RK-7 | Antes de producción |
| DT-1 | Detectivo | Pruebas de mutación (34) y contrapruebas de los instrumentos (semanas 10 y 11) | RK-5 | Cada PR |
| DT-2 | Detectivo | INT-10 y CAM-01…06 | RK-1 | Cada PR |
| DT-3 | Detectivo | Prueba de reintento de cada escritura que el cliente puede repetir (nueva en la semana 15) | RK-2 | Cada PR |
| DT-4 | Detectivo | API-01…04 y el recorrido sobre la API real | RK-4 | Cada PR |
| DT-5 | Detectivo | Revisión técnica formal con checklist (semana 13) | Todos | Fin de iteración |

## 4. Calendario

| Cuándo | Actividad | Métricas | Responsable |
|---|---|---|---|
| Cada cambio (antes del PR) | Suite contra PostgreSQL y acoplamiento | Q-1, Q-4 | Autor |
| Cada PR | Mutaciones, contrato y recorridos de interfaz | Q-2, Q-3, Q-5 | Autor y revisor técnico |
| Cada semana | Medir el gate y revisar la matriz de riesgos | Q-6 | Responsable de release |
| Fin de iteración (cada 2 semanas) | Revisión técnica formal | Q-7 | Moderador |
| Semana 15 | Corregir RT-01 con su prueba y la matriz RF/RNF → pruebas | Q-1, Q-2, Q-7 | Autor |
| Antes de producción | PV-5 (identidad real) y medición del gate por HTTP con la base | Q-6 | Responsable de release y Ops |

## 5. Limitaciones

- **Q-6 mide la función del dominio**, no la petición HTTP con PostgreSQL. Es lo que promete el RNF-08, pero no es el tiempo que ve el usuario.
- **Las métricas se calculan con comandos locales.** El repositorio no tiene CI que las corra en cada PR. El plan nombra el comando de cada una para que el pipeline las adopte sin cambios.
