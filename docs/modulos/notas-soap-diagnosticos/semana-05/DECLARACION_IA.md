# Declaración de uso de inteligencia artificial — Semana 5

| | |
|---|---|
| **Estudiante** | Joshua Eduardo García Reyes (`jgarciar73-svg`) |
| **Módulo** | ASII-11 Notas SOAP y diagnósticos |
| **Entrega** | Semana 5 — Diseño cliente-servidor, evaluación de una frontera de microservicio, contrato API y plan de integración |

## 1. Herramienta utilizada

| Herramienta | Proveedor | Modo de uso |
|---|---|---|
| Claude (aplicación de escritorio, modo Cowork) | Anthropic | Asistente con acceso a la carpeta de mi worktree. Escribió el código y la documentación en un entorno de trabajo, ejecutó las pruebas, la medición y la validación del contrato, y copió los archivos a mi carpeta. No hizo commits, push ni PR: los hice yo. |

## 2. Propósito del uso

- Convertir el micro-HIS de las semanas 3 y 4 en un servidor con contrato: rutas con permiso, `X-Request-ID`, registro de acceso, `Idempotency-Key`, nuevo endpoint de listado y 503 cuando la base no responde.
- Escribir el contrato OpenAPI 3.1 y las pruebas que lo comparan con el código (`API-01` a `API-04`).
- Escribir la librería cliente (`src/Cliente/`) con timeout, reintento y circuit breaker, y sus pruebas (`CLI-01` a `CLI-08`).
- Escribir el script de medición `bin/medir.php` y ejecutarlo.
- Escribir las fuentes PlantUML de los cinco diagramas y generar sus imágenes.
- Redactar el documento de la semana, el borrador del issue y del PR de integración, la guía de defensa oral y esta declaración.

## 3. Prompts relevantes

1. "Ya empecemos con la siguiente" (continuar con la semana 5 después de que se integró el PR de la semana 4).
2. Texto de la tarea (matriz del README del repositorio, fila 11, semana 5):
   > Evolucione el MISMO «Micro-HIS Notas SOAP y diagnósticos» y el flujo «registro de nota SOAP y asociación de diagnóstico clínico» hacia un diseño cliente-servidor y evalúe una frontera de microservicio. Especifique contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia; incluya diagramas y una migración razonada. Puede entregar diseño/prototipo: no construya infraestructura de producción ni divida el sistema sin una justificación medible.
3. Entregable del plan semanal del README: «Contrato API preliminar y plan de integración. Endpoints, payloads, errores, permisos, rama, worktree y PR.»
4. Seguir la misma estructura de carpetas de las semanas anteriores y entregar la semana como un PR propio hacia `develop`.

## 4. Contenido de origen y contenido generado

| Elemento | Origen |
|---|---|
| Reglas R-01 a R-08 y casos de uso | Míos, de la semana 1 |
| RF/RNF y criterios de aceptación (incluido RNF-07, 2 s p95) | Míos, de la semana 2 |
| Micro-HIS en capas, MVC, Repository y bandeja de salida | De las semanas 3 y 4 (generados con IA a partir de mi diseño) |
| Tablas de HOSPITAL y CENTRAL, plantillas de issue y PR, flujo con worktree | Repositorio del grupo |
| Cambios al código del servidor, `src/Cliente/`, `bin/medir.php` | Generados con IA |
| `api/openapi.yaml` | Generado con IA; validado con Redocly CLI 1.34.3 |
| Pruebas nuevas (`API`, `CLI`, `OBS`, `APP-13` a `17`, `HTTP-11` a `14`, `REP-07/08`, `ARQ-13/14`, `INT-09`) | Generadas con IA |
| Criterios C1 a C5 y sus umbrales | Propuesta generada con IA; los valores de hoy salen de la situación real del módulo |
| `semana-05-cliente-servidor-microservicios.md` | Borrador generado con IA |
| `plan/issue-asii-11-notas-soap.md` y `plan/pr-integracion-notas-soap.md` | Borradores generados con IA sobre las plantillas del repositorio |
| `diagramas/*.puml` y `*.png` | Generados con IA; imágenes generadas con PlantUML |
| `evidencia/*.txt` | Salida real de las pruebas, la medición, el lint y la API, ejecutados con PHP 8.3.6 en Linux y PostgreSQL 16 |
| `GUIA_DEFENSA_ORAL.md` | Borrador generado con IA |
| `DECLARACION_IA.md` | Borrador (este archivo) |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisión de no separar el módulo | | |
| Criterios C1 a C5 y sus umbrales | | |
| Contrato OpenAPI | | |
| Idempotencia en el registro | | |
| Librería cliente y reglas de reintento | | |
| Migración por fases | | |
| Plan de issue, rama, worktree y PR | | |
| Diagramas | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` en mi computadora y anoto el resultado: ____________
- [ ] Ejecuté `php bin/medir.php` en mi computadora y anoto el p50 de A y de C: ____________
- [ ] Leí el documento y puedo explicar por qué el módulo no se separa hoy.
- [ ] Puedo explicar qué pasa si se pierde la respuesta de un registro y el cliente reintenta.
- [ ] Comprobé que no hay credenciales en el repositorio (solo variables de entorno).
- [ ] Comprobé que las referencias bibliográficas existan.
- [ ] Puedo explicar y modificar el código y los diagramas sin ayuda (ver `GUIA_DEFENSA_ORAL.md`).

**Fecha de validación:** ____________
