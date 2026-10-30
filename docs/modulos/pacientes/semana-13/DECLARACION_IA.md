# Declaración de uso de inteligencia artificial — Semana 13

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 13 — Calidad del software y revisión técnica formal |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Redactar el plan de la revisión, el checklist de 15 ítems, el formato de hallazgos, las plantillas (CSV y acta) y el diagrama del proceso, siguiendo la estructura de la semana 13 de RBAC.
- Adaptar `criterios-entrada.php` al paquete de Pacientes.
- Hacer la autorrevisión del código y escribir `verificar-autorrevision.php`, que reproduce cada hallazgo contra el kernel.
- Redactar el documento de la semana, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Prepare una revisión técnica formal de Pacientes: registro, edición, búsqueda y detalle, enfocada en reglas de dominio, contrato API, permisos/tenant, integridad de identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad y dependencias estrictamente necesarias para «registro, búsqueda y actualización segura de un paciente». Entregable/Evidencia: Plan de revisión, checklist de 12–15 ítems, roles de autor/moderador/revisores, criterios de entrada/salida y formato de hallazgos.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana **no** se corrigió el código: la tarea es preparar la revisión, y los hallazgos quedan abiertos para el retrabajo.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `plan-revision-tecnica.md`, `checklist-revision.md`, `formato-hallazgos.md`, `autorrevision-autor.md` | Borrador completo (estructura tomada de RBAC, contenido de Pacientes) |
| `plantillas/*`, `diagramas/*` | Adaptados de RBAC |
| `herramientas/criterios-entrada.php` | Adaptado de RBAC (paquete y rutas de Pacientes) |
| `herramientas/verificar-autorrevision.php` | Borrador completo |
| `evidencia/*.txt` | Salida real de los scripts |
| `semana-13-revision-tecnica-pacientes.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Los 7 hallazgos son del código que escribió la propia IA** en semanas anteriores. AR-02 y AR-03 (tenant en el índice de duplicados) son defectos reales que ninguna de las 87 pruebas detectaba.
- **El primer reparto del paquete (1822 líneas en una sesión) no pasó E-4.** Se rearmó en tres sesiones antes de guardar la evidencia, así que `criterios-entrada-1-antes.txt` solo muestra las fallas de E-5 y E-6.
- **En AR-07, la primera versión del script dio un falso «400»:** PHP convierte una clave numérica de arreglo en entero. Se corrigió el script, no el código revisado.
- **Las severidades son propuestas del autor:** el equipo las confirma o las ajusta en la reunión.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Plan y reparto en 3 sesiones | | |
| Checklist RT-01..RT-15 | | |
| Roles y nombres (E-8) | | |
| Hallazgos AR-01..AR-07 y severidades | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php herramientas/criterios-entrada.php` y obtuve E-1..E-6 PASA.
- [ ] Ejecuté `php herramientas/verificar-autorrevision.php` y vi cada hallazgo.
- [ ] Acordé con el grupo quién modera y quién revisa.

**Fecha de validación:** ____________
