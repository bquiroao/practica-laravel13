# Ejemplo SRP — Usuarios, tenants y sesiones

Demostración en PHP 8.2+ vanilla del flujo «alta de usuario por tenant, autenticación segura y revocación de sesión». No requiere Composer, Laravel ni una base de datos.

```text
ejemplo-srp/
├── antes/AllInOneIdentityService.php
├── despues/
│   ├── IdentityWorkflow.php
│   └── InMemoryAdapters.php
└── verificar.php
```

## Ejecutar

Desde la raíz del repositorio:

```bash
php docs/modulos/gestion-usuarios-tenants-autenticacion/semana-02/ejemplo-srp/verificar.php
```

El proceso devuelve código 0 cuando todas las aserciones pasan. La salida obtenida se guarda en [`../evidencia/salida-verificar.txt`](../evidencia/salida-verificar.txt).

## Seguridad y límites

Los secretos de prueba son inventados y se verifican contra un hash con `password_verify`; no se almacenan en el modelo de identidad ni se escriben en la salida de auditoría. El emisor crea un objeto que representa los claims, no un JWT criptográficamente firmado. La blacklist se simula en memoria; ninguna de estas piezas representa una integración productiva.
