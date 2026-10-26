# Guía de defensa oral — Semana 14

## Presentación breve

«El Plan SQA protege la emisión completa de órdenes desde el expediente y la lectura de su seguimiento confirmado. Propone siete métricas para integridad, autorización, trazabilidad, consulta, cambios de estado, duplicidad y disponibilidad; cada una tiene fuente, responsable, umbral y acción. Las metas son iniciales y deben aprobarse con evidencia de una línea base: no son estadísticas medidas ni SLA vigentes. El plan distingue el prototipo probado de los controles productivos aún no acreditados y deja los estados y transiciones bajo el contrato del EMR y laboratorio.»

## Preguntas para explicar el plan

1. **¿Las metas ya se miden en producción?** No. Son objetivos SQA propuestos; las fuentes de producción y la telemetría no están acreditadas. Se debe registrar `N/D` y habilitar la fuente, no inventar un valor.
2. **¿Por qué emisión íntegra tiene objetivo 100 %?** La operación no debe presentarse confirmada si faltan partidas o falla la persistencia; cualquier parcial confirmado exige investigación.
3. **¿Qué pasa si el seguimiento no responde?** Se informa indisponibilidad, se conserva el último dato con su frescura explícita si la política lo permite o se omite como actual. Nunca se transforma el fallo en lista vacía o estado supuesto.
4. **¿Qué estados puede tener una orden?** El plan no fija la máquina de estados. Debe aprobarla el responsable del EMR/laboratorio, junto con orígenes y transiciones válidas.
5. **¿Cómo se evita duplicar una orden tras timeout?** Con una operación idempotente durable y una consulta del resultado por el mismo identificador antes de repetir; el mecanismo necesita implementación y pruebas.
6. **¿El prototipo demuestra integración EMR?** No. Sus tests usan dobles y datos sintéticos; no prueban producción ni un EMR real.
7. **¿Quién aprueba los riesgos?** Los dueños asignados, seguridad y jefatura según el impacto; los riesgos críticos no se aceptan solo por tener una métrica.

## Recorrido de evidencia

- [Plan SQA](semana-14-plan-sqa-ordenes-laboratorio-emr.md)
- Siete métricas y acciones: sección 3.
- Matriz de riesgos: sección 4.
- Controles y calendario: secciones 5 y 6.
- Trazabilidad con evidencia de semanas 1, 2 y 15: sección 7.
