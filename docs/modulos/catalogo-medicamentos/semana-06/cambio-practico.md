# Registro del cambio práctico — primer parcial

> **Caso preparado.** El cambio real lo pide el ingeniero durante la defensa. Este registro muestra
> cómo se responde a un cambio probable; si el pedido es otro, se usa el mismo formato
> (sección 6) y se completa el día de la evaluación.

## 1. Cambio solicitado (supuesto)

«Un mismo medicamento puede comercializarse en **varias presentaciones** (caja de 10, de 30, frasco
de 100 mL), y **cada presentación tiene su propia vigencia**. Debe poder buscarse por presentación y
no deben duplicarse.»

## 2. Análisis contra el diseño actual

| Tema | Hoy | Con el cambio |
|---|---|---|
| Medicamentos | Una versión = ficha completa (incluye `presentacion`) | Se separa **producto** (principio activo, concentración, forma, vía) de **presentación** (envase, registro sanitario) |
| Presentaciones | Un texto por versión | Entidad `Presentacion` con código propio `MED-000102-P01` dentro del producto |
| Vigencia | RN-01 por código | RN-01 por **presentación**; el producto está vigente si alguna presentación lo está |
| Búsqueda | Devuelve versiones vigentes | Devuelve presentaciones vigentes, agrupadas por producto; filtro nuevo `presentacion` |
| Unicidad | RN-04 por clave del producto | RN-04 se mantiene a nivel producto; regla nueva RN-07: no dos presentaciones vigentes iguales (mismo envase normalizado) del mismo producto |

## 3. Impacto por capa (qué cambia y qué no)

| Capa | Cambio | Esfuerzo estimado |
|---|---|---|
| Domain | `Producto` y `Presentacion`; `ClaveEquivalencia` queda en `Producto`; nueva `ClavePresentacion` | Medio |
| Repository (ISP) | `BusquedaCatalogo::buscarVigentes` devuelve presentaciones; nuevo método en `ControlEquivalencia` para RN-07 | Bajo: las interfaces de rol limitan el impacto a quien las usa |
| Persistence | Tabla `medicamento_presentaciones` con `EXCLUDE` por presentación; `medicamentos` conserva el producto | Medio (migración de datos: cada versión actual → 1 presentación) |
| Application | `RegistrarPresentacion`, `DarDeBajaPresentacion`; `RegistrarMedicamento` crea producto + primera presentación | Medio |
| API | `v1` compatible: se agrega `presentaciones[]` en la respuesta; el campo `presentacion` se mantiene con la primera vigente. Endpoints nuevos bajo `/medicamentos/{codigo}/presentaciones` | Bajo |
| Presentation (web) | Detalle con tabla de presentaciones y acciones por fila | Bajo |

Lo que **no** cambia: reglas RN-02, RN-03 (sigue «vigente a una fecha»), RN-05, el tenant por token,
el formato de errores y la Composition Root (solo registra los casos de uso nuevos).

## 4. Pruebas que se agregarían

| ID | Tipo | Caso |
|---|---|---|
| T-CP-01 | Unitaria | Dos presentaciones del mismo producto con vigencias distintas: cada una vigente en su intervalo. |
| T-CP-02 | Unitaria | RN-07: segunda presentación «Caja con 21 cápsulas» vigente → 409 `DUPLICADO_PRESENTACION`. |
| T-CP-03 | Contrato | Búsqueda por presentación con el mismo resultado en PDO e InMemory. |
| T-CP-04 | API | `GET /medicamentos/{codigo}` sigue devolviendo `presentacion` (compatibilidad v1). |

## 5. Decisión defendida

Se acepta el cambio **sin romper la API v1** y sin mover reglas a la presentación: el diseño en
capas con interfaces de rol permite agregar `Presentacion` como un nivel más del dominio. El riesgo
principal es la migración de datos (cada versión actual se convierte en un producto con una
presentación), que se haría con un script idempotente y una prueba de conteo antes/después.

## 6. Formato para el cambio real del día de la defensa

| Campo | Contenido |
|---|---|
| Cambio pedido (textual) | *(completar en la defensa)* |
| Reglas afectadas (RN) | |
| Capas afectadas | |
| Qué no cambia | |
| Pruebas nuevas | |
| Decisión y riesgo | |
