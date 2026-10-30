# Semana 3 — Catálogo de medicamentos: Micro-HIS en 4 capas y vista C4

**Flujo:** alta, búsqueda y control de vigencia de un medicamento.
**Código:** `../micro-his/` (fuera de las carpetas de semana; se continúa en las semanas 4 y 5).

## 1. Qué pide la tarea y dónde se cumple

| Pide | Dónde |
|---|---|
| PHP 8.2+ vanilla, sin framework | `micro-his/` sin Composer: `autoload.php` propio |
| Presentation | `src/Presentation/Consola/ConsolaCatalogo.php`, `bin/catalogo.php` |
| Application | `src/Application/` (un caso de uso por clase: CU-01…CU-06 y RF-09) |
| Domain | `src/Domain/Modelo`, `Errores`, `Repositorio` (interfaz Repository) y `Puertos` |
| Persistence con PDO y sentencias preparadas | `src/Persistence/RepositorioMedicamentosPdo.php`, `UsoPrescripcionesPdo.php` |
| Composition Root | `bootstrap.php` |
| Prueba de camino feliz | `pruebas/unitarias/casos-de-uso.php` (CA-01) y `pruebas/integracion/persistencia-pdo.php` (con base) |
| Prueba de regla de dominio | `pruebas/unitarias/dominio.php` (RN-01…RN-06) y `casos-de-uso.php` (RN-02, RN-04) |
| Prueba de error de persistencia | Con doble `EscrituraQueFalla` y con base caída (puerto 1) — CA-13 |
| Vista C4/UML y dependencias | `diagramas/c4-contexto.png`, `c4-componentes.png`, `dependencias-capas.png` |

## 2. Capas y responsabilidades

| Capa | Responsabilidad | Depende de |
|---|---|---|
| Presentation | Leer la orden, llamar al caso de uso y mostrar el resultado; traducir errores a 422/409/404/503. Sin SQL ni reglas. | Application |
| Application | Orquestar un caso de uso: validar la entrada, consultar, aplicar la regla del dominio y guardar. | Domain (solo interfaces) |
| Domain | Reglas y datos del catálogo: `Medicamento` (versión inmutable), `Vigencia` [desde, hasta), `ClaveEquivalencia`, `FichaMedicamento`, `CriterioBusqueda`, `Limites`. Define las interfaces que necesita. | Nada |
| Persistence | Implementar las interfaces con PDO pgsql; traducir errores de la base. | Domain |
| Composition Root | Crear PDO, adaptadores y casos de uso, y conectarlos. | Todas (es el único) |

### Reglas implementadas en el dominio (con su prueba)

| Regla | Dónde vive | Prueba |
|---|---|---|
| RN-01 versiones sin solape | `Medicamento::nuevaVersion`, `Vigencia::seCruzaCon`; en la base `EXCLUDE ex_medicamentos_codigo_vigencia` | `dominio.php` (CA-09, CA-10); `persistencia-pdo.php` (solape y atomicidad) |
| RN-02 no se borra lo usado | `EliminarRegistroSinUso` + puerto `ConsultaUsoPrescripciones`; `Medicamento::darDeBaja` | `casos-de-uso.php` y `persistencia-pdo.php` (CA-11) |
| RN-03 búsqueda de vigentes | `CriterioBusqueda`; SQL con `ESCAPE '!'` | `dominio.php`; `persistencia-pdo.php` (CA-06, CA-07, CA-08) |
| RN-04 sin duplicados | `ClaveEquivalencia`; `RegistrarMedicamento`; en la base `EXCLUDE ex_medicamentos_clave_vigencia` | `casos-de-uso.php` (CA-04, CA-05); `persistencia-pdo.php` |
| RN-05 dominio = columnas | `Limites` (el script de esquema usa las mismas constantes) | `dominio.php` (límites); `persistencia-pdo.php` (information_schema y valor máximo) |
| RN-06 código del hospital | `CodigoMedicamento`; `siguienteCodigo` por hospital | `dominio.php`; `casos-de-uso.php` (el Sur empieza en MED-000001) |

## 3. Cómo ejecutar (desde la raíz del repo)

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
docker compose up -d
docker compose ps
php scripts/crear-esquema.php
php bin/catalogo.php buscar amoxi
php pruebas/ejecutar.php --evidencia=../semana-03/evidencia/salida-pruebas.txt
cd ../../../..
```

Esperar a que `docker compose ps` muestre **healthy** antes de `crear-esquema.php`.

**Resultado al preparar la entrega:** 34 pruebas, 34 correctas, 0 fallas (PHP 8.3.6 y PostgreSQL
16.15 local en el puerto 55445, sin Docker). Tu evidencia es la que genera el comando anterior en tu
equipo; si algo difiere, la salida dice qué prueba falló.

## 4. Verificar dependencias

Ninguna clase de Domain o Application importa Persistence, Presentation ni PDO:

```powershell
Get-ChildItem src\Domain, src\Application -Recurse -Filter *.php |
  Select-String -Pattern 'use Catalogo\\(Persistence|Presentation)|\\PDO'
```

Resultado esperado: **ninguna línea**.

## 5. Decisiones

- **Versiones inmutables:** un cambio nunca edita una fila; cierra la versión anterior y agrega otra. El historial (CU-05) sale gratis y el auditor ve qué estuvo vigente en cada fecha.
- **Doble defensa:** las reglas RN-01 y RN-04 se validan en el dominio (mensaje claro) y la base las repite con `EXCLUDE USING gist` (RNF-04). Si dos personas registran a la vez, la base responde y el adaptador lo traduce a 409, no a 500.
- **Orden de búsqueda estable:** `ORDER BY nombre COLLATE "C"` para que PDO e InMemory (semana 4) devuelvan el mismo orden sin depender del locale de la base.
- **`ESCAPE '!'`:** con barra invertida, el analizador de PDO confunde la comilla y pierde parámetros. Lo detectó la prueba de búsqueda al preparar la entrega.
- **Tenant explícito:** todos los métodos reciben `hospitalId`. En la semana 3 la consola lo toma de `--hospital`; en la semana 5 se tomará del token.

## 6. Riesgos y pendientes

- Tabla `medicamentos` y su ubicación en HOSPITAL: **propuesta del Micro-HIS** (no se pudo leer el esquema real).
- `referencias_prescripcion` es una **simulación**; el contrato real con `prescripciones-alergias` está pendiente de acordar con su dueño.
- La búsqueda con `LIKE '%…%'` recorre la tabla del hospital; RNF-03 se mide en la semana 15 y, si no cumple, se propone un índice `pg_trgm`.
- La búsqueda distingue acentos ("amoxicilína" no encuentra "amoxicilina"); queda anotado para la semana 9.
