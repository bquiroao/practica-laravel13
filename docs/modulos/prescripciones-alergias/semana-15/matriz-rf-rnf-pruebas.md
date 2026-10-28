# Matriz RF/RNF → prueba — Prescripciones con validación de alergias

**Estudiante:** Boris Alexander Quiroa Orellana · **Fuente de los requisitos:**
- RF-01..14 y RNF-01..08: [semana 2](../semana-02/semana-02-dip-requerimientos-diseno.md);
- RF-15..18 y RNF-16: [semana 6](../semana-06/registro-cambio-practico.md);
- RF-19, RF-20 y RNF-17: [semana 12](../semana-12/registro-cambio-solicitado.md);
- RNF-09..13: [matriz de la semana 12](../semana-12/matriz-decision-rf-rnf-evidencia.md).

**Prefijos de las pruebas nuevas de esta semana:**
- **UNI-xx:** unitarias;
- **CB-xx:** caja blanca;
- **CN-xx:** caja negra;
- **DEF-xx:** defecto abierto, que falla a propósito (se ejecuta con `--defectos`).

Las demás son de las semanas 3 a 12 y siguen en la suite (129/129 con PostgreSQL).

| Leyenda de técnica | |
|---|---|
| U | Unitaria (una clase, sin E/S) |
| B | Caja blanca (caminos, ramas, MC/DC) |
| N | Caja negra (equivalencia, límites, permisos, errores, por HTTP) |
| I | Integración con PostgreSQL |
| M | Mutación que la prueba detecta |

## 1. Requisitos funcionales

| Req. | Qué exige (resumen) | Pruebas de la semana 15 | Pruebas anteriores | Técnicas |
|---|---|---|---|---|
| RF-01 | Emitir con paciente e ítems; 201 con id y Location | CB-01 (C11), **CN-07** (201 + Location) | APP-01, HTTP-01, INT-01 | B, N, I |
| RF-02 | Ítem inválido → 422 con la lista de campos | **UNI-03**, CB-01 (C7), **CN-01** (frecuencia), CN-03 (días) | DOM-01, APP-09, INT-07, M5 | U, B, N, I |
| RF-03 | Dosis diaria ≤ máxima, inclusiva | **UNI-02**, **CN-02** (1000/1000,5 c/6 h) · **DEF-08** (abierto: el total de dos ítems no se suma) | DOM-02, APP-09, M5 | U, N |
| RF-04 | La emisión vuelve a verificar; nunca acepta el resultado del cliente | CB-01 (C6, C8) | APP-10; paridad S11 paso 14 | B |
| RF-05 | Coincidencia directa activa = bloqueo, por código o por nombre normalizado | **UNI-04**, CB-01 (C8) · **DEF-03** (abierto: un nombre escrito de otra forma no coincide) | DOM-03, APP-05, INT-03, M3 | U, B, I |
| RF-06 | Cruzada: justificación ≥ 20 y override; mortal o desconocida bloquea | **UNI-05**, **UNI-07**, **CB-02** (MC/DC), CB-01 (C10), **CN-03** (19/20), **CN-04** (ruiz → 409) | DOM-04, DOM-09, APP-06, APP-07, M4, M7, M28, M29 | U, B, N |
| RF-07 | Sin registros: exigir y guardar la confirmación | UNI-07, **CB-02** (MC/DC), CB-01 (C9) | APP-08, M6 | U, B |
| RF-08 | Fuente caída → 503 sin emitir | CB-01 (C6), **CN-06** (503 sin detalles) | APP-13, HTTP-03, INT-06, M1, M2, M20, M21 | B, N, I |
| RF-09 | Paciente o prescripción de otro hospital = inexistente (404) | CB-01 (C5), CB-03 (R2), **CN-05** (404 idéntico) | APP-03, APP-11, HTTP-07, M9, M16 | B, N |
| RF-10 | Cada operación exige su permiso en el hospital del token | CB-01 (C1), CB-03 (R1), **CN-04** (tabla de decisión), **CN-05** (tenant) | APP-11, HTTP-04, M11 | B, N |
| RF-11 | Lo emitido no se modifica ni se borra | **UNI-01**, CN-06 (PUT/DELETE → 405) | DOM-08, INT-02, M14 | U, N, I |
| RF-12 | Idempotencia: misma clave y contenido → la misma; otro contenido → 409; **otro prescriptor → 409** | CB-01 (C2, C3, **C4**, C12, C13), **CB-02** (MC/DC E·F), **CN-07** | APP-12, APP-17, INT-04, M8, M13, M17, **M36** | B, N, I |
| RF-13 | Verificación guardada con la prescripción; trazas en la bandeja | CB-01 (C6 y C8 con su traza), CB-03 (R8 todo o nada) | APP-01, APP-14, INT-05, M10 | B, I |
| RF-14 | La previa informa los 5 resultados, sin efectos | CN-01, CN-02 (por la previa) | APP-04, HTTP-08 | N |
| RF-15 | Anular con permiso y motivo ≥ 10 | **UNI-08**, CB-03 (R3, R4), **CN-03** (9/10), CN-04 | ANU-01, ANU-02, M31 | U, B, N |
| RF-16 | Solo se anula una EMITIDA; ya anulada → 409 | UNI-08, CB-03 (R5, **R6 carrera**) · **DEF-05** (abierto: el 409 no dice quién anuló) | ANU-03, CON-*-08, M32 | U, B |
| RF-17 | Anulación y traza juntas | CB-03 (R8) | ANU-05, INT-10, M33 | B, I |
| RF-18 | Anular no depende del Expediente | — | ANU-06 | — |
| RF-19 | La previa informa `puede_justificar` solo con ADVERTENCIA | UNI-07, CB-02 (rama de la previa) | JUS-01..03, M34, M35 | U, B |
| RF-20 | La UI decide la hoja con `puede_justificar` | — | Recorrido S12 (24, 26) | — |

