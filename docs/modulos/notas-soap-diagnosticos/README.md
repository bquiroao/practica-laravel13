# Módulo Notas SOAP y diagnósticos (ASII-11)

Registro de notas clínicas en formato SOAP (Subjetivo, Objetivo, Análisis y Plan), asociación de diagnósticos CIE-10, firma de la nota y consulta del historial dentro del expediente del paciente.

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73-svg`)

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «registro de nota SOAP y asociación de diagnóstico clínico»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-proceso-notas-soap.md](semana-01/semana-01-uml-proceso-notas-soap.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | Requerimientos funcionales y no funcionales, criterios de aceptación y aplicación de SRP con ejemplo ejecutable antes/después | [semana-02-srp-requerimientos-diseno.md](semana-02/semana-02-srp-requerimientos-diseno.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 3 | Micro-HIS en PHP 8.2 vanilla con capas Presentation, Application, Domain y Persistence, PDO con sentencias preparadas y pruebas; vista arquitectónica C4/UML | [semana-03-arquitectura-micro-his.md](semana-03/semana-03-arquitectura-micro-his.md) | [DECLARACION_IA.md](semana-03/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-03/GUIA_DEFENSA_ORAL.md) |

## Micro-HIS

El código ejecutable del módulo está en [`micro-his-notas-soap/`](micro-his-notas-soap/). Se inició en la semana 3 y se sigue usando en las semanas 4 y 5. Para correr sus pruebas:

```bash
cd docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap
php tests/ejecutar.php
```

## Diagramas

Cada semana guarda sus fuentes editables (`.puml`) y las imágenes generadas (`.png`) en `semana-NN/diagramas/`. Para regenerar las imágenes:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/notas-soap-diagnosticos/semana-NN/diagramas/*.puml
```
