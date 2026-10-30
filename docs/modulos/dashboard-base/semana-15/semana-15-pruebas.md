# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra del Dashboard base

| | |
|---|---|
| **Tarea Odoo** | 2442 — Semana 15: Pruebas unitarias, caja blanca y caja negra |
| **Flujo** | «Navegación del dashboard con estados de carga, vacío, error y resumen inicial» |
| **Objeto de prueba** | [`micro-his-dashboard-base/`](../micro-his-dashboard-base/) de `develop` (semanas 3 y 4), sin modificarlo |
| **Ejecutor** | [pruebas/pruebas-s15.php](pruebas/pruebas-s15.php) · salida: [evidencia/salida-pruebas.txt](evidencia/salida-pruebas.txt) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y entorno

Se prueban las reglas propias del dashboard: layout del resumen inicial (menú y tarjetas), navegación por permiso, tarjetas y sus estados de carga, vacío y error, y los códigos que recibe el cliente. No se prueban la sesión ni el RBAC (son de otro módulo), ni los módulos que aportan los conteos (pacientes, citas y camas): CN-04 solo verifica que el dashboard **rechace** un hospital ajeno. El tiempo de espera de 10 s y el botón «Reintentar» viven en el cliente (`verificar.mjs` de la semana 2) y no se repiten. Las pruebas corren con PHP 8.4.23 sin dependencias. Las de caja negra levantan `php -S` en un puerto libre con un SQLite temporal nuevo que se borra al final. Todos los datos son ficticios.

## 2. Matriz RF/RNF → prueba

