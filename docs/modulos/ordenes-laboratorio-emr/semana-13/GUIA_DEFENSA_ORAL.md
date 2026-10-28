# Guía de defensa oral — Semana 13

## Resumen de la entrega

La entrega formaliza una revisión técnica de la emisión y el seguimiento de órdenes de laboratorio desde el expediente. Contrasta los requisitos de las semanas 1 y 2 con el prototipo SRP, propone roles independientes y define checklist, entrada/salida y formato uniforme de hallazgos.

## Preguntas para la defensa

1. **¿Qué se revisó realmente?**
   El modelo, requerimientos, workflow, fixture y runner de Semana 2; no un EMR conectado ni un sistema productivo.
2. **¿Por qué el checklist marca límites de evidencia?**
   Una interfaz llamada `createAtomically` no prueba que un repositorio use transacciones; el prototipo usa memoria y así lo documenta.
3. **¿Qué significa el hallazgo de tenant?**
   La autorización recibe actor/paciente/episodio, pero no un ámbito hospitalario explícito. La integración debe resolver la fuente autorizada y denegar acceso cruzado.
4. **¿El prototipo tiene un API HTTP?**
   No. Se registra como contrato pendiente, no se inventan rutas ni respuestas del sistema.
5. **¿Por qué los estados `TEST_*` no son estados del hospital?**
   Son valores sintéticos de fixture; el ciclo de vida requiere acuerdo con los propietarios de órdenes y laboratorio.
6. **¿Cómo se distingue una lista vacía de un error de consulta?**
   Los resultados del caso de uso tienen estados diferentes para `EMPTY`, `FOUND`, `FORBIDDEN` y `UNAVAILABLE`.
7. **¿Qué roles aprueban el cierre?**
   Moderador, revisor de dominio, API/integración, seguridad/tenant y persistencia/SQA. Sus nombres reales aún deben asignarse.
8. **¿Qué hace que un hallazgo sea verificable?**
   ID, requisito, ubicación, condición, resultado observado/esperado, impacto, acción, responsable y evidencia de retest.

## Evidencia a mostrar

- Ejecutar desde la raíz: `php docs/modulos/ordenes-laboratorio-emr/semana-02/ejemplo-srp/verificar.php`.
- Comparar el resultado con las 34 aserciones registradas en `semana-02/evidencia/salida-verificar.txt`.
- Mostrar el [plan de revisión](semana-13-revision-tecnica-ordenes-emr.md), los 15 ítems, los cinco hallazgos y los criterios de salida.

## Límite que debe declararse

Las pruebas con dobles validan reglas de aplicación. No prueban autenticación multi-tenant, contrato API, transacción de base de datos, historial de auditoría ni interoperabilidad clínica. Los hallazgos son resultados de inspección de escritorio; la reunión formal y las aprobaciones quedan pendientes de asignación.
