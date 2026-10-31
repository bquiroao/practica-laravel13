# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra del Dashboard de ocupación hospitalaria

| | |
|---|---|
| **Tarea Odoo** | 2502 — Semana 15: Pruebas unitarias, caja blanca y caja negra |
| **Flujo** | «Consolidación y consulta de ocupación por sala y estado de cama» |
| **Objeto de prueba** | [`micro-his-dashboard-ocupacion/`](../micro-his-dashboard-ocupacion/) de `develop` (semanas 3, 4 y 6), sin modificarlo |
| **Ejecutor** | [pruebas/pruebas-s15.php](pruebas/pruebas-s15.php) · salida: [evidencia/salida-pruebas.txt](evidencia/salida-pruebas.txt) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y entorno

Se prueban las reglas propias del tablero: fórmula de ocupación, salas, estados de cama, indicadores (ocupación e inconsistencias), actualización del corte y procedencia de los datos. No se prueban el catálogo de camas ni las estancias (son de otros módulos; aquí solo se leen) ni el RBAC: CN-04 solo verifica que el tablero **rechace** a quien no tiene permiso. RNF-03 sigue cubierto por `verificar.php` de la semana 2 y no se repite. Las pruebas corren con PHP 8.4.23 sin dependencias. Las de caja negra levantan `php -S` en un puerto libre con un SQLite temporal nuevo que se borra al final. Todos los datos son ficticios.

## 2. Matriz RF/RNF → prueba

| Requisito | Unitarias | Caja blanca | Caja negra | Estado |
|---|---|---|---|---|
| RF-01 Ocupación = ocupadas ÷ (disponibles + ocupadas) (R-01) | U-01 | — | CN-01 | ✔ |
| RF-02 «No aplica» sin camas operativas; sin salas inactivas (R-02, R-03) | U-02, U-06 | CB-02 | CN-01 | ✔ |
| RF-03 Marcar inconsistencias sin corregirlas (R-04) | U-03 | CB-02 | CN-01, CN-07 | ✘ DEF-02 (sala inactiva) |
| RF-04 Corte con hora y aviso a los 15 minutos (R-05, R-06) | U-04, U-06 | CB-01 | CN-02 | ✔ |
| RF-05 Filtro por estado de cama (CU-03) | U-05 | CB-01 | CN-03 | ✔ |
| RF-06 Si falla la lectura, se conserva el corte anterior (R-09) | — | CB-02 | CN-06 | ✔ |
| RNF-01 Permiso de ocupación en el mismo hospital (R-08, CP-04 de la S14) | — | — | CN-04 | ✘ DEF-01 |
| RNF-02 El tablero no cambia estados de cama | — | — | CN-03 | ✔ |
| RNF-04 Un fallo no deja el tablero en blanco | — | — | CN-06, CN-08 | ✘ DEF-03 |
| RNF-05 Los cortes solo tienen cifras (R-07) | U-06 | — | — | ✔ |

## 3. Pruebas unitarias

| ID | Unidad | Casos | Resultado |
|---|---|---|---|
| U-01 | `OcupacionSala::calcular` | 1 ocupada, 1 disponible, 1 en mantenimiento y 1 inactiva = 50.0; 1 de 3 = 33.3 | ✔ |
| U-02 | `OcupacionSala::calcular` | Solo mantenimiento e inactiva = «no aplica»; sin camas = «no aplica»; todas ocupadas = 100.0 | ✔ |
| U-03 | `OcupacionSala::calcular` | Ocupada sin estancia, ocupada con dos y disponible con una se marcan; ocupada con una y mantenimiento sin estancia no | ✔ |
| U-04 | `Corte::esViejo` | 900 s = no, 901 s = sí, 0 s = no | ✔ |
| U-05 | `Corte::filtrar` | Sin filtro todas; `occupied` solo B; `inactive` ninguna; `OCCUPIED` y vacío = E3 | ✔ |
| U-06 | `ConsolidadorOcupacion` + InMemory | Sala inactiva fuera; sala sin camas = «no aplica»; el corte guarda la hora del reloj; solo claves de cifras | ✔ |

## 4. Caja blanca

**CB-01 — caminos básicos de `ConsultaOcupacion::consultar` con `Corte::filtrar`.** Hay 3 decisiones (`corte === null`, `estado === null` y el estado inválido), así que V(G) = 4. Un doble espía fija el corte; el aviso de viejo se comprueba en los dos sentidos.

| Camino | Condición que lo fuerza | Esperado |
|---|---|---|
| C1 | No hay corte (aunque el filtro sea inválido) | vacío |
| C2 | Corte reciente, sin filtro | 2 salas, sin aviso |
| C3 | Estado «ocupada» | E3 |
| C4 | Estado `available` | 1 sala |
| — | Corte de hace 1000 s | 2 salas con aviso |

