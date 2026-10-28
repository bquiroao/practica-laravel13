# Pruebas del flujo de órdenes de laboratorio desde EMR

Suite PHP 8.2+ sin Composer ni infraestructura externa, enfocada solo en los casos de uso de emisión, seguimiento y actualización de estado del ejemplo de Semana 2.

Desde la raíz del repositorio:

```powershell
php docs/modulos/ordenes-laboratorio-emr/semana-15/pruebas/run.php
php docs/modulos/ordenes-laboratorio-emr/semana-02/ejemplo-srp/verificar.php
```

La suite Semana 15 contiene 3 pruebas unitarias, 2 de caja blanca y 4 de caja negra. Los dobles configurables y espías están en `TestDoubles.php`; los casos se identifican como `U`, `WB` o `BB` en la salida. Cada grupo prueba exclusivamente el módulo de órdenes, sin iniciar Laravel ni acceder a autenticación general, recepción, procesamiento o resultados.

Los datos `TEST_*` son fixtures sintéticos, no códigos clínicos ni estados productivos.
