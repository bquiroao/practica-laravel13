# Guía de defensa oral — Semana 1 (UML del proceso)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Documento:** [semana-01-uml-proceso-e2e-ci.md](semana-01-uml-proceso-e2e-ci.md)

## 1. Resumen en 60 segundos

El HIS tiene un workflow de GitHub Actions que valida la descripción y el nombre de la rama de los PR, pero no corre pruebas. Y según la API de GitHub, **nunca se ejecutó: 0 corridas**. No hay pruebas E2E, ni quality gate, ni guía de despliegue.

Modelé el proceso que falta:
1. Se planifican los escenarios E2E de cada versión.
2. El pipeline los ejecuta sobre un artefacto identificado por su digest.
3. Cada resultado conserva su evidencia con hash.
4. Un quality gate de cinco reglas decide si la versión avanza.
5. Solo se despliega lo aprobado, comprobando que el digest es el mismo que se probó.

Son 12 casos de uso, un diagrama de actividad con 10 decisiones y 4 excepciones, y una secuencia con 26 mensajes, todos trazados a 14 reglas.

## 2. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el digest y no solo el número de versión? | Dos builds del mismo commit pueden diferir (dependencias, fecha). El digest identifica el artefacto exacto: se prueba y se despliega ese, y no otro (R-03, R-11). |
| ¿Qué es un escenario INESTABLE? | Uno que falló y pasó al reintentar. Contarlo como PASA esconde fallas intermitentes; por eso tiene su propio estado y su propio límite en el gate (R-06, R-09c). |
| ¿Por qué un crítico no puede entrar en cuarentena? | Porque sería la forma más fácil de saltarse el gate. La cuarentena es solo para no críticos, con dueño y vencimiento (R-07). |
| ¿Cómo sabe que el CI nunca corrió? | La API de Actions del repositorio devuelve `total_count: 0`. Y PR recientes como el #398 no reportan ningún check. |
| ¿Qué pasa con los datos de pacientes en los videos? | Los escenarios solo usan datos sintéticos (R-01). Por eso la evidencia puede mostrar la pantalla completa. |
| ¿En qué se diferencia del módulo de regresión manual? | Aquel deja fuera la ejecución automática; este es esa pieza. Sus resultados pueden servir de evidencia a una campaña manual. |
