# Declaración de uso de inteligencia artificial — Semana 3

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Entrega:** Semana 3 (tarea Odoo 2430) — Micro-HIS por capas y vista arquitectónica C4/UML de «navegación del dashboard con estados de carga, vacío, error y resumen inicial»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió el Micro-HIS, sus pruebas, el documento y los diagramas; ejecutó `php -l`, las pruebas y una prueba de mutación en una carpeta temporal; generó los PNG con PlantUML local; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Iniciar el Micro-HIS del módulo en PHP 8.2+ vanilla con las cuatro capas, PDO con sentencias preparadas y las tres pruebas mínimas, y documentar su vista C4/UML.

## 3. Prompts relevantes

El enunciado de la tarea 2430, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo la salida real de las pruebas: `micro-his-dashboard-base/` (`bootstrap.php`, `database/schema.sql`, `pruebas/pruebas.php` y un archivo por capa en `src/`), `semana-03/semana-03-arquitectura-micro-his.md`, `semana-03/diagramas/` (2 `.puml` y 2 `.png`), `semana-03/evidencia/salida-pruebas.txt`, `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`. Supuestos: SQLite como base de prueba con solo las columnas que se cuentan; tres tarjetas (pacientes activos, citas de hoy y camas disponibles); nombres de permisos `pacientes.ver`, `citas.ver` y `camas.ver` propuestos; los permisos y el hospital llegan en la petición; «cargando», el límite de 10 s y la limpieza de sesión quedan en el frontend.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Código, documento y diagramas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 26/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
