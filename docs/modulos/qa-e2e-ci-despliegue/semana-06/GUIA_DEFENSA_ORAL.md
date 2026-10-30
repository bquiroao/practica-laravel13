# Guía de defensa oral — Semana 6 (primera evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Presentación:** [PDF](presentacion/semana-06-defensa-qa-e2e.pdf)

## 1. Resumen en 60 segundos

El módulo hace que cada versión del HIS se pruebe de punta a punta sobre un artefacto identificado por su digest, con evidencia conservada. Un quality gate de reglas explícitas decide, y solo se despliega lo aprobado.

La arquitectura:
- capas con el dominio en el centro;
- DIP en el gate;
- Repository con dos adaptadores y una suite de contrato;
- un contrato OpenAPI verificado contra el código;
- una frontera de microservicio evaluada midiendo y descartada.

El cambio práctico, despliegue reproducible, entró con la prueba primero: una entidad, un puerto, un caso de uso, una ruta y una tabla, sin tocar lo anterior. Resultado: 86 pruebas y 31 mutaciones detectadas.

## 2. Tiempos (12 minutos)

| Diapositiva | Minutos |
|---|---|
| 1 · Portada | 0:45 |
| 2 · Punto de partida y UML | 1:30 |
| 3 · Requisitos y DIP | 1:45 |
| 4 · Capas, MVC y Repository | 1:45 |
| 5 · Contrato y frontera | 1:45 |
| 6 · Diagrama trazable | 1:15 |
| 7 · Cambio práctico | 2:15 |
| 8 · Cierre | 1:00 |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué la ÚLTIMA evaluación y no cualquier APROBADO? | Porque una evaluación posterior puede rechazar la misma versión (por ejemplo, por antigüedad). La mutación M26 usa la primera y CAM-02 la detecta. |
| ¿Por qué 72 horas? | Es la política inicial, configurable (`vigencia_aprobacion_horas`). Lo importante es que vence y que el límite está probado (CAM-04). |
| ¿Qué pasa si el smoke falla? | El despliegue queda REVERTIDO y registra a qué versión volvió, la última DESPLEGADA en ese entorno. |
| ¿Por qué el pipeline no puede desplegar? | Mínimo privilegio: el que construye y prueba no es el que instala. Por HTTP le da 403 (CAM-06). |
| ¿Y si alguien inserta un despliegue directo en la base? | El trigger exige el digest de la versión y el último gate APROBADO (INT-10, M30). |
| ¿Qué fue lo del almacén? | Una prueba intermitente mostró que un artefacto corrupto no se podía volver a guardar nunca. Ahora se repara con los bytes correctos (PER-05). |
| ¿Por qué el contrato pasó a 1.1.0 y no a 2.0.0? | Es aditivo: una operación nueva y dos códigos. No cambia nada de lo que ya existía. |
