# Ejemplo OCP — Gobernanza y auditoría, semana 2

Ejemplo ejecutable en PHP 8.2+ vanilla, sin Composer ni framework, del flujo «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas».

```
ejemplo-ocp/
├── antes/BitacoraAuditoria.php        ← diseño sin OCP (if/elseif para reglas y filtros)
├── despues/
│   ├── autoload.php                   ← autoload PSR-4 mínimo
│   ├── composicion.php                ← raíz de composición: único lugar con clases concretas
│   ├── src/Dominio/                   ← Movimiento (inmutable), Sello, ConsultaMovimientos, Resultado…
│   ├── src/Contratos/                 ← ReglaMovimiento y FiltroMovimiento (extensión); Bitacora, Transaccion, PoliticaAcceso (puertos)
│   ├── src/Reglas/                    ← validaciones del registro
│   ├── src/Filtros/                   ← filtros de la consulta (rango obligatorio, usuario, módulo, acción)
│   ├── src/Servicios/                 ← ServicioRegistro, ServicioConsulta, VerificadorIntegridad (cerrados a modificación)
│   ├── src/EnMemoria/                 ← adaptadores y dobles en memoria
│   └── extension/                     ← FiltroResultado y ReglaModuloDelEmisor, agregados sin tocar el núcleo
├── verificar.php                      ← 21 pruebas: criterios de aceptación, OCP y paridad antes/después
└── mutaciones.php                     ← 8 defectos inyectados en copias temporales: todos deben detectarse
```

## Ejecutar

Desde la raíz del repositorio:

```bash
php docs/modulos/gobernanza-auditoria/semana-02/ejemplo-ocp/verificar.php
php docs/modulos/gobernanza-auditoria/semana-02/ejemplo-ocp/mutaciones.php
```

Los dos devuelven el código de salida 0 si todo está bien y 1 si algo falla.
