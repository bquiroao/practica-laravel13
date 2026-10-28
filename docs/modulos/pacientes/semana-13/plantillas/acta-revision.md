# Acta de la revisión técnica formal — Pacientes (plantilla)

> La completa el **registrador** durante la reunión y la firma el **moderador** al cerrar. Una copia por sesión (S1, S2, S3).

## Datos

| Campo | Valor |
|---|---|
| Sesión | S1 · Reglas de dominio / S2 · Aplicación y persistencia / S3 · Contrato, frontera, permisos/tenant y dependencias |
| Fecha y hora | |
| Duración real (máx. 90 min) | |
| Commit revisado | (el de `evidencia/criterios-entrada-2-despues.txt`) |
| Material enviado el (≥ 48 h antes, E-7) | |

## Participantes

| Rol | Nombre | Preparación (min) | Asistió |
|---|---|---:|:-:|
| Autor | Boris Alexander Quiroa Orellana | | |
| Moderador | | | |
| Registrador | | | |
| Revisor R1 · Permisos, tenant y confidencialidad | | | |
| Revisor R2 · Contrato y frontera | | | |
| Revisor R3 · Dominio e integridad | | | |

## Criterios de entrada

| Criterio | Estado |
|---|---|
| E-1..E-6 (automáticos, `herramientas/criterios-entrada.php`) | PASA / FALLA |
| E-7 Material ≥ 48 h antes | |
| E-8 Roles confirmados | |

Si algún criterio falla, **la reunión no se hace**. Se reprograma y se anota el motivo.

## Hallazgos de la sesión

Los hallazgos se registran con el [formato](../formato-hallazgos.md) en [registro-hallazgos.csv](registro-hallazgos.csv). Aquí va solo el resumen:

| Severidad | De la preparación | Nuevos en la reunión | Total |
|---|---:|---:|---:|
| Crítico | | | |
| Mayor | | | |
| Menor | | | |
| Observación | | | |

**Preguntas respondidas por el autor** (y si se convirtieron en hallazgo):

| Pregunta | Respuesta | ¿Hallazgo? |
|---|---|---|
| | | |

## Decisión (la toma el moderador)

- [ ] **Aceptado**: sin críticos ni mayores abiertos.
- [ ] **Aceptado con condiciones**: mayores con plan y fecha; el moderador verifica el retrabajo sin otra reunión.
- [ ] **Re-inspección**: al menos un crítico, o más de 3 mayores, o densidad Crítico + Mayor > 2 por KLOC. Nueva reunión sobre lo corregido.

## Métricas

| Métrica | Valor |
|---|---|
| Líneas efectivas revisadas | |
| Horas de preparación (suma) | |
| Tasa de preparación (LOC/h por revisor) | |
| Densidad (Crítico + Mayor + Menor) / KLOC | |
| Hallazgos nuevos en la reunión / total | |

## Cierre (después del retrabajo)

| Criterio de salida | Verificado por el moderador |
|---|---|
| S-1 Cero críticos abiertos | |
| S-2 Mayores corregidos, o con decisión aprobada + issue | |
| S-3 Suite en verde y lint válido tras el retrabajo | |
| S-4 Cada corrección tiene su verificación anotada | |
| S-5 Métricas y acta archivadas | |

Firma del moderador: ____________ · Fecha: ____________
