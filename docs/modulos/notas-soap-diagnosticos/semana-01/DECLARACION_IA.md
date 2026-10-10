# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Joshua Eduardo García Reyes (`jgarciar73-svg`) |
| **Módulo** | ASII-11 Notas SOAP y diagnósticos |
| **Entrega** | Semana 1 — Modelado UML del proceso «registro de nota SOAP y asociación de diagnóstico clínico» |

## 1. Herramienta utilizada

| Herramienta | Proveedor | Modo de uso |
|---|---|---|
| Claude (aplicación de escritorio, modo Cowork) | Anthropic | Asistente con acceso de lectura a mi repositorio personal y al repositorio del grupo. No hizo commits ni push: los hice yo. |

## 2. Propósito del uso

- Revisar mi entrega original de la semana 1 (repositorio personal) contra la consigna y señalar lo que faltaba, como el diagrama de casos de uso.
- Revisar el esquema de la base HOSPITAL del repositorio del grupo (`clinical_notes`, `diagnoses`, `care_assignments` y sus triggers) para que el modelo coincida con el sistema real.
- Adaptar la entrega a la estructura de carpetas que acordó el grupo (`docs/modulos/<modulo>/semana-NN/`).
- Escribir las fuentes PlantUML de los tres diagramas y generar sus imágenes.
- Redactar el documento principal, la matriz de trazabilidad y la guía de defensa oral.

## 3. Prompts relevantes

1. "Revisa mi repositorio de las semanas 1 a 11 y dime si cada semana está correcta según la consigna."
2. "Empecemos con la semana 1."
3. Ajustar la entrega a la estructura de `docs/` que subió un compañero a `develop`.
4. Texto de la tarea:
   > Modele el proceso «registro de nota SOAP y asociación de diagnóstico clínico». Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas. Entregable/Evidencia: UML de casos de uso y narrativa de alcance.

## 4. Contenido de origen y contenido generado

| Elemento | Origen |
|---|---|
| Actores, alcance y casos de uso UC-01 a UC-04, regla de que una nota firmada no se edita | Mío, de mi entrega original en el repositorio personal |
| `semana-01-uml-proceso-notas-soap.md` | Borrador generado con IA a partir de mi entrega original |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador generado con IA; imágenes generadas con PlantUML |
| `GUIA_DEFENSA_ORAL.md` | Borrador generado con IA |
| `DECLARACION_IA.md` | Borrador (este archivo) |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Actores y casos de uso CU-01 a CU-05 | | |
| Reglas R-01 a R-08 | | |
| Diagrama de actividad | | |
| Diagrama de secuencia | | |
| Matriz de trazabilidad | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Leí completo el documento y entiendo cada decisión del modelo.
- [ ] Verifiqué que las reglas coincidan con las tablas `clinical_notes`, `diagnoses` y `care_assignments` del repositorio.
- [ ] Comprobé que los identificadores CU, A, D, E, S y R sean coherentes entre los tres diagramas y la matriz.
- [ ] Comprobé que las referencias bibliográficas existan.
- [ ] Puedo explicar y modificar los diagramas sin ayuda (ver `GUIA_DEFENSA_ORAL.md`).

**Fecha de validación:** ____________
