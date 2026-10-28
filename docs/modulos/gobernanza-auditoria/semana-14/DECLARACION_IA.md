# Declaración de uso de inteligencia artificial — Semana 14

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 14 — Plan SQA |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Definir las métricas y escribir el script que las mide sobre el Micro-HIS.
- Escribir la prueba de detección con defectos inyectados y los controles detectivos en SQL, con sus datos de prueba.
- Redactar el plan, la matriz de riesgos, el tablero, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defina el plan SQA de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema: calidad, confiabilidad, seguridad, trazabilidad y riesgos de audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas. Asigne responsable, umbral, fuente y acción para cada métrica y control. Entregable/Evidencia: Plan SQA con 5–7 métricas, matriz de riesgos, controles preventivos/detectivos, responsables y calendario de seguimiento.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-14/herramientas/*` | Completo |
| `semana-14/*.md`, `plantillas/*` | Borradores |
| `semana-14/evidencia/*` | Salida real de los scripts y de PostgreSQL 18 |

**Hechos a tener en cuenta:**
- **La primera versión de M5 tomó el enum equivocado** del contrato (los estados de la verificación, 3 códigos, en lugar de los 16 de error). Se corrigió antes de medir la línea base.
- **El volumen de M7 (2 000 movimientos por día) y los umbrales son supuestos** que el plan pide calibrar.
- **Los responsables distintos del estudiante están «por designar».**

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Métricas M1..M7 y umbrales | | |
| Matriz de riesgos | | |
| Calendario y responsables | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `medir-sqa.php` y `probar-deteccion.php`.
- [ ] Designé a los responsables de QA, DBA y seguridad.

**Fecha de validación:** ____________
