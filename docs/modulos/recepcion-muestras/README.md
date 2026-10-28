# Módulo: recepción de muestras y códigos identificadores

Modela la recepción de muestras de laboratorio, su vinculación con la orden y el paciente correctos, la asignación de un identificador único y el rechazo controlado cuando existe una no conformidad.

**Estudiante:** Mercedes Azucena Lopez Perez

## Entregas por semana

| Semana | Entrega | Documento | Declaración IA | Defensa oral |
|---:|---|---|---|---|
| 1 | UML del proceso «recepción, identificación inequívoca y rechazo controlado de muestra»: casos de uso, actividad, secuencia y narrativa de alcance | [semana-01-uml-recepcion-muestras.md](semana-01/semana-01-uml-recepcion-muestras.md) | [DECLARACION_IA.md](semana-01/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-01/GUIA_DEFENSA_ORAL.md) |
| 13 | Revisión técnica formal del módulo de recepción | [semana-13-revision-tecnica-formal.md](semana-13/semana-13-revision-tecnica-formal.md) | [DECLARACION_IA.md](semana-13/DECLARACION_IA.md) | No aplica |
| 2 | Requerimientos RF/RNF, criterios de aceptación y aplicación de LSP al flujo, con diseño antes/después y ejemplo PHP ejecutable | [semana-02-lsp-recepcion-muestras.md](semana-02/semana-02-lsp-recepcion-muestras.md) · [ejemplo LSP](semana-02/ejemplo-lsp/README.md) | [DECLARACION_IA.md](semana-02/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-02/GUIA_DEFENSA_ORAL.md) |
| 3 | Micro-HIS PHP CLI por capas, persistencia PDO preparada y vistas C4/UML del módulo y dependencias | [semana-03-arquitectura-recepcion-muestras.md](semana-03/semana-03-arquitectura-recepcion-muestras.md) · [ejemplo CLI](semana-03/ejemplo/README.md) | [DECLARACION_IA.md](semana-03/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-03/GUIA_DEFENSA_ORAL.md) |
| 6 | Defensa de arquitectura: actores, UML, RF/RNF, SOLID, capas, Repository y contrato API; cambio práctico, matriz decisión→evidencia y registro de defensa | [presentación (8 diapositivas)](semana-06/presentacion-semana-06.md) · [registro del cambio](semana-06/registro-cambio-practico.md) | [DECLARACION_IA.md](semana-06/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-06/GUIA_DEFENSA_ORAL.md) |
| 8 | Diseño UX de recepción, identificación inequívoca, aceptación/rechazo, cadena de custodia y errores con seis wireframes anotados | [semana-08-ux-recepcion-muestras.md](semana-08/semana-08-ux-recepcion-muestras.md) | [DECLARACION_IA.md](semana-08/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-08/GUIA_DEFENSA_ORAL.md) |
| 10 | Diseño para movilidad (320 a 430 px): 4 pantallas, reglas de breakpoint y dos escenarios móviles | [semana-10-movilidad-recepcion-muestras.md](semana-10/semana-10-movilidad-recepcion-muestras.md) | [DECLARACION_IA.md](semana-10/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-10/GUIA_DEFENSA_ORAL.md) |
| 11 | Prototipo navegable responsive: camino feliz, rechazo con motivo, bloqueo de código duplicado, mapa de navegación y capturas desktop/móvil | [semana-11-prototipo-navegable-recepcion-muestras.md](semana-11/semana-11-prototipo-navegable-recepcion-muestras.md) · [prototipo](../../public/prototipos/recepcion-semana-11/) | [DECLARACION_IA.md](semana-11/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-11/GUIA_DEFENSA_ORAL.md) |
| 12 | Segunda evaluación parcial: recorrido ejecutable de 10 pasos, matriz decisión→RF/RNF→evidencia y respuesta a un cambio (hipotético) | [semana-12-defensa-recepcion-muestras.md](semana-12/semana-12-defensa-recepcion-muestras.md) | [DECLARACION_IA.md](semana-12/DECLARACION_IA.md) | [GUIA_DEFENSA_ORAL.md](semana-12/GUIA_DEFENSA_ORAL.md) |

## Diagramas

Las fuentes PlantUML editables y sus imágenes se guardan en `semana-NN/diagramas/`. Para regenerar las imágenes desde la raíz del repositorio:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/recepcion-muestras/semana-01/diagramas/*.puml
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/recepcion-muestras/semana-03/diagramas/*.puml
```

## Semana 14

[Plan SQA: métricas, riesgos, controles y calendario](semana-14/semana-14-plan-sqa-recepcion-muestras.md).
## Semana 15

[Pruebas, matriz, capturas, defectos y cobertura pendiente](semana-15/README.md).
