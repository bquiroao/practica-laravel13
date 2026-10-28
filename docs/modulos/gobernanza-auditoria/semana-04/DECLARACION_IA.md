# Declaración de uso de inteligencia artificial — Semana 4

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 4 — Arquitectura en capas, MVC y patrón Repository |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Reorganizar la entrada en MVC y crear los adaptadores InMemory de producción y la suite de contrato.
- Analizar las migraciones centrales del proyecto (`central_inbox_messages`, `sync_nodes`, `hospitals`, `central_users`) y diseñar la integración con el repositorio compartido.
- Escribir y ejecutar el validador del SQL de integración sobre las migraciones reales.
- Hacer los diagramas y redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Continúe el MISMO «Micro-HIS Gobernanza y auditoría de movimientos del sistema» y el flujo «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas». Organice la entrada con MVC; defina una interfaz Repository en el límite apropiado y adaptadores PDO e InMemory. El controlador no tendrá SQL ni reglas de negocio. Además, analice y diagrame cómo un repositorio de datos compartido integraría este módulo. Entregable/Evidencia: Capas, responsabilidades y objetos reutilizables.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `micro-his-auditoria/` (MVC, EnMemoria, fábrica, contrato, demo, validador, SQL central) | Borrador completo |
| `semana-04/diagramas/*`, documento, guía y declaración | Borradores |
| `semana-04/evidencia/*.txt` | Salida real de los comandos |

**Hechos a tener en cuenta:**
- **El validador falló dos veces por suposiciones de la IA:**
  - el formato de cierre del heredoc de las migraciones (`SQL);`);
  - una columna `code` que `networks` no tiene.
  Se corrigió el validador; las migraciones no se tocaron.
- **El consumidor de la bandeja está diseñado, no implementado.** Su vista sí se ejecutó.
- **Los roles y `GRANT` están documentados, no creados.**

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| MVC y reglas del controlador | | |
| Integración por la bandeja (opción B) | | |
| `usuario_id` sin FK | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php bin/demo.php` y `php tests/ejecutar.php`.
- [ ] Revisé el análisis del repositorio compartido con el compañero del módulo de tenants y sincronización.

**Fecha de validación:** ____________
