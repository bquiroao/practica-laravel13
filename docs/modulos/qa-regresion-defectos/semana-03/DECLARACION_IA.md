# Declaración de uso de inteligencia artificial — Semana 3

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 3 — Micro-HIS por capas en PHP vanilla con PDO; vista C4 |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el código y las pruebas, y ejecutó las pruebas, las mutaciones y el recorrido HTTP contra un PostgreSQL 18 local. Generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Programar el Micro-HIS (cuatro capas, esquema y datos de demostración) a partir de las reglas de la semana 1 y del contrato de la semana 2.
- Escribir las pruebas (dobles, PDO, HTTP, arquitectura, integración) y las mutaciones.
- Producir la vista C4 y redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Inicie el «Micro-HIS QA funcional, pruebas manuales y matriz de regresión» en PHP 8.2+ vanilla. Separe Presentación, Aplicación, Dominio y Persistencia; use PDO con sentencias preparadas y sin framework. Pruebe al menos: camino feliz, una regla de dominio y un error de persistencia (dobles o BD de prueba). Entregable/Evidencia: Vista arquitectónica C4/UML del módulo y sus dependencias.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-qa/**` | Código completo: las cuatro capas, el esquema, los datos, los scripts y las pruebas |
| `semana-03/diagramas/*` | Fuentes PlantUML y sus PNG |
| `semana-03/evidencia/*` | Salidas reales de las pruebas, las mutaciones y el recorrido HTTP. Las capturas son esas salidas renderizadas como imagen |
| `semana-03-arquitectura-micro-his.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Las clases de evidencia** son las de la semana 2, con el namespace cambiado. `Kernel`, `Solicitud`, `Respuesta`, `Ruta`, la autenticación demo, `FabricaConexion`, `PdoTransaccion` y el arnés de pruebas se adaptaron del Micro-HIS Prescripciones del mismo estudiante.
- **Los casos de demostración** (TC-101..TC-111) imitan las suites C1–C4/H1–H4 de `apply-progress.md`. Sus pasos están resumidos y no son los casos que el equipo usa.
- **Los hallazgos H-1..H-4 son reales:** aparecieron durante esta sesión y sus salidas están en `5-hallazgos-en-rojo.txt`.
- **Identidad y permisos están simulados** (tokens demo, política estática).

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisiones D-1..D-9 | | |
| Esquema `qa` y sus triggers | | |
| Pruebas y mutaciones | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` con y sin `QA_DB_DSN`.
- [ ] Ejecuté `bin/demo-http.sh` y revisé las respuestas.
- [ ] Revisé que los diagramas C4 coincidan con el código.

**Fecha de validación:** ____________
