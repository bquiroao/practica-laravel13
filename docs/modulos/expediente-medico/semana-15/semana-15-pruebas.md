# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra del Expediente médico electrónico base

| | |
|---|---|
| **Tarea Odoo** | 2517 — Semana 15: Pruebas unitarias, caja blanca y caja negra |
| **Flujo** | «Apertura y consulta autorizada del expediente longitudinal» |
| **Objeto de prueba** | [`micro-his-expediente/`](../micro-his-expediente/) de `develop` (semanas 3, 4 y 6), sin modificarlo |
| **Ejecutor** | [pruebas/pruebas-s15.php](pruebas/pruebas-s15.php) · salida: [evidencia/salida-pruebas.txt](evidencia/salida-pruebas.txt) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y entorno

Se prueban las reglas propias del expediente: apertura única para pacientes activos, consulta con asignación de atención activa (el episodio), historia longitudinal con vínculo MPI y consentimiento vigente, auditoría de cada operación y minimización de los datos devueltos. No se prueban el MPI, el registro de pacientes, la sesión ni el RBAC (son de otros módulos): CN-04 solo verifica que el expediente **rechace** a quien no tiene sesión o relación de atención. Las pruebas corren con PHP 8.4.23 sin dependencias. Las de caja negra levantan `php -S` en un puerto libre con dos SQLite temporales nuevos (HOSPITAL y CENTRAL) que se borran al final. Todos los datos son ficticios.

## 2. Matriz RF/RNF → prueba

| Requisito | Unitarias | Caja blanca | Caja negra | Estado |
|---|---|---|---|---|
| RF-01 Un único expediente por paciente activo (R-01 a R-03) | U-01, U-03 | — | CN-01 | ✔ |
| RF-02 Consulta solo con asignación activa (R-05) | U-03 | CB-01 (C1) | CN-02 | ✔ |
| RF-03 Otros hospitales: vínculo MPI y consentimiento vigente (R-06, R-10) | U-02, U-04 | CB-01 (C5 a C7), CB-02 | CN-03 | ✔ |
| RF-04 Acceso de emergencia (R-07) | — | — | — | Sin prueba: no está implementado en el Micro-HIS |
| RF-05 Auditar cada operación, incluidos los rechazos (R-08) | U-05 | CB-01, CB-02 | CN-06, CN-08 | ✔ |
| R-04 El expediente nunca se borra | — | — | CN-05 | ✔ (no hay ruta de borrado) |
| R-08 La auditoría no se puede modificar (H-04 de la S13) | — | — | CN-07 | ✘ DEF-02 |
| R-09 Solo el alcance autorizado (H-03 de la S13) | U-04 (solo hospital y número) | — | — | Parcial: el modelo no tiene alcance; H-03 sigue abierto |
| CP-04 Sesión, rol y relación de atención antes de cada caso de uso (H-02 de la S13) | — | — | CN-04 | ✘ DEF-01 |
| RNF-02 Vencimientos con reloj fijo | U-02 | CB-02 | — | ✔ |
| RNF-04 La auditoría no se omite en ningún camino | U-05 | CB-01, CB-02 | CN-08, CN-09 | ✘ DEF-03 (base caída al arrancar) |
| Un HTTP fijo por cada código de rechazo | U-06 | — | — | ✔ |

## 3. Pruebas unitarias

| ID | Unidad | Casos | Resultado |
|---|---|---|---|
| U-01 | `Paciente::numeroParaAbrir` | Inactivo y fallecido = E2; con expediente = E3; activo sin expediente = `EXP-p1` | ✔ |
| U-02 | `Consentimiento::vigente` | Sin consentimiento; vigencia [100, 200) en 99, 100, 199 y 200; sin fin en `PHP_INT_MAX` | ✔ |
| U-03 | `ServicioExpediente` + InMemory | Sin asignación = E4; asignado sin expediente = NF; abierto, la consulta local solo trae el número | ✔ |
| U-04 | `ServicioExpediente` + doble de CENTRAL | Vinculado con consentimiento = externos (solo hospital y número); sin vínculo = solo local; vencido = E5 | ✔ |
| U-05 | Auditoría | Abrir, abrir otra vez, consultar sin asignación y consultar con CENTRAL caída dejan `ok`, `E3`, `E4` y `E8` | ✔ |
| U-06 | Códigos de rechazo | E2, E3, E4, NF y E5 están en `VistaJson::HTTP` | ✔ |

## 4. Caja blanca

**CB-01 — caminos básicos de `ServicioExpediente::consultar`.** Hay 6 decisiones (asignación, paciente existente, expediente, `!longitudinal`, `!vinculadoMpi` y consentimiento vigente), así que V(G) = 7. Un doble espía de CENTRAL cuenta sus llamadas y se cuentan los eventos de auditoría.

| Camino | Condición que lo fuerza | Esperado | CENTRAL | Eventos |
|---|---|---|---|---|
| C1 | Sin asignación activa | E4 | 0 | 1 |
| C2 | Paciente inexistente (con asignación) | E2 | 0 | 1 |
| C3 | Sin expediente | NF | 0 | 1 |
| C4 | Consulta local | expediente | 0 | 1 |
| C5 | Longitudinal sin vínculo MPI | solo local | 0 | 1 |
| C6 | Consentimiento vencido | E5 | 1 | 1 |
| C7 | Consentimiento vigente | externos | 2 | 1 |

