# Semana 14 — Confiabilidad y aseguramiento de calidad

**Tarea:** plan SQA del módulo: calidad del flujo, confiabilidad, seguridad, trazabilidad y riesgos, con responsable, umbral, fuente y acción para cada métrica y control.
**Entregable:** plan SQA con **6 métricas**, matriz de riesgos, controles preventivos y detectivos, responsables y calendario.

Responsables: **propuesta**, por confirmar con el grupo. Erick = autor del módulo; Boris = líder que revisa y une los PR.

## 1. Métricas

| # | Métrica | Umbral | Fuente | Responsable | Acción si no se cumple |
|---|---|---|---|---|---|
| M1 | Pruebas automáticas que pasan | 100 % en cada PR | `php pruebas/ejecutar.php` (`salida-pruebas.txt`) | Erick | El PR no se pide a revisión |
| M2 | Reglas RN-01 a RN-11 con al menos una prueba | 11/11 | Matriz RF/RNF → prueba (semana 15) | Erick | Agregar la prueba antes de cerrar la semana |
| M3 | Tamaño de PR | < 400 líneas (sin .png) | `git diff --stat origin/develop...HEAD` | Erick | Partir el PR en A, B, C… |
| M4 | Tiempo hasta el acuse (p90, por prioridad) | CRITICA ≤ 15 min; ALTA ≤ 30 min | `alerta_evento`: momento «acusada» − momento «enviada» | Erick (consulta); jefatura (uso) | Revisar los destinatarios del nivel 0 |
| M5 | Alertas con escalamiento agotado | 0 por semana | `alerta_critica.escalamiento_agotado = true` | Erick | Analizar la causa de cada caso con coordinación |
| M6 | Defectos abiertos de severidad mayor o crítica | 0 al cierre de la semana 15 | Registro de defectos (semana 15) y RTF (semana 13) | Boris (verifica) | Bloquear el merge del último PR |

Consulta para M4 (propuesta, sobre el esquema del Micro-HIS):
```sql
SELECT a.prioridad,
       percentile_cont(0.9) WITHIN GROUP (ORDER BY ac.momento - en.primero) AS p90_acuse
FROM alerta_critica a
JOIN (SELECT alerta_id, min(momento) AS primero FROM alerta_evento WHERE evento = 'enviada' GROUP BY alerta_id) en
     ON en.alerta_id = a.id
JOIN alerta_evento ac ON ac.alerta_id = a.id AND ac.evento = 'acusada'
GROUP BY a.prioridad;
```
*En el curso no hay volumen real; M4 y M5 se demuestran con datos ficticios.*

## 2. Matriz de riesgos

Probabilidad e impacto en escala de 1 a 3; exposición = P × I.

| ID | Riesgo | P | I | Exp. | Control preventivo | Control detectivo |
|---|---|---|---|---|---|---|
| R1 | Una alerta crítica no llega a nadie (directorio vacío o canal caído) | 2 | 3 | 6 | RN-03 sube de nivel; RN-08 reintenta 3 veces | M5 y el evento «escalamiento agotado» |
| R2 | Acuse duplicado o perdido por mala conexión | 2 | 2 | 4 | Acuse idempotente (RN-06); no marcar acusada sin respuesta | Prueba CA-08 y un solo evento «acusada» en la base |
| R3 | Acceso a alertas de otro hospital | 1 | 3 | 3 | Tenant del token; filtro en el repositorio | Pruebas RNF-01 (contrato, API, web) |
| R4 | Cierre por un rol no autorizado | 2 | 2 | 4 | `Permisos` (RF-08) | Ítem 9 de la RTF; prueba [DEFECTO D-03] |
| R5 | Historial alterado | 1 | 3 | 3 | *Trigger* que bloquea UPDATE y DELETE (RN-10) | Prueba de integración del *trigger* |
| R6 | Escalamiento que no corre (planificador detenido) | 2 | 3 | 6 | Plazos calculados desde `plazo_desde` (se recupera en la siguiente ejecución) | Alerta si no hay eventos «escalada» en 24 h con pendientes vencidas (propuesta) |
| R7 | Exposición de datos clínicos en avisos | 1 | 3 | 3 | Resumen con código de paciente (RNF-02) | Prueba RNF-02 del resumen |
| R8 | Contratos con otros módulos sin confirmar | 3 | 2 | 6 | Bandeja e idempotencia por `id_origen` | Revisión con los dueños en la semana 15 |

## 3. Controles

| Tipo | Control | Cuándo | Responsable |
|---|---|---|---|
| Preventivo | Pruebas antes de cada PR (M1) y PR menor de 400 líneas (M3) | Cada PR | Erick |
| Preventivo | Revisión técnica formal (semana 13) | Una vez, antes de la semana 15 | Boris (moderador) |
| Preventivo | Reglas en el dominio con reloj inyectable | Diseño | Erick |
| Detectivo | Prueba de contrato del repositorio (8 × 2) | Cada PR que toque persistencia | Erick |
| Detectivo | Revisión del `git diff --stat` y del autor del PR | Cada merge | Boris |
| Detectivo | Consultas M4 y M5 | Semanal (propuesta) | Erick |

## 4. Calendario de seguimiento

| Semana | Actividad SQA | Evidencia |
|---|---|---|
| 14 | Aprobación del plan; línea base de M1–M3 | Este documento |
| 15 (inicio) | RTF del Micro-HIS (semana 13) | Acta y hallazgos RTF |
| 15 | Pruebas formales, matriz RF/RNF → prueba (M2) y registro de defectos (M6) | `semana-15/` |
| 15 (cierre) | Verificación de M6 = 0 mayores abiertos, o justificación documentada | PR final |
| Después del curso | M4 y M5 semanales si el módulo pasa al HIS real | Consultas SQL |
