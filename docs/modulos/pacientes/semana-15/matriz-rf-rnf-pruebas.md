# Matriz RF/RNF → prueba — Pacientes

**Proceso:** «registro, búsqueda y actualización segura de un paciente»

**Cómo leer la matriz.** Las pruebas **nuevas** de la semana 15 van en negrita:

| Prefijo | Tipo | Archivo |
|---|---|---|
| UNI | Unitaria | `tests/Dominio/UnitariasReglasPrueba.php` |
| CB | Caja blanca | `tests/Aplicacion/CajaBlancaPrueba.php` |
| CN | Caja negra | `tests/Presentacion/CajaNegraPrueba.php` |
| DEF | Defecto abierto | `tests/Defectos/DefectosPrueba.php` |

Las demás ya existían (S3–S12) y siguen corriendo en la misma suite.

**Comando:**

```bash
php tests/ejecutar.php
```

Se ejecuta en `docs/modulos/pacientes/micro-his-pacientes/`. Resultado: **108 pruebas: 106 pasan, 0 fallan y 2 se omiten** (requieren PostgreSQL).

Los defectos abiertos se ven con `php tests/ejecutar.php --defectos --solo=DEF`: hoy fallan los 6.

**Origen de los requisitos:**
- RF-01..RF-11 y RNF-01..RNF-09: [semana 2](../semana-02/semana-02-lsp-requerimientos-diseno.md).
- RF-12..RF-16 y RNF-10: [semana 6](../semana-06/registro-cambio-practico.md).
- RF-17..RF-19 y RNF-11: [semana 12](../semana-12/registro-cambio-solicitado.md).
- RNF-12..RNF-15: [semana 12](../semana-12/matriz-decision-rf-rnf-evidencia.md).

## 1. Requisitos funcionales

| RF | Requisito (resumen) | Unitarias | Caja blanca | Caja negra | Otras existentes | Defectos |
|---|---|---|---|---|---|---|
| RF-01 | Registrar con datos válidos (R-01) y documento opcional; código único y versión 1 | **UNI-01**, **UNI-02**, **UNI-03**, **UNI-04** | **CB-01** (C1, C6) | **CN-01** (V1–V3, I1–I5), **CN-03** (límites) | DOM-01, DOM-02, APP-01, HTTP-01 | **DEF-05**, **DEF-06**, OBS-01, OBS-03 |
| RF-02 | Documento ya registrado → rechazo con el código y el documento enmascarado | **UNI-09** | — | **CN-01** (I6) | DOM-06, APP-02, HTTP-03 | **DEF-01** |
| RF-03 | Coincidencia probable: advertir; solo supervisión confirma, con motivo | **UNI-09** | — | **CN-01** (I7), **CN-05** (columna «confirmar») | DOM-06 | **DEF-02**, OBS-02 |
| RF-04 | Paciente sin documento = identidad provisional, sin ser duplicado de otros provisionales | **UNI-03**, **UNI-09** | **CB-03** (C con null) | **CN-01** (V3), **CN-02** (E7) | — | — |
| RF-05 | Buscar por código, documento o nombre (≥ 3 letras) + nacimiento; hospital activo; máximo 20; datos mínimos | **UNI-06**, **UNI-07** | **CB-03** (`coincideCon`, MC/DC) | **CN-02** (B1–B6), **CN-04** (2/3 letras, 20/21 resultados, formatos de fecha) | DOM-05, HTTP-04, PER-02 | **DEF-04** |
| RF-06 | Detalle con versión; otro hospital = el mismo 404 que uno inexistente | **UNI-08** | — | **CN-05** (fila «otro hospital»), **CN-06** (404 idéntico) | APP-05, HTTP-05 | — |
| RF-07 | Versión vieja → 409 sin modificar | — | **CB-04** (S5) | **CN-04** (If-Match vigente ± 1; 9/10 dígitos), **CN-06** (428, 400) | APP-03, HTTP-06, PER-05 | — |
| RF-08 | Motivo para cambiar identidad; documento de otro → rechazo | **UNI-05**, **UNI-10** | **CB-02** (R5, R8, R10), **CB-03** (A ∨ B ∨ C), **CB-04** (S4) | **CN-02** (E2–E4) | DOM-07, DOM-08, APP-04 | — |
| RF-09 | Fallecidos e inactivos: solo supervisión | **UNI-10** | **CB-02** (R6: antes que la protección) | — | DOM-07, APP-04 | — |
| RF-10 | Eventos de auditoría; cambio y evento en la misma transacción | **UNI-06** (resumen sin datos) | **CB-01** (C7 rollback), **CB-04** (S6 rollback, S7 antes/después) | **CN-07** (paso 10) | APP-01, APP-07, REF-03, M3/M6 (S14) | — |
| RF-11 | Hospital y permiso por operación | — | **CB-01** (C2: permiso antes que todo), **CB-04** (S1: antes de cargar) | **CN-05** (tabla de 41 celdas), **CN-06** (sin hospital) | APP-05, HTTP-02 | **DEF-03** |
| RF-12 | Proteger exige `pacientes.proteger` y motivo | **UNI-10** | — | **CN-05** (columna «proteger»), **CN-07** (paso 1) | PROT-01, DOM-09 | — |
| RF-13 | Por nombre no aparecen protegidos sin permiso; por código o documento, reducidos | **UNI-07** | **CB-03** (P y Q) | **CN-07** (pasos 2, 3 y 9) | PROT-02 | **DEF-04** (conflicto con RNF-10) |
| RF-14 | Detalle reducido de un protegido | **UNI-08** | — | **CN-07** (paso 4) | PROT-03 | — |
| RF-15 | Un protegido solo lo edita quien lo ve; cambiar la protección exige permiso y motivo; ausente = sin cambio | **UNI-10** | **CB-02** (R1, R3, R7) | **CN-07** (pasos 6–8) | PROT-04, REF-01 | — |
| RF-16 | Duplicados de un protegido sin revelar su código | **UNI-09** | — | **CN-07** (paso 5) | PROT-05 | — |
| RF-17 | `PUT` sin `documento` → el documento no cambia | — | **CB-02** (R4: sin consultar el índice) | **CN-02** (E5) | B4-01 | — |
| RF-18 | `documento: null` lo quita, como cambio de identidad | **UNI-05** | **CB-02** (R5) | **CN-02** (E6, E7) | B4-02 | — |
| RF-19 | `nombres` y `apellidos` por separado; `null` en la vista reducida | **UNI-08** | — | **CN-07** (paso 4) | B4-03 | — |