**CB-02 — MC/DC y salidas de la auditoría.** `vigente()` = `c !== null && desde <= ahora && (hasta === null || ahora < hasta)` con 5 casos: nulo, antes del inicio, sin fin, antes del fin y en el fin (F, F, V, V, F). `auditado()` tiene 3 salidas: un rechazo con la auditoría caída sale como E8 (no se pierde en silencio), y la caída de CENTRAL sale como E8 con su evento.

**Sensibilidad.** Dos mutaciones de una línea en una copia temporal, comparando las unitarias y de caja blanca: aceptar el instante de fin del consentimiento (`<=` en vez de `<`) lo detectan U-02, U-04, CB-01 y CB-02, y saltarse el vínculo con el MPI lo detectan U-04 y CB-01.

## 5. Caja negra (HTTP)

| ID | Técnica | Entradas | Esperado | Obtenido |
|---|---|---|---|---|
| CN-01 | Equivalencia de la apertura | activo; el mismo otra vez; inactivo; fallecido; inexistente | 201 `EXP-p1`, 409 E3, 422 E2 ×3 | ✔ |
| CN-02 | Consulta autorizada | asignado; sin asignación; asignación cerrada; asignado sin expediente | 200, 403 E4, 403 E4, 404 NF | ✔ |
| CN-03 | Límites de vigencia | consentimiento vigente; vencido hace 60 s; sin vínculo MPI | 200 con h2 y h3 (no el propio), 403 E5, 200 solo local | ✔ |
| CN-04 | Permisos y sesión | abrir sin `X-Profesional`; abrir sin relación de atención; consultar sin `X-Tenant-ID` | 401, 403, 401 | ✘ 201, 201, 200 |
| CN-05 | Errores del cliente | `DELETE`, `POST .../external`, `/patients/p5`, barra final | 404 ×4 | ✔ |
| CN-06 | Auditoría por HTTP | consulta aceptada, consulta rechazada y apertura rechazada | 3 eventos con su resultado | ✔ |
| CN-07 | Integridad de la auditoría | `UPDATE` de los eventos E4 a `ok` en la base HOSPITAL | rechazado | ✘ 3 filas cambiadas |
| CN-08 | CENTRAL caída | consulta longitudinal | 503 E8 sin detalles, con evento E8 | ✔ |
| CN-09 | Error de infraestructura | La base HOSPITAL no se puede abrir al arrancar | 503 E8, sin detalles | ✘ 200 con el «Fatal error» |

CN-03 usa márgenes de 60 s porque el servidor lee su propio reloj; los límites exactos están en U-02. CN-02 acepta el 403 E4 actual; que E4 no sea el código de la semana 1 ya es H-01 de la S13 y no se registra de nuevo.

## 6. Comandos y resultados

```powershell
php docs/modulos/expediente-medico/semana-15/pruebas/pruebas-s15.php           # 14 de 17 pasan; 3 fallan (exit 1)
php docs/modulos/expediente-medico/micro-his-expediente/pruebas/pruebas.php    # regresión: todo correcto (exit 0)
```

Capturas: [01-pruebas-develop.png](capturas/01-pruebas-develop.png) y [02-regresion-mutaciones.png](capturas/02-regresion-mutaciones.png).

## 7. Defectos registrados

| ID | Prueba | Severidad | Descripción | Causa probable | Propuesta | Estado |
|---|---|---|---|---|---|---|
| DEF-01 | CN-04 | Crítica | Cualquiera abre expedientes sin identificarse o sin relación con el paciente (201), y se consulta sin hospital porque se asume `h1` | `public/index.php` toma el profesional y el hospital de las cabeceras, con `h1` por defecto, y `abrir` no comprueba rol ni relación | Sesión obligatoria (401) y guardia de rol y relación de atención (403) antes de cada caso de uso, sin hospital por defecto | Abierto; confirma H-02 y CP-04 |
| DEF-02 | CN-07 | Mayor | Un `UPDATE` cambió 3 rechazos de la auditoría a «ok» | `audit_events` es una tabla común, sin restricción de solo inserción | Triggers que rechacen `UPDATE` y `DELETE` en `audit_events` (como `cross_hospital_access_audits_immutable`) | Abierto; confirma H-04 |
| DEF-03 | CN-09 | Mayor | Con la base HOSPITAL caída responde 200 y muestra el error de PHP con SQLSTATE y la ruta del servidor; no queda evento | `new PDO` está en `base()` de `public/index.php`, fuera del adaptador que traduce a E8 | Capturar `PDOException` al conectar y responder `VistaJson::noDisponible()` (503 E8) | Abierto; nuevo |

DEF-01 es crítica porque se trata de datos clínicos: abrir el expediente de un paciente sin relación de atención es el acceso que el módulo debe impedir. Los defectos se registran y no se corrigen aquí: corregirlos cambia el Micro-HIS, y eso corresponde a un PR propio con su prueba.

## 8. Bibliografía

Myers, G. J., Sandler, C. y Badgett, T. (2011). *The Art of Software Testing* (3.ª ed.). Wiley · McCabe, T. J. (1976). «A Complexity Measure». *IEEE Transactions on Software Engineering*, SE-2(4) · ISO/IEC/IEEE 29119-4:2021. *Test techniques*.
