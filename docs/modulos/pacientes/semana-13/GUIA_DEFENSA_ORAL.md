# Guía de defensa oral — Semana 13

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-13-revision-tecnica-pacientes.md](semana-13-revision-tecnica-pacientes.md) · **Plan:** [plan-revision-tecnica.md](plan-revision-tecnica.md)

## 1. Resumen en 60 segundos

> «Preparé una inspección formal del módulo Pacientes: roles separados, preparación individual con checklist, una reunión donde solo se registran hallazgos y criterios de entrada y salida explícitos. El paquete son 3106 líneas en tres sesiones: reglas de dominio, aplicación y persistencia, y contrato con permisos y tenant. Tres porque el primer reparto daba 1822 líneas en una sesión y el propio verificador de entrada lo rechazó. El checklist tiene 15 ítems, cada uno con dónde mirar, cómo verificar y una severidad por defecto. Antes de convocar, hice la autorrevisión con el mismo checklist y encontré 7 hallazgos reales, todos reproducidos contra el kernel. El más grave es crítico: al registrar en un hospital un documento que ya existe en otro, el 409 revela el código y el documento enmascarado de un paciente ajeno. Ninguna de las 87 pruebas lo detectaba, y eso muestra para qué sirve una revisión.»

## 2. Orden sugerido de la exposición

1. **Tipo de revisión** (plan §1): por qué inspección y no *walkthrough*.
2. **Paquete y sesiones** (plan §2): el límite de líneas y el rechazo del primer reparto.
3. **Checklist** (2 o 3 ítems de ejemplo): RT-07, RT-10, RT-12.
4. **Roles** (plan §3) y **reglas de la reunión** (§5).
5. **Criterios de entrada**: ejecutar `php herramientas/criterios-entrada.php`.
6. **Autorrevisión**: ejecutar `php herramientas/verificar-autorrevision.php` y mostrar AR-02.
7. **Criterios de salida y decisión** (§7).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Inspección | Datos de pacientes y personas protegidas: el costo de un defecto es alto y la revisión tiene que dejar evidencia. |
| Límite de 1600 líneas por sesión | A más de ~200 LOC/h de preparación, o más de 90 minutos de reunión, la detección cae: se hojea en lugar de leer. |
| Criterios de entrada automatizados | Nadie revisa código que no compila o que no pasa sus pruebas. El script rechaza la reunión sin discusión. |
| No se discuten soluciones en la reunión | La reunión encuentra; el retrabajo decide. Discutir soluciones consume el tiempo de encontrar. |
| Autorrevisión con el mismo formato | Prueba el checklist antes de usarlo con el equipo y fija una línea base (2.25 por KLOC). |
| Severidad por impacto, no por esfuerzo | Una línea mal puesta que revela a una paciente protegida es crítica aunque se corrija en un minuto. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre inspección, *walkthrough* y revisión informal?**
En la inspección hay roles, preparación con checklist, criterios de entrada y salida, y métricas. En el *walkthrough* el autor guía la lectura y los participantes comentan, con menos preparación. La revisión informal no deja registro.

**¿Por qué el autor no puede ser moderador?**
Porque el moderador decide severidades y la salida; el autor tiene interés en el resultado. El autor sí está en la reunión: responde preguntas, pero no defiende.

**¿Por qué AR-02 es crítico si «solo» revela un código?**
Revela que existe un paciente en **otro** hospital y los últimos dígitos de su documento, a alguien que no tiene acceso a ese hospital. Contradice D-20, el mismo 404 para «no existe» y «es de otro hospital». Por el criterio del formato, un salto de tenant es crítico.

**¿Qué pasa si la revisión encuentra un crítico?**
La decisión es **re-inspección**: el autor corrige con una prueba nueva y se hace una reunión solo sobre lo corregido. No se cierra hasta S-1: cero críticos abiertos.

**¿Cómo se mide si la revisión valió la pena?**
Con la densidad de hallazgos contra la línea base (2.25 por KLOC), la tasa de preparación (si supera 300 LOC/h, fue superficial) y, después, la eficacia: cuántos defectos del mismo tipo aparecieron después de la revisión.

## 5. Recorrido de demostración

Desde `docs/modulos/pacientes/semana-13/`:

1. `php herramientas/criterios-entrada.php` → E-1..E-6 PASA, y el paquete por sesión.
2. `php herramientas/verificar-autorrevision.php` → los 7 hallazgos con su evidencia.
3. Abrir `plantillas/registro-hallazgos.csv` en una hoja de cálculo.
