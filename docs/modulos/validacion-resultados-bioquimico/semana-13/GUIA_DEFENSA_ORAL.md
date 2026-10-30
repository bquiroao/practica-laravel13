# Guía de defensa oral — Semana 13

## Resumen en 60 segundos

«Preparé una revisión técnica formal de la validación de resultados por bioquímico. El plan tiene cinco etapas, tres roles de revisión más autora, moderador y escriba, un checklist de 15 ítems y criterios de entrada y salida. Revisé el código de la Semana 2: 33 aserciones pasan y no hay reglas clínicas codificadas. Encontré 7 hallazgos y tres son bloqueadores: el hospital llega en el comando en vez de salir de la sesión, no hay separación entre quien captura y quien valida, y la decisión no queda firmada ni ligada al valor revisado. El dictamen es aprobado con condiciones como material académico y no aprobado para integración persistente.»

## Demostración

1. Mostrar el checklist y su estado (8 cumplen, 3 parciales, 4 no cumplen).
2. Abrir `ReviewWorkflow.php` y señalar `ReviewCommand::hospitalId` (REV13-01).
3. Mostrar `ReviewDecisionRecord`, que no guarda valor ni firma (REV13-03).
4. Ejecutar `php verificar.php` desde `semana-02/ejemplo-dip/`.

## Preguntas probables

**¿Por qué el hospital en el comando es un problema?** Si lo envía el cliente, la autorización por hospital se puede eludir; debe salir de la sesión autenticada.

**¿Qué falta para hablar de firma?** Guardar un resumen del valor y la unidad revisados y definir si es sello de sistema o firma electrónica, con un responsable normativo.

**¿Quién modera si el trabajo es individual?** En una revisión real el autor no modera; aquí los roles son provisionales y se declara.

**¿Se revisó la bioquímica?** Se confirmó que el código no codifica intervalos ni normalidad; esas reglas las define un responsable clínico.

**¿Por qué aprobado con condiciones?** Las reglas de aplicación están demostradas, pero faltan los tres bloqueadores antes de una integración real.

## Evidencia

- [Documento](semana-13-revision-tecnica-validacion-resultados.md)
- [Validación](evidencia/validacion-semana-13.txt)
