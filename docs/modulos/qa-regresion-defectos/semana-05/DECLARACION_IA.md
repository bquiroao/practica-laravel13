# Declaración de uso de inteligencia artificial — Semana 5

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 5 — Cliente-servidor, contrato API y frontera de microservicio |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el contrato, el cliente, el adaptador remoto, las pruebas y la medición, y los ejecutó contra un PostgreSQL 18 local. Corrió el lint con Redocly y generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir el contrato OpenAPI y sus pruebas de conformidad.
- Diseñar el cliente con su bandeja de pendientes, el adaptador HTTP del almacén con circuito y la observabilidad.
- Medir las fronteras, analizar la decisión y redactar el plan de issue, rama, worktree y PR.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe el módulo en cliente-servidor y evalúe una frontera de microservicio: especifique contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia. Incluya diagramas y una migración razonada; no divida el sistema sin justificación medible. Entregable/Evidencia: Contrato API + plan de issue/rama/worktree/PR.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».
3. «continua».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-qa/api/openapi.yaml` | Contrato completo |
| `micro-his-qa/src/Cliente/*`, `src/Persistencia/Http/*`, `src/Presentacion/Http/Observabilidad/*`; cambios en `Kernel`, `FabricaAdaptadores`, `contenedor.php` y `config.php` | Código |
| `micro-his-qa/bin/{almacen-simulado.php,medir.php}` | Scripts |
| `micro-his-qa/tests/{Api,Cliente}/*`, `ARQ-10` y las mutaciones M23–M29 | Pruebas |
| `semana-05/diagramas/*`, `semana-05/plan/*` | Diagramas y plan |
| `semana-05/evidencia/*` | Salidas reales |
| `semana-05-cliente-servidor-microservicios.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Los umbrales de C-1..C-5 son propuestos**, no medidos en producción. Los valores «hoy» de C-1 y C-3 son estimaciones del uso esperado.
- **El issue del plan no se creó en GitHub:** es un borrador.
- **`CircuitoInterruptor` y el transporte HTTP del cliente** se adaptaron del Micro-HIS Prescripciones del mismo estudiante.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Separar el almacén, no QA | | |
| Bandeja de pendientes en el cliente | | |
| Criterios C-1..C-5 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté las pruebas, la medición y el lint.
- [ ] Revisé el contrato contra las rutas.
- [ ] Revisé el plan de issue, rama, worktree y PR.

**Fecha de validación:** ____________
