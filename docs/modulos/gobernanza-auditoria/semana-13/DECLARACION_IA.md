# Declaración de uso de inteligencia artificial — Semana 13

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 13 — Revisión técnica formal |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Redactar el plan, el checklist, el formato de hallazgos y las plantillas.
- Adaptar el script de criterios de entrada (de Pacientes, semana 13).
- Hacer la autorrevisión del autor y escribir el script que reproduce cada hallazgo contra el kernel real.
- Redactar la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Prepare una revisión técnica formal de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema, enfocada en reglas de dominio, contrato API, permisos/tenant, integridad de audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas y dependencias estrictamente necesarias. Entregable/Evidencia: Plan de revisión, checklist de 12–15 ítems, roles de autor/moderador/revisores, criterios de entrada/salida y formato de hallazgos.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-13/*.md`, `plantillas/*`, `diagramas/*` | Borradores |
| `semana-13/herramientas/*.php` | `criterios-entrada.php` adaptado de Pacientes; `verificar-autorrevision.php` nuevo |
| `semana-13/evidencia/*.txt` | Salida real de los scripts |

**Hechos a tener en cuenta:**
- **La autorrevisión la hizo la IA sobre código que ella misma escribió** en semanas anteriores. Los 6 hallazgos se reproducen contra el kernel real, pero los puntos ciegos del autor también son suyos.
- **AR-01 se clasificó como Crítico.** En Pacientes, el hallazgo equivalente fue Mayor. Aquí el token demo tiene alcance de **red**. Conviene que el estudiante y el moderador confirmen la severidad.
- **Los revisores y el moderador figuran «por confirmar»:** son personas que el estudiante debe designar.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Plan y roles | | |
| Checklist RT-01..RT-15 | | |
| Hallazgos AR-01..AR-06 y sus severidades | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté los dos scripts de `herramientas/`.
- [ ] Designé al moderador y a los revisores.

**Fecha de validación:** ____________
