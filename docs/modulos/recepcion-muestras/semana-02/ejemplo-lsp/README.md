# Ejemplo ejecutable de LSP — recepción de muestras

Ejemplo pequeño en PHP 8.2+ vanilla para mostrar cómo se pueden sustituir políticas de aceptación de muestras sin sorprender al consumidor. No utiliza framework, Composer, base de datos ni dependencias externas.

## Estructura

- `src/SpecimenAcceptance.php`: objeto de muestra, decisión, contrato, políticas y servicio consumidor.
- `tests/run.php`: contrato de sustitución y pruebas de escenarios, con un runner mínimo.

## Ejecutar

Desde este directorio:

```powershell
php -l src/SpecimenAcceptance.php
php -l tests/run.php
php tests/run.php
```

La salida esperada es un listado de aserciones `PASS` y el total final sin fallos. Para un tipo de muestra distinto, la política especializada devuelve explícitamente «no aplica» y el servicio conserva el resultado de las reglas generales.

## Límite

El ejemplo demuestra decisiones de dominio en memoria. No crea una orden persistida, no reserva un identificador y no determina criterios clínicos para pruebas concretas.