## 2. Requisitos no funcionales

| Req. | Qué exige | Pruebas de la semana 15 | Pruebas anteriores | Técnicas |
|---|---|---|---|---|
| RNF-01 | Dominio y Aplicación sin PDO, SQL ni HTTP | UNI-01..08 corren sin kernel ni base | ARQ-01..05 | U |
| RNF-02 | Cambiar la fuente de alergias no toca Dominio ni Aplicación | CB-01 (C6) con `FuenteAlergiasCaida`; CN-06 y CN-07 con `FuenteConmutable` | CON-*, CLI-05 | B, N |
| RNF-03 | Fail-closed: ninguna falla se lee como «sin alergias» | CB-01 (C6), CN-06 | APP-13, M1, M2, M20, M21 | B, N |
| RNF-04 | Testabilidad: los criterios corren sin base | 18 de las 18 pruebas nuevas corren sin base | — | U, B, N |
| RNF-05 | Si la base no confirma, 503 sin traza | CB-01 (C14), CB-03 (R7, R8) | APP-15, APP-16 | B |
| RNF-06 | Sentencias preparadas | — | PER-*, M12 | — |
| RNF-07 | Trazas sin datos clínicos | CB-01 (C6, C8: recurso `paciente:<id>`) | APP-01, OBS-02 | B |
| RNF-08 | Límite de 2 s del Expediente | — | CLI-06, M22 | — |
| RNF-09 | Alergias siempre visibles en la UI | — | Recorrido S11/S12 (02, 14, 17) | — |
| RNF-10 | Cada código tiene su mensaje y está documentado | CN-06 afirma `RUTA_NO_ENCONTRADA` y `METODO_NO_PERMITIDO` · **DEF-07** (abierto: `SERVICIO_NO_DISPONIBLE` sin emisor) | API-03 | N |
| RNF-11..13 | WCAG, sin datos en la URL, paridad del prototipo | — | Recorrido y paridad (S11, S12) | — |
| RNF-16, RNF-17 | Compatibilidad del contrato y caracterización | — | CAR-01, API-01..03, JUS-04 | — |

## 3. Cobertura de la tarea

| Pide la tarea | Mínimo | Entregado |
|---|:-:|---|
| Unitarias para las reglas de prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro | 3 | **8** (UNI-01..08), una regla por prueba |
| Caja blanca con ramas y condiciones del flujo | 2 | **3** (CB-01 caminos básicos, CB-02 MC/DC, CB-03 ramas), con **100 %** de líneas en las 4 clases del flujo |
| Caja negra con equivalencia, límites, permisos y errores | 4 | **7** (CN-01 equivalencia; CN-02 y CN-03 límites; CN-04 y CN-05 permisos y tenant; CN-06 errores; CN-07 idempotencia) |
| Sin probar módulos ajenos | — | El Expediente, RBAC y la identidad se simulan con los adaptadores de demostración y los dobles (`FuenteConmutable`, `FuenteAlergiasCaida`, `PoliticaAccesoEstatica`) |
| Trazadas a RF/RNF | — | Esta matriz: 20 RF y 15 RNF; 18 RF con pruebas de esta semana (RF-18 y RF-20 se cubren con pruebas anteriores) |
