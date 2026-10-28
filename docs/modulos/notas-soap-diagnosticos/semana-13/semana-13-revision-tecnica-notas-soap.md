# Semana 13 — Revisión técnica formal de Notas SOAP y diagnósticos

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73-svg`) · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Proceso:** «registro de nota SOAP y asociación de diagnóstico clínico»

| Pedido | Dónde |
|---|---|
| Plan de revisión | [plan-revision-tecnica.md](plan-revision-tecnica.md): tipo, preguntas P1–P5, paquete en 2 sesiones (2883 líneas), calendario con diagrama del proceso, reglas, métricas y riesgos |
| Checklist de 12–15 ítems | [checklist-revision.md](checklist-revision.md): **15 ítems** (RT-01..RT-15) en 3 perspectivas, cada uno con dónde mirar, cómo verificar, regla y severidad |
| Roles | [Plan §3](plan-revision-tecnica.md#3-roles): autor, moderador, registrador y revisores R1–R3, con lo que hace y lo que **no** hace cada uno |
| Criterios de entrada y salida | [Plan §6–7](plan-revision-tecnica.md#6-criterios-de-entrada): E-1..E-8 (6 automatizados en [criterios-entrada.php](herramientas/criterios-entrada.php)) y S-1..S-5 con regla de decisión |
| Formato de hallazgos | [formato-hallazgos.md](formato-hallazgos.md): 13 campos, severidad por impacto clínico, métricas y acta; plantilla [registro-hallazgos.csv](plantillas/registro-hallazgos.csv) |

## 1. Enfoque

Los cinco temas que pide la tarea (reglas de dominio, contrato API, permisos y hospital, integridad, dependencias) son las preguntas P1–P5 del [plan](plan-revision-tecnica.md#1-tipo-y-objetivo), cada una con sus ítems del checklist. Tres decisiones: **inspección** y no *walkthrough*, porque se revisa el acceso a notas clínicas; **dos sesiones** de menos de 1600 líneas, por el límite de preparación (150–200 LOC/h) y de reunión (90 min); y **verificación objetiva**: cada ítem trae una prueba, un comando o una búsqueda.

## 2. La preparación ya se ejecutó

**Puerta de entrada.** Se corrió dos veces:

```text
Antes de escribir el checklist y la autorrevisión:
FALLA  E-5  checklist con 0 ítems; plantilla de hallazgos no existe
FALLA  E-6  0 hallazgos de la autorrevisión del autor
Ahora:
PASA   E-2  Total: 124  Pasan: 123  Fallan: 0  Omitidas: 1
PASA   E-3  20 guardas de contrato (API) y de capas (ARQ), 0 sin pasar
PASA   E-4  S1 = 1401 y S2 = 1482 líneas efectivas (máximo 1600 por sesión)
Criterios automáticos: 6  Fallan: 0  (E-7 y E-8: manuales)
```

**Autorrevisión** ([autorrevision-autor.md](autorrevision-autor.md)). Apliqué el checklist a mi propio código y encontré **6 hallazgos reales**: 1 crítico (un médico de otro hospital puede leer las notas), 1 mayor (autenticación de demostración en el punto de entrada real), 2 menores y 2 observaciones. Los tres más graves se reproducen con [verificar-autorrevision.php](herramientas/verificar-autorrevision.php). No se corrigieron: llegan abiertos a la reunión, porque la tarea es preparar la revisión y corregir antes rompería E-1.

## 3. Qué falta para ejecutar la revisión

| Pendiente | Quién |
|---|---|
| Confirmar moderador, registrador y R1–R3 (E-8) | El grupo |
| Fijar el día D y enviar el material ≥ 48 h antes (E-7) | Moderador |
| Preparación individual y reuniones S1 y S2 | Revisores |
| Retrabajo de los hallazgos aceptados, cada uno con una prueba | Autor |
| Seguimiento, criterios de salida y acta | Moderador |

## 4. Verificación y defensa oral

```bash
cd docs/modulos/notas-soap-diagnosticos/semana-13
php herramientas/criterios-entrada.php        # 6 criterios automáticos, 0 fallan
php herramientas/verificar-autorrevision.php  # reproduce AR-01, AR-02 y AR-04
```

Esta semana no se modificó el Micro-HIS (124 pruebas, igual que en la S12).

**En 2 minutos:** «Preparé una inspección formal de mi módulo: 2883 líneas en dos sesiones, un checklist de 15 ítems que dice dónde mirar y cómo comprobar cada cosa, y roles separados. La puerta de entrada es un script: falló mientras faltaba el checklist y ahora pasa. Probé el checklist conmigo mismo y encontró un defecto crítico que ninguna prueba veía: un médico de otro hospital puede leer las notas. Llega abierto a la reunión para que el equipo decida.»

**Pregunta probable:** *¿Por qué no corrigió AR-01 si ya lo sabía?* Porque la revisión debe ver el mismo paquete que se preparó (E-1). Corregirlo antes le quitaría al equipo la oportunidad de confirmar la severidad y de buscar otros casos del mismo defecto.
