# Guía de defensa oral — Semana 14 (plan SQA)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-14-plan-sqa.md](semana-14-plan-sqa.md)

## 1. Resumen en 60 segundos

El plan SQA tiene:
- **6 métricas**, cada una con su fuente, su umbral, un responsable y una acción. Un script las calcula todas y devuelve cuántas están fuera.
- **Una matriz de 11 riesgos**, cada uno con la evidencia que lo sostiene; tres son altos.
- **8 controles preventivos y 8 detectivos.** Cuatro de los detectivos son consultas SQL que probé sembrando un caso positivo dentro de una transacción.
- **Un calendario:** por PR, antes de cada cierre, semanal, mensual y antes de cada release.

La línea base da 4 de 6 en umbral:
- M-4 sigue con el crítico de la semana 13;
- M-3 encontró que tres reglas tienen pruebas, pero ninguna prueba dice qué regla verifica.

RNF-08, que estaba pendiente desde la semana 2, quedó medido: 140 ms con 500 casos en PostgreSQL, contra un límite de 1 s.

## 2. Demostración sugerida (3 minutos)

1. `php herramientas/medir-sqa.php`: el tablero con OK y FUERA, y su código de salida 2.
2. Abrir `controles-sqa.sql` en CD-01: «filas solo si hay un problema».
3. `psql -f herramientas/validar-controles.sql`: cada control encuentra su positivo y, después del ROLLBACK, 0 filas.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué 6 métricas y no más? | Cada una tiene una fuente automática y una acción. Agregar una sin acción solo hace ruido. |
| Si M-1 y M-2 están al 100 %, ¿por qué no se libera? | Porque M-4 tiene un crítico abierto. La semana 13 mostró 7 hallazgos con las pruebas al 100 %: por eso M-4 está en el plan. |
| ¿Qué es un control compensatorio? | Uno que detecta lo que el código todavía no impide. CD-01 encuentra los críticos sin clasificar mientras RT-01 no esté corregido. |
| ¿Cómo sabe que los controles SQL funcionan? | Sembré un positivo de cada uno en una transacción: cada control lo encontró, y después del ROLLBACK dieron 0 filas. |
| ¿M-3 no es solo una cuestión de nombres? | Las pruebas existen, pero sin el nombre de la regla ninguna herramienta puede rastrear regla → prueba. Una trazabilidad que solo una persona puede seguir no escala. |
| ¿Por qué M-2 y M-6 se leen de un archivo? | Son lentas (minutos). Pero si la corrida tiene más de 7 días, la métrica sale fuera: una métrica vieja no está en verde. |
| ¿Quién decide aceptar un riesgo? | El Responsable de producto, con una excepción firmada. El autor no puede aceptar sus propios riesgos. |
