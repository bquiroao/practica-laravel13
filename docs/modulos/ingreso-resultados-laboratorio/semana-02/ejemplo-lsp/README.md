# Ejemplo ejecutable de LSP — ingreso de resultados

Ejemplo PHP 8.2+ vanilla para captura inicial y corrección controlada de resultados pendientes. No requiere Composer, framework, red ni base de datos.

## Estructura

- `src/LabResultWorkflow.php`: tipos inmutables, versión, contrato de repositorio, adaptadores y servicio.
- `tests/run.php`: pruebas de aceptación y contrato compartido por los adaptadores.

## Ejecución

Desde este directorio:

```powershell
php -l src/LabResultWorkflow.php
php -l tests/run.php
php tests/run.php
```

La salida esperada termina con `OK` y un número de aserciones. Ambas implementaciones de `PendingResultRepository` se prueban con los mismos escenarios.

## Límites

Se usan resultados ficticios y un adaptador en memoria. No se incluyen rangos clínicos, unidades por examen, permisos reales, validación bioquímica, API ni conexión PostgreSQL.
