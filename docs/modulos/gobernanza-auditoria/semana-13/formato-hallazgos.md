# Formato de hallazgos — revisión técnica formal de Auditoría

**Para qué existe el formato:**
- Un hallazgo se tiene que poder **reproducir** sin el revisor que lo encontró.
- Se tiene que poder **priorizar**.
- Se tiene que poder **cerrar** con evidencia.

**Dónde se registra:**
- En [`plantillas/registro-hallazgos.csv`](plantillas/registro-hallazgos.csv), una fila por hallazgo.
- Los de la autorrevisión, además, se explican en [autorrevision-autor.md](autorrevision-autor.md).

**En la reunión** solo se **registran y clasifican**. No se discute la solución: es una regla de la reunión.

## 1. Campos

| Campo | Obligatorio | Contenido | Ejemplo |
|---|:-:|---|---|
| `id` | Sí | `AR-nn` (autorrevisión), `Rn-nn` (revisor n, preparación) o `RT-nn` en la reunión | `AR-02` |
| `item` | Sí | Ítem del checklist que refuta | `RT-15` |
| `ubicacion` | Sí | `archivo:líneas`, separados por `·` | `src/Persistencia/Pdo/PdoBitacora.php:118-122` |
| `descripcion` | Sí | Qué pasa, en una o dos frases, **sin la solución** | La verificación carga la cadena completa en memoria |
| `evidencia` | Sí | Cómo se reproduce: un comando, una prueba o una petición, y lo que se obtuvo | `verificar-autorrevision.php` → 866 B por movimiento |
| `regla_afectada` | Sí | R-xx, RF/RNF o D-xx que incumple | `RNF-06, R-03` |
| `severidad` | Sí | Ver §2 | `Mayor` |
| `tipo` | Sí | Ver §3 | `Riesgo` |
| `propuesta` | No | Una idea de solución; la decide el autor **después** de la reunión | Verificar por lotes con un cursor por secuencia |
| `responsable` | Sí | Quién lo cierra | `Autor` |
| `estado` | Sí | `Abierto` · `En corrección` · `Cerrado` · `Aceptado (riesgo)` · `Rechazado` | `Abierto` |
| `verificacion` | Al cerrar | La evidencia del cierre: prueba nueva, mutación o commit | `INT-09 + M27` |
| `encontrado_en` | Sí | `autorrevision` · `preparacion` · `reunion` | `autorrevision` |

## 2. Severidad

| Severidad | Criterio en este módulo | Efecto en la decisión (plan §7) |
|---|---|---|
| **Crítico** | Rompe la inmutabilidad, el sello, el tenant o la meta-auditoría, o permite leer o registrar sin permiso | Uno solo impide «Aceptado» |
| **Mayor** | Incumple un requisito o deja un riesgo real de disponibilidad o seguridad sin mitigación | «Aceptado con condiciones»: plazo y verificación |
| **Menor** | Defecto acotado, sin pérdida de evidencia ni de datos | Se registra y se planifica |
| **Observación** | Mejora o duda; no incumple nada | No afecta la decisión |

## 3. Tipo

| Tipo | Qué es |
|---|---|
| **Defecto** | El código no hace lo que dice un requisito o una regla |
| **Riesgo** | El código hace lo que dice, pero una condición posible (volumen, configuración, otro emisor) produce un daño |
| **Contrato** | El contrato y el código, o el contrato y lo que necesitan los clientes, no coinciden |
| **Mantenibilidad** | Dificulta cambiar el código sin romperlo |

## 4. Ejemplo de un hallazgo completo

```csv
AR-02,RT-15,src/Persistencia/Pdo/PdoBitacora.php:118-122 · src/Aplicacion/CasosDeUso/VerificarIntegridad.php:54-57,La verificación carga la cadena completa de un hospital en memoria (fetchAll) y la recorre; el costo crece con cada movimiento y con cada lectura (R-08).,verificar-autorrevision.php → 866 B por movimiento hidratado: 1 M de movimientos ≈ 826 MB (memory_limit 128 MB),"RNF-06, R-03",Mayor,Riesgo,"Verificar por lotes (WHERE secuencia > :ultima LIMIT 5000) arrastrando el sello anterior; o desde el último ancla verificada.",Autor,Abierto,,autorrevision
```

## 5. Métricas que se calculan con el registro

| Métrica | Fórmula | Para qué |
|---|---|---|
| Densidad de hallazgos | hallazgos / KLOC revisadas, por sesión | Comparar sesiones y decidir si hace falta re-revisar |
| Ritmo de preparación | líneas revisadas / hora, por revisor | Debe quedar entre 150 y 200 LOC/h; si es mayor, la preparación fue superficial |
| Efectividad de la autorrevisión | hallazgos AR / total | Si la reunión encuentra pocos distintos de los AR, la autorrevisión fue buena (o la reunión, débil) |
| Hallazgos por perspectiva | conteo por D/C/S/I/A | Detectar una perspectiva sin cobertura |
| Tiempo de cierre | días entre `Abierto` y `Cerrado`, por severidad | Alimenta el plan SQA (semana 14) |
