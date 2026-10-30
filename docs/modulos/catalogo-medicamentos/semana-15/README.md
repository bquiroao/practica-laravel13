# Semana 15 — Catálogo de medicamentos: pruebas unitarias, caja blanca y caja negra

| Pide | Dónde | Cantidad |
|---|---|---|
| Matriz RF/RNF → prueba | `matriz-rf-rnf-pruebas.md` | 9 RF y 8 RNF |
| Unitarias para reglas | `micro-his/pruebas/unitarias/semana15-reglas.php` | 5 (U-01 a U-05) + 2 de defectos |
| Caja blanca (ramas y condiciones) | `micro-his/pruebas/caja-blanca/caminos.php`, `diagramas/grafo-registrar-nueva-version.png` | 3 (CB-01 a CB-03) |
| Caja negra (equivalencia, límites, permisos, errores) | `micro-his/pruebas/caja-negra/api-equivalencia-limites.php` | 8 (CN-01 a CN-08) + 1 de defecto |
| Comandos y resultados | Secciones 2 y 3 | — |
| Capturas | `capturas/salida-pruebas-semana15.png`, `capturas/medicion-rnf03.png` | 2 |
| Defectos registrados | `defectos.md` | 3 (D-01 a D-03) |

## 1. Diseño de las pruebas

### Unitarias (reglas del catálogo)

| ID | Regla | Técnica |
|---|---|---|
| U-01 | RN-03 vigencia `[desde, hasta)` | Valores límite: día anterior, primer día, último día, día `hasta` |
| U-02 | RN-04 clave de equivalencia | Equivalencia: mayúsculas acentuadas y espacios internos |
| U-03 | RN-05 concentración | Equivalencia de representaciones (`500`, `500.000`, `0500`, `500,0`) y mínimo `0.001` |
| U-04 | RN-01 versiones inmutables | La baja crea otra versión cerrada; el original no cambia |
| U-05 | RNF-06 contrato de adaptadores | PDO e InMemory implementan la misma interfaz con los mismos métodos |

### Caja blanca

**CB-02 — `RegistrarNuevaVersion::ejecutar`.** Grafo en `diagramas/grafo-registrar-nueva-version.png`:
6 decisiones (D1 código, D2 datos, D3 historial, D4 abierta, D5 fecha posterior, D6 equivalente),
incluidas las que lanzan excepciones en el dominio. Complejidad ciclomática V(G) = 6 + 1 = **7**,
así que se necesitan 7 caminos independientes; la prueba ejecuta los 7 (C1–C7).

**CB-01 — `Vigencia::seCruzaCon`.** Dos condiciones con `hasta` opcional
(c1: `otra.hasta == null || este.desde < otra.hasta`; c2: `este.hasta == null || otra.desde < este.hasta`).
Los 5 casos hacen verdadera y falsa cada condición, por nulo y por comparación, e incluyen el
límite de intervalos contiguos.

**CB-03 — `CriterioBusqueda::desdeFiltros`.** Cada decisión verdadera y falsa: largo del texto
(1 y 61), forma desconocida, ni texto ni forma, fecha inválida, solo forma con fecha por defecto y
texto normalizado.

### Caja negra (sobre la API, sin mirar el código)

| ID | Técnica | Clases / valores |
|---|---|---|
| CN-01 | Valores límite | Nombre de 2, 3, 150 y 151 caracteres → 422, 201, 201, 422 |
| CN-02 | Equivalencia y límites | Concentración 0, 0.001, 999999.999, 1000000, 1.2345, «diez» |
| CN-03 | Límites y equivalencia | `q` de 1, 2, 60 y 61; fecha válida, 2026-02-30 y 1999-12-31 |
| CN-04 | Tabla de decisión de permisos | 4 roles × 3 acciones = 12 resultados esperados |
| CN-05 | Equivalencia de tenant | Hospital Sur sobre códigos del Norte |
| CN-06 | Errores | `X-Request-Id` en 401, 404, 409 y 204 |
| CN-07 | Errores | 500 sin SQL ni rutas (RNF-08) |
| CN-08 | Errores del flujo | 409 `EN_USO` y 409 `YA_DADO_DE_BAJA` |

## 2. Comandos (desde la raíz del repo)

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
docker compose ps
php pruebas/ejecutar.php --evidencia=../semana-15/evidencia/salida-pruebas.txt
php scripts/medir-busqueda.php --evidencia=../semana-15/evidencia/rnf03-busqueda.txt
php ../semana-14/evidencia/medir-trazabilidad.php --evidencia=../semana-15/evidencia/trazabilidad.txt
cd ../../../..
```

`medir-busqueda.php` vacía y recarga la base **de prueba** (`micro_medicamentos_test`); no toca la principal.

## 3. Resultados al preparar la entrega

Entorno: PHP 8.3.6 y PostgreSQL 16.15 local en el puerto 55445 (Linux, sin Docker). Las capturas
de `capturas/` muestran esas salidas; tu evidencia es la que generen los comandos en tu equipo.

| Comando | Resultado |
|---|---|
| `ejecutar.php` | 88 pruebas, 85 correctas, 0 fallas, 3 defectos abiertos documentados |
| `medir-busqueda.php` | 5 000 versiones; p50 = 4,2 ms, p95 = 6,3 ms, máximo = 7,2 ms → RNF-03 cumple |
| `medir-trazabilidad.php` | 25 de 27 requisitos con prueba (93 %) |

Los tiempos de `medir-busqueda.php` dependen del equipo: en Windows con Docker pueden ser mayores;
el criterio es p95 ≤ 300 ms.

## 4. Lo que no se prueba aquí

Módulos ajenos (`prescripciones-alergias`, farmacia, inventario): el uso en prescripciones se
reemplaza con la tabla de simulación y con dobles, sin llamar a otro módulo.
