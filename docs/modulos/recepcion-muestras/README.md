# Módulo: recepción de muestras y códigos identificadores

Modela la recepción de muestras de laboratorio, su vinculación con la orden y el paciente correctos, la asignación de un identificador único y el rechazo controlado cuando existe una no conformidad.

**Estudiante:** Mercedes Azucena Lopez Perez

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «recepción, identificación inequívoca y rechazo controlado de muestra»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-recepcion-muestras.md](semana-01/semana-01-uml-recepcion-muestras.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | Requerimientos RF/RNF, criterios de aceptación y aplicación de LSP al flujo, con diseño antes/después y ejemplo PHP ejecutable | [semana-02-lsp-recepcion-muestras.md](semana-02/semana-02-lsp-recepcion-muestras.md) · [ejemplo LSP](semana-02/ejemplo-lsp/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |

## Diagramas

Las fuentes PlantUML editables y sus imágenes se guardan en `semana-NN/diagramas/`. Para regenerar las imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/recepcion-muestras/semana-01/diagramas/*.puml
```
