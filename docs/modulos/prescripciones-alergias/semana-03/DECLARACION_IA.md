# Declaración de uso de inteligencia artificial — Semana 3

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias |
| **Entrega** | Semana 3 — Inicio del Micro-HIS en PHP vanilla por capas y vista arquitectónica C4/UML |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó las pruebas contra un PostgreSQL 18 local y el servidor embebido de PHP. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Portar el dominio y los puertos de la semana 2 al Micro-HIS y escribir las capas de Aplicación, Persistencia (PDO) y Presentación (HTTP).
- Diseñar el esquema PostgreSQL a partir de la migración real de `allergies` y proponer las tablas de prescripción de la tarea 3.5.
- Escribir el arnés, los dobles, las 55 pruebas, las 14 mutaciones y el recorrido HTTP; ejecutarlos y guardar la evidencia.
- Dibujar las vistas C4, de paquetes y dinámica, y redactar el documento y la guía.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Inicie «Micro-HIS Prescripciones electrónicas con validación de alergias» en PHP 8.2+ vanilla para ejecutar «creación de prescripción con verificación previa de alergias». Separe Presentation, Application, Domain y Persistence; use PDO con sentencias preparadas, sin framework, y pruebe al menos camino feliz, regla de dominio y error de persistencia mediante dobles o una base de prueba. Entregable/Evidencia: Vista arquitectónica C4/UML del módulo y dependencias.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-prescripciones/` (código, esquema, pruebas, scripts) | Código completo |
| `semana-03-arquitectura-micro-his.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo; imágenes generadas con PlantUML (biblioteca C4) |
| `evidencia/*.txt` | Salidas reales de la ejecución |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- Los hallazgos H-1 y H-2 aparecieron al ejecutar el recorrido HTTP real. Se corrigieron con su prueba, y se comprobó que la prueba falla sin la corrección.
- H-3 fue un error de la IA al escribir un doble de prueba, no del código; se detectó porque la prueba falló.
- Las tablas de prescripción son una **propuesta** para la tarea 3.5 del plan del esquema: hay que acordarlas con el equipo del esquema antes de convertirlas en migración.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisiones AD-01..AD-08 | | |
| Esquema propuesto para la tarea 3.5 | | |
| Bandeja local de trazas (outbox) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté la suite con y sin base y obtuve los mismos resultados.
- [ ] Ejecuté `bin/demo-http.sh` y revisé las respuestas.

**Fecha de validación:** ____________
