# Declaración de uso de inteligencia artificial — Semana 3

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 3 — Micro-HIS en PHP vanilla por capas y vista arquitectónica C4/UML |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Programar el Micro-HIS (cuatro capas, PDO, esquema SQL) a partir del diseño de la semana 2.
- Escribir las pruebas: con dobles, de arquitectura, de integración con PostgreSQL y de mutación.
- Levantar un **clúster PostgreSQL temporal** (puerto 55432, solo local, en una carpeta de trabajo) para la integración, sin tocar el servidor instalado en el equipo.
- Hacer los diagramas C4/UML y redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Inicie «Micro-HIS Gobernanza y auditoría de movimientos del sistema» en PHP 8.2+ vanilla para ejecutar «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas». Separe Presentation, Application, Domain y Persistence; use PDO con sentencias preparadas, sin framework, y pruebe al menos camino feliz, regla de dominio y error de persistencia mediante dobles o una base de prueba. Entregable/Evidencia: Vista arquitectónica C4/UML del módulo y dependencias.
2. «puedes continuar» (después de una pausa de la sesión).

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-auditoria/**` | Borrador completo; reglas y filtros portados de la semana 2 |
| `semana-03/diagramas/*` | Borrador completo; imágenes generadas con PlantUML |
| `semana-03/evidencia/*.txt` | Salida real de los comandos |
| Documento, guía y declaración | Borradores |

**Hechos a tener en cuenta:**
- **Fallaron pruebas por errores de la IA:**
  - APP-07 usaba el mismo `event_id` en dos hospitales;
  - INT-01 eligió claves que `jsonb` no reordenaba;
  - PER-02 buscaba una columna que también aparece en el SELECT.
  Se corrigieron las pruebas, no el código.
- **El defecto del 302 lo encontró el recorrido con el servidor real**, no las pruebas. Se corrigió el código y se agregó la comprobación a HTTP-01.
- **La canonización del detalle** se diseñó anticipando el comportamiento de `jsonb`. La mutación M1 demuestra que es necesaria.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisiones DA-01..DA-10 | | |
| Esquema `audit_logs` | | |
| Diagramas C4 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` (33 pasan, 6 omitidas).
- [ ] Ejecuté las pruebas con PostgreSQL (39/39) o revisé la evidencia.

**Fecha de validación:** ____________
