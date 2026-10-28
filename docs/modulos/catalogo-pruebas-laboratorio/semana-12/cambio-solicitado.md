# Respuesta documentada al cambio solicitado

> **Nota:** el cambio real lo pide el ingeniero en la evaluación. Este registro está preparado con un cambio probable; si se pide otro, se reemplazan las secciones con el mismo formato.

## 1. Cambio

«En el catálogo y en el detalle, avisar cuando una prueba vence en 7 días o menos, para que el médico no la ordene sin saberlo y el Jefe registre a tiempo la versión nueva.»

## 2. Análisis

| Pregunta | Respuesta |
|---|---|
| ¿Cambia una regla de negocio? | No. La vigencia (RN-04 a RN-06) sigue igual; solo cambia cómo se muestra. |
| ¿Cambia el contrato API? | No. `vigencia.hasta` ya viene en cada prueba. |
| ¿Qué componente cambia? | `AvisoVigencia` (frontend) y la tarjeta o fila del catálogo. Se mantiene la decisión D-B01: el frontend no decide si la prueba está vigente. Los días restantes se calculan solo para mostrarlos, a partir de la fecha de referencia y `hasta`. |
| ¿Qué pasa con la accesibilidad? | El aviso lleva texto («Vence el 31/01/2027, en 3 días»), no solo color, y un ícono con texto alternativo. |
| ¿Y en móvil? | La tarjeta muestra «Vence en 3 días» en lugar de la fecha completa. |

## 3. Diseño

| Estado | Condición (para mostrar) | Mensaje |
|---|---|---|
| Vigente | `hasta` vacío o a más de 7 días de la fecha de referencia | «Vigente» |
| Por vencer | `hasta` entre 0 y 7 días después de la fecha de referencia | «Vence el DD/MM/AAAA (en N días)» con ícono de advertencia |
| Con versión nueva | Hay otra versión que empieza el día siguiente a `hasta` | «Vence el DD/MM/AAAA; desde el DD/MM/AAAA rige la versión N» |

## 4. Verificación propuesta

- Con fecha de referencia 2027-01-28, `GLU-001` versión 1 (hasta 2027-01-31) muestra «Vence el 31/01/2027 (en 3 días); desde el 01/02/2027 rige la versión 2».
- Con fecha 2027-01-15 muestra solo «Vigente hasta 31/01/2027».
- El texto del aviso lo lee NVDA (por verificar a mano).
