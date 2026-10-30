# Declaración de uso de inteligencia artificial — Semana 13

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 13 — Calidad del software y revisiones técnicas formales |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Revisar el código del Micro-HIS (configuración, arranque, reglas, esquema SQL, dependencias) para escribir un checklist verificable.
- Redactar:
  - el plan de revisión;
  - el checklist de 15 ítems;
  - el formato de hallazgos;
  - las plantillas (CSV de hallazgos, CSV de preparación, acta).
- Escribir el verificador de criterios de entrada (`criterios-entrada.php`) y el script que reproduce hallazgos (`verificar-autorrevision.php`).
- Hacer la autorrevisión del autor con el checklist y registrar 7 hallazgos.
- Dibujar el diagrama del proceso (PlantUML).
- Redactar la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Prepare una revisión técnica formal de RBAC: roles, permisos y protección de rutas, enfocada en reglas de dominio, contrato API, permisos/tenant, integridad de roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables y dependencias estrictamente necesarias para «asignación de rol, autorización de una operación y denegación trazable». Entregable/Evidencia: Plan de revisión, checklist de 12–15 ítems, roles de autor/moderador/revisores, criterios de entrada/salida y formato de hallazgos.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `plan-revision-tecnica.md`, `checklist-revision.md`, `formato-hallazgos.md`, `autorrevision-autor.md`, `plantillas/*` | Borrador completo |
| `herramientas/*.php` | Borrador completo |
| `evidencia/*.txt` | Salidas reales de los scripts (PHP 8.4) |
| `diagramas/semana-13-proceso-revision.*` | PlantUML 1.2024.7 |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Notas de honestidad:**

- **La autorrevisión la redactó la IA aplicando el checklist al código.** Los 7 hallazgos se verificaron contra el código real (líneas citadas) y 3 se reproducen con un script. El estudiante debe confirmar que está de acuerdo con cada severidad antes de la reunión.
- **No se inventaron participantes.** Moderador, registrador y revisores quedan «por confirmar»: los acuerda el grupo.
- **No se ejecutó la reunión ni el retrabajo.** La tarea es preparar la revisión, y los hallazgos quedan abiertos a propósito.
- **Se corrigieron referencias erróneas al revisar el material:**
  - pruebas que no existían con ese nombre («OCP», «CA-12» y «ARQ-07» para la librería cliente);
  - números de línea;
  - un criterio de entrada (E-6) que contaba mal.
- **El Micro-HIS no se modificó esta semana.**

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Plan, sesiones y calendario | | |
| Checklist RT-01..RT-15 | | |
| Formato de hallazgos y severidades | | |
| Hallazgos de la autorrevisión AR-01..AR-07 | | |
| Criterios de entrada y salida | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `criterios-entrada.php` (6/6 PASA) y `verificar-autorrevision.php`.
- [ ] Revisé cada hallazgo AR en el código citado y estoy de acuerdo con su severidad (o la ajusté).
- [ ] Acordé con el grupo quién es moderador, registrador y revisores.
- [ ] Puedo explicar por qué no se corrigieron los hallazgos antes de la reunión.

**Fecha de validación:** ____________
