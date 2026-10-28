# Guía de defensa oral — Semana 14

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Plan:** [plan-sqa-pacientes.md](plan-sqa-pacientes.md) · **Riesgos:** [matriz-riesgos.md](matriz-riesgos.md)

## 1. Resumen en 60 segundos

> «Definí el plan SQA de Pacientes con siete métricas que cubren calidad, confiabilidad, seguridad, trazabilidad e integridad de la identidad. No son números a mano: un script las mide contra el kernel real. Ejecuta escenarios de duplicados, de confidencialidad entre roles y hospitales, y fallas inyectadas en la base y en la bitácora. La línea base da cinco en verde y dos en rojo, y los dos rojos son defectos reales que encontró la revisión de la semana 13: el 409 de duplicados revela pacientes de otro hospital. Para probar que las métricas sirven, inyecté tres defectos en copias aparte y cada uno lo detectaron dos métricas. Cada métrica y cada control tiene responsable, umbral, fuente y acción. La matriz tiene 12 riesgos, tres altos, todos con tratamiento en la semana 15, y el calendario dice qué se corre en cada PR, cada semana y antes de liberar.»

## 2. Orden sugerido de la exposición

1. **Atributos y métricas** (plan §1 y §3): una métrica por atributo.
2. **Ejecutar** `php herramientas/medir-sqa.php` y explicar los dos ROJOS.
3. **Detección de defectos** (§3.1): los tres mutantes.
4. **Matriz de riesgos**: los tres Altos y su tratamiento.
5. **Controles y responsables** (§4, §5, §2): la regla de independencia.
6. **Calendario** (§8).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Métricas medidas por un script contra el kernel real | Una métrica que se llena a mano mide la opinión del que la llena. El script repite exactamente lo mismo cada semana. |
| M5 sondea respuestas reales buscando datos prohibidos | La confidencialidad no se mide con «hay pruebas»; se mide mirando qué le llega al que no debe ver. |
| M7 lee el registro de hallazgos de la revisión | Conecta la revisión (S13) con el SQA (S14): un hallazgo abierto es un riesgo medible. |
| Mutantes para validar las métricas | Si una métrica no se pone en rojo con un defecto de su tema, no sirve como control. |
| RSQA independiente del autor | Quien corrige no puede dar por verificado su propio arreglo. |
| Sin métrica de rendimiento | El flujo no tiene un RNF de tiempo propio; medirlo sería ruido. Si el HIS fija un SLA, entra como M8. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre control de calidad y aseguramiento de la calidad?**
El control de calidad encuentra defectos en el producto (pruebas, revisiones). El aseguramiento define y vigila el **proceso** que hace que los defectos no lleguen: métricas, umbrales, responsables, controles y calendario.

**¿Qué es un control preventivo y uno detectivo?**
El preventivo evita que el defecto entre (pruebas antes del código, revisión formal, guardas de capas). El detectivo encuentra el que entró (la suite, la medición semanal, la revisión de la bitácora).

**¿Por qué la probabilidad de RS-01 es 5?**
Porque está demostrada: el script la reproduce cada vez. La matriz asigna P con evidencia: un hallazgo abierto y una métrica en rojo la suben.

**¿Cómo se cierra un riesgo Alto?**
No por decisión: cuando la métrica asociada está en VERDE y el hallazgo está Verificado por alguien que no es el autor.

**¿Qué pasa si una métrica queda roja dos semanas?**
Se escala al Integrador y se congela la liberación (plan §6).

## 5. Recorrido de demostración

Desde `docs/modulos/pacientes/semana-14/`:

1. `php herramientas/medir-sqa.php` → 5 VERDE · 2 ROJO.
2. Mostrar `evidencia/deteccion-mutantes.txt`.
3. Abrir `plantillas/tablero-seguimiento.csv`: la fila S14 y la acción de cada ROJO.
