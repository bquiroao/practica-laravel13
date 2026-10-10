# Ejemplo OCP — RBAC semana 2

Ejemplo ejecutable en PHP 8.2+ vanilla, sin Composer ni framework, del flujo «asignación de rol, autorización de una operación y denegación trazable».

```
ejemplo-ocp/
├── antes/ServicioAutorizacion.php   ← diseño sin OCP (if/elseif + archivo concreto)
├── despues/
│   ├── autoload.php                 ← autoload PSR-4 mínimo
│   ├── composicion.php              ← raíz de composición: único lugar con clases concretas
│   ├── src/Dominio/                 ← Membresia, solicitudes, Decision, EventoAuditoria...
│   ├── src/Contratos/               ← interfaces (puntos de extensión y puertos)
│   ├── src/Reglas/                  ← reglas de autorización y de asignación
│   ├── src/Servicios/               ← Autorizador y ServicioAsignacionRol (cerrados a modificación)
│   ├── src/EnMemoria/               ← adaptadores y dobles de prueba en memoria
│   └── extension/ReglaRedHospitalaria.php  ← regla nueva agregada sin modificar el núcleo
└── verificar.php                    ← 18 pruebas: criterios de aceptación, OCP y paridad
```

## Ejecutar

Desde la raíz del repositorio:

```bash
php docs/modulos/rbac/semana-02/ejemplo-ocp/verificar.php
```

Devuelve el código de salida 0 si todas las pruebas pasan y 1 si alguna falla.
