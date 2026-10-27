# Módulo: ingreso de resultados de laboratorio

Modela la captura de resultados asociados con una orden de laboratorio y la corrección controlada de resultados que todavía están pendientes de validación.

**Estudiante:** Mercedes Azucena Lopez Perez

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «captura y corrección controlada de resultados pendientes»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-ingreso-resultados.md](semana-01/semana-01-uml-ingreso-resultados.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | RF/RNF, criterios de aceptación y aplicación de LSP al diseño del flujo, con ejemplo PHP verificable y pruebas de contrato | [semana-02-lsp-ingreso-resultados.md](semana-02/semana-02-lsp-ingreso-resultados.md) · [ejemplo LSP](semana-02/ejemplo-lsp/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 3 | Micro-HIS PHP CLI por capas para captura y corrección versionada de resultados pendientes, PDO preparado y vistas C4/UML | [arquitectura y ejemplo](semana-03/semana-03-arquitectura-ingreso-resultados.md) · [prototipo CLI](semana-03/ejemplo/README.md) | [DECLARACION_IA.md](semana-03/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-03/GUIA_DEFENSA_ORAL.md) |
| 4 | Arquitectura en capas con entrada MVC y patrón Repository (adaptadores PDO e InMemory), pruebas de contrato y análisis de repositorio compartido | [semana-04-capas-mvc-repositorio-ingreso-resultados.md](semana-04/semana-04-capas-mvc-repositorio-ingreso-resultados.md) · [ejemplo](semana-04/ejemplo/README.md) | [DECLARACION_IA.md](semana-04/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-04/GUIA_DEFENSA_ORAL.md) |
| 6 | Primera evaluación parcial: presentación (8 diapositivas), diagrama trazable, matriz decisión→evidencia y cambio práctico defendido sobre valor/unidad/rango | [semana-06-evaluacion-parcial-arquitectura-ingreso.md](semana-06/semana-06-evaluacion-parcial-arquitectura-ingreso.md) · [registro del cambio](semana-06/registro-cambio-practico.md) | [DECLARACION_IA.md](semana-06/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-06/GUIA_DEFENSA_ORAL.md) |
| 11 | Prototipo navegable desktop y móvil para ingreso, corrección y validación de resultados con enfoque en prevención de errores y accesibilidad | [semana-11-prototipo-navegable-ingreso-resultados.md](semana-11/semana-11-prototipo-navegable-ingreso-resultados.md) | [DECLARACION_IA.md](semana-11/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-11/GUIA_DEFENSA_ORAL.md) |

## Diagramas

Las fuentes PlantUML editables y sus imágenes se guardan en `semana-NN/diagramas/`. Para regenerar las imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/ingreso-resultados-laboratorio/semana-01/diagramas/*.puml
```
