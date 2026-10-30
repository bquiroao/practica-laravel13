# Matriz decisión → evidencia (semanas 1–6)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final

Cada decisión de arquitectura tiene:
- la regla o el requisito que la motiva;
- el lugar donde vive;
- una prueba que la verifica;
- cuando existe, una **mutación** que la rompe y que la suite detecta, para saber que la prueba no pasa por casualidad.

Rutas relativas a `micro-his-e2e/`. Evidencia: [cambio-3-suite-completa.txt](evidencia/cambio-3-suite-completa.txt) (86/86) y [mutaciones.txt](evidencia/mutaciones.txt) (31/31).

| # | Decisión | Sem. | Regla / RF / RNF | Dónde vive | Prueba | Mutación |
|---|---|---|---|---|---|---|
| D-01 | Escenarios solo con datos sintéticos `SINT-…` | 1 | R-01 / RF-01, RNF-06 | `EscenarioE2E::registrar`; `CHECK` en `escenarios` | DOM-01, CON-01-PDO | M1 |
| D-02 | El plan incluye todos los críticos y los módulos afectados | 1 | R-02 / RF-03 | `Version::planificar` | DOM-02, APP-09 | M2 |
| D-03 | El plan se congela al iniciar la primera ejecución | 1 | R-02 | `Version::congelarPlan`; trigger `versiones_proteger` | DOM-03, INT-04 | M3 |
| D-04 | Versión = semver + commit + **digest**; un digest, una versión | 1 | R-03 / RF-02, RNF-07 | `Version`, `UNIQUE (tenant, digest)` | APP-09 | — |
| D-05 | La ejecución corre sobre el digest de su versión, también en la base | 1, 3 | R-03 / RF-04 | `Ejecucion::iniciar`; trigger `ejecuciones_proteger` | DOM-04, INT-03 | M4, M15 |
| D-06 | Un PASA con reintentos es INESTABLE, y lo decide el servidor | 1 | R-06 / RF-06 | `ResultadoEscenario::desde`; `CHECK` en `resultados` | DOM-05, INT-04 | M5 |
| D-07 | Un resultado por escenario; los reintentos van en `intentos` | 3 | R-04 | `Ejecucion::agregar` | DOM-06 | M6 |
| D-08 | **DIP:** el dominio posee los puertos; el gate es una función pura | 2 | RNF-01..03 | `Dominio/Puertos`, `QualityGate` | `medir-dip.php` (12 → 0), ARQ-01..05 | (sem. 2: 8/8) |
| D-09 | Gate: digest y reglas a–e; OMITIDO cuenta como no aprobado | 2 | R-09 / RF-09 | `QualityGate::evaluar` | DOM-08..10, APP-02 | M7, M8, M9 |
| D-10 | Fail-closed: la evidencia se confirma ANTES de la base | 1, 3 | R-05, R-08 / RNF-04 | `ReportarResultado` | APP-04, CLI-05 | M10 |
| D-11 | Las reglas se revisan ANTES del almacén: sin artefactos huérfanos | 3 | R-04 | `ReportarResultado` | APP-05 | M12 |
| D-12 | Idempotencia antes que el estado; `UNIQUE (tenant, clave)` | 3 | R-12 / RF-13 | `ReportarResultado`; esquema | APP-03, INT-05, CON-04 | M11, M16 |
| D-13 | Resultado, evidencia y traza en una transacción | 3 | R-14 | `Transaccion` | APP-08, INT-06, CON-07 | M13 |
| D-14 | Resultados, evidencias, evaluaciones, traza y despliegues solo se agregan (en la base) | 3, 6 | R-04, R-08 | Triggers `solo_agregar` | INT-02, INT-10 | M14 |
| D-15 | MVC: formulario (forma, 400) → caso de uso → vista (HTTP) | 4 | — | `Presentacion/Http/*` | MVC-01..05, ARQ-06 | M18, M21 |
| D-16 | Repository con dos adaptadores y una suite de contrato | 4 | RNF-05 | `Dominio/Puertos`, `Persistencia/*` | CON-01..08 × 2 | M17 |
| D-17 | Repositorio compartido: hecho publicado en la misma transacción, RLS por hospital | 4 | R-13 | `integracion-compartida.sql` | INT-08, INT-09 | M19, M20 |
| D-18 | Contrato OpenAPI = código | 5 | R-13 | `api/openapi.yaml` | API-01..04 | M24 |
| D-19 | El pipeline reintenta con la misma clave y deja pendientes en su bandeja | 5 | R-12 / RNF resiliencia | `Cliente/ReportadorCi` | CLI-01..07 | M22, M23 |
| D-20 | X-Request-ID y registro de acceso sin datos sensibles | 5 | RNF observabilidad | `Kernel` | OBS-01..03 | M25 |
| D-21 | **No** se separa la ingesta: 0 de 5 criterios, medido | 5 | — | `bin/medir.php` | `medicion-frontera.txt` | — |
| **D-22** | **Despliegue reproducible:** solo con el último gate APROBADO, vigente (72 h) y sobre el digest instalado; guía versionada; smoke que falla → REVERTIDO | **6** | **R-11 / RF-12** | `Despliegue::registrar`; trigger `despliegues_exigir_gate` | **CAM-01..06, INT-10, CON-08** | **M26–M30** |
| D-23 | Un artefacto corrupto se repara al volver a guardarlo | 6 | R-08 | `AlmacenEvidenciasEnDisco::guardar` | PER-05 | M31 |

**Cobertura:**
- **23 decisiones**, todas con una prueba.
- **20 con al menos una mutación** que la rompe y se detecta.
- Las tres sin mutación son de diseño o de proceso: D-04 (el `UNIQUE` del digest), D-08 (medido aparte en la semana 2) y D-21 (una decisión de no hacer).
