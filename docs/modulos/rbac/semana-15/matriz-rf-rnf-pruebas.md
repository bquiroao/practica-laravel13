# Matriz RF/RNF → prueba — RBAC

**Proceso:** «asignación de rol, autorización de una operación y denegación trazable»

**Cómo leer la matriz.** Las pruebas **nuevas** de la semana 15 van en negrita:

| Prefijo | Tipo |
|---|---|
| UNI | Unitaria |
| CB | Caja blanca |
| CN | Caja negra |
| DEF | Defecto abierto |

Las demás ya existían (S3–S12) y siguen corriendo en la misma suite.

**Comando:**

```bash
php tests/ejecutar.php
```

Se ejecuta en `docs/modulos/rbac/micro-his-rbac/`. Resultado: **99 pruebas: 97 pasan, 0 fallan y 2 se omiten** (PostgreSQL).

Los defectos abiertos se ven con `php tests/ejecutar.php --defectos --solo=DEF`: hoy fallan los 5.

## 1. Requisitos funcionales

| RF | Requisito (resumen) | Unitarias | Caja blanca | Caja negra | Otras existentes | Defectos |
|---|---|---|---|---|---|---|
| RF-01 | El administrador asigna un rol del catálogo a una persona de su hospital | **UNI-04**, **UNI-07** | — | **CN-01** (clase válida) | APP-01, HTTP-01 | **DEF-01** |
| RF-02 | Asignar exige `roles.asignar` | — | **CB-04** (403 antes del controlador) | **CN-04** (columna «asignar») | APP-04, HTTP-03 | — |
| RF-03 | Validar usuario activo, rol del catálogo, sin duplicados ni autoasignación | **UNI-06**, **UNI-07** | — | **CN-01** (6 clases inválidas) | DOM-04, APP-05 | **DEF-01**, **DEF-02** |
| RF-04 | `ROL_ASIGNADO` en la misma transacción que la membresía | — | — | — | PER-04, PER-06, REV-05, M5 (S14) | — |
| RF-05 | Autorizar cada operación por su permiso | **UNI-03** | **CB-01** (C1, C3) | **CN-04** (35 celdas) | APP-02, APP-03, HTTP-04 | — |
| RF-06 | Denegar si el hospital del token ≠ el de la solicitud | **UNI-01** | — | **CN-04** (fila `admin-norte`), **CN-05** | HTTP-07, PEF-05 | **DEF-05** |
| RF-07 | Solo cuentan membresías `active` dentro de su vigencia | **UNI-02** (límites) | **CB-03** (MC/DC) | **CN-04** (filas pedro, lucía, nuevo) | DOM-01, REV-02 | — |
| RF-08 | 403 con `correlation_id` y mensaje genérico | — | **CB-04** | **CN-04** (cuerpo = `mensaje` + `correlation_id`), **CN-06** | HTTP-03, MVC-02 | — |
| RF-09 | Toda denegación registra `ACCESO_DENEGADO` con los 8 datos | **UNI-08** | **CB-01** (C2–C5) | **CN-04** (motivo en la bitácora) | APP-03, PER-09 | **DEF-04** |
| RF-10 | El auditor localiza un evento por `correlation_id` | — | — | **CN-03** (referencia de 1/64/65), **CN-05** (otro hospital), **CN-06** | HTTP-06, APP-06 | — |
| RF-11 | Revocar con motivo | — | — | **CN-02**, **CN-06** | REV-01 | — |
| RF-12 | Tras revocar, la operación se deniega y se audita | — | — | **CN-06** | REV-02 | — |
| RF-13 | Sin `roles.revocar`, la revocación se deniega | — | **CB-04** | **CN-04** (columna «revocar») | REV-03 | — |
| RF-14 | Reglas de revocación: autorrevocación, motivo ≥ 10, membresía revocable | **UNI-05**, **UNI-06** | — | **CN-02** (9/10/500/501) | REV-04, REV-06 | — |
| RF-15 | Permisos efectivos de la sesión (S12) | — | — | **CN-03** (listas de 0/1/20/21) | PEF-01..10 | **DEF-05** |

## 2. Requisitos no funcionales

| RNF | Requisito | Pruebas de esta semana | Otras existentes / comentario |
|---|---|---|---|
| RNF-01 | Fail-closed | **CB-01** (C2, C5), **CB-02** (sin reglas, regla que lanza), **CB-04** (500 ante fallas del controlador) | DOM-03, PER-10, M5 (S14) |
| RNF-02 | Disponibilidad de la evidencia (respaldo) | **CB-01** (C4) | PER-09. Falta la falla doble: **DEF-04**. |
| RNF-03 | Bitácora append-only | — | Puerto con solo `registrar()` (revisión) + trigger SQL. INT-03 omitida: requiere PostgreSQL. |
| RNF-04 | El 403 no revela rol, permiso ni motivo | **CN-04**, **CN-05** (404 idéntico entre hospitales), **CB-04** (500 sin el mensaje de la excepción) | HTTP-03, REV-06 |
| RNF-05 | Extensibilidad (OCP) | **UNI-09** (orden y corto circuito), **CB-02** | OCP-01..03 (S2) |
| RNF-06 | Mantenibilidad | — | ARQ-01..08, `bin/acoplamiento.php` |
| RNF-07 | Testabilidad | Todas las pruebas nuevas usan los puertos con dobles: ninguna necesita red ni base de datos | — |
| RNF-08 | Aislamiento multi-tenant | **UNI-01**, **CN-04**, **CN-05** | HTTP-06, HTTP-07 |
| RNF-09 | Decisión < 50 ms p95 | — | M7 (S14): 0.008 ms en memoria. **Pendiente** con PostgreSQL. |
| RNF-10..13 | UX, accesibilidad, movilidad, conexión limitada | — (fuera del alcance de esta semana) | `recorrido.cjs` y `verificar-responsive.cjs` (S10–S12) |
| RNF-14 | La bitácora registra intentos, no consultas | — | PEF-02, PEF-10. Caso residual: **DEF-05**. |

## 3. Totales

| Tipo | Pedido | Hecho | IDs |
|---|---:|---:|---|
| Unitarias | ≥ 3 | **9** | UNI-01..UNI-09 |
| Caja blanca | ≥ 2 | **4** | CB-01..CB-04, con cobertura de líneas medida (98.7 %) |
| Caja negra | ≥ 4 | **6** | CN-01..CN-06: equivalencia, límites, permisos, errores y trazabilidad |
| Defectos | — | **5 abiertos + 1 observación** | DEF-01..DEF-05 ([registro](registro-defectos.md)) |

**Cobertura de la matriz:**

| Requisitos | Cuántos tienen prueba esta semana |
|---|---|
| RF-01..RF-15 | 14 de 15. RF-04 ya estaba cubierto por pruebas de transacción anteriores. |
| RNF de comportamiento (01, 02, 04, 05, 07, 08) | 6 de 6. Los RNF 03, 06, 09 y 10–14 se cubren con otras herramientas o quedan pendientes, según la tabla. |
