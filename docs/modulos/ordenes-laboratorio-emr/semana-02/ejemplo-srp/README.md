# Ejemplo SRP — Órdenes de laboratorio desde EMR

Prototipo autocontenido en PHP 8.2+ de emisión, consulta de seguimiento y aplicación de una actualización de estado. No requiere Composer, Laravel, base de datos ni datos clínicos reales.

## Ejecutar desde la raíz del repositorio

```bash
php docs/modulos/ordenes-laboratorio-emr/semana-02/ejemplo-srp/verificar.php
```

La ejecución cubre solicitudes inválidas, acceso denegado, contexto y catálogo, escritura completa/fallida, consulta encontrada/vacía/no disponible y actualización aceptada/rechazada. La salida capturada está en [`../evidencia/salida-verificar.txt`](../evidencia/salida-verificar.txt).

## Límites de la demostración

- `InMemoryLabOrderFixture` implementa varios puertos como fixture para compartir estado; no es un adaptador de producción.
- `TEST_INITIAL` y `TEST_NEXT` son etiquetas sintéticas para probar que la política se consulta. No son estados del HIS ni definen transiciones reales.
- La persistencia en memoria comprueba el resultado observable, pero no sustituye una transacción de base de datos.
- El modelo no firma ni procesa solicitudes clínicas reales y no implementa una pantalla o API Laravel.
