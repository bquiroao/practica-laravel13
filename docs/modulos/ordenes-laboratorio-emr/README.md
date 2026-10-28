# Módulo: órdenes de laboratorio desde EMR

Modela la emisión de una o varias pruebas de laboratorio desde el expediente médico electrónico y la consulta del estado de las órdenes asociadas al paciente y episodio de atención.

**Estudiante:** Mercedes Azucena Lopez Perez

**Email:** mlopez58@miumg.edu.gt

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «emisión de orden desde el expediente y seguimiento de su estado»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-ordenes-laboratorio-emr.md](semana-01/semana-01-uml-ordenes-laboratorio-emr.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF, criterios de aceptación y aplicación de SRP al flujo de emisión y seguimiento, con ejemplo PHP verificable | [semana-02-srp-ordenes-laboratorio-emr.md](semana-02/semana-02-srp-ordenes-laboratorio-emr.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 15 | Pruebas unitarias, de caja blanca y caja negra para reglas y seguimiento de órdenes, trazadas a RF/RNF y con evidencia de ejecución | [semana-15-pruebas-ordenes-emr.md](semana-15/semana-15-pruebas-ordenes-emr.md) · [suite](semana-15/pruebas/README.md) | [DECLARACION_IA.md](semana-15/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-15/GUIA_DEFENSA_ORAL.md) |

## Diagramas

Las fuentes PlantUML editables y sus imágenes se guardan en `semana-NN/diagramas/`. Para regenerar las imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/ordenes-laboratorio-emr/semana-01/diagramas/*.puml
```

## Semana 8

[UX: flujo por rol y seis wireframes anotados](semana-08/semana-08-ux-ordenes-emr.md).

## Semana 9

[Evaluación UX/accesibilidad: checklist, hallazgos y backlog](semana-09/README.md).
