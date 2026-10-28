# Cambio solicitado y respuesta documentada

## Pedido

> «En el teléfono quiero ver si la ocupación viene subiendo o bajando, por semana, sin perder el valor del mes.»

## Análisis

| Aspecto | Impacto |
|---|---|
| Indicador | El mismo: ocupación de camas |
| Agregación | Por semana (granularidad `semana`, RN-03); cada semana es su propia ocupación, no una parte del mes |
| Filtros | Mismo período; la granularidad se agrega como parámetro opcional (ya previsto en el contrato API v1) |
| Fuente | Las mismas tablas (`bed_stay`) |
| Precisión | Un decimal en cada semana, con el formateador único |
| Visualización móvil | No cabe un gráfico grande en 360 px sin desplazar el valor principal |

## Decisión

1. Debajo del valor del mes se agrega una **lista compacta de 4–5 semanas** con su valor y una flecha ▲▼ respecto de la semana anterior. Es una lista y no un gráfico, porque se lee bien con lector de pantalla y en 360 px.
2. Cada semana es un enlace a su propio desglose por sala, así cada número sigue siendo trazable (RN-07).
3. El valor del mes sigue primero y grande; la tendencia es secundaria (jerarquía de la semana 10).
4. En escritorio la misma información se muestra como gráfico de líneas con una tabla alternativa oculta para lectores de pantalla.

## Riesgos y cómo se evitan

| Riesgo | Cómo se evita |
|---|---|
| Sumar las semanas para «comprobar» el mes | La ayuda aclara que la ocupación semanal no se suma; RN-07 se aplica dentro de cada semana |
| Semanas incompletas al inicio o fin del mes | Se marcan como «semana parcial» con sus fechas |
| Más carga en la base | Una sola consulta agrupada por semana; se mide con el log de duración (RNF-01) |

## Qué cambia en el diseño

| Pieza | Cambio |
|---|---|
| Dominio | `Filtros` acepta `granularidad`; el reporte devuelve la serie de la tendencia |
| API | Parámetro `granularidad=semana` en `/api/v1/reportes/ocupacion` (compatible con v1) |
| Frontend | Componente nuevo `ListaTendencia`; `TarjetaIndicador` no cambia |
| Pruebas | Cada semana: desglose suma su numerador; semanas parciales marcadas |
