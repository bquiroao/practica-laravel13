# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias |
| **Entrega** | Semana 1 — Modelado UML del proceso «creación de prescripción con verificación previa de alergias» |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Revisar el esquema real del proyecto para basar el modelo en él:
  - la tabla `allergies` (alérgeno por código o nombre, tipo, severidad con `unknown`, estado);
  - la tarea 3.5 pendiente (tablas de medicamentos y prescripciones) y la regla de inmutabilidad de lo firmado.
- Redactar la narrativa de alcance, las reglas de negocio, la especificación de casos de uso y la matriz de trazabilidad.
- Escribir las fuentes PlantUML de los diagramas de casos de uso, actividad y secuencia, y generar sus imágenes.
- Preparar la guía de defensa oral.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Modele el proceso «creación de prescripción con verificación previa de alergias». Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas. Entregable/Evidencia: UML de casos de uso y narrativa de alcance.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-01-uml-proceso-prescripciones.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo; las imágenes se generaron con PlantUML |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- La numeración S-01..S-65 de la matriz **se calculó desde el archivo `.puml`** (cada flecha suma uno, `autonumber`), no a mano.
- Las reglas clínicas (bloqueo absoluto por coincidencia directa, severidad `unknown` como la peor, justificación de 20 caracteres, límite de 2 s, duración de 1 a 90 días) las propuso la IA. **Deben validarse con personal clínico** (médico y farmacéutico) antes de implementarse.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Actores y casos de uso | | |
| Reglas R-01..R-12 | | |
| Bloqueo seguro ante la fuente caída | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé que los tres diagramas compartan los identificadores de la matriz.
- [ ] Revisé las reglas contra el esquema real de `allergies`.

**Fecha de validación:** ____________
