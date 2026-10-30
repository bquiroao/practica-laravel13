# Ejemplo DIP: quality gate antes y después

PHP 8.2+ sin dependencias. Explicación completa en [semana-02-dip-requerimientos-diseno.md](../semana-02-dip-requerimientos-diseno.md).

| Archivo | Qué es |
|---|---|
| `antes/EvaluadorGate.php` | El gate dentro de un script de CI: crea sus detalles (GitHub, S3, PDO, archivos, reloj). No se ejecuta: ese es el problema |
| `despues/src/Dominio/` | Modelo, **puertos** (las abstracciones que posee el dominio) y `QualityGate`, la regla pura |
| `despues/src/Aplicacion/EvaluarGate.php` | CU-09: recibe los puertos por el constructor |
| `despues/src/Adaptadores/` | JUnit, JSON de otro proveedor, almacén en disco por contenido, relojes y dobles en memoria |
| `datos/` | La ejecución run-101 en los dos formatos y sus 3 artefactos (`php datos/generar.php` los regenera) |
| `verificar.php` | Los 14 criterios de aceptación |
| `medir-dip.php` | Dependencias del alto nivel hacia detalles, antes y después |
| `mutaciones.php` | 8 cambios en la regla que los criterios deben detectar |

```bash
php verificar.php && php medir-dip.php && php mutaciones.php
```
