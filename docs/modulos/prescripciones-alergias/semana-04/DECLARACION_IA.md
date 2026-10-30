# Declaración de uso de inteligencia artificial — Semana 4

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias |
| **Entrega** | Semana 4 — Capas, MVC, Repository con adaptadores PDO e InMemory y análisis del repositorio de datos compartido |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó las pruebas y el validador contra un PostgreSQL 18 local. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Refactorizar la entrada del Micro-HIS a MVC (formulario, controlador, vistas).
- Mover los adaptadores en memoria a producción, crear la fábrica de adaptadores y convertir los dobles en decoradores.
- Escribir la suite de contrato, las pruebas MVC y de arquitectura, y 5 mutaciones nuevas.
- Leer las migraciones reales de la base de hospital, proponer `integracion-hospital.sql` y escribir el validador que lo aplica sobre ellas.
- Dibujar los diagramas y redactar el documento y la guía.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Continúe el MISMO «Micro-HIS Prescripciones electrónicas con validación de alergias» y el flujo «creación de prescripción con verificación previa de alergias». Organice la entrada con MVC; defina una interfaz Repository en el límite apropiado y adaptadores PDO e InMemory. El controlador no tendrá SQL ni reglas de negocio. Además, analice y diagrame cómo un repositorio de datos compartido integraría este módulo. Entregable/Evidencia: Capas, responsabilidades y objetos reutilizables.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `micro-his-prescripciones/` (MVC, `EnMemoria`, `FabricaAdaptadores`, pruebas, mutaciones, scripts, `integracion-hospital.sql`) | Código completo |
| `semana-04-capas-mvc-repositorio.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo; imágenes generadas con PlantUML |
| `evidencia/*.txt` | Salidas reales de la ejecución |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- H-4 lo encontró la suite de contrato en su primera ejecución; se guardó la salida en rojo antes de corregir.
- H-5 (la mutación M8 que sobrevivió) lo encontraron las mutaciones después del cambio. La IA analizó que no era una mutación equivalente y agregó la prueba que faltaba.
- `integracion-hospital.sql` es una **propuesta** para el slice H5: hay que acordarla con el equipo del esquema y con el del Expediente (la vista).

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| MVC y reglas ARQ-07/ARQ-08 | | |
| Adaptadores InMemory de producción y suite de contrato | | |
| Opción B del repositorio compartido (vista + FK + trigger de asignación) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la suite, las mutaciones y el validador de integración y obtuve los mismos resultados.
- [ ] Revisé el análisis del repositorio compartido contra las migraciones del hospital.

**Fecha de validación:** ____________