**CB-02 — decisiones y condiciones de la consolidación.** Si la fuente falla, la excepción sale antes de `guardarCorte` (0 escrituras, R-09). Con una sala sin camas, `cama !== null` toma la rama falsa y la sala se guarda vacía. La condición de R-04 (`estancias !== (ocupada ? 1 : 0)`) se cubre con 5 combinaciones: ocupada con 1, 0 y 2, y disponible con 0 y 1, que dan F, V, V, F y V.

**Sensibilidad.** Dos mutaciones de una línea en una copia temporal, comparando las unitarias y de caja blanca: contar las camas en mantenimiento como operativas lo detectan U-01 y U-02, y avisar con 15 minutos exactos (`>=` en vez de `>`) lo detecta U-04.

## 5. Caja negra (HTTP)

| ID | Técnica | Entradas | Esperado | Obtenido |
|---|---|---|---|---|
| CN-01 | Equivalencia del corte | Sin corte; sala A con 3 ocupadas (A3 con 2 estancias y A4 sin ninguna) y 1 disponible; sala M en mantenimiento; sala X inactiva | 200 vacío; 201 con 2 salas; A = 75.0; M = «no aplica»; A3 y A4 inconsistentes | ✔ |
| CN-02 | Valores alrededor del límite | Cortes de 0 s, 880 s y 920 s | sin aviso, sin aviso, con aviso | ✔ |
| CN-03 | Filtro y solo lectura | `?estado=occupied`, `maintenance`, `OCCUPIED`, vacío; estados de cama antes y después de consolidar | A; M; 422; 422; iguales | ✔ |
| CN-04 | Permisos | Consultar sin permiso; consultar y consolidar con `X-Tenant-ID: hospital-ajeno` | 403, 403, 403 | ✘ 200, 200, 201 |
| CN-05 | Errores del cliente | `GET /ocupacion/cortes`, `DELETE /ocupacion`, `/ocupacion/salas/A` | 404 ×3 | ✔ |
| CN-06 | Falla de lectura | La lectura de salas falla al consolidar | 503 E8 sin detalles; la consulta sigue con el corte anterior | ✔ |
| CN-07 | Procedencia | Cama X1 ocupada con estancia abierta en la sala inactiva X | X1 visible como inconsistencia | ✘ X1 no aparece |
| CN-08 | Error de infraestructura | La base no se puede abrir al arrancar | 503 E8, sin detalles | ✘ 200 con el «Fatal error» |

El límite exacto de los 15 minutos se prueba en U-04; por HTTP se usa margen porque el reloj del servidor avanza entre la inserción y la consulta. CN-03 acepta el 422 E3 actual para el estado inválido; que E3 signifique otra cosa en la semana 1 ya es H-02 de la S13 y no se registra de nuevo.

## 6. Comandos y resultados

```powershell
php docs/modulos/dashboard-ocupacion/semana-15/pruebas/pruebas-s15.php                   # 13 de 16 pasan; 3 fallan (exit 1)
php docs/modulos/dashboard-ocupacion/micro-his-dashboard-ocupacion/pruebas/pruebas.php    # regresión: todo correcto (exit 0)
```

Capturas: [01-pruebas-develop.png](capturas/01-pruebas-develop.png) y [02-regresion-mutaciones.png](capturas/02-regresion-mutaciones.png).

## 7. Defectos registrados

| ID | Prueba | Severidad | Descripción | Causa probable | Propuesta | Estado |
|---|---|---|---|---|---|---|
| DEF-01 | CN-04 | Mayor | Cualquiera consulta y consolida sin permiso y con un hospital ajeno (200 y 201) | `atender()` no recibe usuario, permiso ni hospital, y `public/index.php` no lee `X-Tenant-ID` | Guardia de autorización (E3, 403) antes de consultar y consolidar, con el permiso de ocupación y el tenant del RBAC | Abierto; confirma H-01 y CP-04 |
| DEF-02 | CN-07 | Menor | Una cama ocupada con estancia abierta en una sala inactiva desaparece del corte sin marca: ese paciente no se ve | `camasDeSalasActivas` filtra `w.status = 'active'` antes de mirar las estancias | Sin consolidar la sala (R-03), reportar aparte las camas de salas inactivas con estancias abiertas | Abierto; confirma H-04 |
| DEF-03 | CN-08 | Mayor | Con la base caída responde 200 y muestra el error de PHP con SQLSTATE y la ruta del servidor | `new PDO` está en `public/index.php`, fuera del adaptador que traduce a E8 | Capturar `PDOException` al conectar y responder `VistaJson::noDisponible()` (503 E8) | Abierto; nuevo |

Los defectos se registran y no se corrigen aquí: corregirlos cambia el Micro-HIS, y eso corresponde a un PR propio con su prueba.

## 8. Bibliografía

Myers, G. J., Sandler, C. y Badgett, T. (2011). *The Art of Software Testing* (3.ª ed.). Wiley · McCabe, T. J. (1976). «A Complexity Measure». *IEEE Transactions on Software Engineering*, SE-2(4) · ISO/IEC/IEEE 29119-4:2021. *Test techniques*.
