# Módulo QA funcional, pruebas manuales y matriz de regresión

**Adaptación para este ejercicio:** gestión de campañas de regresión y defectos del Sistema Hospitalario Integrado.

| Parte | Qué cubre |
|---|---|
| **Casos manuales trazados** | Casos con pasos y resultado esperado, versionados, aprobados por alguien distinto del autor y vinculados a los requisitos que verifican |
| **Campañas de regresión** | Alcance tomado de la matriz de regresión (los casos críticos de cada funcionalidad afectada por el build), congelado al iniciar |
| **Evidencia y defectos** | Sin evidencia confirmada no hay FALLA; cada FALLA tiene su defecto, con severidad y un ciclo de vida que cierra QA re-ejecutando |
| **Decisión y trazabilidad** | GO / NO-GO calculable; requisito ↔ caso ↔ intento ↔ evidencia ↔ defecto en los dos sentidos |

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «diseño de casos manuales, campaña de regresión, evidencia, defectos y trazabilidad». Punto de partida: el registro **manual** de regresiones del esquema del HIS (`apply-progress.md`), que no tiene requisitos, evidencia, defectos ni criterio de salida. Casos de uso (CU-01..CU-14, sin «editar resultado», «borrar evidencia» ni que el desarrollador cierre su defecto); actividad con 26 decisiones, 19 excepciones y 10 resultados (GO y NO-GO); secuencia con 76 mensajes en 6 bloques; narrativa de alcance, modelo de objetos, 14 reglas de negocio y matriz de trazabilidad con sus dos huecos declarados. | [semana-01-uml-proceso-qa-regresion.md](semana-01/semana-01-uml-proceso-qa-regresion.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
