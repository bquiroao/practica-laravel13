# Ejemplo LSP — Pacientes semana 2

Ejemplo ejecutable en PHP 8.2+ vanilla, sin Composer ni framework, del flujo «registro, búsqueda y actualización segura de un paciente». Todos los datos son ficticios.

```
ejemplo-lsp/
├── antes/                               ← diseño con 3 violaciones de LSP
│   ├── IdentificadorTemporal.php        ← V1: clave vacía y compartida
│   ├── RepositorioPacientes.php         ← clase base concreta que lee y escribe
│   ├── ReplicaPacientes.php             ← V2: hereda y lanza LogicException al escribir
│   ├── RepositorioPacientesConCache.php ← V3: hereda y escribe sin comparar la versión
│   └── ServicioPacientes.php            ← un solo servicio con 3 instanceof
├── despues/
│   ├── autoload.php                     ← autoload PSR-4 mínimo
│   ├── composicion.php                  ← raíz de composición: único lugar con clases concretas
│   ├── src/Dominio/                     ← Paciente, Identificador (Dpi, Pasaporte), CriterioBusqueda...
│   ├── src/Contratos/                   ← ConsultaPacientes, RepositorioPacientes y demás puertos
│   ├── src/Servicios/                   ← registro, búsqueda y actualización (sin instanceof)
│   ├── src/Adaptadores/                 ← réplica de lectura y caché (decorador)
│   └── src/EnMemoria/                   ← repositorio, MPI, bitácora y transacción en memoria
├── pruebas/contratos.php                ← pruebas de contrato reutilizables (Identificador, Consulta, Repositorio)
└── verificar.php                        ← 25 pruebas: criterios de aceptación, LSP, antes y paridad
```

## Ejecutar

Desde la raíz del repositorio:

```bash
php docs/modulos/pacientes/semana-02/ejemplo-lsp/verificar.php
```

Devuelve el código de salida 0 si todas las pruebas pasan y 1 si alguna falla.

## Probar un subtipo nuevo

Para comprobar que una implementación nueva es sustituible, se pasa a la función de contrato que corresponde. Por ejemplo, un repositorio PDO:

```php
$violaciones = contratoRepositorioPacientes(static fn () => new RepositorioPacientesPdo($conexionDePrueba));
// [] = cumple el contrato; si no, cada elemento dice qué cláusula viola.
```
