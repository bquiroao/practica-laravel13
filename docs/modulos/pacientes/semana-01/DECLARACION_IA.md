# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 1 — Modelado UML del proceso «registro, búsqueda y actualización segura de un paciente» |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Revisar las migraciones existentes del proyecto (`local_patients` en la base del hospital; `mpi_patients`, `mpi_identifiers`, `patient_hospital_links` e `identity_match_candidates` en la base central) para que el modelo se basara en el esquema real: campos, estados, unicidad del identificador y cifrado.
- Redactar la narrativa de alcance, las reglas de negocio, la especificación de casos de uso y la matriz de trazabilidad.
- Escribir las fuentes PlantUML de los diagramas de casos de uso, actividad y secuencia, y generar sus imágenes.
- Preparar la guía de defensa oral.

## 3. Prompts relevantes

1. Iniciar el nuevo módulo «Pacientes: registro, edición, búsqueda y detalle».
2. Instrucción de la tarea, entregada textualmente:
   > Modele el proceso «registro, búsqueda y actualización segura de un paciente». Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas. Entregable/Evidencia: UML de casos de uso y narrativa de alcance.
3. Instrucciones permanentes: en cada tarea semanal agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL`, y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-01-uml-proceso-pacientes.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |
| `../README.md` (índice del módulo) | Borrador completo |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Narrativa de alcance (incluido / excluido / supuestos) | | |
| Actores y casos de uso CU-01 a CU-08 | | |
| Reglas de negocio R-01 a R-10 | | |
| Diagrama de actividad | | |
| Diagrama de secuencia | | |
| Matriz de trazabilidad | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Leí completo el documento y entiendo cada decisión del modelo.
- [ ] Verifiqué que los campos, estados y restricciones citados correspondan a las migraciones `local_patients` y `mpi_identifiers` del repositorio.
- [ ] Comprobé que los identificadores CU, A, D, E, F, R y S sean coherentes entre los tres diagramas y la matriz.
- [ ] Comprobé que las referencias bibliográficas existan.
- [ ] Puedo explicar y modificar los diagramas sin ayuda (ver `GUIA_DEFENSA_ORAL.md`).

**Fecha de validación:** ____________
