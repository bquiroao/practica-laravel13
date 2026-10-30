---
name: Tarea de módulo ASII
title: "ASII-22 — QA E2E: el pipeline reporta los resultados E2E al módulo y el gate decide la promoción"
labels: ["asii", "module", "qa-e2e", "ci"]
assignees: ["bquiroao"]
---

## Datos del módulo

- **Número:** ASII-22
- **Módulo:** QA, pruebas E2E, CI y guía de despliegue final (adaptación: gestión de ejecuciones automatizadas y calidad de versiones)
- **Estudiante:** Boris Alexander Quiroa Orellana
- **GitHub:** `bquiroao`
- **Rama esperada:** `feature/asii-22-qa-e2e-reportador-ci-bquiroao`
- **Worktree esperado:** `../shi-asii-22-qa-e2e`

## Objetivo

Que cada corrida E2E del pipeline del HIS deje sus resultados y su evidencia en el módulo QA E2E, y que la promoción de una versión dependa del quality gate (contrato `api/openapi.yaml` 1.0.0). Hoy el workflow del repositorio no corre pruebas y nunca se ejecutó (0 corridas en Actions).

## Alcance mínimo

- [x] Contrato API 1.0.0 con 8 operaciones, `x-permiso` y 36 códigos de error, verificado contra el código (API-01..04).
- [x] Cliente del pipeline (`ReportadorCi`) con reintentos con la misma `Idempotency-Key` y bandeja de pendientes en el workspace del job.
- [x] Observabilidad: `X-Request-ID` en toda respuesta y una línea JSON de acceso por petición.
- [ ] Job de CI que construye el artefacto UNA vez, registra la versión con su digest, corre los E2E y reporta (depende de que los administradores habiliten GitHub Actions).
- [ ] Paso final del job: `reenviarPendientes()` y evaluación del gate por el Responsable de release.

## Criterios de aceptación

- [ ] **CA-I1:** Dado un job con la API caída durante el reporte, cuando la API vuelve y corre el paso final, entonces todos los resultados quedan registrados una sola vez (CLI-03, CLI-05).
- [ ] **CA-I2:** Dado un 4xx (escenario fuera del plan), entonces el job no reintenta y lo muestra como rechazado (CLI-04).
- [ ] **CA-I3:** Dado un contrato modificado sin el código, o al revés, entonces API-01..04 fallan en el PR.
- [ ] **CA-I4:** Dado el registro de acceso, entonces ninguna línea contiene el token ni el contenido de un artefacto (OBS-02).

## Riesgos

- GitHub Actions no está habilitado en el repositorio: sin eso, nada de esto corre automáticamente.
- El token del pipeline es un secreto: va en los secretos del repositorio, nunca en el YAML.
