# Registro del cambio práctico defendido — R-15: «un defecto MAYOR abierto en un caso crítico bloquea el GO»

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Fecha:** 2026-10-29

| | |
|---|---|
| **Pedido** | Responsable de producto (simulado para la defensa) |
| **Temas que toca** | Campañas, matriz, defectos, severidad y trazabilidad. Evidencia: sin cambios, y se explica por qué |
| **Resultado** | 5 pruebas nuevas, en rojo antes y en verde después; 99/99 en total; 4 mutaciones nuevas, 33/33 detectadas; contrato 1.0.0 → 1.1.0 (aditivo) |

## 1. Solicitud

> «La versión 1.4.0 salió con GO y en producción la nota firmada mostraba la fecha en otro formato. Había un defecto MAYOR abierto sobre TC-107, que es crítico para el expediente, pero la campaña dio GO porque solo bloqueaban los críticos. Queremos que eso no vuelva a pasar.»

**Cómo pasó con las reglas de la semana 1** (lo reproduce [demo-r15.txt](evidencia/demo-r15.txt), pasos 3–9):
1. TC-107 FALLA y se reporta un defecto MAYOR.
2. Otro tester re-ejecuta TC-107 en otra estación y **PASA**, sin indicar `verifica_defecto`: el defecto sigue ABIERTO.
3. El último intento de cada caso es PASA y la tasa es 100 %. No hay críticos abiertos, así que antes daba **GO**.

## 2. Requisito nuevo

| ID | Regla | Criterio de aceptación |
|---|---|---|
| **R-15** | Una campaña no da GO si existe un defecto de severidad **MAYOR**, no CERRADO, sobre un caso que la matriz marca **crítico** para alguna de las funcionalidades de la campaña | CAM-01..05 |
| CA-R15-a | Un MAYOR sobre un caso **no** crítico (TC-109) no bloquea | CAM-02 |
| CA-R15-b | Un MAYOR **CORREGIDO** pero no verificado sigue bloqueando; uno CERRADO por la re-ejecución, no | CAM-03, CAM-05 |
| CA-R15-c | La trazabilidad dice, por defecto, si bloquea el GO, **antes** de cerrar | CAM-04 |
| CA-R15-d | La decisión NO_GO dice la causa: «1 defecto MAYOR abierto en un caso crítico de la matriz» | CAM-01 |

## 3. Análisis de impacto (antes de programar)

| Tema | ¿Cambia? | Dónde |
|---|---|---|
| **Campañas** | Sí: el cierre recibe un dato más | `Campana::cerrar(int, int)`, con un valor por omisión para no romper llamadas existentes |
| **Matriz** | Se **usa**, no cambia | `RepositorioCasos::criticosDe(tenant, funcionalidades)`, de la semana 3 |
| **Defectos** | Sí: una consulta nueva en el puerto | `RepositorioDefectos::mayoresAbiertos()`, en PDO y en memoria |
| **Severidad** | Se usa la clasificada, no la propuesta | Un defecto NUEVO (sin clasificar) no tiene severidad confirmada y no cuenta, como con los críticos (CON-06) |
| **Evidencia** | **No** | Un defecto MAYOR nace de una FALLA, que ya exige evidencia (R-05) |
| **Trazabilidad** | Sí: un campo nuevo | `bloquea_go` en cada defecto; OpenAPI 1.1.0 (campo nuevo, ningún cliente se rompe) |
| **Esquema de la base** | **No** | La consulta usa columnas que ya existían: no hay migración |
| Presentación (Kernel, MVC) | No | La salida ya llevaba `causas` y `defectos` |

**Por qué «crítico para las funcionalidades de ESTA campaña»** y no «crítico en cualquier funcionalidad»: la matriz es por funcionalidad. TC-109 es parte de `expediente` pero no es crítico. Si la regla mirara la criticidad global, un caso crítico para otra funcionalidad bloquearía campañas que no la tocan. M31 cuenta los MAYOR de todo el alcance y se detecta.

## 4. Implementación

