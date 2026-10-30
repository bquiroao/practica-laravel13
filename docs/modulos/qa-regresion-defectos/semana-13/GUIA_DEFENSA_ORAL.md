# Guía de defensa oral — Semana 13 (revisión técnica formal)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-13-revision-tecnica-formal.md](semana-13-revision-tecnica-formal.md)

## 1. Resumen en 60 segundos

Preparé la revisión técnica formal del Micro-HIS QA:
- **Plan:** 3 319 líneas en alcance, cinco roles con focos que no se pisan, seis criterios de entrada, cinco de salida y un calendario de siete días.
- **Checklist:** 14 preguntas de sí o no, cada una con una forma de verificarla.
- **Formato de hallazgos:** ubicación, tipo, severidad, regla y un comando que lo reproduce.

Para probar el plan lo ejecuté una vez y encontré 7 hallazgos que ninguna de las 100 pruebas ni de las 35 mutaciones veía. El más grave: un defecto propuesto como crítico y todavía sin clasificar no bloquea el GO. La revisión piloto da **no aceptado**, y las correcciones van a la semana 15 con la prueba primero.

## 2. Demostración sugerida (3 minutos)

1. `php tests/ejecutar.php` → 100/100: todo en verde.
2. `php herramientas/sonda-revision.php` → 6 escenarios que se reproducen, cada uno con lo esperado frente a lo observado.
3. Leer RT-01 en voz alta: «severidad CRITICA, clasificado false, bloquea_go false → cierre GO».
4. Abrir `Defecto.php:133`: `criticoAbierto()` solo mira la severidad clasificada.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| Si las pruebas están en verde, ¿para qué revisar? | Las pruebas verifican lo que alguien pensó probar. Los 7 hallazgos están en lo que nadie pensó: un dato sin clasificar, otra campaña, una versión posterior, cero casos aplicables. |
| ¿Por qué RT-01 es crítico y no mayor? | Por la definición del formato: puede liberar un build que la regla no libera. Con 40 casos y umbral 95 %, una sola falla así da GO. |
| ¿Por qué no corrigió los hallazgos esta semana? | En una revisión formal el autor no corrige durante la revisión; corrige en el retrabajo, con una prueba que falla primero. La semana 15 pide pruebas y defectos registrados: ahí entran. |
| ¿Una revisión hecha por el mismo autor vale? | Como prueba del plan, sí. Como revisión, no: rompe la independencia y lo digo en el documento. Los 7 hallazgos son un piso. |
| ¿Por qué el moderador no revisa código? | Para mantenerse neutral cuando hay que decidir si algo es un defecto o qué severidad tiene. |
| ¿Cómo se sabe si el checklist es bueno? | Por las métricas: si se descartan más del 30 % de los candidatos, el checklist es ambiguo. Si un ítem nunca encuentra nada en varias revisiones, se cambia. |
| ¿RT-02 no es igual a H-3 de la semana 11? | Es el mismo patrón en otro lugar. Por eso C-12 pregunta si la trazabilidad muestra lo mismo que usará el cierre. |
