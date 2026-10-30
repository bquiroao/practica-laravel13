# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra del Catálogo de salas, wards y camas

| | |
|---|---|
| **Tarea Odoo** | 2457 — Semana 15: Pruebas unitarias, caja blanca y caja negra |
| **Flujo** | «Creación de sala y cama con control de estado operativo» |
| **Objeto de prueba** | [`micro-his-catalogo-camas/`](../micro-his-catalogo-camas/) de `develop` (semanas 3 y 4), sin modificarlo |
| **Ejecutor** | [pruebas/pruebas-s15.php](pruebas/pruebas-s15.php) · salida: [evidencia/salida-pruebas.txt](evidencia/salida-pruebas.txt) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y entorno

Se prueban las reglas propias del catálogo: salas, camas, estados operativos, capacidad y consistencia. No se prueban la autenticación ni el RBAC (son de otro módulo), ni la auditoría central. CN-04 solo verifica que el catálogo **rechace** a quien no tiene permiso. Las pruebas se ejecutan con PHP 8.4.23 sin dependencias. Las de caja negra levantan `php -S` en un puerto libre, con un SQLite temporal nuevo que se borra al final. Todos los datos son ficticios.

## 2. Matriz RF/RNF → prueba

| Requisito | Unitarias | Caja blanca | Caja negra | Estado |
|---|---|---|---|---|
| RF-01 Sala con código único (R-01) | — | CB-02 | CN-02 | ✘ DEF-01 |
| RF-02 Cama única en sala activa, nace disponible (R-02–R-04) | U-02 | CB-02 | — | ✔ |
| RF-03 Cambio de estado con motivo (R-09) | U-03 | CB-01 (C2) | CN-03 | ✔ |
| RF-04 Transiciones permitidas (R-05) | U-01 | CB-01 (C4) | CN-01 | ✔ |
| RF-05 Cama ocupada no cambia a mano (R-06) | U-01 | CB-01 (C4) | CN-01 | ✔ |
| RF-06 Auditoría de altas y cambios | U-03 | CB-01 (C5, C6) | — | Parcial: los cambios quedan en el historial; las altas y el «quién» (H-02 de la S13) son de la auditoría central |
| RNF-01 Permiso y hospital (R-07, CP-04 de la S14) | — | — | CN-04 | ✘ DEF-02 |
| RNF-03 Reglas sin base de datos | U-01 | — | — | ✔ |
| RNF-04 Un código y un HTTP fijo por error | U-04 | — | CN-05, CN-06 | ✘ DEF-03 |
| Capacidad de sala (semana 6, PR #433) | U-05 | — | — | Pendiente: pasa con el código de la S6 |
| Concurrencia (E7) | — | CB-01 (C5) | — | ✔ |

## 3. Pruebas unitarias

| ID | Unidad | Casos | Resultado |
|---|---|---|---|
| U-01 | `EstadoCama::validarCambioA` | Los 16 pares de la matriz 4 × 4: 5 permitidos, 4 «E5» desde ocupada y 7 «E6» | ✔ |
| U-02 | `ServicioCatalogo` + InMemory | Cama nueva «available»; «101» en la sala B se admite, y en la A se repite (E3) | ✔ |
| U-03 | `ServicioCatalogo` + InMemory | Motivo «   » = E2; la cama sigue igual y el historial queda vacío | ✔ |
| U-04 | Códigos de rechazo | Los 7 códigos que lanzan dominio y aplicación están en `VistaJson::HTTP` | ✔ |
| U-05 | `CapacidadSala` (S6) | 0 y «2» = E2; 1 de 2 admite; 2 de 2 = E9; sin tope admite | Pendiente en `develop`; ✔ contra la S6 |

## 4. Caja blanca

**CB-01 — caminos básicos de `ServicioCatalogo::cambiarEstado`.** El método tiene 4 decisiones propias, así que V(G) = 5. A eso se suma la salida por la excepción del dominio: 6 caminos. Un doble espía cuenta las escrituras en el repositorio.

| Camino | Condición que lo fuerza | Esperado | Escrituras |
|---|---|---|---|
| C1 | La cama no existe | NF | 0 |
| C2 | Motivo vacío | E2 | 0 |
| C3 | Estado «borrada» | E2 | 0 |
| C4 | Cama ocupada → disponible | E5 | 0 |
| C5 | Otro usuario la cambió antes (`false`) | E7 | 1 |
| C6 | Disponible → mantenimiento | ok | 1 |

**CB-02 — condiciones.** `registrarSala`: MC/DC de `trim(código) === '' || trim(nombre) === ''` con (V, –), (F, V) y (F, F). `registrarCama`: el orden de las decisiones es sala → código → duplicado. Una sala inexistente con código vacío da E4, no E2, y la sala inactiva también da E4 (la condición es `!== true`). Solo los dos caminos válidos escriben.

**Sensibilidad.** Se corrieron dos mutaciones de una línea en una copia temporal. Permitir inactiva → mantenimiento la detecta U-01, e ignorar el `false` del repositorio la detecta CB-01.

## 5. Caja negra (HTTP)

| ID | Técnica | Entradas | Esperado | Obtenido |
|---|---|---|---|---|
| CN-01 | Equivalencia del estado | `maintenance` (válida); `occupied` a mano, `AVAILABLE`, vacío, `borrada` | 200; 422 E6, 422 E2 ×3 | ✔ |
| CN-02 | Límites del código de sala | vacío, « », «B», « A» con la A existente | 422 E2, 422 E2, 201, 409 E3 | ✘ « A» = 201 |
| CN-03 | Límites del motivo | vacío, «   », «x» | 422 E2, 422 E2, 200 | ✔ |
| CN-04 | Permisos | sin `X-Tenant-ID`; `X-Tenant-ID: hospital-ajeno` | 403, 403 | ✘ 201, 201 |
| CN-05 | Errores del cliente | `GET /salas`, cama 999, cuerpo que no es JSON | 404, 404 NF, 422 E2 | ✔ |
| CN-06 | Error de infraestructura | La base no se puede abrir al arrancar | 503 E8, sin detalles | ✘ 200 con el «Fatal error» |

## 6. Comandos y resultados

```powershell
php docs/modulos/catalogo-salas-camas/semana-15/pruebas/pruebas-s15.php            # 9 de 12 pasan; 3 fallan (exit 1)
php docs/modulos/catalogo-salas-camas/micro-his-catalogo-camas/pruebas/pruebas.php  # regresión: todo correcto (exit 0)
```

Con el Micro-HIS de la S6, en una copia temporal, pasan 10 de 13: U-05 pasa y siguen las mismas 3 fallas. Capturas: [01-pruebas-develop.png](capturas/01-pruebas-develop.png) y [02-regresion-s6-mutaciones.png](capturas/02-regresion-s6-mutaciones.png).

## 7. Defectos registrados

| ID | Prueba | Severidad | Descripción | Causa probable | Propuesta | Estado |
|---|---|---|---|---|---|---|
| DEF-01 | CN-02 | Mayor | «A» y « A» se registran como dos salas (201 y 201) | `registrarSala` valida con `trim()` pero guarda el código sin normalizar | Guardar `trim($sala)` y `trim($cama)`, y agregar el caso a `pruebas.php` | Abierto; confirma H-03 de la S13 |
| DEF-02 | CN-04 | Crítica | Cualquiera registra salas sin permiso ni hospital (201) | `atender()` no recibe usuario, permiso ni tenant | Guardia de autorización (E1, 403) antes de cada caso de uso, con el RBAC como dependencia | Abierto; confirma H-01 y CP-04 |
| DEF-03 | CN-06 | Mayor | Con la base caída responde 200 y muestra el error de PHP con SQLSTATE y la ruta del servidor | `new PDO` está en `public/index.php`, fuera del adaptador que traduce a E8 | Capturar `PDOException` al conectar y responder `VistaJson::noDisponible()` | Abierto; nuevo |

Los defectos se registran y no se corrigen aquí: corregirlos cambia el Micro-HIS, y eso corresponde a un PR propio con su prueba.

## 8. Bibliografía

Myers, G. J., Sandler, C. y Badgett, T. (2011). *The Art of Software Testing* (3.ª ed.). Wiley · McCabe, T. J. (1976). «A Complexity Measure». *IEEE Transactions on Software Engineering*, SE-2(4) · ISO/IEC/IEEE 29119-4:2021. *Test techniques*.
