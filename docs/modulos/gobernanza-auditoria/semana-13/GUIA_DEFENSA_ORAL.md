# Guía de defensa oral — Semana 13

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-13-revision-tecnica-auditoria.md](semana-13-revision-tecnica-auditoria.md)

## 1. Resumen en 60 segundos

Preparé una **inspección formal** del Micro-HIS Auditoría con:
- un checklist de 15 afirmaciones para refutar, en cinco perspectivas;
- roles con independencia (el autor no conduce);
- criterios de entrada que **un script verifica**: sin checklist ni autorrevisión, no hay reunión;
- una regla de decisión explícita.

Antes de convocar hice mi autorrevisión y encontré 6 problemas reales, reproducidos contra el kernel. El más grave es crítico: el servicio acepta siempre los tokens de demostración.

## 2. Orden sugerido de la exposición

1. Por qué una inspección y no un walkthrough (plan §1).
2. Checklist: cómo cubre las cinco áreas de la tarea (§1 del documento).
3. Roles y perspectivas (plan §3).
4. `php herramientas/criterios-entrada.php`, en vivo: antes y después.
5. Autorrevisión: AR-01, AR-02 y AR-04 con `verificar-autorrevision.php`.
6. Criterios de salida y por qué hoy el resultado sería «Re-revisión».

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Inspección formal | La bitácora es evidencia: sus defectos no se ven usándola, sino cuando ya hace falta |
| Lectura por perspectivas | Evita que todos miren el tenant y nadie las dependencias |
| Criterios de entrada automatizados | Un criterio que nadie verifica no es un criterio; el «antes» falla, y eso lo prueba |
| Un Crítico impide aceptar | Afecta la inmutabilidad, el tenant o la evidencia: no se acepta con condiciones |
| Sesiones ≤ 1600 líneas y ≤ 90 min | La efectividad de las inspecciones cae con reuniones largas y preparación rápida |

## 4. Preguntas probables y respuestas

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué AR-01 es crítico si es «solo para la demo»? | Porque nada impide desplegarlo así, y el token de red está en el repositorio: lectura de toda la red con un texto público. |
| ¿Cómo midió AR-02? | Hidraté 20 000 movimientos y medí la memoria: 866 B cada uno. El resto es aritmética contra el `memory_limit` por defecto. |
| ¿Por qué no corrigió los hallazgos? | La tarea es preparar la revisión. En una inspección, el autor no corrige antes de la reunión lo que los revisores deben ver; se corrige en el retrabajo, con su prueba. |
| ¿Quién modera si el equipo es pequeño? | Un integrante de otro módulo; el docente como R4. La regla: el autor nunca modera ni registra. |
| ¿Qué pasa si E-5 o E-6 fallan? | No hay reunión: el script sale con código 1, y lo muestra la evidencia del «antes». |

## 5. Recorrido de demostración

```bash
cd docs/modulos/gobernanza-auditoria/semana-13
php herramientas/criterios-entrada.php                 # E-1..E-6 PASA y el paquete por sesión
php herramientas/verificar-autorrevision.php 2>/dev/null   # AR-01..AR-06 reproducidos
```
