# Módulo Notas SOAP y diagnósticos (ASII-11)

Registro de notas clínicas en formato SOAP (Subjetivo, Objetivo, Análisis y Plan), asociación de diagnósticos CIE-10, firma de la nota y consulta del historial dentro del expediente del paciente.

**Estudiante:** Joshua Eduardo García Reyes (`jgarciar73-svg`)

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «registro de nota SOAP y asociación de diagnóstico clínico»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-proceso-notas-soap.md](semana-01/semana-01-uml-proceso-notas-soap.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 2 | Requerimientos funcionales y no funcionales, criterios de aceptación y aplicación de SRP con ejemplo ejecutable antes/después | [semana-02-srp-requerimientos-diseno.md](semana-02/semana-02-srp-requerimientos-diseno.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 3 | Micro-HIS en PHP 8.2 vanilla con capas Presentation, Application, Domain y Persistence, PDO con sentencias preparadas y pruebas; vista arquitectónica C4/UML | [semana-03-arquitectura-micro-his.md](semana-03/semana-03-arquitectura-micro-his.md) | [DECLARACION_IA.md](semana-03/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-03/GUIA_DEFENSA_ORAL.md) |
| 4 | MVC en la entrada, interfaz Repository con adaptadores PDO y en memoria, análisis y diagramas del repositorio de datos compartido | [semana-04-mvc-repository.md](semana-04/semana-04-mvc-repository.md) | [DECLARACION_IA.md](semana-04/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-04/GUIA_DEFENSA_ORAL.md) |
| 5 | Diseño cliente-servidor y evaluación de una frontera de microservicio: contrato API OpenAPI, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad, consistencia, migración razonada y plan de issue, rama, worktree y PR | [semana-05-cliente-servidor-microservicios.md](semana-05/semana-05-cliente-servidor-microservicios.md) | [DECLARACION_IA.md](semana-05/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-05/GUIA_DEFENSA_ORAL.md) |
| 6 | Primera evaluación parcial: defensa de la arquitectura (actores, UML, RF/RNF, SRP, capas, Repository y contrato API) con presentación, diagrama trazable, matriz decisión → evidencia y cambio práctico «corregir una nota firmada» | [semana-06-defensa-arquitectura.md](semana-06/semana-06-defensa-arquitectura.md) | [DECLARACION_IA.md](semana-06/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-06/GUIA_DEFENSA_ORAL.md) |
| 7 | Diseño de componentes backend y frontend (Vue 3 + Pinia) con contratos de entrada/salida, y refactor medido del punto de mayor acoplamiento (los casos de uso que escriben notas) sin ampliar el módulo | [semana-07-componentes-refactor.md](semana-07/semana-07-componentes-refactor.md) | — | [GUIA_DEFENSA_ORAL.md](semana-07/GUIA_DEFENSA_ORAL.md) |
| 8 | Diseño de experiencia de usuario: user flow por rol (médico y enfermería) y 5 wireframes anotados con estados, mensajes, validaciones, reglas de interacción, ayuda y protección de datos | [semana-08-ux-notas-soap.md](semana-08/semana-08-ux-notas-soap.md) | — | [GUIA_DEFENSA_ORAL.md](semana-08/GUIA_DEFENSA_ORAL.md) |
| 9 | Evaluación de usabilidad y accesibilidad del diseño de la semana 8: checklist de 82 ítems (Nielsen, WCAG 2.2 AA y reglas del módulo), medición de contraste con script, 16 hallazgos con evidencia y backlog priorizado | [semana-09-evaluacion-ux-notas-soap.md](semana-09/semana-09-evaluacion-ux-notas-soap.md) | — | [GUIA_DEFENSA_ORAL.md](semana-09/GUIA_DEFENSA_ORAL.md) |
| 10 | Diseño para movilidad (320–430 px): prototipo responsive de 5 pantallas, jerarquía de contenido, reglas de breakpoint verificadas en 33 vistas, recuperación ante conexión limitada con reintento medido sobre el Micro-HIS y dos escenarios móviles | [semana-10-movil-notas-soap.md](semana-10/semana-10-movil-notas-soap.md) | — | [GUIA_DEFENSA_ORAL.md](semana-10/GUIA_DEFENSA_ORAL.md) |
| 11 | Prototipo navegable desktop y móvil (una sola app): camino feliz (registrar y firmar) y error crítico (respuesta perdida al guardar y al firmar), mapa de navegación y 28 capturas tomadas por un recorrido automático (28 comprobaciones, axe 0) | [semana-11-prototipo-notas-soap.md](semana-11/semana-11-prototipo-notas-soap.md) | — | [§7 Defensa oral](semana-11/semana-11-prototipo-notas-soap.md#7-defensa-oral) |

## Micro-HIS

El código ejecutable del módulo está en [`micro-his-notas-soap/`](micro-his-notas-soap/). Se inició en la semana 3 y se sigue usando en las semanas 4 a 7. Desde la semana 5 tiene un contrato OpenAPI en [`micro-his-notas-soap/api/openapi.yaml`](micro-his-notas-soap/api/openapi.yaml). Para correr sus pruebas o la demostración sin base de datos:

```bash
cd docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap
php tests/ejecutar.php
php bin/demo-en-memoria.php
```

## Diagramas

Cada semana guarda sus fuentes editables (`.puml`) y las imágenes generadas (`.png`) en `semana-NN/diagramas/`. Para regenerar las imágenes:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/notas-soap-diagnosticos/semana-NN/diagramas/*.puml
```
