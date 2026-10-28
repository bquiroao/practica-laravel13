# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Lopez Perez, Mercedes Azucena |
| **Módulo** | Órdenes de laboratorio desde EMR |
| **Entrega** | Semana 2 — Aplicación de SRP al flujo de emisión y seguimiento |

## Herramienta y propósito

Se utilizó un asistente de IA integrado en VS Code como apoyo para interpretar la captura de la asignación, mantener la trazabilidad con Semana 1, proponer una separación de responsabilidades, elaborar un ejemplo PHP y redactar documentación. El material requiere revisión y validación de la estudiante antes de presentarse.

## Artefactos elaborados con apoyo de IA

| Archivo | Aporte |
|---|---|
| `semana-02-srp-ordenes-laboratorio-emr.md` | Requerimientos funcionales/no funcionales, criterios de aceptación, análisis SRP y trazabilidad. |
| `diagramas/semana-02-clases-*.puml` | Representación editable de diseños antes y después de aplicar SRP. |
| `ejemplo-srp/src/*.php` | Casos de uso, contratos y fixture en memoria para el flujo de ejemplo. |
| `ejemplo-srp/verificar.php` | Aserciones ejecutables relacionadas con los criterios CA. |
| `ejemplo-srp/README.md`, `evidencia/salida-verificar.txt` | Instrucciones de ejecución y evidencia generada. |
| `GUIA_DEFENSA_ORAL.md` | Guía de exposición y preguntas posibles. |
| `DECLARACION_IA.md` | Borrador de esta declaración. |

## Límites y validación

El ejemplo no usa datos reales de pacientes ni se integra con Laravel, EMR, catálogo o base de datos. Los códigos `TEST_INITIAL`, `TEST_NEXT` y `TEST_FINAL` son sintéticos; no proponen estados clínicos ni sustituyen el acuerdo pendiente con los módulos de laboratorio. El adaptador implementa varias interfaces solo para servir de fixture en memoria.

La estudiante debe leer el informe y el código, ejecutar el comando documentado, confirmar las reglas y criterios respecto a la consigna, y adaptar cualquier contenido para que refleje su comprensión.

## Revisión humana

| Elemento | Decisión (aceptado / modificado / descartado) | Observaciones |
|---|---|---|
| Requerimientos y criterios | | |
| Separación de responsabilidades | | |
| Contratos del EMR, catálogo y persistencia | | |
| Alcance del ciclo de vida de estados | | |
| Ejemplo y resultados de prueba | | |

- [ ] Leí y comprendo el informe, los diagramas y el ejemplo.
- [ ] Ejecuté y revisé la suite de verificación.
- [ ] Confirmé que los estados sintéticos no se presentan como estados productivos.
- [ ] Revisé el material y lo adapté a mi propia comprensión.

**Fecha de revisión:** ____________________