## 2. Requisitos no funcionales

| RNF | Requisito | Pruebas de esta semana | Otras existentes / comentario |
|---|---|---|---|
| RNF-01 | Sustituibilidad (LSP) de los identificadores | **UNI-01**, **UNI-02**, **UNI-03** (DPI y pasaporte por el mismo contrato) | DOM-04, contratos de repositorio |
| RNF-02 | Seguridad de tipos en la entrada | **CN-01** (I8–I10: 400 por tipos) | MVC-01, MVC-04 |
| RNF-03 | Confidencialidad del documento | **UNI-08** (ninguna vista lleva el DPI en claro), **UNI-01** (enmascarado) | PER-07, OBS-02. Fuga entre hospitales: **DEF-01** |
| RNF-04 | Integridad (sin duplicados ni escrituras a medias) | **CB-01** (C4, C5, C7), **CB-04** (S5, S6), **UNI-09** | IDEM-01..04, APP-07, M4 (S14) |
| RNF-05 | Minimización de datos | **UNI-06** (resumen auditable), **UNI-08**, **CN-05** (cada rechazo trae solo `error`) | HTTP-04 |
| RNF-06 | Disponibilidad de lectura | **CN-06** (503 genérico) | APP-08, CLI-06 |
| RNF-07 | Aislamiento multi-hospital | **CN-05** (filas «otro hospital» y «cabecera de norte»), **CN-06** | APP-05, HTTP-05. Fugas abiertas: **DEF-01**, **DEF-02** |
| RNF-08 | Testabilidad | Las 21 pruebas nuevas usan dobles en memoria: **ninguna necesita red ni base de datos** | — |
| RNF-09 | Rendimiento | — | Sin RNF de tiempo propio (S14 §5). **Pendiente** con PostgreSQL. |
| RNF-10 | Ninguna respuesta revela a un protegido | **UNI-08**, **CB-02** (R1: 403 antes que 422), **CN-07** | PROT-06, M5 (S14). Caso residual: **DEF-04** |
| RNF-11 | Editar con solo lo que devuelve el detalle | **CN-02** (E5) | B4-04 |
| RNF-12, RNF-13 | Accesibilidad y movilidad | — (fuera del alcance: son de la interfaz) | `recorrido.cjs`, `verificar-responsive.cjs` (S10–S12) |
| RNF-14 | Conexión limitada: reintentar no duplica | **CB-01** (C3–C5, C7), **CN-04** (Idempotency-Key 7/8/64/65) | IDEM-01..04, CLI-03, CLI-07 |
| RNF-15 | Privacidad en el cliente | — (fuera del alcance: es del prototipo) | `recorrido.cjs` (URL sin datos personales) |

## 3. Cobertura de la matriz

| | Total | Con prueba nueva (S15) | Solo con pruebas anteriores | Sin prueba ejecutada |
|---|---:|---:|---:|---:|
| RF | 19 | **19** | 0 | 0 |
| RNF del backend (01–11, 14) | 12 | **11** | 0 | 1 (RNF-09) |
| RNF de la interfaz (12, 13, 15) | 3 | 0 | 3 | 0 |

**Lo que la matriz deja ver.**
- Ningún RF depende de un solo tipo de prueba: cada uno de los 19 tiene pruebas nuevas de **al menos dos** de los tres tipos.
- RF-09 no tiene caso de caja negra. Se cubre con UNI-10 y CB-02, y con DOM-07 y APP-04 de semanas anteriores.
- Los defectos se concentran en dos zonas:
  - **aislamiento entre hospitales** (RF-02, RF-03, RNF-07);
  - **validación de la identidad** (RF-01).
- La confidencialidad entre roles de un mismo hospital, en cambio, no tiene defectos abiertos.
