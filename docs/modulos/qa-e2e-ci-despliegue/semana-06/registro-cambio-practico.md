# Registro del cambio práctico — Despliegue reproducible

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final

## 1. El pedido

> «La versión 2.4.0 se instaló en producción con un digest que nadie había probado: el pipeline reconstruyó la imagen antes de desplegar. Además, la última evaluación del gate de esa versión era un RECHAZADO; el APROBADO era de un día antes. Queremos que no se pueda desplegar nada que el gate no haya aprobado, sobre ese mismo artefacto y con una aprobación reciente, y que quede registrado con qué guía se desplegó y qué pasó si la verificación posterior falló.»

Toca los seis aspectos que pide la tarea:

| Aspecto | Cómo lo toca el cambio |
|---|---|
| Ejecuciones E2E | El smoke posterior es una ejecución E2E corta; si falla, el despliegue queda REVERTIDO |
| CI | El pipeline **no** puede desplegar: solo el Operador, con `e2e.desplegar` (R-13) |
| Quality gates | Cuenta la **última** evaluación, no cualquier APROBADO del pasado |
| Versiones | El estado de lo instalado por entorno: el vigente es el último DESPLEGADO |
| Evidencia | Cada despliegue queda en la trazabilidad de la versión, con guía, operador y resultado |
| Despliegue reproducible | El digest instalado = el evaluado = el de la versión; la guía es versionada (`guia-AAAA.N`) |

## 2. Reglas nuevas (R-11 detallada)

| # | Regla | Error |
|---|---|---|
| R-11a | La **última** evaluación del gate de la versión debe ser APROBADO | 409 `GATE_NO_APROBADO` |
| R-11b | Digest instalado = digest de la versión = digest evaluado | 409 `DIGEST_DISTINTO` |
| R-11c | La aprobación tiene 72 h o menos (`vigencia_aprobacion_horas` en la política) | 409 `APROBACION_VENCIDA` |
| R-11d | Guía con versión `guia-AAAA.N`; entorno y smoke válidos | 422 `DATOS_INVALIDOS` |
| R-11e | Smoke FALLA → REVERTIDO, a la versión DESPLEGADA antes en ese entorno | 201 con `estado: REVERTIDO` |

## 3. Pruebas primero (rojo)

Se escribieron CAM-01..06 antes del código: 6 de 6 en rojo ([cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt)).

## 4. Impacto

| Capa | Cambio |
|---|---|
| Dominio | `Despliegue` (entidad con R-11a..e) y el puerto `RepositorioDespliegues` |
| Aplicación | `RegistrarDespliegue`; `ConsultarTrazabilidad` muestra los despliegues |
| Persistencia | Adaptadores PDO y en memoria (contrato CON-08); tabla `despliegues` con trigger R-11 y de solo agregar |
| Presentación | `FormularioVersion::despliegue`, `VersionesControlador::desplegar` y la ruta |
| Contrato | **1.1.0, aditivo**: la operación nueva, 2 códigos de error y el esquema `DespliegueNuevo` |
| Configuración | `vigencia_aprobacion_horas: 72`; el rol del Operador con `e2e.desplegar` |

**Sin cambios:** el gate, el reporte de resultados, el plan y las ejecuciones, y las 76 pruebas previas.

## 5. Lo que pasó al implementarlo

| Corrida | Resultado | Evidencia |
|---|---|---|
| 1 | CAM-01..06 en verde, pero **API-01 en rojo**: el código tenía una ruta que el contrato no documentaba | [cambio-2-contrato-sin-actualizar.txt](evidencia/cambio-2-contrato-sin-actualizar.txt) |
| 2 | Contrato 1.1.0 → verde; **PER-04 en rojo de forma intermitente** (ver abajo) | — |
| 3 | **86/86** con PostgreSQL y **31/31** mutaciones | [cambio-3-suite-completa.txt](evidencia/cambio-3-suite-completa.txt), [mutaciones.txt](evidencia/mutaciones.txt) |

**API-01 hizo su trabajo:** cambiar el código sin el contrato rompe la suite.

**El fallo intermitente encontró un defecto real del almacén.** PER-04 usaba una carpeta temporal con el PID, que Windows reutiliza: a veces encontraba el archivo alterado de una corrida anterior. Al mirar por qué fallaba, apareció el defecto:
- si un artefacto guardado se corrompe, `guardar()` veía que el archivo existía, no lo reescribía y la verificación fallaba;
- ese artefacto no se podía volver a guardar **nunca**;
- en el pipeline, el resultado habría quedado pendiente para siempre.

Corrección: si el archivo existe pero no verifica, se repara con los bytes correctos (PER-05, M31). La prueba ahora usa una carpeta única.

## 6. Mutaciones del cambio

| Mutación | Detectada por |
|---|---|
| M26 se usa la PRIMERA evaluación, no la última | CAM-02 |
| M27 no se compara el digest instalado | CAM-03 |
| M28 la aprobación no vence | CAM-04 |
| M29 un smoke que falla deja DESPLEGADO | CAM-05 |
| M30 la base acepta un despliegue sin gate | INT-10 |
| M31 el almacén no repara un artefacto corrupto | PER-05 |
