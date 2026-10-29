# Módulo QA, pruebas E2E, CI y guía de despliegue final

**Adaptación para este ejercicio:** gestión de ejecuciones automatizadas y calidad de versiones del Sistema Hospitalario Integrado.

| Parte | Qué cubre |
|---|---|
| **Planificación** | Escenarios E2E con criticidad y datos sintéticos; plan por versión con todos los críticos, congelado al iniciar |
| **Ejecución E2E en CI** | Corridas del pipeline sobre un artefacto identificado por su digest; resultados que se agregan, con reintentos visibles como INESTABLE |
| **Evidencia** | Trazas, capturas, videos, logs y JUnit con SHA-256 y retención; sin datos de pacientes reales |
| **Quality gates y despliegue** | Decisión calculable con reglas explícitas y excepciones con dueño y caducidad; despliegue reproducible que verifica el digest y puede revertir |

**Estudiante:** Boris Alexander Quiroa Orellana

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «planificación de pruebas, ejecución E2E, conservación de evidencia y aplicación de quality gates». **Punto de partida verificado:** el workflow del HIS valida el PR pero no corre pruebas, y GitHub Actions informa **0 ejecuciones** en la historia del repositorio. No hay E2E, quality gate ni guía de despliegue. 8 actores (4 humanos, el pipeline y 3 sistemas), 11 objetos de dominio con sus invariantes y 14 reglas. 12 casos de uso (include: evidencia y gate; extend: excepción). Actividad en 5 carriles con 10 decisiones, 4 excepciones y 4 resultados. Secuencia de 26 mensajes (reportar con evidencia, evaluar el gate). Matriz CU ↔ actividad ↔ secuencia ↔ regla. | [semana-01-uml-proceso-e2e-ci.md](semana-01/semana-01-uml-proceso-e2e-ci.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
