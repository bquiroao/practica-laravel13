# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra de Contratos API (centro de documentación por rol)

| | |
|---|---|
| **Tarea Odoo** | 2487 — Semana 15: Pruebas unitarias, caja blanca y caja negra |
| **Flujo** | «Publicación y consulta de documentación y manuales diferenciados por rol» |
| **Objeto de prueba** | [`micro-his-contratos-api/`](../micro-his-contratos-api/) de `develop` (semanas 3 y 4), sin modificarlo |
| **Ejecutor** | [pruebas/pruebas-s15.php](pruebas/pruebas-s15.php) · salida: [evidencia/salida-pruebas.txt](evidencia/salida-pruebas.txt) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y entorno

Se prueban las reglas propias del centro de documentación: metadatos de cada versión (tipo OpenAPI, Postman o manual), versionado semántico, revisión con separación de funciones, audiencia por rol, obsolescencia y códigos de error. No se prueban la sesión ni el RBAC (son de otro módulo): CN-04 solo verifica que el centro **rechace** a quien no tiene identidad o permiso. RNF-02 y RNF-03 siguen cubiertos por `verificar.php` de la semana 2 y no se repiten. Las pruebas corren con PHP 8.4.23 sin dependencias. Las de caja negra levantan `php -S` en un puerto libre con un SQLite temporal nuevo que se borra al final. Todos los datos son ficticios.

## 2. Matriz RF/RNF → prueba

| Requisito | Unitarias | Caja blanca | Caja negra | Estado |
|---|---|---|---|---|
| RF-01 Tipo, versión `x.y.z` y audiencia (R-01) | U-02 | — | CN-01 | ✔ |
| RF-02 Versión mayor que la vigente (R-02) | U-02, U-05 | — | CN-02 | ✘ DEF-01 (envíos simultáneos) |
| RF-03 Aprobar o rechazar; el autor no aprueba lo suyo (R-05) | — | CB-01, CB-02 | CN-02 | ✔ |
| RF-04 Consulta solo dentro de la audiencia (R-06) | U-03, U-04 | — | CN-03 | ✔ |
| RF-05 404 fuera de audiencia o inexistente (R-07) | U-04 | — | CN-03, CN-05 | ✔ |
| RF-06 Obsoleta sin borrar y con aviso (R-08) | U-01, U-04 | CB-02 | — | ✔ |
| R-03 Una versión publicada no se edita | U-01 | — | — | ✔ |
| RNF-01 Identidad y roles desde la sesión (H-01 de la S13, CP-04 de la S14) | — | — | CN-04 | ✘ DEF-02 |
| RNF-04 El 404 no revela si existe | U-04 | — | CN-03 (mismo cuerpo) | ✔ |
| RNF-05 Las versiones nunca se borran | U-04 | — | — | ✔ |
| Un HTTP fijo por error; E8 sin detalles técnicos | U-06 | — | CN-05, CN-06 | ✘ DEF-03 |
| Concurrencia de la revisión (E7) | — | CB-01 (C4) | CN-02 | ✔ |
| Endpoints frente al contrato OpenAPI (semana 5) | — | — | `verificar-contrato.php` (18 de 18) | ✔ (regresión) |
| R-04 y R-09: lint del contenido, Postman contra OpenAPI y ejemplos ficticios (CP-05 de la S14) | — | — | — | Sin prueba: el Micro-HIS guarda metadatos, no el contenido |

## 3. Pruebas unitarias

| ID | Unidad | Casos | Resultado |
|---|---|---|---|
| U-01 | `EstadoVersion::validarCambioA` | Los 16 pares de la matriz 4 × 4: 3 permitidos y 13 «E7» | ✔ |
| U-02 | `VersionNueva::validar` | `swagger`, `1.1`, `1.1.0-beta`, audiencia «  », slug vacío = E2; `1.0.9` y `1.0.8` = E3; `postman` `1.0.10` > `1.0.9` (orden por versión, no por texto) | ✔ |
| U-03 | `Audiencia::permite` | Sin roles, sin rol común = no; un rol común = sí | ✔ |
| U-04 | `ServicioConsulta` + InMemory | Vigente = 1.10.0 (no 1.9.0); la obsoleta avisa; rol ajeno e inexistente dan el mismo E6 | ✔ |
| U-05 | `RepositorioPdo` (SQLite en memoria) | Segundo envío de la 2.0.0 que ya pasó la validación: esperado E3 | ✘ obtiene E8 |
| U-06 | Códigos de rechazo | Los 6 códigos que lanzan dominio y aplicación están en `VistaJson::HTTP` | ✔ |

## 4. Caja blanca

**CB-01 — caminos básicos de `ServicioRevision::cambiar`.** El método tiene 3 decisiones (`?? throw`, el `if` del autor y el `if` del resultado del repositorio), así que V(G) = 4. La excepción de `validarCambioA` agrega una salida: 5 caminos. Un doble espía fija las versiones y cuenta las escrituras.

| Camino | Condición que lo fuerza | Esperado | Escrituras |
|---|---|---|---|
| C1 | La versión 9 no existe | NF | 0 |
| C2 | Aprobar una rechazada (el dominio lo impide) | E7 | 0 |
| C3 | El autor aprueba su versión | E5 | 0 |
| C4 | Otro revisor ganó antes (`false`) | E7 | 1 |
| C5 | Otro revisor aprueba | ok | 1 |

