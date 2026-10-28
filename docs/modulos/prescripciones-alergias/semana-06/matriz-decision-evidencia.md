# Matriz decisión → evidencia (semanas 1–7)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias

**Cómo leer la matriz:**
- Cada decisión de arquitectura tiene su origen (semana y regla), el requisito que satisface, el lugar del código donde vive y la **prueba** que la demuestra.
- La columna **Mutación** dice qué defecto inyectado hace fallar esa prueba (`tests/mutaciones.php`). Prueba que la prueba no pasa por casualidad.
- Las rutas de código son relativas a [`micro-his-prescripciones/`](../micro-his-prescripciones/README.md).

## Matriz

| ID | Decisión | Origen | RF / RNF | Dónde vive | Prueba | Mutación |
|---|---|---|---|---|---|---|
| D-01 | Verificación **previa** consultiva y **reverificación** obligatoria al emitir | S1 R-03 | RF-04, RF-14 | `Aplicacion/Servicios/EvaluacionClinica`, `VerificarAlergiasPrevia` | APP-04, APP-10, HTTP-08 | — (la protege CAR-01) |
| D-02 | Coincidencia directa = bloqueo **absoluto**, por código o por nombre normalizado | S1 R-04 | RF-05 | `Dominio/Reglas/VerificadorAlergias` | DOM-03, APP-05, INT-03 | M3 |
| D-03 | Reactividad cruzada con severidad `life_threatening` **o `unknown`** = bloqueo | S1 R-05 | RF-06 | `Dominio/Modelo/Alergia::impideJustificar()` | DOM-04, APP-07 | M4 |
| D-04 | Advertencia: justificación ≥ 20 **y** permiso `override`, consultado solo si hace falta | S1 R-05 · S7 | RF-06 | `Dominio/Reglas/PoliticaEmision` | APP-06, DOM-09 | M7, M28, M29 |
| D-05 | Alergias no evaluadas exigen confirmar «sin alergias conocidas» | S1 R-06 | RF-07 | `PoliticaEmision` | APP-08, DOM-09 | M6 |
| D-06 | **Fail-closed** escrito en el contrato del puerto (DIP): nunca un expediente vacío ante una falla | S1 R-07 · S2 | RF-08, RNF-03 | `Aplicacion/Puertos/FuenteAlergias`, `PdoFuenteAlergias`, `HttpFuenteAlergias` | PER-01, APP-13, INT-06, HFA-02 | M1, M2, M20, M21 |
| D-07 | Dosis diaria ≤ máxima del catálogo (inclusiva) | S1 R-02 | RF-03 | `Dominio/Reglas/ValidadorPrescripcion` | DOM-02, APP-09 | M5 |
| D-08 | Idempotencia por `Idempotency-Key` + `UNIQUE`; el reintento **no** reverifica | S1 R-11 · S4 H-5 | RF-12 | `EmitirPrescripcion`, `schema.sql` | APP-12, APP-17, INT-04, CLI-02 | M8, M13 |
| D-09 | El hospital sale del **token**; lo ajeno responde igual que lo inexistente | S1 R-08 | RF-09 | `Kernel`, `Pacientes`, repositorios | HTTP-04, APP-11, CON-*-04, ANU-04 | M9, M11, M12, M16 |
| D-10 | Inmutable: sin métodos que cambien + trigger en la base | S1 R-10 | RF-11 | `Prescripcion` (readonly), `schema.sql` | DOM-08, INT-02 | M14 |
| D-11 | Traza en la **misma transacción** (bandeja / outbox) | S1 R-12 · S3 | RF-13 | `PdoRegistroAuditoria`, `EmitirPrescripcion` | APP-14, INT-05 | M10 |
| D-12 | Cuatro capas con la dependencia hacia el Dominio; PDO y SQL solo en Persistencia | S3 | RNF-01 | toda la estructura | ARQ-01..06 | — |
| D-13 | MVC: el controlador no tiene SQL ni reglas; solo las vistas conocen HTTP | S4 | Tarea S4 | `Presentacion/Http/{Controladores,Vistas}` | ARQ-07, ARQ-08, MVC-01..04 | M15, M19 |
| D-14 | Repository en el Dominio; PDO e InMemory con **la misma** suite de contrato | S4 | RNF-02 | `Dominio/Puertos`, `Persistencia/{Pdo,EnMemoria}` | CON-MEM-01..08, CON-PDO-01..08, ARQ-09 | M16, M17, M32 |
| D-15 | La dosis se normaliza a decimal (hallazgo H-4 de la suite de contrato) | S4 | — | `Prescripcion` | CON-PDO-01, INT-09 | M18 |
| D-16 | Contrato OpenAPI = código | S5 | — | `api/openapi.yaml` | API-01..03 | M27 |
| D-17 | Cliente **sin** bandeja local: CONFIRMADO / RECHAZADO / INCIERTO | S5 | — | `Cliente/ClientePrescripciones` | CLI-01..05 | M23, M24 |
| D-18 | **No** separar el microservicio: con *fail-closed* se multiplica la indisponibilidad | S5 | — | `bin/medir.php` | Medición: 0/200 emitidas con el Expediente remoto caído | — |
| D-19 | Observabilidad sin datos clínicos; `X-Request-ID` seguro | S5 | — | `Kernel`, `RegistroAccesoJson` | OBS-01..03 | M25, M26 |
| D-20 | La decisión clínica vive en **una** política del Dominio (refactor medido) | S7 | — | `PoliticaEmision`, `DecisionEmision` | DOM-09, CAR-01 | M29, M30 |
| D-21 | En el HIS, leer alergias por una **vista**, sin privilegios sobre la tabla del Expediente | S4 | — | `database/integracion-hospital.sql` | `bin/validar-integracion-hospital.php` (13/13) | — |
| D-22 | **Anular como hecho nuevo**; el estado se deriva | **S6** | RF-15..17 | `Anulacion`, `PoliticaAnulacion`, `prescription_voids` | ANU-01..05, CON-*-08, INT-10 | M31, M32, M33 |
| D-23 | **Bloqueo seguro asimétrico:** anular no depende del Expediente | **S6** | RF-18 | `AnularPrescripcion` (no recibe `FuenteAlergias`) | ANU-06 | — (no hay dependencia que mutar) |

## Cobertura

| Medida | Valor |
|---|---|
| Decisiones con al menos una prueba ejecutable | 23 de 23 (D-18 y D-21 con su script de medición o de validación) |
| Decisiones con una mutación que las vigila | 18 de 23 |
| Pruebas | 107/107 con PostgreSQL 18 |
| Mutaciones | 33/33 detectadas |

## Cómo usar la matriz en la defensa

1. Elegir una decisión (por ejemplo, D-06 *fail-closed*).
2. Abrir el archivo donde vive y señalar la línea.
3. Ejecutar su prueba: `php tests/ejecutar.php --solo=PER,APP,HFA`.
4. Para mostrar que la prueba no pasa por casualidad, aplicar a mano la mutación (M1: el adaptador PDO devuelve una lista vacía si la consulta falla) y ver que falla PER-01; luego restaurar.
