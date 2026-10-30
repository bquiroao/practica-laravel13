# Checklist de la revisión técnica formal (15 ítems)

Cada ítem se responde **Sí / No / No aplica** con la evidencia (archivo y línea, o salida de un
comando). Un «No» genera un hallazgo con el formato de `formato-hallazgos.md`.
Rutas relativas a `docs/modulos/catalogo-medicamentos/micro-his/`.

## A. Reglas de dominio

| ID | Pregunta | Cómo verificarlo |
|---|---|---|
| C-01 | ¿Ninguna operación puede dejar dos versiones vigentes del mismo código (RN-01) en el dominio **y** en la base? | Leer `Medicamento::nuevaVersion`; buscar `ex_medicamentos_codigo_vigencia` en `scripts/crear-esquema.php`; prueba «RNF-04 la base rechaza un solape». |
| C-02 | ¿Un código usado en prescripciones nunca se borra y siempre se ofrece la baja (RN-02)? | `EliminarRegistroSinUso`; prueba CA-11 con dobles y con base. |
| C-03 | ¿La búsqueda solo devuelve vigentes a la fecha y trata `%`, `_` y `!` como texto en PDO e InMemory (RN-03)? | `RepositorioMedicamentosPdo::buscarVigentes` (`ESCAPE '!'`); contrato casos 1–3. |
| C-04 | ¿La clave de duplicados normaliza el principio activo y la verificación cubre alta **y** nueva versión (RN-04)? | `ClaveEquivalencia::normalizarPrincipio`; `RegistrarMedicamento` y `RegistrarNuevaVersion`. |
| C-05 | ¿Todo límite de `Limites` coincide con su columna y todo dato inválido produce 422 con **todos** los errores a la vez (RN-05, RF-02)? | Prueba «columnas coinciden con Limites»; probar valor 0 sin unidad (H-05). |

## B. Contrato API

| ID | Pregunta | Cómo verificarlo |
|---|---|---|
| C-06 | ¿Cada endpoint, código de error y encabezado de `semana-05/contrato-api.md` existe en `KernelApi` y viceversa? | Comparar la tabla §2 con `KernelApi::rutas`; `pruebas/api/api.php`. |
| C-07 | ¿Todas las respuestas de error usan el formato único y llevan `request_id` igual al encabezado `X-Request-Id`? | Prueba «formato único de error»; revisar los 503 que salen fuera de `KernelApi` (`public/index.php`). |
| C-08 | ¿Los `POST` no se pueden duplicar sin control (409 por RN-04) y los reintentos solo se recomiendan para `GET`? | `contrato-api.md` §6; prueba de alta duplicada. |

## C. Permisos y tenant

| ID | Pregunta | Cómo verificarlo |
|---|---|---|
| C-09 | ¿La matriz de `Permisos` coincide con el contrato y cada acción del controlador llama a `exigir()` antes del caso de uso? | `ApiMedicamentos.php`; prueba «permisos por rol». |
| C-10 | ¿El hospital y el usuario salen solo del token y se descartan del cuerpo? ¿Otro hospital recibe 404? | `CuerpoJson::leer`; prueba RNF-02. |
| C-11 | ¿El registro de peticiones excluye tokens y datos clínicos, y los errores 5xx no muestran SQL ni trazas? | `KernelApi::registro`; prueba «el registro no lleva el token»; prueba CA-13. |

## D. Integridad y concurrencia

| ID | Pregunta | Cómo verificarlo |
|---|---|---|
| C-12 | ¿Cada escritura es atómica (transacción en PDO, copia en InMemory) y una falla no deja datos parciales? | `enTransaccion`; prueba «reemplazarVigente es atómico» (PDO e InMemory). |
| C-13 | ¿Dos altas simultáneas en el mismo hospital terminan bien para el usuario, o al menos con 409 y no 500? | `siguienteCodigo` + `uq_medicamentos_codigo_version`; ejercicio de dos altas con el mismo código calculado. |

## E. Dependencias estrictamente necesarias

| ID | Pregunta | Cómo verificarlo |
|---|---|---|
| C-14 | ¿Domain y Application no importan Persistence, Presentation ni PDO, y el controlador no tiene SQL ni reglas? | `pruebas/unitarias/arquitectura.php`. |
| C-15 | ¿El catálogo depende de otros módulos solo por el puerto `ConsultaUsoPrescripciones` y no lee tablas ajenas? ¿Lo pendiente con `prescripciones-alergias` está marcado como tal? | Buscar nombres de tablas ajenas en `src/`; `semana-04/README.md` §5. |

## Comando base para la preparación

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
php pruebas/ejecutar.php --evidencia=../semana-13/evidencia/pruebas-antes-de-revision.txt
cd ../../../..
```