**CB-02 — MC/DC.** `$nuevo === Publicada && $v['autor'] === $revisor` con (V, V) = E5, (V, F) = ok y (F, V) = ok (el autor sí puede marcar obsoleta su versión). En `rechazar`, `trim($motivo) !== ''` con « » = E2 sin escribir y con motivo = ok.

**Sensibilidad.** Dos mutaciones de una línea en una copia temporal, comparando las unitarias y de caja blanca: permitir publicada → en revisión la detecta U-01, y dejar que el autor apruebe lo suyo la detectan CB-01 y CB-02.

## 5. Caja negra (HTTP)

| ID | Técnica | Entradas | Esperado | Obtenido |
|---|---|---|---|---|
| CN-01 | Equivalencia del envío | válido; `swagger`; `1.1`; audiencia `[]` | 201; 422 E2 ×3 | ✔ |
| CN-02 | Revisión y límites de versión | el autor aprueba; otro aprueba; de nuevo; `1.0.0` otra vez; `0.9.9`; `1.0.1`; rechazo sin motivo y con motivo; reenvío de `1.0.1` | 403 E5, 200, 409 E7, 409 E3, 409 E3, 201, 422 E2, 200, 201 | ✔ |
| CN-03 | Audiencia | lector `medico`; lector `enfermeria`; documento inexistente | 200, 404 E6, 404 E6 con el mismo cuerpo | ✔ |
| CN-04 | Permisos | enviar sin `X-Usuario`; aprobar con el rol `paciente` | 403, 403 | ✘ 201, 200 |
| CN-05 | Errores del cliente | versión 99; slug `API-Citas`; cuerpo que no es JSON; versión `9.9.9` | 404 NF, 404, 422 E2, 404 E6 | ✔ |
| CN-06 | Error de infraestructura | La base no se puede abrir al arrancar | 503 E8, sin detalles | ✘ 200 con el «Fatal error» |

## 6. Comandos y resultados

```powershell
php docs/modulos/contratos-api/semana-15/pruebas/pruebas-s15.php            # 11 de 14 pasan; 3 fallan (exit 1)
php docs/modulos/contratos-api/micro-his-contratos-api/pruebas/pruebas.php  # regresión: todo correcto (exit 0)
npx @redocly/cli@1.34.3 bundle docs/modulos/contratos-api/semana-05/api/openapi.yaml --ext json -o openapi.json
php docs/modulos/contratos-api/semana-05/evidencia/verificar-contrato.php openapi.json   # 18 de 18: el Micro-HIS cumple el contrato (exit 0)
```

Contra el Micro-HIS de la semana 6 (PR #436, sin integrar), en una copia temporal, el resultado es el mismo: 11 de 14 y las mismas 3 fallas. Capturas: [01-pruebas-develop.png](capturas/01-pruebas-develop.png) y [02-regresion-s6-mutaciones.png](capturas/02-regresion-s6-mutaciones.png).

## 7. Defectos registrados

| ID | Prueba | Severidad | Descripción | Causa probable | Propuesta | Estado |
|---|---|---|---|---|---|---|
| DEF-01 | U-05 | Menor | Con dos envíos simultáneos de la misma versión, el índice único evita el duplicado, pero el segundo recibe E8 (503) en vez de E3 (409) | `RepositorioPdo::ejecutar` traduce toda `PDOException`, incluida la violación de unicidad (SQLSTATE 23000), a `ErrorPersistencia` | En `guardarEnRevision`, traducir el 23000 a `ReglaDeDominio('E3')` y agregar U-05 a `pruebas.php` | Abierto; confirma H-04 de la S13 |
| DEF-02 | CN-04 | Mayor | Cualquiera envía versiones sin identidad (201) y un rol sin permiso de revisión aprueba (200); como el usuario llega en `X-Usuario`, también se puede saltar R-05 escribiendo otro nombre | `public/index.php` arma la sesión con las cabeceras que manda el cliente y nadie comprueba el permiso | Tomar usuario y roles de la sesión del HIS y exigir el permiso de revisión antes de cada caso de uso (E1, 403), con el RBAC como dependencia | Abierto; confirma H-01 y CP-04 |
| DEF-03 | CN-06 | Mayor | Con la base caída responde 200 y muestra el error de PHP con SQLSTATE y la ruta del servidor | `new PDO` está en `public/index.php`, fuera del adaptador que traduce a E8 | Capturar `PDOException` al conectar y responder 503 E8 con `VistaJson` | Abierto; nuevo |

Los defectos se registran y no se corrigen aquí: corregirlos cambia el Micro-HIS, y eso corresponde a un PR propio con su prueba.

## 8. Bibliografía

Myers, G. J., Sandler, C. y Badgett, T. (2011). *The Art of Software Testing* (3.ª ed.). Wiley · McCabe, T. J. (1976). «A Complexity Measure». *IEEE Transactions on Software Engineering*, SE-2(4) · ISO/IEC/IEEE 29119-4:2021. *Test techniques*.
