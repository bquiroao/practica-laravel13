# Formato de hallazgos — revisión técnica formal de Prescripciones

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
| `ubicacion` | Sí | `archivo:líneas`, separados por `·` | `src/Aplicacion/CasosDeUso/EmitirPrescripcion.php:124-128` |
| `descripcion` | Sí | Qué pasa, en una o dos frases, **sin la solución** | Otro médico recibe «ya emitida» una prescripción ajena con la misma clave |
| `evidencia` | Sí | Cómo se reproduce: un comando, una prueba o una petición, y lo que se obtuvo | `verificar-autorrevision.php` → dr-ruiz con la clave de dr-lopez → 200 YA_EMITIDA #1 |
| `regla_afectada` | Sí | R-xx, RF/RNF o D-xx que incumple | `R-11, R-09` |
| `severidad` | Sí | Ver §2 | `Mayor` |
| `tipo` | Sí | Ver §3 | `Defecto` |
| `propuesta` | No | Una idea de solución; la decide el autor **después** de la reunión | Incluir el usuario en la huella |
| `responsable` | Sí | Quién lo cierra | `Autor` |
| `estado` | Sí | `Abierto` · `En corrección` · `Cerrado` · `Aceptado (riesgo)` · `Rechazado` | `Abierto` |
| `verificacion` | Al cerrar | La evidencia del cierre: prueba nueva, mutación o commit | `APP-18 + M36` |
| `encontrado_en` | Sí | `autorrevision` · `preparacion` · `reunion` | `autorrevision` |

## 2. Severidad

| Severidad | Criterio en este módulo | Efecto en la decisión (plan §7) |
|---|---|---|
| **Crítico** | Permite emitir un medicamento contra una alergia que debía bloquear, rompe el bloqueo seguro, la inmutabilidad o el tenant, o permite emitir, leer o anular sin permiso | Uno solo impide «Aceptado» |
| **Mayor** | Incumple un requisito o deja un riesgo real de disponibilidad o seguridad sin mitigación | «Aceptado con condiciones»: plazo y verificación |
| **Menor** | Defecto acotado, sin riesgo clínico ni pérdida de datos | Se registra y se planifica |
| **Observación** | Mejora o duda; no incumple nada | No afecta la decisión |

## 3. Tipo

| Tipo | Qué es |
|---|---|
| **Defecto** | El código no hace lo que dice un requisito o una regla |
| **Riesgo** | El código hace lo que dice, pero una condición posible (cómo se escribe una alergia, configuración, otro usuario) produce un daño |
| **Contrato** | El contrato y el código, o el contrato y lo que necesitan los clientes, no coinciden |
| **Mantenibilidad** | Dificulta cambiar el código sin romperlo |

## 4. Ejemplo de un hallazgo completo

```csv
AR-02,RT-14,src/Aplicacion/CasosDeUso/EmitirPrescripcion.php:60-63 · src/Aplicacion/CasosDeUso/EmitirPrescripcion.php:124-128 · database/schema.sql (UNIQUE hospital+clave),"El reintento se busca por (hospital, clave) y la huella no incluye al usuario: otro médico del mismo hospital que envía el mismo contenido con la misma clave recibe 200 YA_EMITIDA con la prescripción ajena, antes de evaluar su permiso override.","verificar-autorrevision.php → dr-lopez 201 #1; dr-ruiz (sin override) misma clave → 200 YA_EMITIDA #1; prescriptor dr-lopez; 0 trazas de dr-ruiz","R-11, R-09, RT-08, RT-10",Mayor,Defecto,"Incluir el usuario en la huella (otro usuario → 409 CLAVE_REUTILIZADA) o unicidad (hospital, prescriptor, clave).",Autor,Abierto,,autorrevision
```

## 5. Métricas que se calculan con el registro

| Métrica | Fórmula | Para qué |
|---|---|---|
| Densidad de hallazgos | hallazgos / KLOC revisadas, por sesión | Comparar sesiones y decidir si hace falta re-revisar |
| Ritmo de preparación | líneas revisadas / hora, por revisor | Debe quedar entre 150 y 200 LOC/h; si es mayor, la preparación fue superficial |
| Efectividad de la autorrevisión | hallazgos AR / total | Si la reunión encuentra pocos distintos de los AR, la autorrevisión fue buena (o la reunión, débil) |
| Hallazgos por perspectiva | conteo por D/C/S/I/A | Detectar una perspectiva sin cobertura |
| Tiempo de cierre | días entre `Abierto` y `Cerrado`, por severidad | Alimenta el plan SQA (semana 14) |
