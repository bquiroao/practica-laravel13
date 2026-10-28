# Ejemplo ISP — Prototipo NativePHP

Ejemplo verificable en PHP 8.2+ vanilla del flujo acotado «consultar el estado de una orden de laboratorio de demostración y notificar el resultado».

```text
ejemplo-isp/
├── antes/NativeDeviceCapabilities.php
├── despues/
│   ├── NativeCapabilities.php
│   └── InMemoryAdapters.php
└── verificar.php
```

## Ejecutar

Desde la raíz del repositorio:

```bash
php docs/modulos/investigacion-prototipo-nativephp/semana-02/ejemplo-isp/verificar.php
```

La salida real se conserva en [`../evidencia/salida-verificar.txt`](../evidencia/salida-verificar.txt). Las pruebas no requieren Composer, una base de datos ni instalar NativePHP.

## Límite de la demostración

Este prototipo verifica la separación de interfaces y la política del flujo con datos ficticios y adaptadores en memoria. No empaqueta ni ejecuta una app nativa. La conexión real con NativePHP, la selección del paquete para el destino móvil/escritorio y la llamada autenticada a un backend Laravel se deben validar en el prototipo de la semana siguiente; no se afirma que exista una API nativa instalada en este repositorio.