| Requisito | Unitarias | Caja blanca | Caja negra | Estado |
|---|---|---|---|---|
| RF-01 Conteo en cada tarjeta (R-06) | U-01, U-04 | CB-01 (C5) | CN-01 | ✔ |
| RF-02 «Vacío» con acción sugerida (R-05) | U-01 | CB-01 (C4), CB-02 | CN-01 | ✔ |
| RF-03 «Error» ante un 5xx (R-04, R-08) | U-01 | CB-01 (C3) | CN-06 | ✔ en el servidor; los 10 s son del cliente |
| RF-04 Sin sesión u hospital = 401 (R-01, R-07) | U-03 | CB-01 (C1), CB-02 | CN-02 | ✔ |
| RF-05 403 oculta la tarjeta (R-07) | — | CB-01 (C2) | CN-03, CN-05 | ✘ DEF-02 (una tarjeta inexistente también da 403) |
| RF-06 Cada tarjeta carga y falla por su cuenta (R-03) | U-04 | — | CN-06 | ✔ |
| R-02 Menú y tarjetas solo con permiso (layout) | U-02, U-03 | CB-02 | CN-03 | ✔ |
| CU-03 Navegación con E6 y NF (semana 6, PR #446) | U-05 | — | — | Pendiente: pasa con el código de la S6 |
| RNF-01 Ningún texto con códigos o errores técnicos (R-09) | U-01 | — | CN-06, CN-07 | ✘ DEF-03 |
| RNF-05 Las tarjetas solo muestran conteos | U-04 | — | — | ✔ |
| Hospital y permisos desde la sesión (CP-04 de la S14, H-01 de la S13) | — | — | CN-04 | ✘ DEF-01 |
| Un HTTP fijo por cada código de rechazo | U-06 | — | — | ✔ |

## 3. Pruebas unitarias

| ID | Unidad | Casos | Resultado |
|---|---|---|---|
| U-01 | `EstadoTarjeta`, `Tarjeta::texto` | 0 = vacío con sugerencia; 1 = «1 paciente activo»; 2 = «2 citas hoy»; cargando; error sin detalles | ✔ |
| U-02 | `Catalogo::menu` y `tarjetas` | Sin permisos nada; `citas.ver` solo citas; `CITAS.VER` o `usuarios.ver` nada; los 3 permisos en cualquier orden dan el orden fijo | ✔ |
| U-03 | `ServicioDashboard::inicio` | Hospital vacío o «   » = E1; con hospital, el menú del rol y las tarjetas en «cargando» | ✔ |
| U-04 | `ServicioDashboard::tarjeta` + doble | Citas falla y queda en error; pacientes (3) y camas (0) siguen; cada vista solo lleva `id`, `estado` y `texto` | ✔ |
| U-05 | `Catalogo::destino` (S6) | `/citas` con permiso; sin permiso = E6; `/usuarios` = NF | Pendiente en `develop`; ✔ contra la S6 |
| U-06 | Códigos de rechazo | Los códigos que lanzan dominio y aplicación (E1, E4) están en `VistaJson::HTTP` | ✔ |

## 4. Caja blanca

**CB-01 — caminos básicos de `ServicioDashboard::tarjeta`.** El flujo tiene 4 decisiones: hospital vacío (en `exigirSesion`), tarjeta permitida (`?? throw`), fallo de la fuente (`catch`) y conteo cero (en `deConteo`), así que V(G) = 5. Un doble espía cuenta las lecturas de la base.

| Camino | Condición que lo fuerza | Esperado | Lecturas |
|---|---|---|---|
| C1 | Sin hospital | E1 | 0 |
| C2 | Tarjeta sin permiso | E4 | 0 |
| C3 | La fuente lanza `ErrorPersistencia` | error | 1 |
| C4 | Conteo 0 | vacío | 1 |
| C5 | Conteo 4 | resumen | 1 |

**CB-02 — decisiones y condiciones.** `Tarjeta::texto`: las 4 ramas del `match` y las 2 del singular (`$total === 1` con 1 y con 0). `exigirSesion`: `trim($hospital) === ''` con « h1 » (falso: entra). El filtro de permisos usa `in_array` estricto: `citas.ver ` con espacio no da acceso.

**Sensibilidad.** Dos mutaciones de una línea en una copia temporal, comparando las unitarias y de caja blanca: que `deConteo` nunca dé vacío lo detectan U-01, U-04 y CB-01, y que `tarjeta` ignore el permiso lo detecta CB-01.

## 5. Caja negra (HTTP)

| ID | Técnica | Entradas | Esperado | Obtenido |
|---|---|---|---|---|
| CN-01 | Equivalencia del conteo | 1 paciente activo y 1 inactivo; 2 citas hoy, 1 cancelada y 1 mañana; 0 camas disponibles | 200 «1 paciente activo», 200 «2 citas hoy», 200 vacío | ✔ |
| CN-02 | Límites de sesión y permisos | sin `X-Tenant-ID`; « »; sin permisos; `camas.ver,`; `CITAS_HOY` | 401 E1, 401 E1, 200 con 0 tarjetas, 200 con 1, 404 | ✔ |
| CN-03 | Permisos del rol | tarjeta de citas solo con `camas.ver`; menú con `camas.ver` | 403 E4; menú `/camas` | ✔ |
| CN-04 | Tenant | `X-Tenant-ID: hospital-ajeno` en el resumen y en la tarjeta | 403, 403 | ✘ 200, 200 |
| CN-05 | Errores del cliente | tarjeta `usuarios`; `/dashboard/otra`; `POST /dashboard` | 404, 404, 404 | ✘ 403 E4, 404, 404 |
| CN-06 | Falla de una tarjeta | La consulta de camas falla; pacientes sigue | 503 E5 con texto amable; 200; sin SQLSTATE | ✔ |
| CN-07 | Error de infraestructura | La base no se puede abrir al arrancar | 503 E5, sin detalles | ✘ 200 con el «Fatal error» |

## 6. Comandos y resultados

```powershell
php docs/modulos/dashboard-base/semana-15/pruebas/pruebas-s15.php              # 11 de 14 pasan; U-05 pendiente; 3 fallan (exit 1)
php docs/modulos/dashboard-base/micro-his-dashboard-base/pruebas/pruebas.php   # regresión: todo correcto (exit 0)
```

Con el Micro-HIS de la S6 (PR #446, sin integrar), en una copia temporal, pasan 12 de 15: U-05 pasa y siguen las mismas 3 fallas. Capturas: [01-pruebas-develop.png](capturas/01-pruebas-develop.png) y [02-regresion-s6-mutaciones.png](capturas/02-regresion-s6-mutaciones.png).

## 7. Defectos registrados

| ID | Prueba | Severidad | Descripción | Causa probable | Propuesta | Estado |
|---|---|---|---|---|---|---|
| DEF-01 | CN-04 | Mayor | Con un hospital al que el usuario no pertenece, el resumen y las tarjetas responden 200 | `public/index.php` toma `X-Tenant-ID` y `X-Permisos` de las cabeceras del cliente; solo se comprueba que el hospital no esté vacío | Tomar hospital y permisos de la sesión y del RBAC, y rechazar (403) un hospital sin membresía antes de cada caso de uso (CU-05) | Abierto; confirma H-01 y CP-04 |
| DEF-02 | CN-05 | Menor | Una tarjeta que no existe responde 403 E4, y por R-07 el cliente la oculta como si fuera falta de permiso | `Catalogo::tarjetas($permisos)[$id] ?? E4` junta «no existe» y «sin permiso» | Comprobar primero si la tarjeta existe (NF, 404) y después el permiso (E4, 403) | Abierto; confirma H-03 |
| DEF-03 | CN-07 | Mayor | Con la base caída responde 200 y muestra el error de PHP con SQLSTATE y la ruta del servidor | `new PDO` está en `public/index.php`, fuera del adaptador que traduce a `ErrorPersistencia` | Capturar `PDOException` al conectar y responder cada tarjeta en «error» (503 E5), como pide R-03 | Abierto; nuevo |

Los defectos se registran y no se corrigen aquí: corregirlos cambia el Micro-HIS, y eso corresponde a un PR propio con su prueba.

## 8. Bibliografía

Myers, G. J., Sandler, C. y Badgett, T. (2011). *The Art of Software Testing* (3.ª ed.). Wiley · McCabe, T. J. (1976). «A Complexity Measure». *IEEE Transactions on Software Engineering*, SE-2(4) · ISO/IEC/IEEE 29119-4:2021. *Test techniques*.
