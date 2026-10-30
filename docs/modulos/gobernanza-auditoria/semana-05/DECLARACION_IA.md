# Declaración de uso de inteligencia artificial — Semana 5

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 5 — Cliente-servidor, API REST, frontera de microservicio e integración |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir el contrato OpenAPI y sus pruebas de conformidad.
- Construir la capa Cliente, reutilizando el transporte y el circuit breaker de Pacientes, y la bandeja local.
- Agregar la observabilidad y el ancla externa.
- Medir la frontera, hacer los diagramas, preparar el plan de issue y PR y redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Evolucione el MISMO «Micro-HIS Gobernanza y auditoría de movimientos del sistema» y el flujo «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» hacia un diseño cliente-servidor y evalúe una frontera de microservicio. Especifique contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia; incluya diagramas y una migración razonada. Puede entregar diseño/prototipo: no construya infraestructura de producción ni divida el sistema sin una justificación medible. Entregable/Evidencia: Contrato API y plan de issue, rama, worktree y PR.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `api/openapi.yaml`, `src/Cliente/*`, observabilidad, ancla, `bin/medir.php`, pruebas nuevas | Borrador completo; transporte y circuit breaker portados de Pacientes |
| `semana-05/diagramas/*`, `plan/*`, documento, guía y declaración | Borradores |
| `semana-05/evidencia/*.txt` | Salida real de los comandos |

**Hechos a tener en cuenta:**
- **El lint de Redocly encontró un error de la IA:** una coma dentro de un mapa YAML en línea partía una descripción en dos. Se corrigió.
- **CLI-03 falló en su primera versión** por una expectativa equivocada: el circuito abierto bloquea el reenvío inmediato, y eso es lo correcto. Se corrigió la prueba.
- **Se corrigió la descripción de APP-11**, que mencionaba un caso que la prueba no ejecutaba.
- **La medición usa `php -S`**, no un servidor de producción. Las cifras sirven para comparar, no para dimensionar.
- **El issue NO se creó en GitHub.** Queda listo en `plan/` para que el estudiante decida cuándo crearlo.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisión de no extraer el servicio | | |
| Criterios C-1..C-5 | | |
| Contrato 1.0.0 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté el lint del contrato y las pruebas.
- [ ] Revisé el issue antes de crearlo en GitHub.

**Fecha de validación:** ____________
