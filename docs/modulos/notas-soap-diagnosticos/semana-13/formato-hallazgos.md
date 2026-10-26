# Formato de hallazgos

Cada hallazgo es una fila de [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv). Ya contiene los 6 de la [autorrevisión](autorrevision-autor.md) como ejemplo real.

## 1. Campos

| Campo | Qué va | Ejemplo |
|---|---|---|
| `id` | `AR-nn` (autor) o `H-nn` (revisores), en orden de registro | H-03 |
| `item` | Ítem del checklist que lo encontró, o `—` si salió fuera del checklist | RT-10 |
| `ubicacion` | `archivo:línea`, relativo a `micro-his-notas-soap/` | `src/Aplicacion/CasosDeUso/ConsultarNota.php:25` |
| `descripcion` | Qué está mal, sobre el producto, nunca sobre la persona | «La lectura no compara el hospital del dato» |
| `evidencia` | Cómo se reproduce: prueba, comando o pasos | `verificar-autorrevision.php` AR-01 |
| `regla` | R-xx, RF/RNF, contrato o capa que se incumple | R-01 |
| `severidad` | §2 | Crítico |
| `tipo` | Defecto · Riesgo · Mejora · Pregunta | Defecto |
| `origen` | Autorrevisión · Preparación · Reunión | Preparación |
| `propuesta` | Una frase; no se discute en la reunión | «Guardar el hospital del expediente» |
| `responsable` | Quién hace el retrabajo (normalmente el autor) | Autor |
| `estado` | Abierto → Aceptado o Rechazado → Corregido → Verificado | Abierto |
| `verificacion` | Prueba nueva o comando que demuestra el arreglo; se llena en el retrabajo | Prueba nueva TEN-01 |

## 2. Severidad

Se decide por el **impacto en el paciente y en sus datos**, no por lo difícil del arreglo.

| Severidad | Criterio | Ejemplos en este módulo |
|---|---|---|
| **Crítico** | Expone o altera datos clínicos de otro hospital o paciente, o permite cambiar una nota firmada | Leer notas de otro hospital; editar una nota firmada; guardar una nota sin diagnóstico |
| **Mayor** | Rompe una regla del proceso o del contrato con una condición realista | Autenticación de demostración en producción; un código HTTP no documentado |
| **Menor** | Incumple una regla en un caso raro o deja un riesgo acotado | Clave de idempotencia opcional; extensión PHP no declarada |
| **Observación** | Mejora o pregunta que no incumple ninguna regla | Claves que no vencen; ¿enfermería ve borradores? |

## 3. Métricas que salen del registro

| Métrica | Fórmula |
|---|---|
| Densidad | (Críticos + Mayores + Menores) / KLOC revisadas |
| Tasa de preparación | Líneas de la sesión / horas de preparación de cada revisor (más de 300 LOC/h = lectura superficial) |
| Utilidad del checklist | Hallazgos por ítem; un ítem sin hallazgos en dos revisiones se revisa |
| Avance del retrabajo | Verificados / Aceptados |

## 4. Acta de cada reunión

El moderador la guarda como `semana-13/acta-S1.md` y `acta-S2.md` con: fecha y duración; asistentes y rol; commit revisado y resultado de `criterios-entrada.php`; minutos de preparación por revisor; hallazgos por severidad; ítems no revisados; decisión (Aceptado · Aceptado con condiciones · Re-inspección) y firma del moderador.
