# Ejemplo SRP — Notas SOAP semana 2

Ejemplo ejecutable en PHP 8.2+ vanilla, sin Composer ni framework, del flujo «registro de nota SOAP y asociación de diagnóstico clínico».

```
ejemplo-srp/
├── antes/ServicioNotaSoap.php        ← una sola clase con 8 responsabilidades
├── despues/
│   ├── autoload.php                  ← autoload PSR-4 mínimo
│   ├── composicion.php               ← raíz de composición: único lugar que conecta las piezas
│   ├── src/Dominio/                  ← NotaSoap, SeccionesSoap, Diagnostico, TipoDiagnostico, excepciones
│   ├── src/Contratos/                ← interfaces hacia acceso, expedientes, persistencia, transacción, bitácora
│   ├── src/Servicios/                ← RegistrarNotaConDiagnostico y FirmarNota (coordinan, no validan)
│   ├── src/Presentacion/             ← NotaSoapController (HTTP ↔ caso de uso)
│   └── src/EnMemoria/                ← adaptadores y dobles de prueba en memoria
└── verificar.php                     ← 20 pruebas: criterios de aceptación, SRP, paridad y defectos corregidos
```

## Ejecutar

Desde la raíz del repositorio:

```bash
php docs/modulos/notas-soap-diagnosticos/semana-02/ejemplo-srp/verificar.php
```

Devuelve el código de salida 0 si todas las pruebas pasan y 1 si alguna falla. La salida de referencia está en [`../evidencia/salida-verificar.txt`](../evidencia/salida-verificar.txt).
