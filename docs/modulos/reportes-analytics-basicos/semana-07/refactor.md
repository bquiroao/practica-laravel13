# Refactor concreto: precisión y visualización mezcladas con el cálculo

## Punto con mayor acoplamiento

Al revisar cómo se dibuja un indicador, el acoplamiento más fuerte está entre **agregación, precisión y visualización**: el controlador calcula y redondea el porcentaje, arma el texto con la unidad, y el componente Vue vuelve a calcular porcentajes del desglose a partir de valores ya redondeados.

| Síntoma (antes) | Consecuencia |
|---|---|
| El controlador hace `round($ocupadas / $habilitadas * 100)` y devuelve `"78%"` | La API entrega texto: otro cliente no puede usar el número ni cambiar la precisión |
| Cada sala se convierte a porcentaje redondeado y la vista suma esos porcentajes | Con 18/39, 14/39 y 7/39 los redondeos dan 46 + 36 + 18 = **100**, pero con otros datos dan 99 o 101: RN-07 «falla» en pantalla aunque los datos estén bien |
| La consulta SQL vive en el controlador | No se puede probar el cálculo sin base ni reutilizarlo en la API |
| Cada componente decide su redondeo | Ocupación muestra 78 y la ficha 78.0: dos números distintos para lo mismo |

## Refactor (después)

| Responsabilidad | Antes | Después |
|---|---|---|
| Consultar datos | Controlador (SQL) | `OcupacionPgRepository` (Persistencia) |
| Calcular | Controlador | `ReporteOcupacion` (Dominio) con valores exactos |
| Precisión | Repartida en controlador y Vue | Objeto de valor `Precision` en el `Indicador` (Dominio): decimales y unidad |
| Desglose | Porcentajes redondeados | **Conteos enteros**; el total se compara con el numerador (RN-07) |
| Formatear para la vista | Controlador y componentes | Una sola función `formatearIndicador` en el frontend |

Archivos: `refactor/antes/` (controlador y componente acoplados) y `refactor/despues/` (las mismas piezas separadas). El diagrama `diagramas/refactor-antes-despues.puml` muestra las dependencias de los dos diseños.

## Justificación

1. **Integridad del indicador (RN-07):** el desglose viaja como conteos enteros, que siempre suman el numerador; los porcentajes se calculan al mostrar, desde los conteos.
2. **Precisión consistente:** el dominio declara cuántos decimales tiene cada indicador (ocupación: 1, citas: 0, estancia promedio: 1); la vista no inventa su propia regla.
3. **Separación de capas sin ampliar el módulo:** se mueven responsabilidades a su capa; no se agregan reportes, rutas ni permisos.
4. **Pruebas:** el cálculo se prueba en el dominio sin base (InMemory) y el formateo se prueba como función pura en el frontend.

| Métrica | Antes | Después |
|---|---|---|
| Responsabilidades en el controlador | 4 (SQL, cálculo, redondeo, texto) | 1 (coordinar) |
| Lugares donde se redondea | 3 | 1 |
| ¿El desglose en pantalla siempre suma el numerador? | No | Sí |
| ¿Se prueba el cálculo sin base? | No | Sí |
