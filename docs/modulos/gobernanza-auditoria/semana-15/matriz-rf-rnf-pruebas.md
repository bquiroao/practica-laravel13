# Matriz RF/RNF → prueba — Gobernanza y auditoría

**Estudiante:** Boris Alexander Quiroa Orellana · **Fuente de los requisitos:** RF-01..13 y RNF-01..09 ([semana 2](../semana-02/semana-02-ocp-requerimientos-diseno.md)); RF-14..17 y RNF-10 ([semana 6](../semana-06/registro-cambio-practico.md)); RF-18..21 y RNF-11 ([semana 12](../semana-12/registro-cambio-solicitado.md)); RNF-12..15 ([matriz de la semana 12](../semana-12/matriz-decision-rf-rnf-evidencia.md)).

**Prefijos de las pruebas nuevas de esta semana:**
- **UNI-xx:** unitarias;
- **CB-xx:** caja blanca;
- **CN-xx:** caja negra;
- **DEF-xx:** defecto abierto, que falla a propósito.

Las demás son de las semanas 3 a 12 y siguen en la suite (116/116 con PostgreSQL).

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
| RF-01 | Registrar un movimiento completo | CB-01 (C3) | APP-01, HTTP-01 | B, N |
| RF-02 | Hospital del cuerpo = hospital del token | **UNI-06**, CN-05 | HTTP-02, M12 | U, N |
| RF-03 | Campos obligatorios, módulo, acción del catálogo, resultado, fecha | **UNI-03, UNI-04, UNI-05**, CB-01 (C2), CN-01, CN-03 | CA-01..09 | U, B, N |
| RF-04 | Sin datos prohibidos en el detalle | CN-03 (límite de 4 KB) | DOM-05 | N |
| RF-05 | Idempotencia por (emisor, event_id) | CB-01 (C4, C5), **CB-02** (MC/DC) | APP-03, CON-*-03, M5 | B |
| RF-06 | Ninguna operación modifica ni borra | **UNI-01**, CN-06 (PUT/PATCH/DELETE → 405) | ARQ-05, INT-02, M11 | U, N, I |
| RF-07 | Sello encadenado y verificable | **UNI-02**, **CB-03** (R1–R8) | APP-09, INT-05, M1, M3 | U, B, I |
| RF-08 | Rango obligatorio, real, `desde ≤ hasta`, «hasta» inclusivo en hora local | **UNI-07**, **CN-02** | CA-13, INT-03, M4 | U, N, I |
| RF-09 | Filtros opcionales combinables; los no soportados se rechazan | CN-01, CN-03 | REF-01, REF-05, M24 | N |
| RF-10 | El auditor solo ve su hospital; la red exige `consultar_red` | **CN-04** (tabla de decisión), CN-05 | APP-07, REF-06, M23 | N |
| RF-11 | 50 por página, más recientes primero, con cursor | **CN-07** (50/51), **UNI-08** (cursor) | REF-03, M14 | U, N |
| RF-12 | Cada lectura deja AUDITORIA_CONSULTADA; si falla, no hay resultados | CN-06 (base caída → 503) | APP-05, REF-02, M6 | N |
| RF-13 | Fuera del alcance = inexistente (404) | **CN-05**, UNI-06 | HTTP-05, M22 | U, N |
| RF-14..17 | Corrección trazable: otro movimiento, una sola, mismo emisor y hospital | **CB-01** (C6–C10), **CB-02** | CORR-01..07, INT-08, M19, M20 | B, I |
| RF-18..20 | Filtro por recurso, con tenant, máximo 128 | CN-03 (recurso 128/129) | REC-01..03, M25, M26 | N |
| RF-21 | Pregunta contextual en la UI | — | Recorrido S12 (08a–08c) | — |

## 2. Requisitos no funcionales

| Req. | Atributo | Pruebas de la semana 15 | Pruebas anteriores / métrica | Técnicas |
|---|---|---|---|---|
| RNF-01 | Extensibilidad (OCP) | UNI-04, UNI-05 (reglas aisladas) | OCP-01..03, REC-01 | U |
| RNF-02 | Integridad: toda alteración se detecta | **CB-03** (R3 hueco, R4 sello roto, R6 ancla) | INT-05, INT-07, M17; SQA M3 | B, I |
| RNF-03 | Minimización | CN-03 (detalle de 4 KB) | DOM-05 | N |
| RNF-04 | Aislamiento multi-tenant | **CN-05**, UNI-06 | SQA M4 (12 sondas) | U, N |
| RNF-05 | Transacción: nada a medias | CB-01 (C11) | APP-04, CON-*-08, M7 | B |
| RNF-06 | Rendimiento | — | SQA M3 (ms por 1 000), M7 (capacidad, **en rojo**) | — |
| RNF-07 | Valores como parámetros, nunca concatenados | — | PER-02, ARQ-03, M10 | — |
| RNF-08 | Testabilidad sin base | Todas las UNI, CB y CN corren sin base | 99 de 116 sin base | — |
| RNF-09 | Trazabilidad del emisor | CB-01 (C4 devuelve id y sello) | HTTP-01 | B |
| RNF-10 | La corrección no se usa para encubrir | CB-01 (C10 YA_CORREGIDO) | CORR-04; control C-D4 | B |
| RNF-11 | Contrato = código | CN-06 (códigos) | REC-04, API-01..03; SQA M5 | N |
| RNF-12 | Ninguna lectura sin acción del usuario | — | Recorrido S11/S12 (paso 08) | — |
| RNF-13 | Nada de la consulta en la URL | — | Recorrido S11/S12 (R-D1) | — |
| RNF-14 | WCAG 2.2 AA | — | Recorrido S11/S12 (axe) | — |
| RNF-15 | Paridad prototipo ↔ backend | — | `paridad.cjs` S12 | — |

## 3. Cobertura de la matriz

| Medida | Valor |
|---|---|
| RF con al menos una prueba de la semana 15 | 20 de 21 (la que falta, RF-21, es de interfaz y la cubre el recorrido de la semana 12) |
| RNF con al menos una prueba ejecutable | 15 de 15 (RNF-06 por la métrica M7, que hoy está **en rojo**) |
| Técnicas pedidas | Unitarias: 8 (pide ≥ 3) · Caja blanca: 3 (pide ≥ 2) · Caja negra: 7 (pide ≥ 4) |
| Cobertura de líneas de la caja blanca | 100 % de `RegistrarMovimiento`, `VerificarIntegridad` y `CadenaSellada` (33/33) |
| Módulos ajenos | Ninguno probado: RBAC y la identidad del HIS se simulan con la configuración de demostración |
