# Declaración de uso de inteligencia artificial — Semana 5

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 5 — Cliente-servidor, API REST, frontera de microservicio e integración |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Escribir el contrato OpenAPI 3.1 y validarlo con Redocly CLI.
- Implementar en el Micro-HIS:
  - el endpoint de decisión (controlador, formulario, vista y ruta);
  - la observabilidad (`X-Request-ID` y registro de acceso);
  - la librería cliente con timeout, reintento, circuit breaker y fail-closed.
- Escribir las pruebas de contrato API, cliente, observabilidad y arquitectura, y ejecutarlas.
- Escribir y ejecutar el script de medición de latencia (`bin/medir.php`) para evaluar la frontera de microservicio.
- Escribir los diagramas, el documento, el issue y el borrador del PR, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Evolucione el MISMO «Micro-HIS RBAC: roles, permisos y protección de rutas» y el flujo «asignación de rol, autorización de una operación y denegación trazable» hacia un diseño cliente-servidor y evalúe una frontera de microservicio. Especifique contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia; incluya diagramas y una migración razonada. Puede entregar diseño/prototipo: no construya infraestructura de producción ni divida el sistema sin una justificación medible. Entregable/Evidencia: Contrato API y plan de issue, rama, worktree y PR.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-rbac/api/openapi.yaml` | Borrador completo |
| Cambios en `micro-his-rbac/` (`src/Cliente/*`, endpoint de decisión, `Kernel`, `Respuesta`, `bin/medir.php`, pruebas, README) | Borrador completo |
| `semana-05-cliente-servidor-microservicios.md`, `plan/*.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 |
| `evidencia/*.txt` | Salidas reales de los comandos (PHP 8.4.26, Redocly CLI 1.34.3) |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Verificaciones que hizo la herramienta:**

- Pruebas: 58 pasan, 0 fallan y 2 omitidas (requieren PostgreSQL).
- OpenAPI válido según Redocly.
- Se inyectaron violaciones temporales para comprobar que ARQ-01, API-01 y API-03 fallan, y luego se revirtieron.
- Medición de latencia en 4 escenarios.
- Consulta real al servidor con `php -S` para obtener el registro de acceso.

**Nota sobre las cifras:** las latencias de red varían entre corridas (p50 de 62 ms en una y 33 ms en otra) por usar el servidor de desarrollo de PHP en Windows. El documento lo declara así.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Contrato OpenAPI y decisiones del endpoint de decisión | | |
| Decisión de no dividir el sistema y criterios C1–C5 | | |
| Librería cliente (resiliencia) | | |
| Observabilidad (`X-Request-ID`, registro de acceso) | | |
| Propiedad de datos, comunicación, seguridad y consistencia | | |
| Migración por fases | | |
| Issue, rama, worktree y PR (`plan/`) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` y `php bin/medir.php` en mi equipo y registré mis cifras.
- [ ] Validé el contrato con `npx @redocly/cli lint api/openapi.yaml`.
- [ ] Probé el endpoint de decisión con `curl` (modo `memoria`).
- [ ] Puedo justificar con los datos medidos por qué no conviene separar RBAC hoy.
- [ ] Revisé el issue y el PR de `plan/` antes de usarlos.

**Fecha de validación:** ____________
