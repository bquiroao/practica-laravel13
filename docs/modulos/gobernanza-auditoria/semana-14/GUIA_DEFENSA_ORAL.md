# Guía de defensa oral — Semana 14

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-14-sqa-auditoria.md](semana-14-sqa-auditoria.md)

## 1. Resumen en 60 segundos

El plan SQA tiene 7 métricas, cada una con fuente, umbral, responsable y acción, y **se miden con un script sobre el código real**. La línea base da 5 en verde y 2 en rojo; los dos rojos vienen de la revisión de la semana 13 y tienen fecha de cierre.

Además, demostré que las métricas detectan defectos: inyecté 4 y los 4 se detectaron. Uno solo lo vio la métrica de trazabilidad. Los controles detectivos para la base de producción están probados sobre el esquema real.

## 2. Orden sugerido de la exposición

1. Atributos que cubre el plan (§1 del plan) y responsables (§2).
2. `medir-sqa.php` en vivo: las 7 métricas y los dos rojos.
3. `probar-deteccion.php`: las métricas no son decorativas.
4. Controles preventivos y detectivos; la prueba de los SQL (C-D3 alerta al deshabilitar el trigger).
5. Matriz de riesgos: los 3 altos y sus acciones.
6. Calendario: cadencias e hitos.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Medir con un script, no a mano | Una métrica que nadie puede reproducir no sirve para decidir |
| Probar que las métricas detectan defectos | Una métrica que nunca cambia de color no mide nada |
| M7 (capacidad) además de M3 (integridad) | La cadena puede ser íntegra hoy e inverificable en un año: la confiabilidad tiene fecha |
| QA no es el desarrollador | Independencia: quien mide no corrige |
| ALTERADA = incidente que no se toca | La base manipulada es evidencia; «arreglarla» la destruye |

## 4. Preguntas probables y respuestas

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué presenta un plan con métricas en rojo? | Porque son reales: vienen de la revisión. Ocultarlas sería peor; cada una tiene acción y fecha. |
| ¿De dónde sale el umbral de M7? | Del 50 % del `memory_limit` por defecto, para dejar margen. El volumen de 2 000 por día es un supuesto que C-D5 calibra. |
| ¿Cómo sabe que los SQL funcionan? | Los ejecuté sobre el esquema real: vacío da 0 filas; con datos de prueba, cada control da su caso; al deshabilitar el trigger, C-D3 alerta. |
| ¿Qué pasa si M4 baja de 100 %? | Es un incidente: se bloquea el release y se revisan RT-07..09. |
| ¿Por qué M5 detectó algo que las pruebas de contrato no? | Las guardas verifican que lo que devuelve el servidor esté documentado; M5 verifica lo inverso: que lo documentado tenga su prueba. |

## 5. Recorrido de demostración

```bash
cd docs/modulos/gobernanza-auditoria/semana-14
php herramientas/medir-sqa.php --sin-mutaciones     # rápido (≈ 5 s); M2 queda en ÁMBAR por no medirse
php herramientas/probar-deteccion.php               # 4 de 4 detectados
```
