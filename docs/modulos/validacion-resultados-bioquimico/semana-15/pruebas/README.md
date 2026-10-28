# Suite de pruebas — Validación bioquímica

Suite autónoma en PHP 8.2+ para el flujo de decisión modelado en [Semana 2](../../semana-02/ejemplo-dip/README.md). No requiere Composer, base de datos ni framework.

Desde la raíz del repositorio:

```powershell
php docs/modulos/validacion-resultados-bioquimico/semana-15/pruebas/run.php
```

La suite contiene 6 pruebas unitarias, 2 casos de caja blanca y 4 de caja negra. Cada caso aparece identificado en la salida; el proceso devuelve código 0 si todo pasa y 1 si alguna aserción falla. Los dobles de autorización, reloj y repositorio están en `TestDoubles.php`.

También se ejecuta la suite de criterios de aceptación heredada:

```powershell
php docs/modulos/validacion-resultados-bioquimico/semana-02/ejemplo-dip/verificar.php
```

Ambas suites se limitan al flujo de validación bioquímica. No ejercitan autenticación general, órdenes, recepción de muestras, catálogo, pacientes ni módulos ajenos.
