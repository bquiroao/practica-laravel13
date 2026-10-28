# Registro del cambio práctico defendido - Semana 6

ID: `CC-ING-06-VAL-UNIDAD-RANGO`  
Fecha: 27-10-2026  
Estado: Propuesto y validado en script (no aplicado al flujo productivo)

## 1) Cambio solicitado

Cuando se capture o corrija un resultado, el valor debe venir con unidad (`numero unidad`) y debe poder clasificarse por rango de referencia (`LOW`, `NORMAL`, `HIGH`) sin romper la corrección controlada ni el estado pendiente.

## 2) Situación antes del cambio

- `CaptureOrCorrectResult` acepta cualquier texto no vacío como `value`.
- Ejemplo aceptado por el diseño actual: `5.2` (sin unidad).
- La corrección controlada por versión sí funciona correctamente.

## 3) Diseño del cambio

- Agregar política de validación y clasificación:
  - Parseo `ResultValue`: formato `numero unidad`.
  - Clasificación por `ReferenceRange` según ítem.
- Mantener la regla vigente:
  - Solo se corrige si el resultado está pendiente.
  - Se exige `expectedVersionId`.

## 4) Evidencia ejecutable

Script: [verificacion/cambio-practico.php](verificacion/cambio-practico.php)  
Salida: [evidencia/cambio-practico-semana-06.txt](evidencia/cambio-practico-semana-06.txt)

Resultados clave:

1. El diseño actual acepta `5.2` sin unidad (límite probado).
2. La política propuesta rechaza valores sin unidad.
3. `4.8 mmol/L` se clasifica `NORMAL`.
4. `149 mEq/L` se clasifica `HIGH`.
5. La corrección controlada sube versión y mantiene estado pendiente.
6. Una corrección con versión obsoleta sigue respondiendo conflicto.

## 5) Impacto esperado

- Mejora la calidad semántica del dato clínico desde el ingreso.
- Mejora la trazabilidad hacia validación bioquímica (bandera por rango).
- Mantiene consistencia y concurrencia del diseño actual.

## 6) Riesgos y mitigación

- Riesgo: clientes existentes podrían enviar valor sin unidad.
- Mitigación: ventana temporal de compatibilidad con advertencia y corte gradual.