| Archivo | Cambio |
|---|---|
| `src/Dominio/Puertos/RepositorioDefectos.php` | + `mayoresAbiertos(tenant, casos): int` |
| `src/Persistencia/Pdo/PdoRepositorioDefectos.php` | + una consulta preparada (`severidad = 'MAYOR' AND estado <> 'CERRADO' AND caso_id IN (?…)`) |
| `src/Persistencia/EnMemoria/RepositorioDefectosEnMemoria.php` | + el mismo filtro |
| `src/Dominio/Modelo/Campana.php` | `cerrar(criticos, mayoresEnCriticos = 0)` + la causa nueva |
| `src/Aplicacion/CasosDeUso/CerrarCampana.php` | + `RepositorioCasos`; críticos de la matriz ∩ alcance → `mayoresAbiertos` |
| `src/Aplicacion/CasosDeUso/ConsultarTrazabilidad.php` | + `bloquea_go`, con la misma regla que el cierre |
| `bootstrap/contenedor.php` | `CerrarCampana` recibe el repositorio de casos |
| `api/openapi.yaml` | 1.1.0: `bloquea_go` y la descripción del cierre |
| `tests/Dobles/Adaptadores.php` | El decorador con fallas delega el método nuevo |

No cambiaron el Kernel, los formularios, los controladores, las vistas, `RegistrarResultado` ni el esquema.

## 5. Pruebas: rojo → verde

1. Se escribieron CAM-01..05 **antes** del código: **0 de 5** pasaban ([cambio-1-antes-rojo.txt](evidencia/cambio-1-antes-rojo.txt)). CAM-05 fallaba porque el método no existía.
2. Con la implementación: **5 de 5** ([cambio-2-despues-verde.txt](evidencia/cambio-2-despues-verde.txt)) y **99 de 99** en total, con PostgreSQL ([cambio-3-suite-completa.txt](evidencia/cambio-3-suite-completa.txt)).
3. **Mutaciones.** La primera corrida dio 31 de 33 ([mutaciones-1-m32-sobrevive.txt](evidencia/mutaciones-1-m32-sobrevive.txt)):
   - **M32 sobrevivió, y era un hueco real.** Que un MAYOR **CORREGIDO** siga bloqueando solo se probaba en memoria (CAM-03). Nadie lo comprobaba contra PostgreSQL, así que una consulta SQL que excluyera los CORREGIDO habría pasado. Se agregó ese caso a CAM-05, que corre contra los dos adaptadores.
   - **M33 no corrió por un error en la mutación misma:** comillas dobles de PHP que interpolaban `$d`.
   - Corregidas las dos: **33 de 33** ([mutaciones.txt](evidencia/mutaciones.txt)).

## 6. Demostración de punta a punta

[demo-r15.txt](evidencia/demo-r15.txt) (`php bin/demo-r15.php`):

| Paso | Qué muestra |
|---|---|
| 3–7 | La misma secuencia del pedido: FALLA → MAYOR → PASA sin verificar → todo pasa |
| 8 | La trazabilidad **antes** de cerrar: `bloquea_go: true` en el defecto 1 |
| 9 | Cierre: tasa 100 % y 0 críticos, pero **NO_GO** con la causa R-15 |
| 10–15 | Se corrige, se re-ejecuta **verificando** el defecto (PASA lo cierra) y la campaña siguiente da **GO** |

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Contar **MAYOR no CERRADO** (incluye CORREGIDO) | Contar solo ABIERTO y REABIERTO | CORREGIDO significa «el desarrollador dice que lo arregló», y nadie lo verificó todavía (R-08) |
| Calcular `bloquea_go` en la trazabilidad con la misma regla | Dejar que la interfaz lo deduzca | Una regla en dos lugares diverge. Aquí está en la Aplicación, junto al cierre, y CAM-04 lo verifica |
| Contrato 1.1.0 aditivo | Un endpoint nuevo `/bloqueantes` | Un campo nuevo no rompe clientes; API-01..03 siguen en verde sin tocarlos |
| Parámetro con valor por omisión en `Campana::cerrar` | Cambiar la firma sin él | Las pruebas de dominio existentes (DOM-05, DOM-06) siguen válidas sin tocarlas |

## 8. Riesgos y pendientes

- **Una campaña que antes daba GO ahora puede dar NO_GO.** Es lo pedido, pero conviene avisar a los equipos. El cambio va en las notas de la versión 1.1.0 del contrato.
- **Ninguna regla obliga a indicar `verifica_defecto`** al re-ejecutar un caso con un defecto abierto. R-15 evita el GO, pero el tester podría olvidarlo. Candidato para la semana 8: que la interfaz lo proponga cuando el caso tiene un defecto CORREGIDO.
