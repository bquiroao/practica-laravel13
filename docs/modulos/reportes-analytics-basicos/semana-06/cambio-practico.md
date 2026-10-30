# Registro del cambio práctico defendido · Primera evaluación parcial

## Cambio solicitado

> «El Jefe de área necesita la **estancia promedio (ALOS)** del mes, por sala, con **un decimal**, filtrable por sala, y saber de qué datos sale.»

| Aspecto del cambio | Qué implica |
|---|---|
| Indicador | Estancia promedio = días-cama de los egresos / número de egresos |
| Agregación | Promedio: no se puede sumar ni promediar promedios de sala |
| Filtro | Por sala, además del período |
| Fuente | `bed_stay` con `ended_at` dentro del período (solo estancias que terminaron) |
| Precisión | Un decimal **al mostrar**; el cálculo se hace con los valores exactos |
| Visualización | Valor en «días», desglose por sala y registros de cada egreso |

## Respuesta de diseño

| Capa | Cambio | ¿Se modifica algo existente? |
|---|---|---|
| Domain | Clase nueva `ReporteEstanciaPromedio` que implementa `ContratoReporte`; interfaz nueva `EstanciaRepository` | No |
| Persistence | `EstanciaPdoRepository` y `EstanciaInMemoryRepository` | No |
| Application | Ninguno: `CalcularIndicador` ya trabaja con cualquier reporte | No |
| Presentation / API | Ninguno: misma vista y mismas rutas con `{tipo} = estancia_promedio` | No |
| Composition Root | Una línea: `$registro->registrar(new ReporteEstanciaPromedio($repositorio))` | Sí, una línea |

Es la prueba práctica de OCP y LSP: el cambio se **agrega**, no se mete dentro de lo que ya funciona.

## El punto difícil: RN-07 en un promedio

Un promedio no se desglosa sumando promedios de sala. Ejemplo con datos ficticios de octubre:

| Sala | Días-cama de egresos | Egresos | Promedio de la sala |
|---|---|---|---|
| Medicina | 30 | 6 | 5.0 |
| Cirugía | 20 | 5 | 4.0 |
| Pediatría | 4 | 2 | 2.0 |
| **Total** | **54** | **13** | **54 / 13 = 4.2** |

El promedio de los promedios daría 3.7, que es **incorrecto**. Por eso:

- El `numerador` del indicador son los **días-cama** (54) y el desglose reparte días-cama: 30 + 20 + 4 = 54, así RN-07 se sigue cumpliendo.
- El denominador (13 egresos) va en la ficha de trazabilidad junto con la fórmula.
- Los registros de Medicina son sus **6 egresos**, cada uno con sus días.

## Precisión

- Se calcula 54 / 13 = 4.153846… con el valor completo y se redondea a **4.2** solo en la vista.
- Los promedios por sala se calculan con sus propios días y egresos, nunca a partir del valor redondeado.
- La ficha muestra «4.2 días (54 días-cama / 13 egresos)», así cualquiera puede repetir la cuenta.

## Cómo se verifica

| Prueba nueva | Resultado esperado |
|---|---|
| Camino feliz | 4.2 días; desglose de días-cama suma 54 = numerador |
| Regla de dominio | Sin egresos en el período → «Sin datos» (RN-06), nunca división entre cero |
| Precisión | El valor interno conserva todos los decimales; solo la vista redondea |
| Contrato | Las mismas pruebas de contrato de la semana 2 pasan con el reporte nuevo |
| Permisos | El Médico recibe 403 (RN-01) |

## Resultado de la defensa

| Pregunta del evaluador | Respuesta dada |
|---|---|
| ¿Cuántos archivos existentes cambian? | Uno, y solo una línea: el registro del reporte en `bootstrap.php` |
| ¿Por qué no se suman promedios? | Porque se pierde el peso de cada sala; se desglosan días-cama |
| ¿Dónde se redondea? | Solo en la vista, para no acumular errores |
