# Matriz decisión → evidencia (primer parcial)

Cada decisión de arquitectura con la alternativa descartada, su justificación y el archivo o prueba
que la demuestra. Las rutas son relativas a `docs/modulos/catalogo-medicamentos/`.

| ID | Decisión | Alternativa descartada | Justificación | Evidencia |
|---|---|---|---|---|
| D-ARQ-01 | Versiones inmutables con vigencia `[desde, hasta)` | Editar la fila del medicamento | El auditor y prescripciones necesitan saber qué estuvo vigente en cada fecha (RN-01, CU-05) | `micro-his/src/Domain/Modelo/Medicamento.php`; `pruebas/unitarias/dominio.php` (CA-09, CA-10) |
| D-ARQ-02 | RN-01 y RN-04 en el dominio **y** en la base (`EXCLUDE USING gist`) | Solo validar en el código | Dos altas simultáneas no pueden dejar duplicados; la base responde y se traduce a 409 (RNF-04) | `micro-his/scripts/crear-esquema.php`; `pruebas/integracion/persistencia-pdo.php` (RNF-04) |
| D-ARQ-03 | Interfaces de rol (ISP) en el dominio | Un repositorio con 12 métodos | Cada caso de uso depende solo de lo que usa; dobles más pequeños | `semana-02/ISP.md`; `semana-02/ejemplo-isp/verificar-isp.php` (41 → 8 métodos no usados) |
| D-ARQ-04 | Interfaz Repository en Domain y adaptadores en Persistence | Casos de uso que usan PDO | El dominio no depende de la base; se cambia el adaptador sin tocar reglas | `pruebas/unitarias/arquitectura.php`; `semana-03/diagramas/dependencias-capas.png` |
| D-ARQ-05 | Adaptador InMemory con el mismo contrato que PDO | Dobles ad hoc por prueba | Una prueba de contrato garantiza que ambos responden igual (incluido `%`, `_`, orden y códigos de error) | `pruebas/contrato/repositorio.php` (7 casos × 2) |
| D-ARQ-06 | `Limites` como única fuente de RN-05 y del DDL | Límites repetidos a mano en código y SQL | Un dato inválido da 422 y nunca 500 por columna corta | `src/Domain/Modelo/Limites.php`; prueba «RN-05 las columnas de la base coinciden» |
| D-ARQ-07 | MVC con controlador sin SQL ni reglas | Controlador que valida y consulta | Las reglas viven una sola vez y se reutilizan en consola, web y API | `src/Presentation/Web/ControladorMedicamentos.php`; `pruebas/unitarias/controlador-web.php` |
| D-ARQ-08 | Tenant y usuario tomados del token | `hospital_id` en el cuerpo o en la URL | Imposible pedir o escribir datos de otro hospital (RNF-02) | `src/Presentation/Api/CuerpoJson.php`; `pruebas/api/api.php` (RNF-02) |
| D-ARQ-09 | Formato único de error con `request_id` | Mensajes distintos por endpoint | El cliente programa un solo manejo; soporte rastrea cada falla (RNF-05) | `semana-05/contrato-api.md` §5; `pruebas/api/api.php` (formato del error) |
| D-ARQ-10 | No separar en microservicio todavía | Servicio aparte desde ya | No se cumple ningún gatillo medible; mismo resultado con menor costo | `semana-05/frontera-microservicio.md` §8 (G1–G4) |
| D-ARQ-11 | Puerto `ConsultaUsoPrescripciones` para RN-02 | Leer tablas de prescripciones desde el catálogo | El catálogo no conoce el esquema de otro módulo; el contrato real queda por acordar | `src/Domain/Puertos/ConsultaUsoPrescripciones.php`; `semana-04/README.md` §5 |
| D-ARQ-12 | Búsqueda con `ESCAPE '!'` y orden `COLLATE "C"` | `ESCAPE '\'` y orden del locale | PDO perdía parámetros con `'\'`; el orden debe ser igual en PDO e InMemory | `src/Persistence/RepositorioMedicamentosPdo.php`; contrato casos 2 y 4 |

## Cómo verificar la matriz en la defensa

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
php pruebas/ejecutar.php
php ../semana-02/ejemplo-isp/verificar-isp.php
cd ../../../..
```

Resultado al preparar la entrega: 69 pruebas correctas y verificación ISP `CUMPLE`.
