# Matriz RF/RNF → prueba

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión

Requisitos de la [semana 2](../semana-02/semana-02-lsp-requerimientos-diseno.md). Las pruebas son las de `micro-his-qa/tests/`: **112**, todas en verde con PostgreSQL ([pruebas-2-despues-verde.txt](evidencia/pruebas-2-despues-verde.txt)). En **negrita**, las 12 nuevas de esta semana. Tipo: **U** unitaria, **CB** caja blanca, **CN** caja negra; las anteriores se indican por su grupo.

## Requisitos funcionales

| RF | Requisito (resumen) | Pruebas | Técnica | Cobertura |
|---|---|---|---|---|
| RF-01 | Diseñar un caso completo; 422 con cada campo que falta | DOM-01, MVC-01, APP-01, CON-01 | Unitaria, contrato | ✅ |
| RF-02 | Aprobar solo alguien distinto del autor; **editar un aprobado crea la versión siguiente** | DOM-02, APP-07, INT-04, CON-02 | Unitaria, integración | ⚠️ **Parcial:** la segunda mitad (CA-02) no tiene operación ni prueba. Hallazgo RT-04, abierto |
| RF-03 | Iniciar con build y con todos los críticos de la matriz | DOM-03, APP-06, INT-02, CON-03 | Unitaria, integración | ✅ |
| RF-04 | Resultados solo en campaña EN_CURSO y de su alcance | DOM-04, APP-07, **CN-01**, **CN-04** | Unitaria; **CN equivalencia y errores** | ✅ |
| RF-05 | FALLA/BLOQUEADO exigen evidencia verificable de 5 MB o menos | APP-04, APP-10, DOM-09, **CN-01**, **CN-02** | **CN equivalencia y límites** (5 MB y 5 MB + 1) | ✅ |
| RF-06 | Si el almacén no confirma: 503 y nada registrado | APP-11, AHT-02, CLI-05, **CN-04** | **CN errores** | ✅ |
| RF-07 | Toda FALLA se vincula a un defecto | APP-05, INT-01 | Unitaria, integración | ✅ |
| RF-08 | Clasificar; CRÍTICA exige pasos y evidencia; cambiar la propuesta exige motivo | DOM-07, **UNI-01** | **U** (reclasificar baja la severidad efectiva) | ✅ |
| RF-09 | CORREGIDO por el desarrollador; solo una re-ejecución cierra | DOM-08, APP-08, CAM-03, **UNI-03** | **U** (formato del build) | ✅ |
| RF-10 | Cierre: sin pendientes; GO con 0 críticos, sin MAYOR en críticos y con tasa ≥ umbral | DOM-05, DOM-06, CAM-01..05, APP-14, INT-08, **UNI-01**, **UNI-02**, **UNI-04**, **CB-01**, **CB-02**, **CN-02**, **CN-05** | **U, CB ramas y MC/DC, CN límites** | ✅ |
| RF-11 | Trazabilidad requisito ↔ caso ↔ intento ↔ evidencia ↔ defecto, **y los requisitos sin caso** | APP-02, CAM-04, INT-07, **CB-03**, **CN-05** | **CB caminos**, **CN** | ⚠️ **Parcial:** los requisitos sin caso no se listan (H-2 de la semana 12) |
| RF-12 | Los intentos no se editan ni se borran | INT-03, CON-05 | Integración (triggers) | ✅ |
| RF-13 | Registrar es idempotente con Idempotency-Key | APP-03, APP-15, INT-05, CLI-01..03 | Unitaria, integración, cliente | ✅ |
| RF-14 | Permiso por operación y tenant; traza de cada operación | APP-09, APP-01, HTTP-02, API-01, INT-07, INT-09, **CN-03** | **CN permisos** (6 roles × operación, 2 tenants) | ✅ |

## Requisitos no funcionales

| RNF | Requisito | Pruebas o medición | Cobertura |
|---|---|---|---|
| RNF-01 | Sustituibilidad (LSP) de la evidencia | ARQ-07, DOM-09 | ✅ |
| RNF-02 | Integridad verificable (SHA-256) | DOM-09, PER-06, AHT-01, AHT-02 | ✅ |
| RNF-03 | Fail-closed | APP-11, AHT-02, AHT-03, **CN-04** | ✅ |
| RNF-04 | Protección de datos clínicos | APP-02, OBS-02, INT-09, PER-01 | ✅ |
| RNF-05 | Testabilidad sin base de datos | Toda la suite en memoria (CON-*-MEM, MVC-05) | ✅ |
| RNF-06 | Extensibilidad (0 `instanceof` de subtipos) | ARQ-07 | ✅ |
| RNF-07 | Sentencias preparadas | PER-05 | ✅ |
| RNF-08 | Trazabilidad de 500 casos en menos de 1 s | M-5 del [tablero SQA](../semana-14/herramientas/medir-sqa.php): p95 de 140 ms en PostgreSQL | ✅ Medido, no es una prueba de la suite |

## Resumen

| | Cantidad |
|---|---|
| RF cubiertos por completo | 12 de 14 |
| RF parciales | 2 (RF-02, RF-11); los dos son hallazgos abiertos y registrados |
| RNF cubiertos | 8 de 8 (RNF-08 por medición) |
| Pruebas nuevas | 12: 4 unitarias, 3 de caja blanca, 5 de caja negra |
| Reglas R-01..R-15 nombradas en alguna prueba (M-3) | 15 de 15 (eran 12) |

**Cómo leer «parcial».** Un RF parcial no se esconde detrás de pruebas en verde: la parte que falta tiene un ID de hallazgo y está en el [registro de defectos](registro-defectos.md).
